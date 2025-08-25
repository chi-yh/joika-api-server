<?php
// === 固定寫法：初始化 ===
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
$db = db();
// === 結束：初始化 ===


// === 固定寫法：方法檢查 ===
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    // [調整] 錯誤訊息與團隊規範統一
    echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
    exit;
}
// === 結束：方法檢查 ===


// === 保留：獲取並驗證 URL 傳來的參數 (這是您原有的重要邏輯) ===
$memberId = $_GET["memberId"] ?? null;
if ($memberId === null) {
    http_response_code(400);
    // [調整] 錯誤訊息可以更具體，但格式與團隊規範的精神保持一致
    echo json_encode(["error" => "缺少 memberId 參數"], JSON_UNESCAPED_UNICODE);
    exit;
}
// === 結束：獲取並驗證 URL 傳來的參數 ===


// === 開始：執行 SQL 並處理結果 (結合安全與團隊寫法) ===
try {
    // [堅持] 為了絕對的安全性，處理使用者輸入時必須使用預備語句
    // $sql = "SELECT * FROM favorite_activities WHERE member_id = ?";
    // 修改成 (請換成您的真實欄位)
    $sql = "SELECT MEMBER_ID, ACTIVITY_NO FROM favorite_activities WHERE member_id = ?";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    // $result = $stmt->get_result();
    // $data = $result->fetch_all(MYSQLI_ASSOC);

    // 在 $stmt->execute(); 的下一行，加上這些新程式碼

    // 1. 先把所有查詢結果從資料庫抓到 PHP 的記憶體裡
    $stmt->store_result();

    // 2. 準備好「空的變數」，用來接收每一筆資料的欄位值
    //    變數的順序和數量，必須和步驟一的 SELECT 欄位完全對應！
    //    FAVORITE_NO -> $fav_no
    //    MEMBER_ID   -> $mem_id
    //    ACTIVITY_NO -> $act_no
    $stmt->bind_result($mem_id, $act_no);

    // 3. 準備一個空的陣列，就像一個空箱子，準備裝整理好的資料
    $data = [];

    // 4. 啟動一個迴圈，就像工廠的生產線
    //    $stmt->fetch() 會一筆一筆地把資料從記憶體拿出來
    while ($stmt->fetch()) {
        // 5. 在迴圈裡，手動把每一筆資料，組裝成我們想要的格式
        //    再放進 $data 這個箱子裡
        $data[] = [
            'MEMBER_ID' => $mem_id,
            'ACTIVITY_NO' => $act_no
        ];
    }

    // [調整] 成功時的回傳格式，與團隊規範統一
    // 由於此 API 必須回傳資料，我們在規範的基礎上增加 "data" 欄位
    echo json_encode([
        "success" => true,
        "data" => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // [調整] 失敗時的回傳格式，與團隊規範統一
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "資料庫操作失敗"
    ], JSON_UNESCAPED_UNICODE);
    // 建議在伺服器日誌中記錄詳細錯誤，方便追蹤問題
    error_log('list.php DB Error: ' . $e->getMessage());
}
// === 結束：執行 SQL 並處理結果 ===

?>