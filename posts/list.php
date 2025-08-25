<?php
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/cors.php';
// 檢查請求方法是否為 GET
if ($_SERVER["REQUEST_METHOD"] !== "GET") { //記得要改api類型
http_response_code(405);
echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
exit;
}
// 初始化資料庫連線變數
$mysqli = null;

try {
    // 引入資料庫連線設定
    require_once __DIR__ . '/../config/db.php';

    // 取得連線（可能丟出例外）
    $mysqli = db();

    $sql = "
        SELECT *
        FROM `post` p
        WHERE p.`POST_STATUS` = '顯示'
        AND NOT EXISTS (
            SELECT 1
            FROM `post_report` r
            WHERE r.`POST_NO` = p.`POST_NO`
            AND r.`REPORT_STATUS` = '通過'
        )
        ORDER BY p.`CREATED_AT` DESC
    ";



    // 執行查詢
    $result = $mysqli->query($sql);

    // 取得所有結果並存成關聯性陣列
    $data = $result->fetch_all(MYSQLI_ASSOC);

    // 將結果編碼成 JSON 格式並輸出
    echo json_encode($data, JSON_UNESCAPED_UNICODE);

} catch (mysqli_sql_exception $e) {
    // 資料庫相關錯誤
    http_response_code(500); // 設定 HTTP 狀態碼為 500 (伺服器內部錯誤)
    echo json_encode([
        'error' => true,
        'type' => 'mysqli_sql_exception',
        'message' => '資料庫查詢錯誤: ' . $e->getMessage(),
        'code' => $e->getCode()
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    // 其他所有非預期的錯誤
    http_response_code(500); // 設定 HTTP 狀態碼為 500 (伺服器內部錯誤)
    echo json_encode([
        'error' => true,
        'type' => 'exception',
        'message' => '發生未預期的錯誤: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

} finally {
    // 無論成功或失敗，最後都嘗試安全地關閉資料庫連線
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }
}