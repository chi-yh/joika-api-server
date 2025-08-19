<?php
    # 活動留言檢舉
    # GET
    require_once __DIR__ . '/../../config/db.php';
    if ($_SERVER["REQUEST_METHOD"] == "GET"){

    $db = db();

    $sql = "SELECT 
                acr.*,
                rr.REASON,
                m.MEMBER_NAME AS NAME
            FROM 
                activity_comment_report acr
            LEFT JOIN 
                report_reason rr 
                ON acr.REPORT_REASON_NO = rr.REASON_NO
            LEFT JOIN 
                member m 
                ON acr.REPORTER_ID = m.MEMBER_ID;";
    $result = $db->query($sql);

    $data = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);

    $db->close();
    exit();
    }
    
    http_response_code(403);
    $reply_data = new stdClass(); 
    $reply_data->error = "拒絕存取。";
    echo json_encode($reply_data);
?>