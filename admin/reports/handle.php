<?php
#POST
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

$db = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '不支援的請求方法'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$reportNo = $input['report_no'] ?? null;
$newStatus = $input['status'] ?? null;
$staffId = $input['staff_id'] ?? null;

if (!$reportNo || !$newStatus || !$staffId) {
    http_response_code(400);
    echo json_encode(['error' => '缺少必要欄位'], JSON_UNESCAPED_UNICODE);
    exit;
}

$safeStatus = $db->real_escape_string($newStatus);
$safeReportNo = (int)$reportNo;
$safeStaffId = (int)$staffId;

$sql = "
    UPDATE post_report 
    SET 
        REPORT_STATUS = '{$safeStatus}',
        STAFF_ID = {$safeStaffId},
        HANDLE_AT = NOW()
    WHERE POST_REPORT_NO = {$safeReportNo}
    ";

    $result = $db->query($sql);

if ($result) {
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '資料庫更新失敗：' . $db->error
    ], JSON_UNESCAPED_UNICODE);
}
?>