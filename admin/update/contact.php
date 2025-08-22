<?php
# 更新聯絡表單狀態 & 新增通知
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
            "error" => "請傳送聯絡表單資料陣列"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $db->begin_transaction();

    # 更新 support_form
    $stmtUpdate = $db->prepare("
        UPDATE support_form
        SET 
            FORM_STATUS = ?,
            REPLY_CONTENT = ?,
            REPLY_AT = NOW(),
            PROCESSED_BY = CASE 
                WHEN ? IS NULL THEN NULL
                ELSE (SELECT STAFF_ID FROM staff WHERE STAFF_NAME = ?)
            END
        WHERE FORM_ID = ?
    ");

    # 新增 notification
    $stmtInsert = $db->prepare("
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
            '互動通知'
        )
    ");

    try {
        foreach ($data as $key => $value) {
            if (!isset($value['FORM_ID']) || 
                !array_key_exists('FORM_STATUS', $value) || 
                !array_key_exists('PROCESSED_NAME', $value) || 
                !array_key_exists('REPLY_CONTENT', $value) ||
                !array_key_exists('MEMBER_ID', $value)) {
                throw new Exception("資料錯誤：第 {$key} 筆缺少必要欄位");
            }

            $formId        = intval($value['FORM_ID']);
            $status        = $value['FORM_STATUS'];
            $replyContent  = $value['REPLY_CONTENT'];
            $memberId      = intval($value['MEMBER_ID']);
            $processedName = $value['PROCESSED_NAME']; // 可為 null

            # 先更新 support_form
            $stmtUpdate->bind_param('ssssi', $status, $replyContent, $processedName, $processedName, $formId);
            if (!$stmtUpdate->execute()) {
                throw new Exception("更新聯絡表單 {$formId} 失敗：" . $stmtUpdate->error);
            }

            # 再新增 notification
            $stmtInsert->bind_param('siss', $replyContent, $memberId, $processedName, $processedName);
            if (!$stmtInsert->execute()) {
                throw new Exception("新增通知（會員 {$memberId}）失敗：" . $stmtInsert->error);
            }
        }

        $db->commit();

        echo json_encode([
            "success" => true,
            "message" => "聯絡表單與通知已同步更新"
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }

    $stmtUpdate->close();
    $stmtInsert->close();
    $db->close();
    exit();
}

http_response_code(403);
echo json_encode(["error" => "拒絕存取。"], JSON_UNESCAPED_UNICODE);
?>