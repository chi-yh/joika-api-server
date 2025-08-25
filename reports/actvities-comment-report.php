<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
$db = db();
if (method_exists($db, 'set_charset')) $db->set_charset('utf8mb4');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'只允許 POST']); exit;
}

$memberId = (int)($_SESSION['member_id'] ?? 0);
if ($memberId <= 0) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'請先登入會員']); exit; }

// 讀 JSON 或 form
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input) || !$input) $input = $_POST;

// 參數
$acNo   = (int)($input['activity_comment_no'] ?? 0);
$reason = (int)($input['report_reason_no'] ?? 0);
$desc   = trim((string)($input['report_description'] ?? ''));
if ($acNo <= 0 || $reason <= 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'缺少必要欄位：activity_comment_no, report_reason_no']); exit;
}

// 內容最長 255（你的欄位限制），避免超長被截斷
if (mb_strlen($desc) > 255) $desc = mb_substr($desc, 0, 255);
$descEsc = $db->real_escape_string($desc);

// 1) 檢查 reason 存在
$rs = $db->query("SELECT 1 FROM report_reason WHERE REASON_NO={$reason} LIMIT 1");
if (!$rs || $rs->num_rows === 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'無效的 report_reason_no']); exit; }

// 2) 檢查「活動留言」存在
$rs = $db->query("SELECT 1 FROM activity_comment WHERE ACTIVITY_COMMENT_NO={$acNo} LIMIT 1");
if (!$rs || $rs->num_rows === 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'找不到對應的活動留言']); exit; }

// 3) （可選）避免重複送審：同人、同留言、待審核中就不重複
$dup = $db->query("SELECT 1 FROM activity_comment_report
                WHERE REPORTER_ID={$memberId} AND ACTIVITY_COMMENT_NO={$acNo}
                    AND REPORT_STATUS='待審核' LIMIT 1");
if ($dup && $dup->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['ok'=>false,'error'=>'已送出相同檢舉，等待審核中']); exit;
}

// 4) 寫入（不改表的情況下：HANDLE_AT 先塞 NOW()）
$sql = "
    INSERT INTO activity_comment_report
    (REPORTER_ID, ACTIVITY_COMMENT_NO, REPORT_REASON_NO, REPORT_DESCRIPTION, REPORT_STATUS, CREATED_AT, HANDLE_AT)
    VALUES ({$memberId}, {$acNo}, {$reason}, '{$descEsc}', '待審核', NOW(), NOW())
";
$ok = $db->query($sql);

if ($ok) {
    echo json_encode(['ok'=>true, 'activity_comment_report_id'=>$db->insert_id], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'資料庫操作失敗', 'detail'=>$db->error], JSON_UNESCAPED_UNICODE);
}
