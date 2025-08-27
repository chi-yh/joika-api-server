<?php
// === API: 整合版本 (最終版，兼容無 mysqlnd + 精細狀態檢查) ===

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  http_response_code(200);
  exit;
}

require_once __DIR__ . '/../config/db.php';
$db = db();

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["error" => "不支援的請求方法"]);
    exit;
}

$action = trim($_GET['action'] ?? 'get_participations');
$memberId = isset($_GET['memberId']) ? (int)$_GET['memberId'] : 0;

if ($memberId <= 0 && $action === 'check_prerequisites') {
    // 允許未登入使用者進行公開檢查
} else if ($memberId <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "缺少會員 ID"]);
    exit;
}

// === 流程一：檢查單一活動的「前置狀態」 ===
if ($action === 'check_prerequisites') {
    $activityNo = trim($_GET['activityNo'] ?? '');
    if (empty($activityNo)) {
        http_response_code(400);
        echo json_encode(["error" => "缺少活動編號"]);
        exit;
    }

    // 1. 檢查活動本身的狀態 (ACTIVITY 表)
    $sql_activity = "SELECT ACTIVITY_STATUS FROM ACTIVITY WHERE ACTIVITY_NO = ?";
    $stmt_activity = $db->prepare($sql_activity);
    $stmt_activity->bind_param("s", $activityNo);
    $stmt_activity->execute();
    $stmt_activity->store_result();
    $stmt_activity->bind_result($activity_status);
    
    if ($stmt_activity->fetch()) {
        if ($activity_status === '審核中') {
            echo json_encode(["status" => "activity_pending"]);
            $stmt_activity->close(); $db->close(); exit;
        }
        if ($activity_status === '已取消') {
            echo json_encode(["status" => "activity_cancelled"]);
            $stmt_activity->close(); $db->close(); exit;
        }
    }
    $stmt_activity->close();

    // 如果未登入，則不需要檢查個人狀態
    if ($memberId <= 0) {
        echo json_encode(["status" => "can_proceed"]);
        $db->close(); exit;
    }

    // // 2. 檢查使用者與該活動的關係 (PARTICIPANT 表)
    // $sql_participant = "SELECT JOINER_STATUS FROM PARTICIPANT WHERE PARTICIPANT_ID = ? AND ACTIVITY_NO = ?";
    // $stmt_participant = $db->prepare($sql_participant);
    // $stmt_participant->bind_param("is", $memberId, $activityNo);
    // $stmt_participant->execute();
    // $stmt_participant->store_result();
    // $stmt_participant->bind_result($joiner_status);

    // if ($stmt_participant->fetch()) {
    //     if ($joiner_status === '審核中') {
    //         echo json_encode(["status" => "joiner_pending"]);
    //         $stmt_participant->close(); $db->close(); exit;
    //     }
    // }
    // $stmt_participant->close();

    // 3. 如果以上所有攔截條件都不成立，就回傳「可以繼續」
    echo json_encode(["status" => "can_proceed"]);

// === 流程二：獲取所有相關活動 (預設行為) ===
} else {
    $activityNos = [];

    // --- 查詢一：找出使用者狀態為「已參加」或「審核中」的活動 ---
    $sql_joined = "SELECT ACTIVITY_NO FROM participant WHERE PARTICIPANT_ID = ? AND (JOINER_STATUS = '已參加' OR JOINER_STATUS = '審核中')";
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