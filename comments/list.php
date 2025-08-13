<?php
header("Content-Type: application/json; charset=UTF-8");
require_once "db.php";

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    // 1️⃣ 取得某活動的留言
    case 'GET':
        if (!isset($_GET['activity_no'])) {
            echo json_encode(["error" => "缺少活動編號"]);
            exit;
        }
        $activityNo = intval($_GET['activity_no']);
        $stmt = $pdo->prepare("SELECT * FROM activity_comment 
                               WHERE ACTIVITY_NO = ? AND COMMENT_STATUS = '顯示'
                               ORDER BY CREATED_AT DESC");
        $stmt->execute([$activityNo]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;?>
