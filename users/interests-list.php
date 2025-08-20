<?php
    require_once __DIR__ . '/../config/db.php';
    session_start();

    $mysqli = db();
    $memberId = (int) $_SESSION['member_id'];

    if ($_SERVER["REQUEST_METHOD"] === "GET") {
        $memberId = $_SESSION["member_id"] ?? null;
        if (!$memberId) {
            echo json_encode(["code" => "0001", "msg" => "缺少 memberId"]);
            exit;
        }

        $sql = "SELECT 
            m.MEMBER_ID,
            GROUP_CONCAT(c.CATEGORY_NAME ORDER BY c.CATEGORY_NO SEPARATOR ',') AS category_names
        FROM MEMBER_INTEREST m
        LEFT JOIN CATEGORY c ON m.INTEREST_NO = c.CATEGORY_NO
        WHERE m.MEMBER_ID = ?
        GROUP BY m.MEMBER_ID;"; 

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $memberId);
        $stmt->execute();
        $result = $stmt->get_result();
        $tags = $result->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            "code" => "0000",
            "msg" => "success",
            "data" => $tags
        ]);
    }
?>