<?php
  # 更新活動本身狀態
  # PATCH
  
  require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $db = db();
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "請傳送活動資料陣列"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 開啟 transaction
    $db->begin_transaction();

    $stmt = $db->prepare("
        UPDATE activity
        SET ACTIVITY_STATUS = ?
        WHERE ACTIVITY_NO = ?
    ");

    try {
        foreach ($data as $key => $value) {
            // 檢查必填欄位
            if (!isset($value['ACTIVITY_NO']) || !isset($value['ACTIVITY_STATUS'])) {
                throw new Exception("資料錯誤：第 {$key} 筆活動缺少 ACTIVITY_NO 或 ACTIVITY_STATUS");
            }

            $activityNo = intval($value['ACTIVITY_NO']);
            $status = $value['ACTIVITY_STATUS'];

            $stmt->bind_param('si', $status, $activityNo);

            if (!$stmt->execute()) {
                throw new Exception("更新活動 {$activityNo} 時發生錯誤：" . $stmt->error);
            }
        }

        // 全部成功，提交 transaction
        $db->commit();

        echo json_encode([
            "success" => true,
            "message" => "所有活動狀態已更新"
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
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