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
    // 使用預備語句
    $sql = "SELECT * FROM favorite_activities WHERE member_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $memberId);
    $stmt->execute();

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