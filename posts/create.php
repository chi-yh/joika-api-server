<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$db = db();
session_start();
// 限制 POST 請求
if (empty($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['code' => '0001', 'msg' => '尚未登入']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '只允許 POST 請求']);
    exit;
}

// 驗證必要欄位
if (
    empty($_POST['category_no']) ||
    empty($_POST['post_title']) ||
    empty($_SESSION['member_id']) ||
    empty($_POST['post_content'])
) {
    http_response_code(400);
    echo json_encode([
        'error' => '缺少必要欄位',
        'received' => $_POST
    ]);
    exit;
}

// 轉型與轉義
$category_no = (int)$_POST['category_no'];
$title = $db->real_escape_string($_POST['post_title']);
$member_id = (int)$_SESSION['member_id'];
$content = $db->real_escape_string($_POST['post_content']);
$status = "'顯示'";
$created_at = "'" . date('Y-m-d H:i:s') . "'";

// 封面圖：從文章內容抓第一張 <img>
$imagePathInDB = 'NULL'; // 預設為 NULL

$dom = new DOMDocument();
libxml_use_internal_errors(true); // 防止 HTML 結構錯誤報錯
$dom->loadHTML($_POST['post_content']); // 不用 escaped version
$images = $dom->getElementsByTagName('img');

if ($images->length > 0) {
    $firstImgSrc = $images->item(0)->getAttribute('src');

    // 安全處理：避免空值與非字串
    if (!empty($firstImgSrc) && is_string($firstImgSrc)) {
        // 避免引號錯誤，要加單引號
        $firstImgSrc = $db->real_escape_string($firstImgSrc);
        $imagePathInDB = "'$firstImgSrc'";
    }
}

// 寫入資料庫
$sql = "INSERT INTO post (CATEGORY_NO, POST_TITLE, MEMBER_ID, CREATED_AT, POST_CONTENT, POST_IMG, POST_STATUS)
        VALUES ($category_no, '$title', $member_id, $created_at, '$content', $imagePathInDB, $status)";

$result = $db->query($sql);

if ($result) {
    echo json_encode(['success' => true, 'POST_NO' => $db->insert_id], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode(['error' => '新增文章失敗', 'details' => $db->error], JSON_UNESCAPED_UNICODE);
}

$db->close();
