<?php
// /calendar/my-events.php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
@date_default_timezone_set('Asia/Taipei');

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$memberId = $_SESSION['member_id'] ?? null;
if (!$memberId) {
    http_response_code(401);
    echo json_encode(['error' => '尚未登入'], JSON_UNESCAPED_UNICODE);
    exit;
}

$startStr = $_GET['start'] ?? null;
$endStr   = $_GET['end']   ?? null;


if (!$startStr || !$endStr) {
    $startDt = new DateTime('first day of this month 00:00:00');
    $endDt   = new DateTime('last day of this month 23:59:59');
} else {
    try {
        $startDt = new DateTime($startStr);
        $endDt   = new DateTime($endStr);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['error'=>'Invalid date range'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$rangeStart = $startDt->format('Y-m-d H:i:s');
$rangeEnd   = $endDt->format('Y-m-d H:i:s');

// 連線
$mysqli = db();
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connect error'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 期間重疊條件：
 *   活動開始 < 查詢結束 AND (活動結束 IS NULL OR 活動結束 > 查詢開始)
 *
 * 去重策略：
 *   先選「我是主揪」；再選「我參與但不是我主揪」(NOT IN 主揪清單)
 *   這樣同一活動不會重複出現在結果
 */
$sql = "
    SELECT 
        a.ACTIVITY_NO AS id,
        c.CATEGORY_NAME AS title,
        a.ACTIVITY_START_DATE AS start_dt,
        COALESCE(a.ACTIVITY_END_DATE, DATE_ADD(a.ACTIVITY_START_DATE, INTERVAL 2 HOUR)) AS end_dt,
        a.ACTIVITY_STATUS AS status,
        'host' AS role
    FROM ACTIVITY a
    JOIN CATEGORY c ON a.CATEGORY_NO = c.CATEGORY_NO
    WHERE a.HOST_MEMBER_ID = ?
        AND a.ACTIVITY_START_DATE < ?
        AND (a.ACTIVITY_END_DATE IS NULL OR a.ACTIVITY_END_DATE > ?)
        AND a.ACTIVITY_STATUS IN ('開團中', '已成團', '已完成')

    UNION ALL

    SELECT 
        a.ACTIVITY_NO AS id,
        c.CATEGORY_NAME AS title,
        a.ACTIVITY_START_DATE AS start_dt,
        COALESCE(a.ACTIVITY_END_DATE, DATE_ADD(a.ACTIVITY_START_DATE, INTERVAL 2 HOUR)) AS end_dt,
        a.ACTIVITY_STATUS AS status,
        'participant' AS role
    FROM ACTIVITY a
    JOIN PARTICIPANT p ON a.ACTIVITY_NO = p.ACTIVITY_NO
    JOIN CATEGORY c ON a.CATEGORY_NO = c.CATEGORY_NO
    WHERE p.PARTICIPANT_ID = ?
        AND a.HOST_MEMBER_ID <> ?
        AND a.ACTIVITY_START_DATE < ?
        AND (a.ACTIVITY_END_DATE IS NULL OR a.ACTIVITY_END_DATE > ?)
        AND a.ACTIVITY_STATUS IN ('開團中', '已成團', '已完成')
    ORDER BY start_dt ASC
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'DB prepare error'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param(
    'issiiss',
    $memberId,        // 我主揪
    $rangeEnd,
    $rangeStart,
    $memberId,        // 我參與
    $memberId,        // 但不是我主揪
    $rangeEnd,
    $rangeStart
);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'DB execute error'], JSON_UNESCAPED_UNICODE);
    exit;
}

$res = $stmt->get_result();
$events = [];

function colorByStatus(string $status): string {
    $statusMap = [
        '開團中' => '#4db2e1',
        '已成團' => '#60c18e',
        '已完成' => '#aaaaaa', 
    ];
    return $statusMap[$status] ?? '#81BFDA';
}

while ($row = $res->fetch_assoc()) {
    $startIso = (new DateTime($row['start_dt']))->format(DateTime::ATOM);
    $endIso   = $row['end_dt'] ? (new DateTime($row['end_dt']))->format(DateTime::ATOM) : null;

    $events[] = [
    'id'     => (int)$row['id'],
    'title'  => $row['title'],
    'start'  => $startIso,
    'end'    => $endIso,
    'allDay' => false,
    'color'  => colorByStatus($row['status']), 
    'extendedProps' => [
        'status' => $row['status'],
    ],
];
}

// 回傳「純事件陣列」
echo json_encode($events, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
