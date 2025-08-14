
<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

try {
    $db = db(); // 取得連線

    // === 這裡是每個 API 的業務邏輯區 ===
    // e.g. 查詢資料、回傳 JSON
    // ===================================

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok'     => false,
        'error'  => 'db_error',
        'code'   => $e->getCode(),
        'message'=> $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'     => false,
        'error'  => 'server_error',
        'message'=> $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
    if (isset($db)   && $db   instanceof mysqli)      $db->close();
}
/* 以上為api公版，大家寫的時候可以調整中間綠色區塊*/