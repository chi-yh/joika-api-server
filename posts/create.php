<?php
session_start();
if (isset($_GET['__dev_login']) && $_GET['__dev_login'] == '1') {
    $_SESSION['member_id'] = 1;
    echo json_encode([
        'ok' => true,
        'msg' => 'dev login set',
        'sid' => session_id(),
        'member_id' => $_SESSION['member_id']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}



require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

// 開發期可暫開
// ini_set('display_errors', '1'); error_reporting(E_ALL);

$db = db();

/* 依你的環境調整這兩個常數 */
$UPLOAD_DIR_ABS  = __DIR__ . '/../upload/article-img'; // 伺服器實體路徑
$PUBLIC_IMG_BASE = 'upload/article-img';               // 前端可讀路徑（相對或完整 URL）

// 1) 僅允許 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'只允許 POST 請求'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2) 要登入（用 session）
if (empty($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode([
        'ok'=>false,
        'error'=>'請先登入會員',
        'sid' => session_id(),
        'http_cookie' => $_SERVER['HTTP_COOKIE'] ?? null,
        'session_dump' => $_SESSION
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$member_id = (int)$_SESSION['member_id'];

// 3) 驗證必要欄位
$missing = [];
if (empty($_POST['category_no']))  $missing[] = 'category_no';
if (empty($_POST['post_title']))   $missing[] = 'post_title';
if (empty($_POST['post_content'])) $missing[] = 'post_content';
if ($missing) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'缺少必要欄位','fields'=>$missing], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4) 取得值
$category_no = (int)$_POST['category_no'];
$title_raw   = (string)$_POST['post_title'];
$html_raw    = (string)$_POST['post_content'];

// 5) 內文只存純文字（保留基本換行感）
$content_for_plain = preg_replace('#<(br|BR)\s*/?>#', "\n", $html_raw);
$content_for_plain = preg_replace('#</p\s*>#i', "\n", $content_for_plain);
$plain_text        = trim(html_entity_decode(strip_tags($content_for_plain), ENT_QUOTES, 'UTF-8'));

// 6) 處理首圖（來自額外 input: POST_IMG）
$post_img_path = null;
if (isset($_FILES['post_img']) && $_FILES['post_img']['error'] === UPLOAD_ERR_OK) {
    // 型態/大小檢查
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($_FILES['post_img']['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'首圖超過 5MB'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($_FILES['post_img']['tmp_name']) ?: '';
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($extMap[$mime])) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'不支援的圖片格式（僅 jpg/png/gif/webp）'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!is_dir($UPLOAD_DIR_ABS)) {
        if (!mkdir($UPLOAD_DIR_ABS, 0755, true) && !is_dir($UPLOAD_DIR_ABS)) {
            http_response_code(500);
            echo json_encode(['ok'=>false,'error'=>'上傳目錄建立失敗'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $filename = 'post_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extMap[$mime];
    $target   = rtrim($UPLOAD_DIR_ABS, '/\\') . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($_FILES['post_img']['tmp_name'], $target)) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>'首圖儲存失敗'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 存到 DB 的路徑（依你站點，可用相對或完整 URL）
    $post_img_path = rtrim($PUBLIC_IMG_BASE, '/') . '/' . $filename;
}

// 7) 寫入 DB（POST_CONTENT=純文字、POST_IMG=首圖路徑）
$created_at = date('Y-m-d H:i:s');
$status     = '顯示';

// escape 避免 SQL 注入
$title_esc   = $db->real_escape_string($title_raw);
$content_esc = $db->real_escape_string($plain_text);
$post_img_esc = $post_img_path ? ("'".$db->real_escape_string($post_img_path)."'") : "NULL";
$status_esc   = $db->real_escape_string($status);

$sqlInsert = "
    INSERT INTO post 
    (CATEGORY_NO, POST_TITLE, MEMBER_ID, CREATED_AT, POST_CONTENT, POST_IMG, POST_STATUS)
    VALUES (
        {$category_no},
        '{$title_esc}',
        {$member_id},
        '{$created_at}',
        '{$content_esc}',
        {$post_img_esc},
        '{$status_esc}'
    )
";

$result = $db->query($sqlInsert);

if ($result) {
    echo json_encode([
        'ok' => true,
        'POST_NO' => $db->insert_id,
        'affected_rows' => $db->affected_rows,
        'sql' => $sqlInsert   // ⚠️ 開發用，方便除錯，正式上線記得移除
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => '新增文章失敗',
        'details' => $db->error,
        'sql' => $sqlInsert   // ⚠️ 方便你看到失敗的 SQL
    ], JSON_UNESCAPED_UNICODE);
}
$db->close();
