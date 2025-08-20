<?php
#GET
    header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . '/../../config/db.php';
    
    $db = db();

    if ($_SERVER["REQUEST_METHOD"] !== "GET") {
        http_response_code(405);
        echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
        exit;
        }

        $status = $_GET['status'] ?? null;

        $sql = "SELECT 
                    pr.POST_REPORT_NO,
                    pr.REPORTER_ID,
                    m.MEMBER_NAME AS reporter_name,
                    pr.POST_NO,
                    p.POST_TITLE,
                    pr.POST_COMMENT_NO,
                    pc.COMMENT_CONTENT,
                    pr.REPORT_REASON_NO,
                    rr.REASON,
                    pr.REPORT_DESCRIPTION,
                    pr.REPORT_STATUS,
                    pr.CREATED_AT,
                    s.STAFF_NAME AS staff_name,
                    CASE 
                        WHEN pr.POST_COMMENT_NO IS NULL THEN '文章'
                        ELSE '留言'
                    END AS REPORT_TYPE
                FROM post_report pr
                LEFT JOIN member m ON pr.REPORTER_ID = m.MEMBER_ID
                LEFT JOIN post p ON pr.POST_NO = p.POST_NO
                LEFT JOIN post_comment pc ON pr.POST_COMMENT_NO = pc.POST_COMMENT_NO
                LEFT JOIN report_reason rr ON pr.REPORT_REASON_NO = rr.REASON_NO
                LEFT JOIN staff s ON pr.STAFF_ID = s.STAFF_ID";
        if ($status) {
            $safeStatus = $db->real_escape_string($status);
            $sql .= " WHERE pr.REPORT_STATUS = '{$safeStatus}'";
        }
        
        $result = $db->query($sql);
        
        if ($result) {
            $data = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "error" => "資料庫查詢失敗：" . $db->error
            ], JSON_UNESCAPED_UNICODE);
        }

?>