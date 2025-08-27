<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['code'=>'0002','msg'=>'尚未登入'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mysqli = db();
$activityNo = $_GET['activity_no'] ?? '';

$stmt = $mysqli->prepare("SELECT HOST_MEMBER_ID FROM ACTIVITY WHERE ACTIVITY_NO=?");
$stmt->bind_param('s', $activityNo);
$stmt->execute();
$stmt->bind_result($hostId);
$stmt->fetch();
$stmt->close();

if (!$hostId) {
    echo json_encode(['code'=>'0003','msg'=>'找不到活動'], JSON_UNESCAPED_UNICODE);
    exit;
}

$isHost = (strval($_SESSION['member_id']) === strval($hostId));
echo json_encode(['code'=>'0000','msg'=>'success','is_host'=>$isHost], JSON_UNESCAPED_UNICODE);
