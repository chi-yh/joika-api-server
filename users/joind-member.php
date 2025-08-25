<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['code'=>'0001','msg'=>'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (empty($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['code'=>'0002','msg'=>'尚未登入'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mysqli = db();
$activityNo = (int)($_GET['activity_no'] ?? 0);

// status 白名單（列表=approved→已參加；審核頁=pending→審核中）
$status = $_GET['status'] ?? 'approved';
if ($status === 'pending') {
    $statusCondition = "p.JOINER_STATUS = '審核中'";
} else {
    $statusCondition = "p.JOINER_STATUS = '已參加'";
}

$sql = "
(
    SELECT 
        a.HOST_MEMBER_ID          AS member_id,
        'host'                    AS role,
        m.MEMBER_NICKNAME,
        m.MEMBER_AVATAR,
        m.HOST_SCORE_TOTAL,
        m.HOST_COUNT_TOTAL,
        m.JOINER_SCORE_TOTAL,
        m.JOINER_COUNT_TOTAL
    FROM ACTIVITY a
    JOIN MEMBER m ON m.MEMBER_ID = a.HOST_MEMBER_ID
    WHERE a.ACTIVITY_NO = ?
)
UNION ALL
(
  SELECT
    p.PARTICIPANT_ID          AS member_id,
    'participant'             AS role,
    m.MEMBER_NICKNAME,
    m.MEMBER_AVATAR,
    m.HOST_SCORE_TOTAL,
    m.HOST_COUNT_TOTAL,
    m.JOINER_SCORE_TOTAL,
    m.JOINER_COUNT_TOTAL
  FROM PARTICIPANT p
  JOIN MEMBER m ON m.MEMBER_ID = p.PARTICIPANT_ID
  WHERE p.ACTIVITY_NO = ?
    AND {$statusCondition}
    AND p.PARTICIPANT_ID <> (
      SELECT HOST_MEMBER_ID FROM ACTIVITY WHERE ACTIVITY_NO = ?
    )
)
ORDER BY FIELD(role,'host','participant'), MEMBER_NICKNAME
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['code'=>'9998','msg'=>'Prepare failed: '.$mysqli->error], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param('iii', $activityNo, $activityNo, $activityNo);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['code'=>'9997','msg'=>'Execute failed: '.$stmt->error], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->store_result(); 
$stmt->bind_result(
    $member_id,
    $role,
    $nickname,
    $avatar,
    $host_score_total,
    $host_count_total,
    $joiner_score_total,
    $joiner_count_total
);

$list = [];
while ($stmt->fetch()) {
    $list[] = [
        'member_id'          => $member_id,
        'role'               => $role,
        'MEMBER_NICKNAME'    => $nickname,
        'MEMBER_AVATAR'      => $avatar,
        'HOST_SCORE_TOTAL'   => $host_score_total,
        'HOST_COUNT_TOTAL'   => $host_count_total,
        'JOINER_SCORE_TOTAL' => $joiner_score_total,
        'JOINER_COUNT_TOTAL' => $joiner_count_total,
    ];
}

$stmt->close();

echo json_encode(['code'=>'0000','msg'=>'success','data'=>$list], JSON_UNESCAPED_UNICODE);
