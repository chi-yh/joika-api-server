<?php
require_once __DIR__ . '/../config/cors.php';   // 先處理 CORS
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// 建議設定 session cookie 參數（也可集中放在 bootstrap 檔）
session_set_cookie_params([
    'lifetime' => 60*60*24*7,
    'path' => '/',
    'secure' => false,      // https 才能設 true
    'httponly' => true,
    'samesite' => 'Lax',    // 前後端不同網域且要跨站 cookie 時，可考慮 'None' + secure=true
]);
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['code' => '0000', 'msg' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['code' => '0001', 'msg' => '尚未登入'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mysqli = db();
$memberId = (int) $_SESSION['member_id'];

// 只取需要的欄位，避免把敏感資訊（密碼）回給前端
$sql = "SELECT 
    m.MEMBER_ID,
    m.MEMBER_NICKNAME,
    m.MEMBER_BIRTHDATE,
    m.MEMBER_CITY,
    c.CITY_NAME AS MEMBER_CITY,
    m.MEMBER_OCCUPATION,
    o.OCCUPATION AS MEMBER_OCCUPATION
FROM member m
LEFT JOIN city c ON c.CITY_NO = m.MEMBER_CITY
LEFT JOIN occupation o ON o.OCCUPATION_NO = m.MEMBER_OCCUPATION
WHERE m.member_id = ?";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $memberId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    http_response_code(404);
    echo json_encode(['code' => '0002', 'msg' => '找不到會員'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 算年齡
if (!empty($user['MEMBER_BIRTHDATE'])) {
    $birth = new DateTime($user['MEMBER_BIRTHDATE']);
    $today = new DateTime();
    $user['age'] = $today->diff($birth)->y;
} else {
    $user['age'] = null;
}

http_response_code(200);
echo json_encode([
    'code' => '0003',
    'msg'  => '會員資料取得',
    'data' => $user
], JSON_UNESCAPED_UNICODE);
exit;
