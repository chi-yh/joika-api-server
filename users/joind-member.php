<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
session_start();

/* -----------------------------
* 1) 基本檢查
* ----------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['code' => '0001', 'msg' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['code' => '0002', 'msg' => '尚未登入'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* -----------------------------
* 2) 取得查詢參數（避免 ??；activity_no 轉 int）
* ----------------------------- */
$mysqli = db();
if (function_exists('mysqli_set_charset')) {
    if (!@mysqli_set_charset($mysqli, 'utf8mb4')) {
        @mysqli_set_charset($mysqli, 'utf8');
    }
}

$activityNo = 0;
if (isset($_GET['activity_no'])) {
    $activityNo = (int) trim((string) $_GET['activity_no']);
}

$status = 'approved';
if (isset($_GET['status']) && $_GET['status'] === 'pending') {
    $status = 'pending';
}

if ($activityNo === 0) {
    http_response_code(400);
    echo json_encode(['code' => '0003', 'msg' => '缺少活動編號 activity_no'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($status === 'pending') {
    $statusCondition = "p.JOINER_STATUS = '審核中'";
    $includeHost = false; // ⭐ 審核中不帶主揪
} else {
    $statusCondition = "p.JOINER_STATUS = '已參加'";
    $includeHost = true;  // ⭐ 已參加才帶主揪
}

/* 先抓主揪 */
$getHost = $mysqli->prepare("SELECT `HOST_MEMBER_ID` FROM `activity` WHERE `ACTIVITY_NO` = ?");
if (!$getHost) {
    http_response_code(500);
    echo json_encode(['code'=>'9998','msg'=>'Prepare failed','debug'=>$mysqli->error], JSON_UNESCAPED_UNICODE);
    exit;
}
$getHost->bind_param('i', $activityNo);
if (!$getHost->execute()) {
    http_response_code(500);
    echo json_encode(['code'=>'9997','msg'=>'Execute failed','debug'=>$getHost->error], JSON_UNESCAPED_UNICODE);
    exit;
}
$getHost->bind_result($hostMemberId);
$getHost->fetch();
$getHost->close();

if (!$hostMemberId) {
    http_response_code(404);
    echo json_encode(['code'=>'0008','msg'=>'找不到活動'], JSON_UNESCAPED_UNICODE);
    exit;
}

$loginId = (string) $_SESSION['member_id'];
$hostId  = (string) $hostMemberId;

// ⭐ 審核名單僅主揪可看
if ($status === 'pending' && $loginId !== $hostId) {
    http_response_code(403);
    echo json_encode(['code'=>'0009','msg'=>'僅主揪可查看審核名單'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* -----------------------------
* 3) 動態組 SQL（表名全小寫 + 反引號）
* ----------------------------- */
$sqlParts = array();

if ($includeHost) {
    $sqlParts[] = "
        SELECT 
            a.`HOST_MEMBER_ID`                                      AS member_id,
            'host'                                                  AS role,
            m.`MEMBER_NICKNAME`,
            m.`MEMBER_BIRTHDATE`,
            TIMESTAMPDIFF(YEAR, m.`MEMBER_BIRTHDATE`, CURDATE())    AS AGE,
            c.`CITY_NAME`                                           AS MEMBER_CITY_NAME,
            o.`OCCUPATION`                                          AS MEMBER_OCCUPATION_NAME,
            COALESCE(m.`MEMBER_AVATAR`, '/img/default-avatar.png')  AS MEMBER_AVATAR,
            IF(m.`HOST_COUNT_TOTAL` > 0, m.`HOST_SCORE_TOTAL` / m.`HOST_COUNT_TOTAL`, 0) AS rating,
            m.`HOST_COUNT_TOTAL` AS reviews
        FROM `activity` a
        JOIN `member` m ON m.`MEMBER_ID` = a.`HOST_MEMBER_ID`
        LEFT JOIN `city`       c ON c.`CITY_NO`       = m.`MEMBER_CITY`
        LEFT JOIN `occupation` o ON o.`OCCUPATION_NO` = m.`MEMBER_OCCUPATION`
        WHERE a.`ACTIVITY_NO` = ?
    ";
}

$sqlParts[] = "
    SELECT
        p.`PARTICIPANT_ID`                                       AS member_id,
        'participant'                                            AS role,
        m.`MEMBER_NICKNAME`,
        m.`MEMBER_BIRTHDATE`,
        TIMESTAMPDIFF(YEAR, m.`MEMBER_BIRTHDATE`, CURDATE())     AS AGE,
        c.`CITY_NAME`                                            AS MEMBER_CITY_NAME,
        o.`OCCUPATION`                                           AS MEMBER_OCCUPATION_NAME,
        COALESCE(m.`MEMBER_AVATAR`, '/img/default-avatar.png')   AS MEMBER_AVATAR,
        IF(m.`JOINER_COUNT_TOTAL` > 0, m.`JOINER_SCORE_TOTAL` / m.`JOINER_COUNT_TOTAL`, 0) AS rating,
        m.`JOINER_COUNT_TOTAL` AS reviews
    FROM `participant` p
    JOIN `member` m ON m.`MEMBER_ID` = p.`PARTICIPANT_ID`
    LEFT JOIN `city`       c ON c.`CITY_NO`       = m.`MEMBER_CITY`
    LEFT JOIN `occupation` o ON o.`OCCUPATION_NO` = m.`MEMBER_OCCUPATION`
    WHERE p.`ACTIVITY_NO` = ?
      AND {$statusCondition}
      AND p.`PARTICIPANT_ID` <> (
          SELECT `HOST_MEMBER_ID` FROM `activity` WHERE `ACTIVITY_NO` = ?
      )
";

$sql = implode(" UNION ALL ", $sqlParts) . "
    ORDER BY FIELD(role,'host','participant'), `MEMBER_NICKNAME`
";

/* -----------------------------
* 4) Prepare / Bind / Execute
* ----------------------------- */
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['code' => '9998', 'msg' => 'Prepare failed', 'debug' => $mysqli->error], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($includeHost) {
    // host 段 ? + participant 段 ? + 子查詢 ?
    $stmt->bind_param('iii', $activityNo, $activityNo, $activityNo);
} else {
    // participant 段 ? + 子查詢 ?
    $stmt->bind_param('ii',  $activityNo, $activityNo);
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['code' => '9997', 'msg' => 'Execute failed', 'debug' => $stmt->error], JSON_UNESCAPED_UNICODE);
    exit;
}

/* -----------------------------
* 5) 讀取結果
* ----------------------------- */
$stmt->store_result();
$stmt->bind_result(
    $member_id,
    $role,
    $nickname,
    $birthdate,
    $age,
    $city_name,
    $occ_name,
    $avatar,
    $rating,
    $reviews
);

$list = array();
while ($stmt->fetch()) {
    $list[] = array(
        'member_id'               => $member_id,
        'role'                    => $role,
        'MEMBER_NICKNAME'         => $nickname,
        'MEMBER_BIRTHDATE'        => $birthdate,
        'AGE'                     => is_null($age) ? null : (int)$age,
        'MEMBER_CITY_NAME'        => $city_name,
        'MEMBER_OCCUPATION_NAME'  => $occ_name,
        'MEMBER_AVATAR'           => $avatar,
        'rating'                  => is_null($rating) ? 0.0 : (float)$rating,
        'reviews'                 => (int)$reviews,
    );
}
$stmt->close();

/* -----------------------------
* 6) 回傳
* ----------------------------- */
echo json_encode(['code' => '0000', 'msg' => 'success', 'data' => $list], JSON_UNESCAPED_UNICODE);
