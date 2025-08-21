<?php
  # 更新活動留言檢舉狀態
  # PATCH
  require_once __DIR__ . '/../../config/db.php';

  if($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $db = db();
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if(!is_array(($data))) {
      http_response_code(400);
      echo json_encode([
        "success" => false,
        "error" => "請傳送活動檢舉資料陣列"
      ],JSON_UNESCAPED_UNICODE);
      exit();
    }

    // 開啟 transaction
    $db -> begin_transaction();

     $stmt = $db->prepare("
        UPDATE post_report
        SET 
            REPORT_STATUS = ?,
            STAFF_ID = CASE 
                WHEN ? IS NULL THEN NULL
                ELSE (SELECT STAFF_ID FROM staff WHERE STAFF_NAME = ?)
            END
        WHERE POST_REPORT_NO = ?
    ");

    $allSuccess = true;
    $errors = [];

    try {
      foreach($data as $key => $value) {
        // 檢查必填欄位
        if(!isset($value['id']) || !array_key_exists('status', $value) || !array_key_exists('admin', $value)) {
                throw new Exception("資料錯誤：第 {$key} 筆缺少 id、status 或 admin");
            }

        $reportNo = intval($value['id']);
        $status = $value['status'];
        $adminName = $value['admin']; // 可以是 null

        // bind_param 需要對應類型 s = string, i = int
        // ADMIN_NAME 用兩次 (判斷 null + SELECT)
        $stmt->bind_param('sssi', $status, $adminName, $adminName, $reportNo);

        if(!$stmt->execute()) {
                throw new Exception("更新活動留言檢舉 {$reportNo} 時發生錯誤：" . $stmt->error);
            }
      }

      // 全部成功，提交 transaction
      $db->commit();

      echo json_encode([
          "success" => true,
          "message" => "所有活動留言檢舉已更新"
      ], JSON_UNESCAPED_UNICODE);
    } catch(Exception $e) {
      // 發生錯誤，回滾 transaction
      $db->rollback();
      http_response_code(500);
      echo json_encode([
          "success" => false,
          "error" => $e->getMessage()
      ], JSON_UNESCAPED_UNICODE);
    }

    $stmt->close();
    $db->close();
    exit();

  }
  
  http_response_code(403);
  echo json_encode(["error" => "拒絕存取。"], JSON_UNESCAPED_UNICODE);
?>