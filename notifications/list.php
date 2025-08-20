<?php
#GET
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$db = db();

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
    exit;
}

$memberId = $_GET['member_id'] ?? null;
$type = $_GET['type'] ?? null; // '系統通知' 或 '互動通知'

if (!$memberId) {
    http_response_code(400);
    echo json_encode(["error" => "缺少會員編號"], JSON_UNESCAPED_UNICODE);
    exit;
}

$memberId = (int)$memberId;

$sql = "SELECT 
            n.NOTIFICATION_NO,
            n.NOTIFICATION_TITLE,
            n.NOTIFICATION_CONTENT,
            n.NOTIFICATION_STATUS,
            n.CREATED_AT
        FROM notification n
        WHERE n.MEMBER_ID = $memberId";

if ($type) {
    $safeType = $db->real_escape_string($type);
    $sql .= " AND n.NOTIFICATION_TYPE = '$safeType'";
}

$result = $db->query($sql);

if ($result) {
    $data = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode(["error" => "資料庫查詢失敗：" . $db->error], JSON_UNESCAPED_UNICODE);
}