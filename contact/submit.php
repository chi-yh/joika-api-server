
<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => '不支援的請求方法'], JSON_UNESCAPED_UNICODE);
    exit;
}
$input = json_decode(file_get_contents("php://input"), true);

$member_id = intval($input['member_id'] ?? 0);
$member_phone = trim($input['member_phone'] ?? '');
$member_email = trim($input['member_email'] ?? '');
$form_title = trim($input['form_title'] ?? '');
$form_content = trim($input['form_content'] ?? '');

if (!$member_id || !$member_phone || !$member_email || !$form_title || !$form_content) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '缺少必要欄位']);
    exit;
}

$db = db();
$stmt = $db->prepare("INSERT INTO support_form (MEMBER_ID, MEMBER_PHONE, MEMBER_EMAIL, FORM_TITLE, FORM_CONTENT,FORM_STATUS,CREATED_AT) VALUES (?, ?, ?, ?, ?, '待處理', NOW())");
$stmt->bind_param("issss", $member_id, $member_phone, $member_email, $form_title, $form_content);
$result = $stmt->execute();
$stmt->close();
$db->close();

if ($result) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '資料庫寫入失敗']);
}
?>