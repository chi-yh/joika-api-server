<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Taipei');

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
session_start();

/* 工具 */
function json($d,$c=200){ http_response_code($c); echo json_encode($d,JSON_UNESCAPED_UNICODE); exit; }
function me_id(){ return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null; }

$actNo = (int)($_GET['id'] ?? 0);
if(!$actNo) json(['error'=>'缺少 id'], 400);

$db = db();

/* 1) 取活動 + 分類 + 主揪（改 bind_result + fetch）*/
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
          TIMESTAMPDIFF(YEAR, m.MEMBER_BIRTHDATE, CURDATE()) AS HOST_AGE,

          ROUND(CASE 
          WHEN m.HOST_COUNT_TOTAL > 0 
          THEN m.HOST_SCORE_TOTAL / m.HOST_COUNT_TOTAL 
          ELSE 0 
        END, 1) AS RATING_HOST,
  m.HOST_COUNT_TOTAL AS REVIEWS_HOST,
  ROUND(CASE 
          WHEN m.JOINER_COUNT_TOTAL > 0 
          THEN m.JOINER_SCORE_TOTAL / m.JOINER_COUNT_TOTAL 
          ELSE 0 
        END, 1) AS RATING_JOINER,
  m.JOINER_COUNT_TOTAL AS REVIEWS_JOINER

        FROM activity a
        LEFT JOIN category   c  ON c.CATEGORY_NO      = a.CATEGORY_NO
        LEFT JOIN member     m  ON m.MEMBER_ID        = a.HOST_MEMBER_ID
        LEFT JOIN city       ci ON ci.CITY_NO         = m.MEMBER_CITY
        LEFT JOIN occupation o  ON o.OCCUPATION_NO    = m.MEMBER_OCCUPATION
        WHERE a.ACTIVITY_NO = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $actNo);
$stmt->execute();

$stmt->bind_result(
  $A_ACTIVITY_NO,
  $A_ACTIVITY_NAME,
  $A_CATEGORY_NO,
  $A_ACTIVITY_IMG,
  $A_REG_START,
  $A_REG_DEADLINE,
  $A_START,
  $A_END,
  $A_MIN,
  $A_MAX,
  $A_CURR,
  $A_FEE_NOTES,
  $A_LIMITATION,
  $A_DESC,
  $A_STATUS,
  $A_HOSTER_CANCELLED_AT,
  $A_HOST_MEMBER_ID,             
  $A_CREATED_AT,
  $A_STAFF_ID,
  $A_LOCATION,
  $A_HOSTER_CANCEL_REASON_NO,
  $A_HOSTER_CANCEL_DESCRIPTION,
  $C_CATEGORY_NAME,
  $M_HOST_MEMBER_ID,             
  $M_HOST_NICKNAME,
  $M_HOST_AVATAR,
  $M_HOST_CITY_NAME,
  $M_HOST_OCCUPATION,
  $M_HOST_AGE,
  $M_RATING_HOST,
  $M_REVIEWS_HOST,
  $M_RATING_JOINER,
  $M_REVIEWS_JOINER
);

$act = null;
if ($stmt->fetch()) {
  // 依你原本行為，HOST_MEMBER_ID 用會員表的那個（$M_HOST_MEMBER_ID）
  $act = [
    'ACTIVITY_NO'             => $A_ACTIVITY_NO,
    'ACTIVITY_NAME'           => $A_ACTIVITY_NAME,
    'CATEGORY_NO'             => $A_CATEGORY_NO,
    'ACTIVITY_IMG'            => $A_ACTIVITY_IMG,
    'REGISTRATION_START_DATE' => $A_REG_START,
    'REGISTRATION_DEADLINE'   => $A_REG_DEADLINE,
    'ACTIVITY_START_DATE'     => $A_START,
    'ACTIVITY_END_DATE'       => $A_END,
    'MIN_PARTICIPANT'         => $A_MIN,
    'MAX_PARTICIPANT'         => $A_MAX,
    'CURRENT_PARTICIPANT'     => $A_CURR,
    'FEE_NOTES'               => $A_FEE_NOTES,
    'PARTICIPANT_LIMITATION'  => $A_LIMITATION,
    'ACTIVITY_DESCRIPTION'    => $A_DESC,
    'ACTIVITY_STATUS'         => $A_STATUS,
    'HOSTER_CANCELLED_AT'     => $A_HOSTER_CANCELLED_AT,
    'HOST_MEMBER_ID'          => $M_HOST_MEMBER_ID, // 覆蓋為 member 表 id（與你原本 fetch_assoc 結果一致）
    'CREATED_AT'              => $A_CREATED_AT,
    'STAFF_ID'                => $A_STAFF_ID,
    'LOCATION'                => $A_LOCATION,
    'HOSTER_CANCEL_REASON_NO' => $A_HOSTER_CANCEL_REASON_NO,
    'HOSTER_CANCEL_DESCRIPTION'=> $A_HOSTER_CANCEL_DESCRIPTION,
    'CATEGORY_NAME'           => $C_CATEGORY_NAME,
    'HOST_NICKNAME'           => $M_HOST_NICKNAME,
    'HOST_AVATAR'             => $M_HOST_AVATAR,
    'HOST_CITY_NAME'          => $M_HOST_CITY_NAME,
    'HOST_OCCUPATION'         => $M_HOST_OCCUPATION,
    'HOST_AGE'                => $M_HOST_AGE,
    // 若你之後要用活動表原始 host id，可另外加：
    '_A_HOST_MEMBER_ID'       => $A_HOST_MEMBER_ID,
    'RATING_HOST'             => $M_RATING_HOST,
    'REVIEWS_HOST'            => $M_REVIEWS_HOST,
    'RATING_JOINER'           => $M_RATING_JOINER,
    'REVIEWS_JOINER'          => $M_REVIEWS_JOINER,
  ];
}
$stmt->close();

if (!$act) json(['error'=>'活動不存在'], 404);

/* hoster 區塊 */
$hoster = [
  'MEMBER_ID'        => isset($act['HOST_MEMBER_ID']) ? (int)$act['HOST_MEMBER_ID'] : null,
  'MEMBER_NICKNAME'  => $act['HOST_NICKNAME'] ?? null,
  'MEMBER_AVATAR'    => $act['HOST_AVATAR'] ?? null,
  'CITY_NAME'        => $act['HOST_CITY_NAME'] ?? null,
  'AGE'              => isset($act['HOST_AGE']) ? (int)$act['HOST_AGE'] : null,
  'OCCUPATION'       => $act['HOST_OCCUPATION'] ?? null,
  'NICKNAME'         => $act['HOST_NICKNAME'] ?? null,
  'AVATAR'           => $act['HOST_AVATAR'] ?? null,
  'RATING_HOST'      => isset($act['RATING_HOST'])     ? (float)$act['RATING_HOST']     : 0.0,
  'REVIEWS_HOST'     => isset($act['REVIEWS_HOST'])    ? (int)$act['REVIEWS_HOST']      : 0,
  'RATING_JOINER'    => isset($act['RATING_JOINER'])   ? (float)$act['RATING_JOINER']   : 0.0,
  'REVIEWS_JOINER'   => isset($act['REVIEWS_JOINER'])  ? (int)$act['REVIEWS_JOINER']    : 0,
  'RATING'           => isset($act['RATING_HOST'])     ? (float)$act['RATING_HOST']     : 0.0,
  'REVIEWS'          => isset($act['REVIEWS_HOST'])    ? (int)$act['REVIEWS_HOST']      : 0,

];

/* 2) flags：isHost / isJoiner / canCancel / canRate */
$userId  = me_id();
$isHost  = $userId ? ((int)$act['HOST_MEMBER_ID'] === (int)$userId) : false;

/* isJoiner：participant 有該人且未取消（統一用 JOINER_CANCELLED_AT）*/
$isJoiner = false;
if ($userId) {
  $sql = "SELECT 1 
          FROM participant 
          WHERE ACTIVITY_NO = ? 
            AND PARTICIPANT_ID = ? 
            AND JOINER_CANCEL_AT IS NULL
          LIMIT 1";
  $stmt = $db->prepare($sql);
  $stmt->bind_param("ii", $actNo, $userId);
  $stmt->execute();
  $stmt->bind_result($dummy);
  $isJoiner = $stmt->fetch() ? true : false;
  $stmt->close();
}

$canCancel = false;
$canRate   = false;

try {
  $now      = new DateTime('now');
  $startAt  = !empty($act['ACTIVITY_START_DATE']) ? new DateTime($act['ACTIVITY_START_DATE']) : null;
  $oneDayBefore = $startAt ? (clone $startAt)->modify('-1 day') : null;

  $completedAt = !empty($act['ACTIVITY_END_DATE']) ? new DateTime($act['ACTIVITY_END_DATE']) : null;
  $windowEnd   = $completedAt ? (clone $completedAt)->modify('+7 days') : null;

  if ($act['ACTIVITY_STATUS'] === '已完成' && $completedAt) {
    if ($now >= $completedAt && $now <= $windowEnd) {
      $canRate = ($isHost || $isJoiner);
    }
  }
  if ($startAt && $oneDayBefore && ($now < $oneDayBefore)) {
    if (!in_array($act['ACTIVITY_STATUS'], ['已取消','已完成'], true)) {
      $canCancel = ($isHost || $isJoiner);
    }
  }
} catch (Throwable $e) {
  // ignore
}

/* 3) 參團者 preview（最多 6 筆） */
$participantsPreview = [];
$sql = "SELECT 
          p.PARTICIPANT_ID                 AS MEMBER_ID,
          m.MEMBER_NICKNAME                AS NICKNAME,
          m.MEMBER_AVATAR                  AS AVATAR,
          ci.CITY_NAME                     AS CITY_NAME,
          o.OCCUPATION                     AS OCCUPATION,
          TIMESTAMPDIFF(YEAR, m.MEMBER_BIRTHDATE, CURDATE()) AS AGE,
          ROUND(CASE 
                  WHEN m.JOINER_COUNT_TOTAL > 0 
                  THEN m.JOINER_SCORE_TOTAL / m.JOINER_COUNT_TOTAL 
                  ELSE 0 
               END, 1) AS rating,
          m.JOINER_COUNT_TOTAL AS reviews
        FROM participant p
        JOIN member      m  ON m.MEMBER_ID     = p.PARTICIPANT_ID
        LEFT JOIN city   ci ON ci.CITY_NO      = m.MEMBER_CITY
        LEFT JOIN occupation o ON o.OCCUPATION_NO = m.MEMBER_OCCUPATION
        WHERE p.ACTIVITY_NO = ?
        ORDER BY p.CREATED_AT DESC
        ";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $actNo);
$stmt->execute();
$stmt->bind_result(
  $P_MEMBER_ID,
  $P_NICKNAME,
  $P_AVATAR,
  $P_CITY_NAME,
  $P_OCCUPATION,
  $P_AGE,
  $P_RATING,
  $P_REVIEWS
);
while ($stmt->fetch()) {
  $participantsPreview[] = [
    'MEMBER_ID' => (int)$P_MEMBER_ID,
    'NICKNAME'  => $P_NICKNAME,
    'AVATAR'    => $P_AVATAR,
    'city'      => $P_CITY_NAME,
    'age'       => isset($P_AGE) ? (int)$P_AGE : null,
    'role'      => $P_OCCUPATION,
    'rating'    => isset($P_RATING)  ? (float)$P_RATING  : 0.0,
    'reviews'   => isset($P_REVIEWS) ? (int)$P_REVIEWS : 0,
  ];
}
$stmt->close();

/* 4) 活動評分平均/筆數 + 我是否評過 — 改 bind_result */
$ratings = ['avg'=>0.0, 'count'=>0, 'mine'=>null];

$sql = "SELECT ROUND(AVG(RATING_SCORE),1) AS avg_score, COUNT(*) AS cnt
        FROM rating
        WHERE ACTIVITY_NO = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $actNo);
$stmt->execute();
$stmt->bind_result($avg_score, $cnt);
if ($stmt->fetch()) {
  $ratings['avg']   = (float)($avg_score ?? 0);
  $ratings['count'] = (int)($cnt ?? 0);
}
$stmt->close();

if ($userId) {
  $sql = "SELECT RATING_SCORE
          FROM rating
          WHERE ACTIVITY_NO = ? AND RATER_ID = ?
          ORDER BY RATING_DATE DESC
          LIMIT 1";
  $stmt = $db->prepare($sql);
  $stmt->bind_param("ii", $actNo, $userId);
  $stmt->execute();
  $stmt->bind_result($my_rating);
  if ($stmt->fetch()) {
    $ratings['mine'] = ['rating' => (int)$my_rating];
  }
  $stmt->close();
}

/* 5) 組 response */
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