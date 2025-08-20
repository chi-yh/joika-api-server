<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: http://localhost:5173"); // 記得換成您前端的網址

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
    exit;
}

// 嚴格檢查 ID 是否為純數字
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    http_response_code(400); 
    echo json_encode(["error" => "缺少或無效的文章 ID"], JSON_UNESCAPED_UNICODE);
    exit;
}

$postId = (int)$_GET['id'];
$mysqli = null;

try {
    require_once __DIR__ . '/../config/db.php';
    $mysqli = db();

 // JOIN member 取得暱稱
    $sql = "SELECT p.*, m.MEMBER_NICKNAME
            FROM `post` p
            JOIN `member` m ON p.MEMBER_ID = m.MEMBER_ID
            WHERE p.POST_NO = ? AND p.POST_STATUS = '顯示'";
            
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL 語法準備失敗: " . $mysqli->error);
    }
    
    // POST_NO 是 int, 所以用 "i"
    $stmt->bind_param("i", $postId); 
    
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    if ($data) {
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404);
        echo json_encode(['error' => '找不到指定的文章'], JSON_UNESCAPED_UNICODE);
    }

    $stmt->close();

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => '資料庫查詢錯誤'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => '發生未預期的錯誤'], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }
}