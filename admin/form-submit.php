<?php
# 新增通知
# POST
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = db();
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "請傳送通知資料陣列"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $db->begin_transaction();

    $stmt = $db->prepare("
        INSERT INTO notification (
            NOTIFICATION_TITLE,
            NOTIFICATION_CONTENT,
            MEMBER_ID,
            CREATED_AT,
            AVAILABLE_AT,
            NOTIFICATION_STATUS,
            PROCESSED_BY,
            NOTIFICATION_TYPE
        ) VALUES (
            '您有一則客服回覆',
            ?, 
            ?, 
            NOW(), 
            NULL, 
            '未讀',
            CASE 
                WHEN ? IS NULL THEN NULL
                ELSE (SELECT STAFF_ID FROM staff WHERE STAFF_NAME = ?)
            END,
            '系統通知'
        )
    ");

    try {
        foreach ($data as $key => $value) {
            if (!isset($value['MEMBER_ID']) || !array_key_exists('REPLY_CONTENT', $value) || !array_key_exists('PROCESSED_NAME', $value)) {
                throw new Exception("資料錯誤：第 {$key} 筆缺少 MEMBER_ID、REPLY_CONTENT 或 PROCESSED_NAME");
            }

            $memberId      = intval($value['MEMBER_ID']);
            $replyContent  = $value['REPLY_CONTENT'];     // 通知內容
            $processedName = $value['PROCESSED_NAME'];    // 可為 null

            $stmt->bind_param('siss', $replyContent, $memberId, $processedName, $processedName);

            if (!$stmt->execute()) {
                throw new Exception("新增通知（會員 {$memberId}）時發生錯誤：" . $stmt->error);
            }
        }

        $db->commit();

        echo json_encode([
            "success" => true,
            "message" => "所有通知已新增"
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
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