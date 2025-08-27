<?php
require_once __DIR__ . '/../../config/db.php';

// 開啟錯誤輸出方便 debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $db = db();

    // 從 GET 拿 MEMBER_ID
    $memberId = isset($_GET['MEMBER_ID']) ? intval($_GET['MEMBER_ID']) : 0;
    if ($memberId <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "請提供有效 MEMBER_ID"]);
        exit();
    }

    // 直接用 query 查詢，不用 prepare
    $memberId = $db->real_escape_string($memberId); // 防 SQL 注入
    $sql = "SELECT * FROM member WHERE MEMBER_ID = $memberId";
    $result = $db->query($sql);

    if ($result) {
        if ($row = $result->fetch_assoc()) {
            echo json_encode($row, JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "找不到 MEMBER_ID"]);
        }
        $result->free();
    } else {
        http_response_code(500);
        echo json_encode(["error" => "SQL 執行錯誤: " . $db->error]);
    }

    $db->close();
    exit();
}

http_response_code(403);
echo json_encode(["error" => "拒絕存取。"], JSON_UNESCAPED_UNICODE);
?>