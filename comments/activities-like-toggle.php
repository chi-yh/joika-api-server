<?php
// 活動留言的點讚/取消讚切換

// --- 1. 引用設定檔與設定標頭 ---
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

// --- 2. 檢查請求方法 ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
    exit;
}

// --- 3. 接收並驗證輸入的資料 ---
try {
    $input = json_decode(file_get_contents("php://input"), true);

    $commentNo = intval($input["comment_no"] ?? 0);
    $memberId  = intval($input["member_id"]  ?? 0);
  
    if (!$commentNo || !$memberId) {
        http_response_code(400);
        echo json_encode(["error" => "缺少必要參數"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- 4. 連接資料庫並執行操作 ---
    $db = db();

    $commentTable = "activity_comment";
    $commentPk    = "ACTIVITY_COMMENT_NO";
    $commentType  = "activity";

    // **重要：使用交易確保資料一致性**
    $db->begin_transaction();

    // 檢查是否已經按過讚
    // 這段 SQL 查詢不用改，因為變數已經幫我們代入 'activity' 了
    $stmt = $db->prepare("SELECT 1 FROM comment_like WHERE COMMENT_NO = ? AND COMMENT_TYPE = ? AND MEMBER_ID = ?");
    $stmt->bind_param("isi", $commentNo, $commentType, $memberId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // **收回讚** (已經按過讚了)
        // 步驟 1: 從 comment_like 表刪除紀錄
        $stmt = $db->prepare("DELETE FROM comment_like WHERE COMMENT_NO = ? AND COMMENT_TYPE = ? AND MEMBER_ID = ?");
        $stmt->bind_param("isi", $commentNo, $commentType, $memberId);
        $stmt->execute();

        // 步驟 2: 更新活動留言的讚數 (-1)
        $stmt = $db->prepare("UPDATE {$commentTable} SET LIKE_COUNT = GREATEST(LIKE_COUNT - 1, 0) WHERE {$commentPk} = ?");
        $stmt->bind_param("i", $commentNo);
        $stmt->execute();

        $liked = false;
    } else {
        // **新增讚** (還沒按過讚)
        // 步驟 1: 在 comment_like 表新增一筆紀錄
        $stmt = $db->prepare("INSERT INTO comment_like (COMMENT_NO, COMMENT_TYPE, MEMBER_ID) VALUES (?, ?, ?)");
        $stmt->bind_param("isi", $commentNo, $commentType, $memberId);
        $stmt->execute();

        // 步驟 2: 更新活動留言的讚數 (+1)
        $stmt = $db->prepare("UPDATE {$commentTable} SET LIKE_COUNT = LIKE_COUNT + 1 WHERE {$commentPk} = ?");
        $stmt->bind_param("i", $commentNo);
        $stmt->execute();

        $liked = true;
    }

    $db->commit();

    // --- 5. 回傳最新的讚數 ---
    $stmt = $db->prepare("SELECT LIKE_COUNT FROM {$commentTable} WHERE {$commentPk} = ?");
    $stmt->bind_param("i", $commentNo);
    $stmt->execute();
    $likeResult = $stmt->get_result()->fetch_assoc();
    $currentLikeCount = $likeResult["LIKE_COUNT"] ?? 0;

     echo json_encode([
        "success"    => true,
        "liked"      => $liked,
        "like_count" => $currentLikeCount
    ]);

} catch (Exception $e) {
    // --- 6. 錯誤處理 ---
    if (isset($db)) {
        $db->rollback();
    }

    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(["error" => "伺服器發生錯誤，請稍後再試"], JSON_UNESCAPED_UNICODE);
}
?>