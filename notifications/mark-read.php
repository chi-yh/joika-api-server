<?php
// notifications/mark-read.php
// 將一筆或多筆通知標記為「已讀」
// 介面：POST JSON { "notification_no": 12 } 或 { "ids": [12,13,18] }
// （可選）POST JSON { "all": true } 代表把目前使用者的「所有未讀」標為已讀

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Taipei');

require_once __DIR__ . '/../config/db.php';
session_start();

// 僅允許 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'METHOD_NOT_ALLOWED'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 驗證登入（兼容兩種 session key）
$sessionMemberId = $_SESSION['member_id'] ?? ($_SESSION['user']['id'] ?? null);
if (empty($sessionMemberId)) {
    http_response_code(401);
    echo json_encode(['error' => 'UNAUTHORIZED'], JSON_UNESCAPED_UNICODE);
    exit;
}
$userId = (int)$sessionMemberId;

$db = db();
if (method_exists($db, 'set_charset')) $db->set_charset('utf8mb4');

// 讀取 body（支援 JSON 與 form）
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) $input = $_POST;

// 參數解析：ids / notification_no / all
$markAll = !empty($input['all']) && ($input['all'] === true || $input['all'] === 'true');

$ids = [];
if (!empty($input['ids']) && is_array($input['ids'])) {
    $ids = $input['ids'];
} elseif (!empty($input['notification_no'])) {
    $ids = [ $input['notification_no'] ];
}

// 正規化 ids
$ids = array_values(array_unique(array_map('intval', $ids)));
$ids = array_filter($ids, fn($v) => $v > 0);

// 驗證
if (!$markAll && empty($ids)) {
    http_response_code(400);
    echo json_encode(['error' => 'MISSING_IDS_OR_ALL'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 交易開始（避免局部更新）
    $db->begin_transaction();

    $updatedCount = 0;
    $targetIds = [];

    if ($markAll) {
        // 標記目前使用者所有未讀為已讀
        $sql = "
            UPDATE notification
            SET NOTIFICATION_STATUS='已讀'
            WHERE MEMBER_ID = $userId
                AND NOTIFICATION_STATUS = '未讀'
        ";
        $ok = $db->query($sql);
        if (!$ok) throw new Exception('UPDATE_ALL_FAILED: '.$db->error);
        $updatedCount = $db->affected_rows;

        // 回傳被更新的 id（可選）
        // 這步若資料量很大就不要做；小量時可用
        $sqlFetch = "
            SELECT NOTIFICATION_NO
            FROM notification
            WHERE MEMBER_ID = $userId
                AND NOTIFICATION_STATUS = '已讀'
            ORDER BY NOTIFICATION_NO DESC
            LIMIT 500
        ";
        if ($rs = $db->query($sqlFetch)) {
            $targetIds = array_column($rs->fetch_all(MYSQLI_ASSOC), 'NOTIFICATION_NO');
        }
    } else {
        // 先找出屬於自己的、目前未讀、且在 ids 清單內的那幾筆
        $idList = implode(',', $ids); // 皆為 int 已淨化
        $sqlPick = "
            SELECT NOTIFICATION_NO
            FROM notification
            WHERE MEMBER_ID = $userId
                AND NOTIFICATION_STATUS = '未讀'
                AND NOTIFICATION_NO IN ($idList)
        ";
        $rs = $db->query($sqlPick);
        $targetIds = $rs ? array_column($rs->fetch_all(MYSQLI_ASSOC), 'NOTIFICATION_NO') : [];

        if (!empty($targetIds)) {
            $idList2 = implode(',', array_map('intval', $targetIds));
            $sqlUpd = "
                UPDATE notification
                SET NOTIFICATION_STATUS='已讀'
                WHERE MEMBER_ID = $userId
                    AND NOTIFICATION_NO IN ($idList2)
                    AND NOTIFICATION_STATUS = '未讀'
            ";
            $ok = $db->query($sqlUpd);
            if (!$ok) throw new Exception('UPDATE_FAILED: '.$db->error);
            $updatedCount = $db->affected_rows;
        }
    }

    $db->commit();

    echo json_encode([
        'success'       => true,
        'updated_count' => $updatedCount,
        'ids_updated'   => $targetIds, // 提供前端同步（可依需求拿掉）
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if ($db && $db->errno === 0) { $db->rollback(); }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
