
<?php
 header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . '/../config/db.php';

    $db = db();

    if ($_SERVER["REQUEST_METHOD"] !== "POST") { 
        http_response_code(405);
        echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
        exit;
        }

 $input = json_decode(file_get_contents("php://input"), true);
if (!isset($input['POST_COMMENT_NO']) || !is_numeric($input['POST_COMMENT_NO'])) {
    http_response_code(400);
    echo json_encode(["error" => "缺少或無效的留言編號"]);
    exit;
}

$commentNo = intval($input['POST_COMMENT_NO']);

try {
    $mysqli = db();

    // 先查詢 post_report 是否有通過的檢舉
    $stmt = $mysqli->prepare("SELECT REPORT_STATUS FROM post_report WHERE POST_COMMENT_NO = ? ORDER BY HANDLE_AT DESC LIMIT 1");
    $stmt->bind_param("i", $commentNo);
    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result->fetch_assoc();
    $stmt->close();

    if (!$report || $report['REPORT_STATUS'] !== '通過') {
        echo json_encode(["success" => false, "message" => "檢舉未通過，無法隱藏留言"]);
        $mysqli->close();
        exit;
    }

    // 檢舉通過才隱藏留言
    $stmt = $mysqli->prepare("UPDATE post_comment SET COMMENT_STATUS = '隱藏' WHERE POST_COMMENT_NO = ?");
    $stmt->bind_param("i", $commentNo);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(["success" => true, "message" => "留言已隱藏"]);
    } else {
        echo json_encode(["success" => false, "message" => "找不到留言或已隱藏"]);
    }

    $stmt->close();
    $mysqli->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}




?>