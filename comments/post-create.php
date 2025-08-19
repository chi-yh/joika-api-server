<?php
// ===========偵錯用==========
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ============================
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Origin: *");
// 處理預檢請求（OPTIONS）
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}
require_once __DIR__ . "/../config/db.php";
try {
    $db = db();

    // 僅允許 POST
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 接收 JSON
    $input = json_decode(file_get_contents("php://input"), true);

    $postNo   = $input["post_no"] ?? null;
    $memberId = $input["member_id"] ?? null;
    $content  = $input["comment_content"] ?? "";
    $parentNo = $input["parent_no"] ?? null;

    // 基本驗證
    if (!$postNo || !$memberId || trim($content) === "") {
        http_response_code(400);
        echo json_encode(["error" => "缺少必要參數"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 寫入資料庫
    $stmt = $db->prepare("
         INSERT INTO post_comment 
            (POST_NO, MEMBER_ID, COMMENT_CONTENT, CREATED_AT, PARENT_NO, LIKE_COUNT)
        VALUES (?, ?, ?, NOW(), ?, 0)
    ");
    $stmt->bind_param("iisi", $postNo, $memberId, $content, $parentNo);
    $success = $stmt->execute();

    if ($success) {
        echo json_encode([
            "success"    => true,
            "comment_id" => $stmt->insert_id,
            "created_at" => date("Y-m-d H:i:s"),
             "like_count" => 0

        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error"   => "資料庫操作失敗"
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error"   => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
