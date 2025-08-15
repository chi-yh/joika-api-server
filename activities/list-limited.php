<?php
    # 限時揪團
    // 看ACTIVITY_START_DATE，兩週内

    require_once __DIR__ . '/../config/db.php';
    if ($_SERVER["REQUEST_METHOD"] == "GET"){

        $db = db();

        // 篩選今天 ~ 14 天內，且未取消的活動
        $sql = "
            SELECT * 
            FROM `activity` 
            WHERE `ACTIVITY_START_DATE` BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
              AND `ACTIVITY_STATUS` <> '已取消'
              AND `ACTIVITY_STATUS` <> '已結束'
            ORDER BY `ACTIVITY_START_DATE` ASC
        ";
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