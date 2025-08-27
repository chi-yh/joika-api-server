<?php
// === API: 整合版本 (兼容無 mysqlnd + 識別主揪) ===

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/../config/db.php';
$db = db();

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["error" => "不支援的請求方法"]);
    exit;
}

$action = trim($_GET['action'] ?? 'get_participations');
$memberId = isset($_GET['memberId']) ? (int)$_GET['memberId'] : 0;

if ($memberId <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "缺少會員 ID"]);
    exit;
}

// === 流程一：檢查收藏 (使用 bind_result) ===
if ($action === 'check_favorite') {
    $activityNo = trim($_GET['activityNo'] ?? '');
    if (empty($activityNo)) {
        http_response_code(400);
        echo json_encode(["error" => "缺少活動編號"]);
        exit;
    }

    $sql = "SELECT COUNT(*) FROM favorite_activities WHERE activity_no = ? AND member_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("si", $activityNo, $memberId);
    $stmt->execute();
    
    $stmt->bind_result($count);
    $stmt->fetch();
    
    echo json_encode(["isFavorite" => (int)$count > 0]);
    $stmt->close();

// === 流程二：獲取所有相關活動 (已升級 + 使用 bind_result) ===
} else {
    $activityNos = [];

    // --- 查詢一：找出使用者「參加」的活動 ---
    $sql_joined = "SELECT ACTIVITY_NO FROM PARTICIPANT WHERE PARTICIPANT_ID = ?";
    $stmt_joined = $db->prepare($sql_joined);
    $stmt_joined->bind_param("i", $memberId);
    $stmt_joined->execute();
    
    $stmt_joined->store_result();
    $stmt_joined->bind_result($activity_no_joined);

    while ($stmt_joined->fetch()) {
        $activityNos[] = $activity_no_joined;
    }
    $stmt_joined->close();

    // --- 查詢二：找出使用者「主辦」的活動 ---
    $sql_hosted = "SELECT ACTIVITY_NO FROM ACTIVITY WHERE HOST_MEMBER_ID = ?";
    $stmt_hosted = $db->prepare($sql_hosted);
    $stmt_hosted->bind_param("i", $memberId);
    $stmt_hosted->execute();

    $stmt_hosted->store_result();
    $stmt_hosted->bind_result($activity_no_hosted);

    while ($stmt_hosted->fetch()) {
        $activityNos[] = $activity_no_hosted;
    }
    $stmt_hosted->close();

    // --- 將合併後的陣列，移除重複項並回傳 ---
    echo json_encode(array_values(array_unique($activityNos)));
}

$db->close();
?>