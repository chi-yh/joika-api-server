<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// ---- Session 設定（新舊版相容）----
// 新版（7.3+）支援陣列寫法 + SameSite
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => 60*60*24*7,
        'path'     => '/',
        'secure'   => false,  // https 再改 true
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
} else {
    // 舊版（<7.3）只能用舊參數順序，沒有 SameSite
    // session_set_cookie_params(lifetime, path, domain, secure, httponly)
    session_set_cookie_params(60*60*24*7, '/', '', false, true);
}
session_start();

// ---- Method 檢查 ----
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(array('code' => '0000', 'msg' => 'Method Not Allowed'), defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0);
    exit;
}

// ---- 登入檢查 ----
if (!isset($_SESSION['member_id']) || !$_SESSION['member_id']) {
    http_response_code(401);
    echo json_encode(array('code' => '0001', 'msg' => '尚未登入'), defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0);
    exit;
}

$mysqli   = db();
$memberId = (int) $_SESSION['member_id'];

// ---- 僅取必要欄位 ----
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
if (!$stmt) {
    http_response_code(500);
    echo json_encode(array('code'=>'9999','msg'=>'Server Error (prepare failed)'), defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0);
    exit;
}

$stmt->bind_param("i", $memberId);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(array('code'=>'9999','msg'=>'Server Error (execute failed)'), defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0);
    exit;
}

/*
 * 重要：為了支援舊主機（無 mysqlnd），不要用 get_result()
 * 用 store_result() + bind_result() 來取資料
 */
$stmt->store_result();

if ($stmt->num_rows === 0) {
    http_response_code(404);
    echo json_encode(array('code'=>'0002','msg'=>'找不到會員'), defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0);
    $stmt->close();
    exit;
}

// 依照 SELECT 欄位順序依序綁定變數
$stmt->bind_result(
    $MEMBER_ID,
    $MEMBER_NICKNAME,
    $MEMBER_BIRTHDATE,
    $MEMBER_CITY,
    $MEMBER_CITY_NAME,
    $MEMBER_OCCUPATION,
    $MEMBER_OCCUPATION_NAME,
    $MEMBER_AVATAR,
    $HOST_SCORE_TOTAL,
    $HOST_COUNT_TOTAL,
    $JOINER_SCORE_TOTAL,
    $JOINER_COUNT_TOTAL
);

$stmt->fetch();

// 組成陣列（舊版相容：使用 array() 而非 []）
$user = array(
    'MEMBER_ID'               => $MEMBER_ID,
    'MEMBER_NICKNAME'         => $MEMBER_NICKNAME,
    'MEMBER_BIRTHDATE'        => $MEMBER_BIRTHDATE,
    'MEMBER_CITY'             => $MEMBER_CITY,
    'MEMBER_CITY_NAME'        => $MEMBER_CITY_NAME,
    'MEMBER_OCCUPATION'       => $MEMBER_OCCUPATION,
    'MEMBER_OCCUPATION_NAME'  => $MEMBER_OCCUPATION_NAME,
    'MEMBER_AVATAR'           => $MEMBER_AVATAR,
    'HOST_SCORE_TOTAL'        => (int) $HOST_SCORE_TOTAL,
    'HOST_COUNT_TOTAL'        => (int) $HOST_COUNT_TOTAL,
    'JOINER_SCORE_TOTAL'      => (int) $JOINER_SCORE_TOTAL,
    'JOINER_COUNT_TOTAL'      => (int) $JOINER_COUNT_TOTAL,
);

$stmt->free_result();
$stmt->close();

// ---- 算年齡（可在舊版 PHP 5.2+ 使用）----
if (!empty($user['MEMBER_BIRTHDATE'])) {
    try {
        $birth = new DateTime($user['MEMBER_BIRTHDATE']);
        $today = new DateTime();
        $user['age'] = (int) $today->diff($birth)->y;
    } catch (Exception $e) {
        $user['age'] = null; // 日期異常就給 null
    }
}

// ---- 算評分（避免除以 0）----
$hostScoreTotal   = isset($user['HOST_SCORE_TOTAL'])   ? (int)$user['HOST_SCORE_TOTAL']   : 0;
$hostCountTotal   = isset($user['HOST_COUNT_TOTAL'])   ? (int)$user['HOST_COUNT_TOTAL']   : 0;
$joinerScoreTotal = isset($user['JOINER_SCORE_TOTAL']) ? (int)$user['JOINER_SCORE_TOTAL'] : 0;
$joinerCountTotal = isset($user['JOINER_COUNT_TOTAL']) ? (int)$user['JOINER_COUNT_TOTAL'] : 0;

$user['hostAvg']   = ($hostCountTotal   > 0) ? round($hostScoreTotal   / $hostCountTotal,   0) : null;
$user['joinerAvg'] = ($joinerCountTotal > 0) ? round($joinerScoreTotal / $joinerCountTotal, 0) : null;

// ---- 回傳 ----
http_response_code(200);
echo json_encode(
    array(
        'code' => '0003',
        'msg'  => '會員資料取得',
        'data' => $user
    ),
    defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0
);
exit;
