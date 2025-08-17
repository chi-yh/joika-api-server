<?php
    # 後台登入
    # POST
    require_once __DIR__ . '/../config/db.php';

    if($_SERVER["REQUEST_METHOD"] == "POST"){

        $mysqli = db();

        if (!isset($_POST["username"]) || !isset($_POST["password"])) {
          echo json_encode(false);
          exit();
        }

        $sql = "SELECT * FROM staff WHERE staff_username = ? AND staff_password = ?";
        
        $stmt = $mysqli->prepare($sql);
        // 兩個s，表示兩個都是字串string
        $stmt->bind_param("ss", $_POST["username"], $_POST["password"]);
        $stmt->execute();
        $result = $stmt->get_result();
        $isOK = $result->fetch_all(MYSQLI_ASSOC);

        if ($isOK) {
          // 登入成功 → 回傳 STAFF_NAME
          echo json_encode([
            "success" => true,
            "staff_name" => $isOK[0]["STAFF_NAME"]
          ]);
        } else {
          echo json_encode([
            "success" => false,
            "staff_name" => null
          ]);
        }
        
        exit();
    }
?>