<?php
  // 取得會員資料
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

  // 會員 ID
  $memberId = $_GET['id'] ?? null;
  if (!$memberId) {
    http_response_code(400);
    echo json_encode(["error" => "缺少會員 ID"], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // $memberId = 1; // 測試

  try {
    $sql = "SELECT 
            m.MEMBER_AVATAR AS avatar, 
            m.MEMBER_NAME AS name, 
            m.MEMBER_NICKNAME AS nickname, 
            m.MEMBER_GENDER AS gender, 
            m.MEMBER_BIRTHDATE AS birthdate, 
            m.MEMBER_CITY AS city, 
            m.MEMBER_OCCUPATION AS occupation, 
            GROUP_CONCAT(i.INTEREST_NO) AS interests
          FROM member m 
          LEFT JOIN member_interest i
            ON m.MEMBER_ID = i.MEMBER_ID
          WHERE m.MEMBER_ID = ?
          GROUP BY m.MEMBER_ID";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $memberId);
    $stmt->execute();

    $stmt->bind_result($avatar, $name, $nickname, $gender, $birthdate, $city, $occupation, $interests);

    if ($stmt->fetch()) {
      $data = [
        "avatar" => $avatar,
        "name" => $name,
        "nickname" => $nickname,
        "gender" => $gender,
        "birthdate" => $birthdate,
        "city" => $city,
        "occupation" => $occupation,
        "interests" => $interests ? explode(",", $interests) : []
      ];
      echo json_encode([
        "success" => true,
        "data" => $data
      ], JSON_UNESCAPED_UNICODE);
    } else {
      echo json_encode([
        "success" => false,
        "errors" => [
          "member" => "找不到會員資料"
        ]
      ], JSON_UNESCAPED_UNICODE);
    }

    $stmt->close();

  } catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "資料庫錯誤"], JSON_UNESCAPED_UNICODE);
  }
?>