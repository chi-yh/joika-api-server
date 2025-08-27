<?php
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/cors.php';

// 只允許 GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => '不支援的請求方法'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mysqli = null;

try {
    require_once __DIR__ . '/../config/db.php';
    $mysqli = db();

    // 只排除「通過且目標為文章本體」的檢舉（POST_COMMENT_NO 為 NULL 或 0）
    $sql = "
        SELECT *
        FROM `post` p
        WHERE p.`POST_STATUS` = '顯示'
            AND NOT EXISTS (
                SELECT 1
                FROM `post_report` r
                WHERE r.`POST_NO` = p.`POST_NO`
                    AND r.`REPORT_STATUS` = '通過'
                    AND (r.`POST_COMMENT_NO` IS NULL OR r.`POST_COMMENT_NO` = 0)
            )
        ORDER BY p.`CREATED_AT` DESC
    ";

    $result = $mysqli->query($sql);
    if ($result === false) {
        // 主動擲出例外，讓下方 catch 處理
        throw new mysqli_sql_exception('查詢失敗：' . $mysqli->error, $mysqli->errno);
    }

    $data = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($data ?: [], JSON_UNESCAPED_UNICODE);

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => true,
        'type'    => 'mysqli_sql_exception',
        'message' => '資料庫查詢錯誤: ' . $e->getMessage(),
        'code'    => $e->getCode(),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => true,
        'type'    => 'exception',
        'message' => '發生未預期的錯誤: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);

} finally {
    if ($mysqli instanceof mysqli) {
        $mysqli->close();
    }
}
