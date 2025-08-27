<?php
// === group-signup-create.php (最終完整版，支援重新報名) ===

// 啟用 mysqli 的錯誤報告模式，讓它拋出異常
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- CORS 標頭 ---
require_once __DIR__ . '/../config/cors.php';
// require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

// header('Content-Type: application/json; charset=utf-8');
// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
// header("Access-Control-Allow-Methods: POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  http_response_code(200);
  exit;
}


require_once __DIR__ . '/../config/db.php';
$db = db();
$db->set_charset("utf8mb4");

// --- 方法檢查 ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "不支援的請求方法"]);
    exit;
}

// --- 資料檢查 ---
$input = json_decode(file_get_contents("php://input"), true);
if ($input === null) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "無效的請求資料格式"]);
    exit;
}
$activityNo = trim($input['activityNo'] ?? '');
$memberId = isset($input['memberId']) ? (int)$input['memberId'] : 0;
if (empty($activityNo) || $memberId <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "缺少必要的報名資訊"]);
    exit;
}

// === 使用 try...catch 區塊來執行資料庫操作 ===
try {
    // 第一次嘗試：直接新增紀錄
    $sql_insert = "INSERT INTO participant (ACTIVITY_NO, PARTICIPANT_ID, JOINER_STATUS, CREATED_AT) VALUES (?, ?, '審核中', NOW())";
    $stmt_insert = $db->prepare($sql_insert);
    $stmt_insert->bind_param("si", $activityNo, $memberId);
    $stmt_insert->execute();
    $stmt_insert->close();

    echo json_encode(["success" => true, "message" => "報名成功，待主揪審核"]);

} catch (mysqli_sql_exception $e) {
    // 檢查異常的錯誤碼是否為 1062 (重複鍵)
    if ($e->getCode() === 1062) {
        try {
            // 第二次嘗試：更新「已取消」的紀錄
            $sql_update = "UPDATE participant SET JOINER_STATUS = '審核中', CREATED_AT = NOW() WHERE ACTIVITY_NO = ? AND PARTICIPANT_ID = ? AND JOINER_STATUS = '已取消'";
            $stmt_update = $db->prepare($sql_update);
            $stmt_update->bind_param("si", $activityNo, $memberId);
            $stmt_update->execute();

            if ($stmt_update->affected_rows > 0) {
                echo json_encode(["success" => true, "message" => "重新報名成功，待主揪審核"]);
            } else {
                http_response_code(409);
                echo json_encode(["success" => false, "error" => "您已經報名過此活動，請勿重複報名"]);
            }
            $stmt_update->close();

        } catch (mysqli_sql_exception $update_e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "資料庫操作失敗: " . $update_e->getMessage()]);
        }
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "資料庫操作失敗: " . $e->getMessage()]);
    }
} finally {
    if ($db) {
        $db->close();
    }
}
?>