<?php
#POST
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$db = db();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
    exit;
}

// 取得 POST 的 JSON 內容
$data = json_decode(file_get_contents("php://input"), true);
$notificationNo = $data['notification_no'] ?? null;

// 驗證參數
if (!$notificationNo) {
    http_response_code(400);
    echo json_encode(["error" => "缺少通知編號"], JSON_UNESCAPED_UNICODE);
    exit;
}

// 執行更新
$sql = "
    UPDATE notification
    SET NOTIFICATION_STATUS = '已讀'
    WHERE NOTIFICATION_NO = " . (int)$notificationNo;

$result = $db->query($sql);

if ($result) {
    echo json_encode(["success" => true], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "更新失敗：" . $db->error
    ], JSON_UNESCAPED_UNICODE);
}
?>