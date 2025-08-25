<?php
// === 固定寫法：初始化 ===
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
$db = db();
// === 結束：初始化 ===

// === 固定寫法：方法檢查 ===
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
    exit;
}
// === 結束：方法檢查 ===

// === 保留：取得並驗證參數 ===
$memberId = $_GET["memberId"] ?? null;
if ($memberId === null) {
    http_response_code(400);
    echo json_encode(["error" => "缺少 memberId 參數"], JSON_UNESCAPED_UNICODE);
    exit;
}
$memberId = (int)$memberId; // 確保為整數
// === 結束：取得並驗證參數 ===

try {
<<<<<<< HEAD
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
=======
    // 使用預備語句
    $sql = "SELECT * FROM favorite_activities WHERE member_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
>>>>>>> dev

    // ====== 無 mysqlnd 通用取數法：動態綁定所有欄位 ======
    $meta = $stmt->result_metadata();
    $row  = [];
    $binds = [];

    // 依欄位數建立參考綁定
    while ($field = $meta->fetch_field()) {
        $row[$field->name] = null;
        $binds[] = &$row[$field->name];
    }
    // 將結果欄位綁到 $row 的各鍵
    call_user_func_array([$stmt, 'bind_result'], $binds);

    $data = [];
    // fetch 每一列，記得「複製一份」避免引用被覆蓋
    while ($stmt->fetch()) {
        // 把引用陣列轉為實值陣列
        $data[] = array_map(fn($v) => $v, $row);
    }
    $stmt->close();
    // ====== end 無 mysqlnd 通用取數法 ======

    echo json_encode([
        "success" => true,
        "data" => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "資料庫操作失敗"
    ], JSON_UNESCAPED_UNICODE);
    error_log('favorite_activities list DB Error: ' . $e->getMessage());
}
?>