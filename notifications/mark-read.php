<?php
// notifications/mark-read.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$db = db();

// 只允許 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '不支援的請求方法'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 驗證登入（兼容兩種 session key）
$sessionMemberId = $_SESSION['member_id'] ?? ($_SESSION['user']['id'] ?? null);
if (empty($sessionMemberId)) {
    http_response_code(401);
    echo json_encode(['error' => '未登入'], JSON_UNESCAPED_UNICODE);
    exit;
}
$userId = (int)$sessionMemberId;

// 讀取 body（支援 JSON 與 form）
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) $input = $_POST;

// 支援兩種寫法：{ notification_no: 12 } 或 { ids: [12,13] }
$ids = [];
if (!empty($input['ids']) && is_array($input['ids'])) {
    $ids = $input['ids'];
} elseif (!empty($input['notification_no'])) {
    $ids = [ $input['notification_no'] ];
}

// 驗證
$ids = array_values(array_unique(array_map('intval', $ids)));
$ids = array_filter($ids, fn($v) => $v > 0);
if (empty($ids)) {
    http_response_code(400);
    echo json_e_
}