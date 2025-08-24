<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

session_set_cookie_params([
    'lifetime' => 60*60*24*7, 'path' => '/', 'secure' => false,
    'httponly' => true, 'samesite' => 'Lax'
]);
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['code'=>'0000','msg'=>'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mysqli = db();

/* 只選需要的欄位，避免 SELECT * */
$sql = "SELECT MEMBER_ID, MEMBER_NICKNAME, MEMBER_AVATAR, MEMBER_PASSWORD
        FROM member
        WHERE MEMBER_PHONE = ? AND MEMBER_STATUS = '已通過'
        LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $_POST['member_phone']);
$stmt->execute();

/* 這裡不用 get_result()，改用 store_result + bind_result + fetch */
$stmt->store_result();
if ($stmt->num_rows === 0) {
    echo json_encode(['code'=>'0001','msg'=>'此帳號不存在'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_result($member_id, $nickname, $avatar, $password_hash_or_plain);
$stmt->fetch();

$inputPwd = $_POST['password'] ?? '';

/* 兼容兩種狀況：資料庫已哈希 or 仍是明碼 */
$password_ok = password_verify($inputPwd, $password_hash_or_plain) 
    || ($inputPwd === $password_hash_or_plain);

if (!$password_ok) {
    echo json_encode(['code'=>'0002','msg'=>'密碼錯誤'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* 登入成功 → 設定 session */
$_SESSION['member_id'] = (int)$member_id;
$_SESSION['user'] = [
    'id'       => (int)$member_id,
    'nickname' => $nickname,
    'avatar'   => $avatar,
];

echo json_encode(['code'=>'0003','msg'=>'登入成功'], JSON_UNESCAPED_UNICODE);
