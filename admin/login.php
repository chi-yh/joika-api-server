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

        $sql = "SELECT STAFF_NAME FROM staff WHERE staff_username = ? AND staff_password = ?";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ss", $_POST["username"], $_POST["password"]);
        $stmt->execute();

        // 用 store_result() + bind_result()
        $stmt->store_result();
        $stmt->bind_result($staff_name);

        if ($stmt->fetch()) {
            // 登入成功 → 回傳 STAFF_NAME
            echo json_encode([
                "success" => true,
                "staff_name" => $staff_name
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "staff_name" => null
            ]);
        }

        $stmt->close();
        $mysqli->close();
        exit();
    }
?>