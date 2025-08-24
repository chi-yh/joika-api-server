<?php
# POST 留言檢舉
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$db = db();

if ($_SERVER["REQUEST_METHOD"] !== "POST") { 
    http_response_code(405);
    echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
file_put_contents(__DIR__ . '/debug.log', print_r($input, true));

// 取得參數
$reporterId = (int)($input['reporter_id'] ?? 0);
$commentNo = (int)($input['post_comment_no'] ?? 0);
$reasonNo = (int)($input['report_reason_no'] ?? 0);
$description = trim($input['report_description'] ?? '');

// 驗證參數
if ($reporterId <= 0 || $commentNo <= 0 || $reasonNo <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "缺少必要欄位：reporter_id, post_comment_no, report_reason_no"], JSON_UNESCAPED_UNICODE);
    exit;
}

// 查詢留言對應的文章編號
$sqlFindPost = "SELECT POST_NO FROM post_comment WHERE POST_COMMENT_NO = {$commentNo} LIMIT 1";
$res = $db->query($sqlFindPost);
if (!$res || $res->num_rows === 0) {
    http_response_code(400);
    echo json_encode(["error" => "找不到對應的留言"], JSON_UNESCAPED_UNICODE);
    exit;
}
$row = $res->fetch_assoc();
$postNo = (int)$row['POST_NO'];

// 防止 SQL Injection
$descriptionEscaped = $db->real_escape_string($description);

// 寫入 post_report
$sql = "INSERT INTO post_report (
    REPORTER_ID,
    POST_NO,
    POST_COMMENT_NO,
    REPORT_REASON_NO,
    REPORT_DESCRIPTION,
    REPORT_STATUS,
    CREATED_AT
) VALUES (
    {$reporterId},
    {$postNo},
    {$commentNo},
    {$reasonNo},
    '{$descriptionEscaped}',
    '待審核',
    NOW()
)";

$result = $db->query($sql);

if ($result) {
    echo json_encode(["success" => true], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "資料庫操作失敗"
    ], JSON_UNESCAPED_UNICODE);
}
?>