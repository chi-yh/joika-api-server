<?php
// /calendar/my-events.php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
@date_default_timezone_set('Asia/Taipei');
session_start();

function out_json($status, $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    out_json(405, ['error' => 'Method Not Allowed']);
}

// memberId（避免 ??）
$memberId = isset($_SESSION['member_id']) ? (int)$_SESSION['member_id'] : 0;
if (!$memberId) {
    out_json(401, ['error' => '尚未登入']);
}

// 查詢區間
$startStr = isset($_GET['start']) ? $_GET['start'] : null;
$endStr   = isset($_GET['end'])   ? $_GET['end']   : null;

if (!$startStr || !$endStr) {
    $startDt = new DateTime('first day of this month 00:00:00');
    $endDt   = new DateTime('last day of this month 23:59:59');
} else {
    try {
        $startDt = new DateTime($startStr);
        $endDt   = new DateTime($endStr);
    } catch (Exception $e) {
        out_json(400, ['error'=>'Invalid date range', 'debug'=>$e->getMessage()]);
    }
}

$rangeStart = $startDt->format('Y-m-d H:i:s');
$rangeEnd   = $endDt->format('Y-m-d H:i:s');

// DB
$mysqli = db();
if ($mysqli->connect_errno) {
    out_json(500, ['error' => 'DB connect error', 'debug' => $mysqli->connect_error]);
}
if (function_exists('mysqli_set_charset')) { @mysqli_set_charset($mysqli, 'utf8mb4'); }

/**
 * 期間重疊：
 *   活動開始 < 查詢結束 AND (活動結束 IS NULL OR 活動結束 > 查詢開始)
 * 去重策略：
 *   先選「我是主揪」；再選「我參與但不是我主揪」
 */
$sql = "
    SELECT 
        a.ACTIVITY_NO AS id,
        c.CATEGORY_NAME AS title,
        a.ACTIVITY_START_DATE AS start_dt,
        COALESCE(a.ACTIVITY_END_DATE, DATE_ADD(a.ACTIVITY_START_DATE, INTERVAL 2 HOUR)) AS end_dt,
        a.ACTIVITY_STATUS AS status,
        'host' AS role
    FROM `activity` a
    JOIN `category` c ON a.CATEGORY_NO = c.CATEGORY_NO
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
    FROM `activity` a
    JOIN `participant` p ON a.ACTIVITY_NO = p.ACTIVITY_NO
    JOIN `category` c ON a.CATEGORY_NO = c.CATEGORY_NO
    WHERE p.PARTICIPANT_ID = ?
        AND a.HOST_MEMBER_ID <> ?
        AND a.ACTIVITY_START_DATE < ?
        AND (a.ACTIVITY_END_DATE IS NULL OR a.ACTIVITY_END_DATE > ?)
        AND a.ACTIVITY_STATUS IN ('開團中', '已成團', '已完成')
    ORDER BY start_dt ASC
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    out_json(500, ['error' => 'DB prepare error', 'debug' => $mysqli->error]);
}

$memberIdInt = (int)$memberId;
if (!$stmt->bind_param('issiiss',
    $memberIdInt,
    $rangeEnd,
    $rangeStart,
    $memberIdInt,
    $memberIdInt,
    $rangeEnd,
    $rangeStart
)) {
    out_json(500, ['error' => 'DB bind_param error', 'debug' => $stmt->error]);
}

if (!$stmt->execute()) {
    out_json(500, ['error' => 'DB execute error', 'debug' => $stmt->error]);
}

if (!$stmt->bind_result($id, $title, $start_dt, $end_dt, $status, $role)) {
    out_json(500, ['error' => 'DB bind_result error', 'debug' => $stmt->error]);
}

function colorByStatus($status) {
    $statusMap = array(
        '開團中' => '#4db2e1',
        '已成團' => '#60c18e',
        '已完成' => '#aaaaaa',
    );
    return isset($statusMap[$status]) ? $statusMap[$status] : '#81BFDA';
}

$events = array();
while ($stmt->fetch()) {
    $startIso = (new DateTime($start_dt))->format(DateTime::ATOM);
    $endIso   = $end_dt ? (new DateTime($end_dt))->format(DateTime::ATOM) : null;

    $events[] = array(
        'id'     => (int)$id,
        'title'  => $title,
        'start'  => $startIso,
        'end'    => $endIso,
        'allDay' => false,
        'color'  => colorByStatus($status),
        'extendedProps' => array(
            'status' => $status,
            'role'   => $role,
        ),
    );
}

$stmt->close();
out_json(200, $events);
