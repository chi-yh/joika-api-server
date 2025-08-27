<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

function out_json($status, $arr) {
    http_response_code($status);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    out_json(405, ['code'=>'0001','msg'=>'Method Not Allowed']);
}

if (empty($_SESSION['member_id'])) {
    out_json(401, ['code'=>'0002','msg'=>'尚未登入']);
}

$mysqli = db();
if (function_exists('mysqli_set_charset')) {
    if (!@mysqli_set_charset($mysqli, 'utf8mb4')) {
        @mysqli_set_charset($mysqli, 'utf8');
    }
}

/* 參數處理：避免 ??、轉成 int */
$activityNo = 0;
if (isset($_GET['activity_no'])) {
    $activityNo = (int) trim((string)$_GET['activity_no']);
}
if ($activityNo === 0) {
    out_json(400, ['code'=>'0004','msg'=>'缺少或不合法的 activity_no']);
}

/* 依照伺服器實際表名大小寫調整；此處使用小寫 + 反引號 */
$sql = "SELECT `HOST_MEMBER_ID` FROM `activity` WHERE `ACTIVITY_NO` = ?";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    out_json(500, ['code'=>'9998','msg'=>'Prepare failed','debug'=>$mysqli->error]);
}

if (!$stmt->bind_param('i', $activityNo)) {
    out_json(500, ['code'=>'9998','msg'=>'Bind failed','debug'=>$stmt->error]);
}

if (!$stmt->execute()) {
    $dbg = $stmt->error;
    $stmt->close();
    out_json(500, ['code'=>'9998','msg'=>'Execute failed','debug'=>$dbg]);
}

$stmt->bind_result($hostId);
$stmt->fetch();
$stmt->close();

if (!$hostId) {
    out_json(404, ['code'=>'0003','msg'=>'找不到活動']);
}

$isHost = ((string)$_SESSION['member_id'] === (string)$hostId);
out_json(200, ['code'=>'0000','msg'=>'success','is_host'=>$isHost]);
