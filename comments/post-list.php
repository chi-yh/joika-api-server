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

if (!isset($_GET['post_no']) || !is_numeric($_GET['post_no'])) {
    http_response_code(400);
    echo json_encode(["error" => "缺少有效的文章編號"], JSON_UNESCAPED_UNICODE);
    exit;
}

// --- 主要邏輯 ---

// 1. 直接接收並轉換為整數
$postNo = intval($_GET['post_no']);
$comments = [];

// 2. 只有在 postNo > 0 的情況下才查詢資料庫，避免無效查詢
if ($postNo > 0) {
    $mysqli = db();

    // 3. 直接執行您原本的第二階段查詢 (現在是唯一且正確的查詢)
    $stmt = $mysqli->prepare("
        SELECT 
            pc.POST_COMMENT_NO, 
            pc.MEMBER_ID, 
            m.MEMBER_NICKNAME,
            pc.POST_NO, 
            pc.COMMENT_CONTENT, 
            pc.CREATED_AT, 
            pc.PARENT_NO, 
            pc.COMMENT_STATUS,
            pc.LIKE_COUNT
        FROM post_comment pc
        JOIN member m ON pc.MEMBER_ID = m.MEMBER_ID
        WHERE pc.POST_NO = ? 
          AND pc.COMMENT_STATUS = '顯示'
        ORDER BY pc.CREATED_AT DESC
    ");
    
    // 4. 直接綁定整數 ID
    $stmt->bind_param("i", $postNo);
    $stmt->execute();
    
    $result = $stmt->get_result();
    
    // 5. 使用 fetch_all() 讓程式碼更簡潔
    $comments = $result->fetch_all(MYSQLI_ASSOC);
    
    $stmt->close();
    $mysqli->close();
}

// 6. 回傳最終結果
echo json_encode($comments, JSON_UNESCAPED_UNICODE);

?>