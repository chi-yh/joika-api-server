<?php
    header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . '/../config/db.php';

    $db = db();

    if ($_SERVER["REQUEST_METHOD"] !== "POST") { 
        http_response_code(405);
        echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
        exit;
        }

        $input = json_decode(file_get_contents("php://input"), true);
        
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        $name = trim($input['name'] ?? '');

        if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "請提供正確的 ID"], JSON_UNESCAPED_UNICODE);
        exit;
        }


        $result = $db->query($sql);

        if ($result) {
            echo json_encode(["success" => true], JSON_UNESCAPED_UNICODE);
            } else {
            http_response_code(500);
            echo json_encode([
            "success" => false,
            "error" => "資料庫操作失敗"
            ], JSON_UNESCAPED_UNICODE);
        }
?>