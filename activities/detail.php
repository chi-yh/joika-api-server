<?php
    /*
    主要任務：根據活動 ID，查詢資料庫並回傳該活動的完整資訊。
    使用情境：
    使用者在活動列表點進某個活動的詳情頁。
    前端需要顯示活動的時間、地點、參加人數、主辦者資訊、收藏狀態等。
    有時會附帶該活動的留言列表、照片等附加資料。
    */ 

    header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Taipei');

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
session_start();

function json($d,$c=200){ http_response_code($c); echo json_encode($d,JSON_UNESCAPED_UNICODE); exit; }
function me_id() {
  return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
}
$actNo = (int)($_GET['id'] ?? 0);
if(!$actNo) json(['error'=>'缺少 id'], 400);
$db = db();

/* 1) 取活動 + 分類 + 主揪 */
$sql = "SELECT 
          a.ACTIVITY_NO,
          a.ACTIVITY_NAME,
          a.CATEGORY_NO,
          a.ACTIVITY_IMG,
          a.REGISTRATION_START_DATE,
          a.REGISTRATION_DEADLINE,
          a.ACTIVITY_START_DATE,
          a.ACTIVITY_END_DATE,
          a.MIN_PARTICIPANT,
          a.MAX_PARTICIPANT,
          a.CURRENT_PARTICIPANT,
          a.FEE_NOTES,
          a.PARTICIPANT_LIMITATION,
          a.ACTIVITY_DESCRIPTION,
          a.ACTIVITY_STATUS,
          a.HOSTER_CANCELLED_AT,
          a.HOST_MEMBER_ID,
          a.CREATED_AT,
          a.STAFF_ID,
          a.LOCATION,
          a.HOSTER_CANCEL_REASON_NO,
          a.HOSTER_CANCEL_DESCRIPTION,
          c.CATEGORY_NAME,
          m.MEMBER_ID          AS HOST_MEMBER_ID,
          m.MEMBER_NICKNAME    AS HOST_NICKNAME,
          m.MEMBER_AVATAR      AS HOST_AVATAR,
          ci.CITY_NAME         AS HOST_CITY_NAME,
          o.OCCUPATION         AS HOST_OCCUPATION,
          TIMESTAMPDIFF(YEAR, m.MEMBER_BIRTHDATE, CURDATE()) AS HOST_AGE
        FROM activity a
        LEFT JOIN category   c  ON c.CATEGORY_NO      = a.CATEGORY_NO
        LEFT JOIN member     m  ON m.MEMBER_ID        = a.HOST_MEMBER_ID
        LEFT JOIN city       ci ON ci.CITY_NO         = m.MEMBER_CITY
        LEFT JOIN occupation o  ON o.OCCUPATION_NO    = m.MEMBER_OCCUPATION
        WHERE a.ACTIVITY_NO = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $actNo);
$stmt->execute();
$act = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$act) json(['error'=>'活動不存在'], 404);

// 組 hoster 區塊（前端目前沒用到也無妨）
$hoster = [
  'MEMBER_ID'        => isset($act['HOST_MEMBER_ID']) ? (int)$act['HOST_MEMBER_ID'] : null,
  'MEMBER_NICKNAME'  => $act['HOST_NICKNAME'] ?? null,  
  'MEMBER_AVATAR'    => $act['HOST_AVATAR'] ?? null,     
  'CITY_NAME'        => $act['HOST_CITY_NAME'] ?? null,
  'AGE'              => isset($act['HOST_AGE']) ? (int)$act['HOST_AGE'] : null,
  'OCCUPATION'       => $act['HOST_OCCUPATION'] ?? null,
  'NICKNAME'         => $act['HOST_NICKNAME'] ?? null,
  'AVATAR'           => $act['HOST_AVATAR'] ?? null,
];

// === 2) flags：isHost / isJoiner / canCancel / canRate ===
$userId  = me_id();
$isHost  = $userId ? ((int)$act['HOST_MEMBER_ID'] === (int)$userId) : false;

// isJoiner：participant 有該人，且未取消（JOINER_CANCEL_AT 為 NULL）
$isJoiner = false;
if ($userId) {
  $sql = "SELECT 1 FROM participant 
          WHERE ACTIVITY_NO = ? AND PARTICIPANT_ID = ? AND JOINER_CANCEL_AT IS NULL
          LIMIT 1";
  $stmt = $db->prepare($sql);
  $stmt->bind_param("ii", $actNo, $userId);
  $stmt->execute();
  $isJoiner = (bool)$stmt->get_result()->fetch_row();
  $stmt->close();
}

$canCancel = false;
$canRate   = false;

try {
  $now      = new DateTime('now');
  $startAt  = !empty($act['ACTIVITY_START_DATE']) ? new DateTime($act['ACTIVITY_START_DATE']) : null;
  $oneDayBefore = $startAt ? (clone $startAt)->modify('-1 day') : null;
$completedAt = !empty($act['ACTIVITY_END_DATE'])
  ? new DateTime($act['ACTIVITY_END_DATE'])
  : null;

  $windowEnd = $completedAt ? (clone $completedAt)->modify('+7 days') : null;
  if ($act['ACTIVITY_STATUS'] === '已完成' && $completedAt) {
    if ($now >= $completedAt && $now <= $windowEnd) {
      $canRate = ($isHost || $isJoiner);
    }
  }
  // 取消規則：開始前一天起不能按；且狀態不能是「已取消/已完成」
  if ($startAt && $oneDayBefore && ($now < $oneDayBefore)) {
    if (!in_array($act['ACTIVITY_STATUS'], ['已取消','已完成'], true)) {
      // 只有有身分（主揪或團員）才顯示可取消
      $canCancel = ($isHost || $isJoiner);
    }
  }
} catch (Throwable $e) {
  // 時間格式異常就保持 false
}

// === 3) 參團者 preview（最多 6 筆，含暱稱/頭貼/城市/職業 + 該活動下此人被評分統計） ===
$participantsPreview = [];
$sql = "SELECT 
          p.PARTICIPANT_ID                 AS MEMBER_ID,
          m.MEMBER_NICKNAME                AS NICKNAME,
          m.MEMBER_AVATAR                  AS AVATAR,
          ci.CITY_NAME                     AS CITY_NAME,
          o.OCCUPATION                     AS OCCUPATION,
          TIMESTAMPDIFF(YEAR, m.MEMBER_BIRTHDATE, CURDATE()) AS AGE,
          -- 此人在本活動作為『參與者』被打的平均分數與次數
          (SELECT ROUND(AVG(r2.RATING_SCORE),1)
             FROM rating r2 
            WHERE r2.ACTIVITY_NO = p.ACTIVITY_NO
              AND r2.RATEE_ID    = p.PARTICIPANT_ID
              AND r2.RATEE_ROLE  = '參與者'
          ) AS rating,
          (SELECT COUNT(*)
             FROM rating r3 
            WHERE r3.ACTIVITY_NO = p.ACTIVITY_NO
              AND r3.RATEE_ID    = p.PARTICIPANT_ID
              AND r3.RATEE_ROLE  = '參與者'
          ) AS reviews
        FROM participant p
        JOIN member      m  ON m.MEMBER_ID     = p.PARTICIPANT_ID
        LEFT JOIN city   ci ON ci.CITY_NO      = m.MEMBER_CITY
        LEFT JOIN occupation o ON o.OCCUPATION_NO = m.MEMBER_OCCUPATION
        WHERE p.ACTIVITY_NO = ?
        ORDER BY p.CREATED_AT DESC
        LIMIT 6";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $actNo);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  // 前端 key
  $participantsPreview[] = [
    'MEMBER_ID' => (int)$row['MEMBER_ID'],
    'NICKNAME'  => $row['NICKNAME'],
    'AVATAR'    => $row['AVATAR'],
    'city'   => $row['CITY_NAME'],
    'age'    => isset($row['AGE']) ? (int)$row['AGE'] : null,
    'role'   => $row['OCCUPATION'],
    //
    'rating'  => isset($row['rating'])  ? (float)$row['rating']  : 0.0,
    'reviews' => isset($row['reviews']) ? (int)$row['reviews'] : 0,
  ];
}
$stmt->close();

// === 4) 此活動所有評分的平均/筆數 + 我自己有沒有評過 ===
$ratings = ['avg'=>0.0, 'count'=>0, 'mine'=>null];

$sql = "SELECT ROUND(AVG(RATING_SCORE),1) AS avg_score, COUNT(*) AS cnt
        FROM rating
        WHERE ACTIVITY_NO = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $actNo);
$stmt->execute();
$avgRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($avgRow) {
  $ratings['avg']   = (float)($avgRow['avg_score'] ?? 0);
  $ratings['count'] = (int)($avgRow['cnt'] ?? 0);
}

if ($userId) {
  $sql = "SELECT RATING_SCORE
          FROM rating
          WHERE ACTIVITY_NO = ? AND RATER_ID = ?
          ORDER BY RATING_DATE DESC
          LIMIT 1";
  $stmt = $db->prepare($sql);
  $stmt->bind_param("ii", $actNo, $userId);
  $stmt->execute();
  if ($mine = $stmt->get_result()->fetch_assoc()) {
    $ratings['mine'] = ['rating' => (int)$mine['RATING_SCORE']];
  }
  $stmt->close();
}

// === 5) 組 response ===
// activity 物件：保留大部分欄位 + 額外帶 CATEGORY_NAME 給前端 template 用
$activity = [
  'ACTIVITY_NO'             => (int)$act['ACTIVITY_NO'],
  'ACTIVITY_NAME'           => $act['ACTIVITY_NAME'],
  'CATEGORY_NO'             => (int)$act['CATEGORY_NO'],
  'CATEGORY_NAME'           => $act['CATEGORY_NAME'] ?? null,
  'ACTIVITY_IMG'            => $act['ACTIVITY_IMG'],
  'REGISTRATION_START_DATE' => $act['REGISTRATION_START_DATE'],
  'REGISTRATION_DEADLINE'   => $act['REGISTRATION_DEADLINE'],
  'ACTIVITY_START_DATE'     => $act['ACTIVITY_START_DATE'],
  'ACTIVITY_END_DATE'       => $act['ACTIVITY_END_DATE'],
  'MIN_PARTICIPANT'         => (int)$act['MIN_PARTICIPANT'],
  'MAX_PARTICIPANT'         => (int)$act['MAX_PARTICIPANT'],
  'CURRENT_PARTICIPANT'     => (int)$act['CURRENT_PARTICIPANT'],
  'FEE_NOTES'               => $act['FEE_NOTES'],
  'PARTICIPANT_LIMITATION'  => $act['PARTICIPANT_LIMITATION'],
  'ACTIVITY_DESCRIPTION'    => $act['ACTIVITY_DESCRIPTION'],
  'ACTIVITY_STATUS'         => $act['ACTIVITY_STATUS'],
  'HOSTER_CANCELLED_AT'     => $act['HOSTER_CANCELLED_AT'],
  'HOST_MEMBER_ID'          => (int)$act['HOST_MEMBER_ID'],
  'CREATED_AT'              => $act['CREATED_AT'],
  'STAFF_ID'                => $act['STAFF_ID'],
  'LOCATION'                => $act['LOCATION'],
  'HOSTER_CANCEL_REASON_NO' => $act['HOSTER_CANCEL_REASON_NO'],
  'HOSTER_CANCEL_DESCRIPTION'=> $act['HOSTER_CANCEL_DESCRIPTION'],
];

$out = [
  'activity' => $activity,
  'hoster'   => $hoster,
  'participants' => [
    'count'  => (int)$act['CURRENT_PARTICIPANT'],
    'preview'=> $participantsPreview,
  ],
  'ratings' => $ratings,
  'flags' => [
    'isHost'   => $isHost,
    'isJoiner' => $isJoiner,
    'canCancel'=> $canCancel,
    'canRate'  => $canRate,
  ],
];

http_response_code(200);
echo json_encode($out, JSON_UNESCAPED_UNICODE);
exit;
?>