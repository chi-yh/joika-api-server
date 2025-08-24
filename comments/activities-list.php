<?php
// --- 基礎設定 ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

// --- 驗證請求 ---
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "僅支援 GET 請求方式"], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_GET['activity_no']) || !is_numeric($_GET['activity_no'])) {
    http_response_code(400);
    echo json_encode(["error" => "缺少有效的文章編號"], JSON_UNESCAPED_UNICODE);
    exit;
}

// --- 主要邏輯 ---

// 1. 直接接收並轉換為整數
$activityno = intval($_GET['activity_no']);
$comments = [];

if ($activityno > 0) {
    $mysqli = db();


   $stmt = $mysqli->prepare("
    SELECT 
        pac.ACTIVITY_COMMENT_NO,
        pac.MEMBER_ID,
        m.MEMBER_NICKNAME,
        pac.ACTIVITY_NO,
        pac.COMMENT_CONTENT,
        pac.PARENT_NO,
        pac.CREATED_AT,
        pac.COMMENT_STATUS
    FROM activity_comment pac
    JOIN member m ON pac.MEMBER_ID = m.MEMBER_ID
    WHERE pac.ACTIVITY_NO = ?
      AND pac.COMMENT_STATUS = '顯示'
    ORDER BY pac.CREATED_AT DESC
");
    
    // 4. 直接綁定整數 ID
    $stmt->bind_param("i", $activityno);
    $stmt->execute();
    
    $stmt->execute();
$stmt->store_result();
$stmt->bind_result($comment_no, $member_id, $member_nickname, $activity_no, $comment_content, $parent_no, $created_at, $comment_status);
while ($stmt->fetch()) {
    $comments[] = [
        'ACTIVITY_COMMENT_NO' => $comment_no,
        'MEMBER_ID' => $member_id,
        'MEMBER_NICKNAME' => $member_nickname,
        'ACTIVITY_NO' => $activity_no,
        'COMMENT_CONTENT' => $comment_content,
        'PARENT_NO' => $parent_no,
        'CREATED_AT' => $created_at,
        'COMMENT_STATUS' => $comment_status
    ];
}
$stmt->close();
$mysqli->close();
}

// 6. 回傳最終結果
echo json_encode($comments, JSON_UNESCAPED_UNICODE);

?>