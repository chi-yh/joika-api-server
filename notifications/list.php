<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Taipei');

require_once __DIR__ . '/../config/db.php';

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
// 'domain'   => 'tibamef2e.com',
    'secure'   => $secure,          // HTTPS 必須 true
    'httponly' => true,
    'samesite' => 'None'            
]);
session_start();

// 只允許 GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error'=>'METHOD_NOT_ALLOWED'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 讀取登入者（同時支援兩種寫法）
$userId = (int)($_SESSION['member_id'] ?? ($_SESSION['user']['id'] ?? 0));
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode([
        'error'=>'UNAUTHORIZED',
        // ↓↓↓ 開發期可保留這些 debug，確認 Cookie 與 Session；上線請移除
        // 'sid'   => session_id(),
        // 'cookie'=> $_SERVER['HTTP_COOKIE'] ?? null,
        // 'sess'  => $_SESSION,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = db();
if (method_exists($db, 'set_charset')) $db->set_charset("utf8mb4");

// 查詢參數
$status = $_GET['status'] ?? 'all';   // unread|read|all
$type   = $_GET['type']   ?? 'all';   // system|interact|all
$limit  = isset($_GET['limit'])  ? (int)$_GET['limit']  : 20;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

if ($limit <= 0) $limit = 20;
if ($limit > 100) $limit = 100;
if ($offset < 0) $offset = 0;

// enum map
$mapStatus = ['unread'=>'未讀','read'=>'已讀','all'=>null];
$mapType   = ['system'=>'系統通知','interact'=>'互動通知','all'=>null];
$statusVal = $mapStatus[strtolower($status)] ?? null;
$typeVal   = $mapType[strtolower($type)] ?? null;

// 動態 where
$where = "WHERE MEMBER_ID = $userId";
if ($statusVal) {
    $where .= " AND NOTIFICATION_STATUS = '".$db->real_escape_string($statusVal)."'";
}
if ($typeVal) {
    $where .= " AND NOTIFICATION_TYPE = '".$db->real_escape_string($typeVal)."'";
}
$where .= " AND (AVAILABLE_AT IS NULL OR AVAILABLE_AT <= NOW())";

// 查詢列表
$sql = "SELECT 
            NOTIFICATION_NO       AS notification_no,
            NOTIFICATION_TITLE    AS title,
            NOTIFICATION_CONTENT  AS content,
            NOTIFICATION_STATUS   AS status,
            NOTIFICATION_TYPE     AS type,
            CREATED_AT            AS created_at,
            AVAILABLE_AT          AS available_at
        FROM notification
        $where
        ORDER BY CREATED_AT DESC, NOTIFICATION_NO DESC
        LIMIT $limit OFFSET $offset";

$result = $db->query($sql);
$data   = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// 總數
$sqlCount = "SELECT COUNT(*) AS cnt FROM notification $where";
$countRes = $db->query($sqlCount);
$total    = $countRes ? (int)$countRes->fetch_assoc()['cnt'] : 0;

// 未讀數
$sqlUnread = "SELECT COUNT(*) AS cnt FROM notification 
                WHERE MEMBER_ID = $userId 
                AND NOTIFICATION_STATUS='未讀'
                AND (AVAILABLE_AT IS NULL OR AVAILABLE_AT <= NOW())";
$unreadRes = $db->query($sqlUnread);
$unreadCnt = $unreadRes ? (int)$unreadRes->fetch_assoc()['cnt'] : 0;

echo json_encode([
    'data' => $data,
    'meta' => [
        'limit'        => $limit,
        'offset'       => $offset,
        'total'        => $total,
        'unread_count' => $unreadCnt
    ]
], JSON_UNESCAPED_UNICODE);
