<?php
session_start();

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$db = db();

// 1) 只允許 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false, 'error'=>'只允許 POST 請求'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2) 要登入會員才能檢舉
if (empty($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode([
        'ok'          => false,
        'error'       => '請先登入會員',
        'sid'         => session_id(),
        'http_cookie' => $_SERVER['HTTP_COOKIE'] ?? null,
        'session_dump'=> $_SESSION
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$reporter_id = (int)$_SESSION['member_id'];

// 3) 同時支援 JSON 與 form-data
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input) || count($input) === 0) {
    // 若是 multipart/form-data，json_decode 會是 null，改讀 $_POST
    $input = $_POST;
}

// 4) 取值（欄位：post_no, report_reason_no, report_description）
//    ※ reporter_id 從 session 取
$post_no            = (int)($input['post_no'] ?? 0);
$report_reason_no   = (int)($input['report_reason_no'] ?? 0);
$report_description = trim((string)($input['report_description'] ?? ''));

$desc_esc = $db->real_escape_string($report_description);

// 5) 必要欄位檢查（哪個缺就回報）
$missing = [];
if ($post_no <= 0)          $missing[] = 'post_no';
if ($report_reason_no <= 0) $missing[] = 'report_reason_no';
if (!empty($missing)) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'缺少必要欄位', 'fields'=>$missing], JSON_UNESCAPED_UNICODE);
    exit;
}

// 6) 寫入 DB（直寫 SQL）
$desc_esc = $db->real_escape_string($report_description);

$sql = "
    INSERT INTO post_report
    (REPORTER_ID, POST_NO, REPORT_REASON_NO, REPORT_DESCRIPTION, REPORT_STATUS, CREATED_AT)
    VALUES (
        {$reporter_id},
        {$post_no},
        {$report_reason_no},
        '{$desc_esc}',
        '待審核',
        NOW()
    )
";

$result = $db->query($sql);

if ($result) {
    echo json_encode([
        'ok'        => true,
        'report_no' => $db->insert_id,
        'affected'  => $db->affected_rows
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode([
        'ok'     => false,
        'error'  => '資料庫操作失敗',
        'detail' => $db->error
    ], JSON_UNESCAPED_UNICODE);
}

$db->close();
