<?php
    header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . '/../config/db.php';

    $db = db();

    if ($_SERVER["REQUEST_METHOD"] !== "POST") { 
        http_response_code(405);
        echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
        exit;
        }

        $input = json_decode(file_get_contents("php://input"), true);

        $reporterId = (int)($input['reporter_id'] ?? 0);
        $postNo = (int)($input['post_no'] ?? 0);
        $reasonNo = (int)($input['report_reason_no'] ?? 0);
        $description = trim($input['report_description'] ?? '');

        if ($reporterId <= 0 || $postNo <= 0 || $reasonNo <= 0) {
            http_response_code(400);
            echo json_encode(["error" => "缺少必要欄位：reporter_id, post_no, report_reason_no"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        
        $descriptionEscaped = $db->real_escape_string($description);

        $sql = "INSERT INTO post_report (
            REPORTER_ID,
            POST_NO,
            REPORT_REASON_NO,
            REPORT_DESCRIPTION,
            REPORT_STATUS,
            CREATED_AT
        ) VALUES (
            {$reporterId},
            {$postNo},
            {$reasonNo},
            '{$descriptionEscaped}',
            0,
            NOW()
        )";

        $result = $db->query($sql);

        if ($result) {
            echo json_encode(["success" => true], JSON_UNESCAPED_UNICODE);
            } else {
            http_response_code(500);
            echo json_encode([
            "success" => false,
            "error" => "資料庫操作失敗"
            ], JSON_UNESCAPED_UNICODE);
        }
?>