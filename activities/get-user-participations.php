<?php
// ===get-user-participations.php API: 整合版本 ===

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
    $sql = "SELECT COUNT(*) FROM favorite_activities WHERE activity_no = ? AND member_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("si", $activityNo, $memberId);
    $stmt->execute();
    
    // 【修改點 1】: 使用 bind_result 和 fetch 來獲取單一結果
    $stmt->bind_result($count); // 準備一個變數 $count 來接收 COUNT(*) 的結果
    $stmt->fetch(); // 執行抓取，將結果填入 $count
    
    echo json_encode(["isFavorite" => (int)$count > 0]);

// === 流程二：獲取所有已參加的活動列表 (預設行為) ===
} else {
    // ⚠️ 請將 PARTICIPANT, ACTIVITY_NO, PARTICIPANT_ID 替換為您的真實名稱
    $sql = "SELECT ACTIVITY_NO FROM PARTICIPANT WHERE PARTICIPANT_ID = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    
    // 【修改點 2】: 使用 bind_result 和 while 迴圈來獲取多筆結果
    $stmt->store_result(); // 先將所有結果儲存到記憶體
    $stmt->bind_result($activity_no); // 準備一個變數 $activity_no 來接收 ACTIVITY_NO 的值

    $activityNos = [];
    while ($stmt->fetch()) { // 迴圈一筆一筆地抓取
        $activityNos[] = $activity_no; // 將抓到的值放入陣列
    }
    
    echo json_encode($activityNos);
}

$stmt->close();
$db->close();
?>