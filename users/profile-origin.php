<?php
  // 取得會員資料
  // GET
  require_once __DIR__ . '/../config/cors.php';
  require_once __DIR__ . '/../config/db.php';
  header('Content-Type: application/json; charset=utf-8');

  // 設定 session cookie 參數
  session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']), // 本地可用 HTTP，上線自動用 HTTPS
    'samesite' => 'Strict'
  ]);
  session_start();

  $db = db();

  // 只允許 GET
  if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // 會員 ID
  if (!isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'msg' => '尚未登入'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $memberId = (int)$_SESSION['member_id'];

  try {
    // 查詢會員基本資料 (不包含興趣)
    $sql = "SELECT 
            m.MEMBER_AVATAR AS avatar, 
            m.MEMBER_NAME AS name, 
            m.MEMBER_NICKNAME AS nickname, 
            m.MEMBER_GENDER AS gender, 
            m.MEMBER_BIRTHDATE AS birthdate, 
            m.MEMBER_CITY AS cityNo, 
            m.MEMBER_OCCUPATION AS occupationNo
          FROM member m 
          WHERE m.MEMBER_ID = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    
    // 使用 bind_result 替代 get_result
    $stmt->bind_result($avatar, $name, $nickname, $gender, $birthdate, $cityNo, $occupationNo);
    
    if ($stmt->fetch()) {
      // 組合基本資料
      $data = [
        'avatar' => $avatar,
        'name' => $name,
        'nickname' => $nickname,
        'gender' => $gender,
        'birthdate' => $birthdate,
        'cityNo' => $cityNo,
        'occupationNo' => $occupationNo
      ];
      
      $stmt->close(); // 先關閉第一個 statement
      
      // 查詢該會員的興趣，並組合成物件陣列
      $sql_interests = "SELECT 
                          i.INTEREST_NO AS id, 
                          cat.CATEGORY_NAME AS name 
                        FROM member_interest i
                        JOIN category cat ON i.INTEREST_NO = cat.CATEGORY_NO
                        WHERE i.MEMBER_ID = ?";
      $stmt_interests = $db->prepare($sql_interests);
      $stmt_interests->bind_param("i", $memberId);
      $stmt_interests->execute();
      
      // 使用 bind_result 替代 get_result
      $stmt_interests->bind_result($interest_id, $interest_name);
      
      $interests_array = [];
      while ($stmt_interests->fetch()) {
        $interests_array[] = [
          'id' => (int)$interest_id,
          'name' => $interest_name
        ];
      }
      $data["interests"] = $interests_array; // 將興趣陣列加入
      
      $stmt_interests->close();
      
      echo json_encode([
        "success" => true,
        "data" => $data
      ], JSON_UNESCAPED_UNICODE);

    } else {
      $stmt->close();
      echo json_encode([
        "success" => false,
        "errors" => ["member" => "找不到會員資料"]
      ], JSON_UNESCAPED_UNICODE);
    }

  } catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "資料庫錯誤"], JSON_UNESCAPED_UNICODE);
  }
?>