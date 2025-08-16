<?php
    require_once __DIR__ . '/../config/db.php';

    if($_SERVER["REQUEST_METHOD"] == "POST"){

        $mysqli = db();

        $sql = "SELECT * FROM member WHERE member_phone = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("s", $_POST["member_phone"]);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = $result->fetch_all(MYSQLI_ASSOC);

        if(count($users) == 0){//找不到會員
            $reply_data = [
                "code" => "0001",
                "msg" => "電話不存在"
            ];
        }else{

        }

        echo json_encode($reply_data);
        exit();
    }
?>