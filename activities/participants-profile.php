<?php
// 取得團員資料 (暱稱、評分、居住地、年齡、職業)
// GET
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');
$db = db();

// 只允許 GET
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
  http_response_code(405);
  echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
  exit;
}

// 前端傳送 activityNo
// $activityNo = isset($_GET['activityNo']) ? intval($_GET['activityNo']) : null;
// if (!$activityNo) {
//     http_response_code(400);
//     echo json_encode(["error" => "缺少活動編號"], JSON_UNESCAPED_UNICODE);
//     exit;
// }

$activityNo = 5; // 測試是否有抓到資料

// 用來存放 memberId 與角色
$memberRoles = [];

try {
  // 取得活動主揪ID
  $sql = "SELECT HOST_MEMBER_ID AS hostMemberId FROM activity WHERE ACTIVITY_NO = ?";
  $stmt = $db->prepare($sql);
  $stmt->bind_param("i", $activityNo);
  $stmt->execute();
  $stmt->bind_result($hostMemberId);
  if ($stmt->fetch()) {
    $memberRoles[$hostMemberId] = "host";
  }
  $stmt->close();

  // 取得參團者ID
  $sql = "SELECT PARTICIPANT_ID AS participantId FROM participant WHERE ACTIVITY_NO = ?";
  $stmt = $db->prepare($sql);
  $stmt->bind_param("i", $activityNo);
  $stmt->execute();
  $stmt->bind_result($participantId);

  while ($stmt->fetch()) {
    $memberRoles[$participantId] = "participant";
  }
  $stmt->close();

  // 取得主揪與參團者的會員資料
  $members = [];
  if (count($memberRoles) > 0) {
    $memberIds = array_keys($memberRoles);
    $placeholders = implode(',', array_fill(0, count($memberRoles), '?'));
    $sql = "SELECT 
              m.MEMBER_ID, 
              m.MEMBER_AVATAR AS avatar, 
              m.MEMBER_NICKNAME AS nickname, 
              m.HOST_SCORE_TOTAL AS hostScore, 
              m.HOST_COUNT_TOTAL AS hostCount, 
              m.JOINER_SCORE_TOTAL AS joinerScore, 
              m.JOINER_COUNT_TOTAL AS joinerCount, 
              c.CITY_NAME AS cityName, 
              m.MEMBER_BIRTHDATE AS birthdate, 
              o.OCCUPATION AS occupation
            FROM member m
            LEFT JOIN city c ON c.CITY_NO = m.MEMBER_CITY
            LEFT JOIN occupation o ON o.OCCUPATION_NO = m.MEMBER_OCCUPATION
            WHERE m.MEMBER_ID IN ($placeholders)";

    $stmt = $db->prepare($sql);
    $types = str_repeat("i", count($memberRoles));
    $stmt->bind_param($types, ...array_keys($memberRoles));
    $stmt->execute();
    $stmt->bind_result($memberId, $avatar, $nickname, $hostScore, $hostCount, $joinerScore, $joinerCount, $city, $birthdate, $occupation);

    while ($stmt->fetch()) {
      // 計算年齡
      $age = null;
      if ($birthdate) {
        $birthdateObj = new DateTime($birthdate);
        $today = new DateTime();
        $age = $today->diff($birthdateObj)->y;
      }

      $members[] = [
        "memberId" => $memberId,
        "role" => $memberRoles[$memberId], // 主揪 or 參團者
        "avatar" => $avatar,
        "nickname" => $nickname,
        "hostScore" => $hostScore,
        "hostCount" => $hostCount,
        "joinerScore" => $joinerScore,
        "joinerCount" => $joinerCount,
        "city" => $city,
        "age" => $age,
        "occupation" => $occupation
      ];
    }
    $stmt->close();
  }

  // host 排前，participant 排後
  usort($members, function ($a, $b) {
    if ($a["role"] === $b["role"]) {
      return 0;
    }
    return $a["role"] === "host" ? -1 : 1;
  });

  // 回傳所有資料
  echo json_encode([
    "success" => true,
    "data" => $members
  ], JSON_UNESCAPED_UNICODE);

} catch (mysqli_sql_exception $e) {
  http_response_code(500);
  echo json_encode(["error" => "資料庫錯誤"], JSON_UNESCAPED_UNICODE);
}
?>
