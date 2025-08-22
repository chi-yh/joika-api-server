<?php
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $db = db();

    // 從 GET 拿 MEMBER_ID
    $memberId = isset($_GET['MEMBER_ID']) ? intval($_GET['MEMBER_ID']) : 0;
    if ($memberId <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "請提供有效 MEMBER_ID"]);
        exit();
    }

    // 查詢對應的會員資料
    $stmt = $db->prepare("SELECT * FROM member WHERE MEMBER_ID = ?");
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "找不到 MEMBER_ID"]);
    }

    $stmt->close();
    $db->close();
    exit();
}

http_response_code(403);
echo json_encode(["error" => "拒絕存取。"], JSON_UNESCAPED_UNICODE);
?>