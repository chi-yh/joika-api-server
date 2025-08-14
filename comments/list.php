<?php
// 開啟錯誤顯示
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (!isset($_GET['activity_no'])) {
            echo json_encode(["error" => "缺少活動編號"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $activityNo = intval($_GET['activity_no']);

        // 使用 mysqli prepare 防 SQL injection
        $stmt = $mysqli->prepare("
            SELECT * FROM activity_comment
            WHERE ACTIVITY_NO = ? AND COMMENT_STATUS = '顯示'
            ORDER BY CREATED_AT DESC
        ");

        $stmt->bind_param("i", $activityNo); // i = 整數
        $stmt->execute();
        $result = $stmt->get_result();

        $comments = [];
        while ($row = $result->fetch_assoc()) {
            $comments[] = $row;
        }

        echo json_encode($comments, JSON_UNESCAPED_UNICODE);

        $stmt->close();
        break;

    default:
        echo json_encode(["error" => "不支援的請求方式"], JSON_UNESCAPED_UNICODE);
        break;
}
?>
