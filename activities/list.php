<?php
# GET

// 顯示錯誤（開發時開啟，上線請關閉或寫到 log）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 一開始就設定 JSON header，且不要有任何多餘輸出
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../config/db.php';

    // 取得連線（可能丟出例外）
    $mysqli = db();

    // 查詢資料（建議用 prepare，這裡示範簡單 query 也可）
    $sql = "SELECT * FROM `activity`
            WHERE `ACTIVITY_STATUS` = '開團中' 
    ";
    $result = $mysqli->query($sql);

    $data = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode($data, JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
    // 資料庫相關錯誤
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'type' => 'mysqli_sql_exception',
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // 其他非資料庫錯誤
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'type' => 'exception',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} finally {
    // 安全關閉連線
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }
}