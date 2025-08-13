<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../config/db.php';

    // 你資料表叫 activity 還是 activities？請確認
    $sql = 'SELECT * FROM activity';
    $result = $mysqli->query($sql);

    $data = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode($data, JSON_UNESCAPED_UNICODE);

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ], JSON_UNESCAPED_UNICODE);
}
