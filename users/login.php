<?php
    require_once __DIR__ . '/../config/cors.php';
    require_once __DIR__ . '/../config/db.php';

    session_set_cookie_params([
        'lifetime' => 60*60*24*7, 'path' => '/', 'secure' => false,
        'httponly' => true, 'samesite' => 'Lax'
    ]);
    session_start();

    if($_SERVER["REQUEST_METHOD"] == "POST"){

        $mysqli = db();

        $sql = "SELECT * FROM member WHERE member_phone = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("s", $_POST["member_phone"]);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = $result->fetch_all(MYSQLI_ASSOC);

        if(!$users){//找不到會員
            $reply_data = [
                "code" => "0001",
                "msg" => "電話不存在"
            ];
        }else{
            
            $my_password_verify_result = ($_POST["password"] === $users[0]['MEMBER_PASSWORD']);
            if(!$my_password_verify_result){
                $reply_data = [
                    "code" => "0002",
                    "msg" => "密碼錯誤"
                ];
            }else{
                $_SESSION['user'] = [
                    "id" => $users[0]["MEMBER_ID"],
                    "nickname" => $users[0]["MEMBER_NICKNAME"],
                    "avatar" => $users[0]["MEMBER_AVATAR"]
                ];
                $reply_data = [
                    "code" => "0003",
                    "msg" => "登入成功",
                ];
            }
        }

        echo json_encode($reply_data);
        exit();
    }
?>