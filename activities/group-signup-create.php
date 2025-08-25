<?php
// === group-signup-create.php (最終穩健版) ===
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- 固定標頭 ---
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

require_once __DIR__ . '/../config/db.php';
$db = db();
$db->set_charset("utf8mb4");

// --- 方法檢查 ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "不支援的請求方法"]);
    exit;
}

// --- 資料檢查 ---
$input = json_decode(file_get_contents("php://input"), true);
if ($input === null) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "無效的請求資料格式"]);
    exit;
}
$activityNo = trim($input['activityNo'] ?? '');
$memberId = isset($input['memberId']) ? (int)$input['memberId'] : 0;
if (empty($activityNo) || $memberId <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "缺少必要的報名資訊"]);
    exit;
}

// === 使用 try...catch 區塊來執行資料庫操作 ===
try {
    $activityNoEscaped = $db->real_escape_string($activityNo);
    $sql = "INSERT INTO PARTICIPANT (ACTIVITY_NO, PARTICIPANT_ID, CREATED_AT) VALUES ('{$activityNoEscaped}', {$memberId}, NOW())";
    
    // 嘗試執行查詢
    $db->query($sql);

    // 如果上面一行沒有拋出異常，就代表成功了
    echo json_encode(["success" => true, "message" => "報名成功"]);

} catch (mysqli_sql_exception $e) {
    // === 關鍵修正：在這裡捕捉資料庫異常 ===
    
    // 檢查異常的錯誤碼是否為 1062 (重複鍵)
    if ($e->getCode() === 1062) {
        // 如果是，回傳我們預期的、對使用者友善的 JSON 錯誤訊息
        http_response_code(409); // 409 Conflict 是一個更適合的狀態碼
        echo json_encode([
            "success" => false,
            "error" => "您已經報名過此活動，請勿重複報名"
        ]);
    } else {
        // 如果是其他資料庫錯誤
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "資料庫操作失敗: " . $e->getMessage()
        ]);
    }
} finally {
    // 無論成功或失敗，最後都關閉連線
    if ($db) {
        $db->close();
    }
}
?>