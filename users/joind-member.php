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
 * 2) 取得查詢參數
 * ----------------------------- */
$mysqli     = db();
$activityNo = trim((string)($_GET['activity_no'] ?? ''));

// status 白名單（列表=approved→已參加；審核頁=pending→審核中）
$status = $_GET['status'] ?? 'approved';
if ($status === 'pending') {
    $statusCondition = "p.JOINER_STATUS = '審核中'";
    $includeHost = false; // ⭐ 審核中不帶主揪
} else {
    $statusCondition = "p.JOINER_STATUS = '已參加'";
    $includeHost = true;  // ⭐ 已參加才帶主揪
}

if ($activityNo === '') {
    http_response_code(400);
    echo json_encode(['code' => '0003', 'msg' => '缺少活動編號 activity_no'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* -----------------------------
 * 3) 動態組 SQL
 *   - 主揪：rating = HOST_SCORE_TOTAL / HOST_COUNT_TOTAL，reviews = HOST_COUNT_TOTAL
 *   - 參加者：rating = JOINER_SCORE_TOTAL / JOINER_COUNT_TOTAL，reviews = JOINER_COUNT_TOTAL
 *   - 兩段都 LEFT JOIN city / occupation
 *   - 直接算 AGE
 * ----------------------------- */
$sqlParts = [];

if ($includeHost) {
    $sqlParts[] = "
        SELECT 
            a.HOST_MEMBER_ID                                      AS member_id,
            'host'                                                AS role,
            m.MEMBER_NICKNAME,
            m.MEMBER_BIRTHDATE,
            TIMESTAMPDIFF(YEAR, m.MEMBER_BIRTHDATE, CURDATE())    AS AGE,
            c.CITY_NAME                                           AS MEMBER_CITY_NAME,
            o.OCCUPATION                                          AS MEMBER_OCCUPATION_NAME,
            COALESCE(m.MEMBER_AVATAR, '/img/default-avatar.png')  AS MEMBER_AVATAR,
            IF(m.HOST_COUNT_TOTAL > 0, m.HOST_SCORE_TOTAL / m.HOST_COUNT_TOTAL, 0) AS rating,
            m.HOST_COUNT_TOTAL AS reviews
        FROM ACTIVITY a
        JOIN MEMBER m ON m.MEMBER_ID = a.HOST_MEMBER_ID
        LEFT JOIN city       c ON c.CITY_NO       = m.MEMBER_CITY
        LEFT JOIN occupation o ON o.OCCUPATION_NO = m.MEMBER_OCCUPATION
        WHERE a.ACTIVITY_NO = ?
    ";
}

$sqlParts[] = "
    SELECT
        p.PARTICIPANT_ID                                      AS member_id,
        'participant'                                         AS role,
        m.MEMBER_NICKNAME,
        m.MEMBER_BIRTHDATE,
        TIMESTAMPDIFF(YEAR, m.MEMBER_BIRTHDATE, CURDATE())    AS AGE,
        c.CITY_NAME                                           AS MEMBER_CITY_NAME,
        o.OCCUPATION                                          AS MEMBER_OCCUPATION_NAME,
        COALESCE(m.MEMBER_AVATAR, '/img/default-avatar.png')  AS MEMBER_AVATAR,
        IF(m.JOINER_COUNT_TOTAL > 0, m.JOINER_SCORE_TOTAL / m.JOINER_COUNT_TOTAL, 0) AS rating,
        m.JOINER_COUNT_TOTAL AS reviews
    FROM PARTICIPANT p
    JOIN MEMBER m ON m.MEMBER_ID = p.PARTICIPANT_ID
    LEFT JOIN city       c ON c.CITY_NO       = m.MEMBER_CITY
    LEFT JOIN occupation o ON o.OCCUPATION_NO = m.MEMBER_OCCUPATION
    WHERE p.ACTIVITY_NO = ?
      AND {$statusCondition}
      AND p.PARTICIPANT_ID <> (
          SELECT HOST_MEMBER_ID FROM ACTIVITY WHERE ACTIVITY_NO = ?
      )
";

$sql = implode(" UNION ALL ", $sqlParts) . "
    ORDER BY FIELD(role,'host','participant'), MEMBER_NICKNAME
";

/* -----------------------------
 * 4) Prepare / Bind / Execute
 *    - 包含主揪 → 需要 3 個參數（host 的 ? + participant 的 2 個 ?）
 *    - 不含主揪 → 需要 2 個參數（participant 的 2 個 ?）
 * ----------------------------- */
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['code' => '9998', 'msg' => 'Prepare failed: ' . $mysqli->error], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($includeHost) {
    $stmt->bind_param('sss', $activityNo, $activityNo, $activityNo);
} else {
    $stmt->bind_param('ss',  $activityNo, $activityNo);
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['code' => '9997', 'msg' => 'Execute failed: ' . $stmt->error], JSON_UNESCAPED_UNICODE);
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

$list = [];
while ($stmt->fetch()) {
    $list[] = [
        'member_id'                => $member_id,
        'role'                     => $role,
        'MEMBER_NICKNAME'          => $nickname,
        'MEMBER_BIRTHDATE'         => $birthdate,
        'AGE'                      => is_null($age) ? null : (int)$age,
        'MEMBER_CITY_NAME'         => $city_name,
        'MEMBER_OCCUPATION_NAME'   => $occ_name,
        'MEMBER_AVATAR'            => $avatar,
        'rating'                   => is_null($rating) ? 0.0 : (float)$rating,
        'reviews'                  => (int)$reviews,
    ];
}
$stmt->close();

/* -----------------------------
 * 6) 回傳
 * ----------------------------- */
echo json_encode(['code' => '0000', 'msg' => 'success', 'data' => $list], JSON_UNESCAPED_UNICODE);
