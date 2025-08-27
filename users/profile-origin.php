<?php
// 取得會員資料
// GET
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

// === Session 設定 ===
session_set_cookie_params([
  'httponly' => true,
  'secure' => isset($_SERVER['HTTPS']), // 測試用 HTTP 時會是 false，上線才會 true
  'samesite' => 'Lax' // 先用 Lax，比 Strict 寬鬆
]);
session_start();

// === 限制請求方法 ===
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
  http_response_code(405);
  echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
  exit;
}

// === Debug 模式 (網址加上 ?debug=1) ===
if (isset($_GET['debug']) && $_GET['debug'] == 1) {
  echo json_encode([
    "debug" => [
      "cookie" => $_COOKIE,             // 前端送來的 cookie
      "session_id" => session_id(),     // 目前的 PHP Session ID
      "session_data" => $_SESSION,      // Session 內容
      "get_params" => $_GET             // GET 參數
    ]
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  exit;
}

// === 資料庫連線 ===
$db = db();

// === 抓會員 ID (優先順序：Session > GET 參數) ===
$memberId = null;
$authMethod = null;

if (isset($_SESSION['member_id'])) {
  $memberId = (int)$_SESSION['member_id'];
  $authMethod = "session";

} elseif (isset($_GET['member_id']) && is_numeric($_GET['member_id'])) {
  $memberId = (int)$_GET['member_id'];
  $authMethod = "get_param";

  // 可選：檢查安全 token
  if (isset($_GET['token'])) {
    $expectedToken = hash('sha256', $memberId . 'your_secret_key');
    if ($_GET['token'] !== $expectedToken) {
      http_response_code(401);
      echo json_encode(['success' => false, 'msg' => '驗證失敗'], JSON_UNESCAPED_UNICODE);
      exit;
    }
  }

} else {
  // 抓不到會員 ID，直接輸出 debug 資訊
  http_response_code(401);
  echo json_encode([
    'success' => false,
    'msg' => '尚未登入或缺少會員識別資訊',
    'debug' => [
      'cookie' => $_COOKIE,
      'session_id' => session_id(),
      'session_data' => $_SESSION,
      'get_params' => $_GET
    ]
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  exit;
}

// === 額外 Debug：只想看 ID 時 ?debugId=1 ===
if (isset($_GET['debugId']) && $_GET['debugId'] == 1) {
  echo json_encode([
    "debug" => [
      "auth_method" => $authMethod,
      "memberId" => $memberId,
      "session" => $_SESSION,
      "cookie" => $_COOKIE
    ]
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  // 查詢會員基本資料
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
    // 查詢會員興趣
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
      $row['id'] = (int)$row['id']; // id 強制轉 int
      $interests_array[] = $row;
    }
    $data["interests"] = $interests_array;

    echo json_encode([
      "success" => true,
      "data" => $data
    ], JSON_UNESCAPED_UNICODE);

  } else {
    echo json_encode([
      "success" => false,
      "errors" => ["member" => "找不到會員資料"],
      "debug" => [
        "memberId" => $memberId,
        "authMethod" => $authMethod
      ]
    ], JSON_UNESCAPED_UNICODE);
  }

  $stmt->close();

} catch (mysqli_sql_exception $e) {
  http_response_code(500);
  echo json_encode([
    "error" => "資料庫錯誤",
    "msg" => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}
