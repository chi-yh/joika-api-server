<?php

session_start();

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$db = db();

$UPLOAD_DIR_ABS  = __DIR__ . '/../upload/article-img';
$PUBLIC_IMG_BASE = 'upload/article-img';

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
if (empty($_POST['post_no']))        $missing[] = 'post_no';
if (empty($_POST['category_no']))    $missing[] = 'category_no';
if (empty($_POST['post_title']))     $missing[] = 'post_title';
if (empty($_POST['post_content']))   $missing[] = 'post_content';
if ($missing) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'缺少必要欄位','fields'=>$missing], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4) 取得值
$post_no     = (int)$_POST['post_no'];
$category_no = (int)$_POST['category_no'];
$title_raw   = (string)$_POST['post_title'];
$html_raw    = (string)$_POST['post_content'];

// 5) 內文只存純文字（保留基本換行感）
$content_for_plain = preg_replace('#<(br|BR)\s*/?>#', "\n", $html_raw);
$content_for_plain = preg_replace('#</p\s*>#i', "\n", $content_for_plain);
$plain_text        = trim(html_entity_decode(strip_tags($content_for_plain), ENT_QUOTES, 'UTF-8'));

// 6) 處理首圖（可選）
$post_img_path = null;
if (isset($_FILES['post_img']) && $_FILES['post_img']['error'] === UPLOAD_ERR_OK) {
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

    $post_img_path = rtrim($PUBLIC_IMG_BASE, '/') . '/' . $filename;
}

// 7) 權限檢查：只能編輯自己的文章
$sqlCheck = "SELECT MEMBER_ID FROM post WHERE POST_NO={$post_no} LIMIT 1";
$resCheck = $db->query($sqlCheck);
if (!$resCheck || !$resCheck->num_rows) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'找不到文章'], JSON_UNESCAPED_UNICODE);
    exit;
}
$row = $resCheck->fetch_assoc();
if ((int)$row['MEMBER_ID'] !== $member_id) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'沒有編輯權限'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 8) escape 避免 SQL 注入
$title_esc   = $db->real_escape_string($title_raw);
$content_esc = $db->real_escape_string($plain_text);
$status_esc  = $db->real_escape_string('顯示');
$img_sql     = $post_img_path ? ", POST_IMG='" . $db->real_escape_string($post_img_path) . "'" : "";

// 9) 更新 DB
$sqlUpdate = "
    UPDATE post SET
        CATEGORY_NO={$category_no},
        POST_TITLE='{$title_esc}',
        POST_CONTENT='{$content_esc}',
        POST_STATUS='{$status_esc}'
        {$img_sql}
    WHERE POST_NO={$post_no}
    LIMIT 1
";

$result = $db->query($sqlUpdate);

if ($result && $db->affected_rows) {
    echo json_encode([
        'ok' => true,
        'POST_NO' => $post_no,
        'affected_rows' => $db->affected_rows,
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => '更新文章失敗或無變更',
        'details' => $db->error,
    ], JSON_UNESCAPED_UNICODE);
}
$db->close();