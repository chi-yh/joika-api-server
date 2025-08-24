<?php

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$type = isset($input['type']) ? $input['type'] : '';
$commentNo = isset($input['comment_no']) ? intval($input['comment_no']) : 0;

if (!in_array($type, ['post', 'activity']) || !$commentNo) {
    http_response_code(400);
    echo json_encode(["error" => "缺少或無效的參數"]);
    exit;
}

try {
    $mysqli = db();

    if ($type === 'post') {
        // 查詢 post_report 是否有最新的檢舉狀態
        $stmt = $mysqli->prepare("SELECT REPORT_STATUS FROM post_report WHERE POST_COMMENT_NO = ? ORDER BY HANDLE_AT DESC LIMIT 1");
        $stmt->bind_param("i", $commentNo);
        $stmt->execute();
        $stmt->bind_result($report_status);
        $hasRow = $stmt->fetch();
        $stmt->close();

        $reportStatus = $hasRow ? $report_status : null;

        // 預設回傳
        $response = [
            "report_status" => $reportStatus,
            "success" => false,
            "message" => ""
        ];

        if ($reportStatus === '通過') {
            // 隱藏文章留言
            $stmt = $mysqli->prepare("UPDATE post_comment SET COMMENT_STATUS = '隱藏' WHERE POST_COMMENT_NO = ?");
            $stmt->bind_param("i", $commentNo);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $response["success"] = true;
                $response["message"] = "留言已隱藏";
            } else {
                $response["message"] = "找不到留言或已隱藏";
            }
            $stmt->close();
        } else {
            $response["message"] = "檢舉未通過，無法隱藏留言";
        }
    } else {
        // 查詢 activity_comment_report 是否有最新的檢舉狀態
        $stmt = $mysqli->prepare("SELECT REPORT_STATUS FROM activity_comment_report WHERE ACTIVITY_COMMENT_NO = ? ORDER BY HANDLE_AT DESC LIMIT 1");
        $stmt->bind_param("i", $commentNo);
        $stmt->execute();
        $stmt->bind_result($report_status);
        $hasRow = $stmt->fetch();
        $stmt->close();

        $reportStatus = $hasRow ? $report_status : null;

        $response = [
            "report_status" => $reportStatus,
            "success" => false,
            "message" => ""
        ];

        if ($reportStatus === '通過') {
            // 隱藏活動留言
            $stmt = $mysqli->prepare("UPDATE activity_comment SET COMMENT_STATUS = '隱藏' WHERE ACTIVITY_COMMENT_NO = ?");
            $stmt->bind_param("i", $commentNo);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $response["success"] = true;
                $response["message"] = "留言已隱藏";
            } else {
                $response["message"] = "找不到留言或已隱藏";
            }
            $stmt->close();
        } else {
            $response["message"] = "檢舉未通過，無法隱藏留言";
        }
    }

    $mysqli->close();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>