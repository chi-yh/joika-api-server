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
    c.CITY_NAME AS MEMBER_CITY_NAME,
    m.MEMBER_OCCUPATION,
    o.OCCUPATION AS MEMBER_OCCUPATION_NAME,
    m.MEMBER_AVATAR,
    m.HOST_SCORE_TOTAL,
    m.HOST_COUNT_TOTAL,
    m.JOINER_SCORE_TOTAL,
    m.JOINER_COUNT_TOTAL
FROM member m
LEFT JOIN city c ON c.CITY_NO = m.MEMBER_CITY
LEFT JOIN occupation o ON o.OCCUPATION_NO = m.MEMBER_OCCUPATION
WHERE m.MEMBER_ID = ?";

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
}

//算評分

// 轉數字，防 null
$hostScoreTotal   = isset($user['HOST_SCORE_TOTAL'])   ? (int)$user['HOST_SCORE_TOTAL']: 0;
$hostCountTotal   = isset($user['HOST_COUNT_TOTAL'])   ? (int)$user['HOST_COUNT_TOTAL']: 0;
$joinerScoreTotal = isset($user['JOINER_SCORE_TOTAL']) ? (int)$user['JOINER_SCORE_TOTAL']: 0;
$joinerCountTotal = isset($user['JOINER_COUNT_TOTAL']) ? (int)$user['JOINER_COUNT_TOTAL']: 0;

$user['hostAvg'] = $hostCountTotal   > 0 ? round($hostScoreTotal   / $hostCountTotal,   0) : null;
$user['joinerAvg'] = $joinerCountTotal > 0 ? round($joinerScoreTotal / $joinerCountTotal, 0) : null;



http_response_code(200);
echo json_encode([
    'code' => '0003',
    'msg'  => '會員資料取得',
    'data' => $user
], JSON_UNESCAPED_UNICODE);
exit;
