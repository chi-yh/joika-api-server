<?php
//  GET
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

// 只允許 GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'METHOD_NOT_ALLOWED'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 必須登入
$sessionMemberId = $_SESSION['member_id'] ?? ($_SESSION['user']['id'] ?? null);
if (empty($sessionMemberId)) {
    http_response_code(401);
    echo json_encode(['error' => 'UNAUTHORIZED'], JSON_UNESCAPED_UNICODE);
    exit;
}
$userId = (int)$sessionMemberId;

$db = db();

// 查詢參數
$type           = isset($_GET['type'])   ? trim(strtolower($_GET['type']))   : 'all';     // system|interact|all
$status         = isset($_GET['status']) ? trim(strtolower($_GET['status'])) : 'unread';  // unread|read|all
$since          = isset($_GET['since'])  ? trim($_GET['since'])               : null;      // 'YYYY-MM-DD HH:MM:SS'
$include_future = !empty($_GET['include_future']);                                         // 1: 包含未到可見時間
$limit          = isset($_GET['limit'])  ? (int)$_GET['limit']                : 20;
$offset         = isset($_GET['offset']) ? (int)$_GET['offset']               : 0;

$limit  = max(1, min(100, $limit));
$offset = max(0, $offset);

$where = [];
$where[] = "MEMBER_ID = {$userId}";

// 可見時間（
if (!$include_future) {
    $where[] = "(AVAILABLE_AT IS NULL OR AVAILABLE_AT <= NOW())";
}

// 類型
if ($type === 'system') {
    $where[] = "NOTIFICATION_TYPE = '系統通知'";
} elseif ($type === 'interact') {
    $where[] = "NOTIFICATION_TYPE = '互動通知'";
}

// 狀態
if ($type === 'system') {
    if ($status === 'unread') {
    $where[] = "NOTIFICATION_STATUS = '未讀'";
    } elseif ($status === 'read') {
    $where[] = "NOTIFICATION_STATUS = '已讀'";
    }
} elseif ($type === 'all') {
    if ($status === 'unread') {
    // 系統通知未讀 + 互動通知一律保留
    $where[] = "( (NOTIFICATION_TYPE='系統通知' AND NOTIFICATION_STATUS='未讀') OR NOTIFICATION_TYPE='互動通知' )";
    } elseif ($status === 'read') {
    // 系統通知已讀 + 互動通知一律保留
    $where[] = "( (NOTIFICATION_TYPE='系統通知' AND NOTIFICATION_STATUS='已讀') OR NOTIFICATION_TYPE='互動通知' )";
    }
}


// 起始時間
if ($since !== null && $since !== '') {
    $sinceEsc = $db->real_escape_string($since);
    $where[]  = "CREATED_AT >= '{$sinceEsc}'";
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ----------------------
// 計數
// ----------------------
$sqlCount = "SELECT COUNT(*) AS total FROM notification {$whereSql}";
$rsCount  = $db->query($sqlCount);
$total    = (int)($rsCount->fetch_assoc()['total'] ?? 0);

// ----------------------
// 列表（互動通知的 status 置為 NULL；排序僅對系統通知做未讀優先）
// ----------------------
$sql = "
    SELECT
        n.NOTIFICATION_NO         AS id,
        n.NOTIFICATION_TITLE      AS title,
        n.NOTIFICATION_CONTENT    AS content,
        n.NOTIFICATION_TYPE       AS type,
        n.NOTIFICATION_STATUS     AS status,
        n.CREATED_AT              AS created_at,
        n.AVAILABLE_AT            AS available_at,
        n.PROCESSED_BY            AS processed_by,
        p.POST_TITLE              AS post_title   
    FROM notification n
    LEFT JOIN post p ON n.POST_NO = p.POST_NO  -- 假設通知表有存 POST_NO
    $whereSql
    ORDER BY n.CREATED_AT DESC, n.NOTIFICATION_NO DESC
    LIMIT $limit OFFSET $offset
";
$result = $db->query($sql);
$items = $result->fetch_all(MYSQLI_ASSOC);

// 轉 boolean
foreach ($items as &$it) {
    $it['visible'] = $it['visible'] == 1;
}

$db->close();

// 輸出
echo json_encode([
    'total'  => $total,
    'limit'  => $limit,
    'offset' => $offset,
    'items'  => $items
], JSON_UNESCAPED_UNICODE);
