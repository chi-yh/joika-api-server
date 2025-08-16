<?php
    # 最新揪團
    // 看CREATED_AT

    require_once __DIR__ . '/../config/db.php';
    if ($_SERVER["REQUEST_METHOD"] == "GET"){

    $db = db();

    // $num = isset($_GET['num']) ? (int)$_GET['num'] : 2;
    // if ($num <= 0) $num = 2;

    $sql = "SELECT * FROM `activity`
            WHERE `ACTIVITY_STATUS` <> '已取消'
            AND `ACTIVITY_STATUS` <> '已結束'
            ORDER BY `CREATED_AT` DESC";
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