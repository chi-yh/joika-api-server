<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
$db = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false, 'error'=>'只允許 POST']); exit;
}

$memberId = $_SESSION['member_id'] ?? null;
if (!$memberId) {
    http_response_code(401);
    echo json_encode(['ok'=>false, 'error'=>'請先登入會員']); exit;
}

// 讀 JSON 或 form
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input) || !$input) $input = $_POST;

$commentNo = (int)($input['post_comment_no'] ?? 0);
$reasonNo  = (int)($input['report_reason_no'] ?? 0);
$desc      = $db->real_escape_string(trim((string)($input['report_description'] ?? '')));

if ($commentNo <= 0 || $reasonNo <= 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'缺少必要欄位：post_comment_no, report_reason_no']); exit;
}

// 1) 檢查 reason 是否存在
$sql = "SELECT 1 FROM report_reason WHERE REASON_NO = {$reasonNo} LIMIT 1";
$result = $db->query($sql);
if (!$result || $result->num_rows === 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'無效的 report_reason_no']); exit;
}

// 2) 查留言對應文章
$sql = "SELECT POST_NO FROM post_comment WHERE POST_COMMENT_NO = {$commentNo} LIMIT 1";
$result = $db->query($sql);
if (!$result || $result->num_rows === 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'找不到對應的留言']); exit;
}
$row = $result->fetch_assoc();
$postNo = (int)$row['POST_NO'];

// 3) 寫入
$sql = "
    INSERT INTO post_report
    (REPORTER_ID, POST_NO, POST_COMMENT_NO, REPORT_REASON_NO, REPORT_DESCRIPTION, REPORT_STATUS, CREATED_AT)
    VALUES (
        {$memberId},
        {$postNo},
        {$commentNo},
        {$reasonNo},
        '{$desc}',
        '待審核',
        NOW()
    )
";
$result = $db->query($sql);

if ($result) {
    echo json_encode(['ok'=>true, 'report_no'=>$db->insert_id], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'資料庫操作失敗', 'detail'=>$db->error], JSON_UNESCAPED_UNICODE);
}
