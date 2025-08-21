
<?php

// 顯示錯誤（開發期用，正式環境建議關掉）
ini_set('display_errors', 1);
error_reporting(E_ALL);

 header("Access-Control-Allow-Origin: *");
 header("Content-Type: application/json; charset=UTF-8");

    // require_once __DIR__ . '/../config/cors.php';
    // require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error'=>true,'message'=>'只接受 POST']);
  exit;
}

if (!isset($_FILES['activity_img']) || $_FILES['activity_img']['error'] !== UPLOAD_ERR_OK) {
  http_response_code(400);
  echo json_encode(['error'=>true,'message'=>'沒有上傳檔案或檔案有錯誤']);
  exit;
}

$fileTmp  = $_FILES['activity_img']['tmp_name'];
$fileName = $_FILES['activity_img']['name'];
$fileSize = $_FILES['activity_img']['size'];
$fileType = $_FILES['activity_img']['type'];

// 安全檢查：只允許特定圖片格式
$allowed = ['image/jpeg','image/png','image/webp','image/gif'];
if (!in_array($fileType, $allowed)) {
  http_response_code(400);
  echo json_encode(['error'=>true,'message'=>'只允許 jpg/png/webp/gif 格式']);
  exit;
}

// 產生唯一檔名
$ext = pathinfo($fileName, PATHINFO_EXTENSION);
$safeName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);

// 上傳路徑
$uploadDirAbs = __DIR__ . '/../upload/article-img';
$uploadDirRel = '/upload/article-img';
if (!is_dir($uploadDirAbs)) { mkdir($uploadDirAbs, 0777, true); }

$destAbs = rtrim($uploadDirAbs, '/\\') . '/' . $safeName;
if (!move_uploaded_file($fileTmp, $destAbs)) {
  http_response_code(500);
  echo json_encode(['error'=>true,'message'=>'圖片儲存失敗']);
  exit;
}

$upload_rel_path = rtrim($uploadDirRel, '/\\') . '/' . $safeName;

// 回傳路徑給前端
echo json_encode([
  'error' => false,
  'message' => '上傳成功',
  'path' => $upload_rel_path
], JSON_UNESCAPED_UNICODE);