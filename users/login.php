<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

session_set_cookie_params([
    'lifetime' => 60*60*24*7, 'path' => '/', 'secure' => false,
    'httponly' => true, 'samesite' => 'Lax'
]);
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $mysqli = db();

    $sql = "SELECT * FROM member WHERE member_phone = ? AND member_status = '已通用'";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $_POST["member_phone"]);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc(); // 單筆

    if (!$user) { // 找不到會員
        $reply_data = [
            "code" => "0001",
            "msg"  => "此帳號不存在"
        ];
    } else {
        // ※ 正式上線請改用 password_verify
        $password_ok = ($_POST["password"] === $user['MEMBER_PASSWORD']);

        if (!$password_ok) {
            $reply_data = [
                "code" => "0002",
                "msg"  => "密碼錯誤"
            ];
        } else {
            // 登入成功 → 設定 session
            $_SESSION['member_id'] = (int)$user['MEMBER_ID'];
            $_SESSION['user'] = [
                "id"       => (int)$user["MEMBER_ID"],
                "nickname" => $user["MEMBER_NICKNAME"],
                "avatar"   => $user["MEMBER_AVATAR"]
            ];

            $reply_data = [
                "code" => "0003",
                "msg"  => "登入成功"
            ];
        }
    }

    echo json_encode($reply_data);
    exit();
}
?>
