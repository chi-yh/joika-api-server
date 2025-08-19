<?php
// === 固定寫法：初始化 ===
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
$db = db();
// === 結束：初始化 ===


// === 固定寫法：方法檢查 ===
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
    exit;
}
// === 結束：方法檢查 ===


// === 保留：獲取並驗證 POST 過來的 JSON 資料 (這是您原有的重要邏輯) ===
$input = json_decode(file_get_contents("php://input"), true);
$memberId = $input["memberId"] ?? null;
$activityNo = $input["activityNo"] ?? null;

if ($memberId === null || $activityNo === null) {
    http_response_code(400);
    echo json_encode(["error" => "缺少 memberId 或 activityNo 參數"], JSON_UNESCAPED_UNICODE);
    exit;
}
// === 結束：獲取並驗證 POST 過來的 JSON 資料 ===


// === 開始：核心邏輯 - 新增或刪除 (結合安全與團隊寫法) ===
try {
    // [保留] 步驟 1: 檢查收藏是否存在 (這是您原有的核心邏輯)
    // [堅持] 為了絕對的安全性，必須使用預備語句
    $sqlCheck = "SELECT MEMBER_ID FROM favorite_activities WHERE MEMBER_ID = ? AND ACTIVITY_NO = ?";
    $stmtCheck = $db->prepare($sqlCheck);
    $stmtCheck->bind_param("ii", $memberId, $activityNo);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    // [保留] 步驟 2: 根據是否存在，決定 SQL 操作 (這是您原有的核心邏輯)
    if ($resultCheck->num_rows > 0) {
        // 已收藏，準備執行刪除
        $sql = "DELETE FROM favorite_activities WHERE MEMBER_ID = ? AND ACTIVITY_NO = ?";
    } else {
        // 未收藏，準備執行新增
        $sql = "INSERT INTO favorite_activities (MEMBER_ID, ACTIVITY_NO) VALUES (?, ?)";
    }

    // 步驟 3: 執行最終的 SQL 操作
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ii", $memberId, $activityNo);
    $executionResult = $stmt->execute(); // execute() 會回傳 true 或 false

    // [調整] 統一的回傳格式，與團隊規範完全一致
    if ($executionResult) {
        echo json_encode(["success" => true], JSON_UNESCAPED_UNICODE);
    } else {
        // 如果 execute() 返回 false，但沒有觸發 Exception，手動拋出錯誤
        throw new Exception("資料庫執行失敗");
    }

} catch (Exception $e) {
    // [調整] 統一的失敗回傳格式，與團隊規範完全一致
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "資料庫操作失敗"
    ], JSON_UNESCAPED_UNICODE);
    // 建議在伺服器日誌中記錄詳細錯誤，方便追蹤問題
    error_log('toggle.php DB Error: ' . $e->getMessage());
}
// === 結束：核心邏輯 - 新增或刪除 ===

?>