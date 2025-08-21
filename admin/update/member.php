<?php
  # 更新會員狀態
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
        "error" => "請傳送會員資料陣列"
      ],JSON_UNESCAPED_UNICODE);
      exit();
    }

    // 開啟 transaction
    $db -> begin_transaction();

    $stmt = $db -> prepare("
        UPDATE member
        SET MEMBER_STATUS = ?
        WHERE MEMBER_ID = ?
    ");

    $allSuccess = true;
    $errors = [];

    try {
      foreach($data as $key => $value) {
        // 檢查必填欄位
        if (!isset($value['MEMBER_ID']) || !isset($value['MEMBER_STATUS'])) {
          throw new Exception("資料錯誤：第 {$key} 筆會員缺少 MEMBER_ID 或 MEMBER_STATUS");
        }

        $memberId = intval($value['MEMBER_ID']);
        $status   = $value['MEMBER_STATUS'];

        $stmt->bind_param('si', $status, $memberId);

        if(!$stmt->execute()) {
          throw new Exception("更新會員 {$memberId} 時發生錯誤：" . $stmt->error);
        }
      }

      // 全部成功，提交 transaction
      $db->commit();

      echo json_encode([
          "success" => true,
          "message" => "所有會員狀態已更新"
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