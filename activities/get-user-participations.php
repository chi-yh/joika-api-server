<?php
// === API: 整合版本 ===

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/../config/db.php';
$db = db();

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["error" => "不支援的請求方法"]);
    exit;
}

// --- 透過 "action" 參數來決定要做哪件事 ---
$action = trim($_GET['action'] ?? 'get_participations'); // 預設行為是獲取已參加列表
$memberId = isset($_GET['memberId']) ? (int)$_GET['memberId'] : 0;

if ($memberId <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "缺少會員 ID"]);
    exit;
}


// === 流程一：檢查單一活動是否已收藏 ===
if ($action === 'check_favorite') {
    $activityNo = trim($_GET['activityNo'] ?? '');
    if (empty($activityNo)) {
        http_response_code(400);
        echo json_encode(["error" => "缺少活動編號"]);
        exit;
    }

    // ⚠️ 請將 favorite_activities, activity_no, member_id 替換為您的真實名稱
    $sql = "SELECT COUNT(*) as count FROM favorite_activities WHERE activity_no = ? AND member_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("si", $activityNo, $memberId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    echo json_encode(["isFavorite" => (int)$row['count'] > 0]);

// === 流程二：獲取所有已參加的活動列表 (預設行為) ===
} else {
    $sql = "SELECT ACTIVITY_NO FROM PARTICIPANT WHERE PARTICIPANT_ID = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $activityNos = [];
    while ($row = $result->fetch_assoc()) {
        $activityNos[] = $row['ACTIVITY_NO'];
    }
    
    echo json_encode($activityNos);
}

$stmt->close();
$db->close();
?>