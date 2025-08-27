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

  // 診斷 Session 狀態
  error_log("Session ID: " . session_id());
  error_log("Session data: " . print_r($_SESSION, true));

  // 取得會員 ID (優先順序：Session > GET 參數)
  $memberId = null;
  $authMethod = '';

  if (isset($_SESSION['member_id'])) {
    // 方法1: 使用 Session (推薦)
    $memberId = (int)$_SESSION['member_id'];
    $authMethod = 'session';
    error_log("使用 Session 驗證，Member ID: " . $memberId);
    
  } elseif (isset($_GET['member_id']) && is_numeric($_GET['member_id'])) {
    // 方法2: 使用 GET 參數 (備用方案)
    $memberId = (int)$_GET['member_id'];
    $authMethod = 'get_param';
    error_log("使用 GET 參數驗證，Member ID: " . $memberId);
    
    // 可選：加入額外的安全驗證
    if (isset($_GET['token'])) {
      $expectedToken = hash('sha256', $memberId . 'your_secret_key');
      if ($_GET['token'] !== $expectedToken) {
        http_response_code(401);
        echo json_encode(['success' => false, 'msg' => '驗證失敗'], JSON_UNESCAPED_UNICODE);
        exit;
      }
    }
    
  } else {
    http_response_code(401);
    echo json_encode([
      'success' => false, 
      'msg' => '尚未登入或缺少會員識別資訊',
      'debug' => [
        'session_exists' => isset($_SESSION['member_id']),
        'get_param_exists' => isset($_GET['member_id']),
        'session_keys' => array_keys($_SESSION)
      ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // // 會員 ID
  // if (!isset($_SESSION['member_id'])) {
  //   echo json_encode(['success' => false, 'msg' => '尚未登入'], JSON_UNESCAPED_UNICODE);
  //   exit;
  // }

  // $memberId = (int)$_SESSION['member_id'];

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
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if ($data) {
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
      $result_interests = $stmt_interests->get_result();
      
      $interests_array = [];
      while ($row = $result_interests->fetch_assoc()) {
        // 確保 id 是數字
        $row['id'] = (int)$row['id'];
        $interests_array[] = $row;
      }
      $data["interests"] = $interests_array; // 將興趣陣列加入
      
      echo json_encode([
        "success" => true,
        "data" => $data
      ], JSON_UNESCAPED_UNICODE);

    } else {
      echo json_encode([
        "success" => false,
        "errors" => ["member" => "找不到會員資料"]
      ], JSON_UNESCAPED_UNICODE);
    }

    $stmt->close();

  } catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "資料庫錯誤"], JSON_UNESCAPED_UNICODE);
  }
?>