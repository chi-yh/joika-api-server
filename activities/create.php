<?php

require_once __DIR__ . '/../config/db.php';
$db = db();
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Content-Type: application/json; charset=UTF-8");
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error'=>true,'message'=>'只接受 POST'], JSON_UNESCAPED_UNICODE);
  exit;
}


// 1) 取得欄位（全部用表單 name 對應）
$activity_name         = $_POST['activity_name']         ?? '';
$category_no           = $_POST['category_no']           ?? '';
$min_participant       = (int)($_POST['min_participant'] ?? 1);
$max_participant       = isset($_POST['max_participant']) ? (int)$_POST['max_participant'] : null;
$location              = $_POST['location']              ?? '';
$address               = $_POST['address']               ?? '';
$fee_notes             = $_POST['fee_notes']             ?? null;
$activity_description  = $_POST['activity_description']  ?? null;
$registration_deadline = $_POST['registration_deadline'] ?? ''; // YYYY-MM-DD
$participant_limitation= $_POST['participant_limitation']?? null;
$registration_start_date = $_POST['registration_start_date']?? null;
// 2) dateRange（Element Plus datetimerange）→ 兩個 datetime
$activity_start_date = $_POST['activity_start_date'] ?? '';
$activity_end_date   = $_POST['activity_end_date']   ?? '';


// 3) 基本驗證
if (!$activity_name)          { http_response_code(400); echo json_encode(['error'=>true,'message'=>'活動名稱必填']); exit; }
if (!$category_no)            { http_response_code(400); echo json_encode(['error'=>true,'message'=>'活動類別必選']); exit; }
if (!$location || !$address)  { http_response_code(400); echo json_encode(['error'=>true,'message'=>'活動地點與地址必填']); exit; }
if (!$activity_start_date || !$activity_end_date) {
  http_response_code(400); echo json_encode(['error'=>true,'message'=>'活動開始與結束時間必填']); exit;
}
if (!$registration_deadline)  { http_response_code(400); echo json_encode(['error'=>true,'message'=>'揪團截止日必填']); exit; }
if ($max_participant !== null && $max_participant < $min_participant) {
  http_response_code(400); echo json_encode(['error'=>true,'message'=>'最多人數不可小於最少人數']); exit;
}
$location_full = trim($location.$address); 

// 4) 上傳圖片
$upload_rel_path = null; // 存資料庫用
if (isset($_FILES['activity_img']) && $_FILES['activity_img']['error'] === UPLOAD_ERR_OK) {
  $fileTmp  = $_FILES['activity_img']['tmp_name'];
  $fileName = $_FILES['activity_img']['name'];
  $fileSize = $_FILES['activity_img']['size'];
 $fileType = $_FILES['activity_img']['type'];

  // 安全：允許的 MIME
  $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
  if (!in_array($fileType, $allowed)) {
    http_response_code(400);
    echo json_encode(['error'=>true,'message'=>'只允許上傳圖片（jpg/png/webp/gif）']);
    exit;
  }

  // 產生唯一檔名
  $ext = pathinfo($fileName, PATHINFO_EXTENSION);
  $safeName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);

  $uploadDirAbs = __DIR__ . '/../upload/activities-img';
  $uploadDirRel = '/upload/activities-img'; // 存在資料庫的相對路徑
  if (!is_dir($uploadDirAbs)) { mkdir($uploadDirAbs, 0777, true); }

  $destAbs =rtrim($uploadDirAbs, '/\\') . '/' . $safeName;
  if (!move_uploaded_file($fileTmp, $destAbs)) {
    http_response_code(500);
    echo json_encode(['error'=>true,'message'=>'圖片儲存失敗']);
    exit;
  }
  $upload_rel_path = $upload_rel_path = rtrim($uploadDirRel, '/\\') . '/' . $safeName;
}
$registration_start_date = $_POST['registration_start_date'] ?? null;
if ($registration_start_date === '') { $registration_start_date = null; }
   $current_participant = 0;         // 目前報名人數
  $activity_status     = '審核中';    // 依你的 schema（文字/數字）自己決定
$host_member_id = 1; // 先用假的會員ID，之後換成登入的ID
// 5) 寫入資料庫
try {
  $sql = "INSERT INTO activity (
             ACTIVITY_NAME,
            CATEGORY_NO,
            MIN_PARTICIPANT,
            MAX_PARTICIPANT,
            LOCATION,
            FEE_NOTES,
            ACTIVITY_DESCRIPTION,
            ACTIVITY_START_DATE,
            ACTIVITY_END_DATE,
            REGISTRATION_START_DATE,   
            REGISTRATION_DEADLINE,
            PARTICIPANT_LIMITATION,
            ACTIVITY_IMG,
            CURRENT_PARTICIPANT,       
            ACTIVITY_STATUS,           
            HOST_MEMBER_ID             
          )
           VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?)";

  $stmt = $db->prepare($sql);
$types = 'ssiisssssssssisi';
$stmt->bind_param(
  $types,
  $activity_name,            // 1
  $category_no,              // 2
  $min_participant,          // 3
  $max_participant,          // 4
  $location_full,            // 5
  $fee_notes,                // 6
  $activity_description,     // 7
  $activity_start_date,      // 8
  $activity_end_date,        // 9
  $registration_start_date,  // 10
  $registration_deadline,    // 11
  $participant_limitation,   // 12
  $upload_rel_path,          // 13
  $current_participant,      // 14
  $activity_status,          // 15
  $host_member_id            // 16
);
$stmt->execute();
$id = (int)$db->insert_id;

  echo json_encode([
    'error' => false,
    'message' => '建立成功',
    'id' => (int)$db->insert_id,
    'img' => $upload_rel_path
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>true,'message'=>'寫入失敗','detail'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>