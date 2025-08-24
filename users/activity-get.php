<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

session_set_cookie_params([
    'lifetime' => 60*60*24*7,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['code'=>'0001','msg'=>'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['code' => '0002', 'msg' => '尚未登入'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mysqli   = db();
$memberId = (int) $_SESSION['member_id'];
$type     = $_GET['type'] ?? 'hosted';
$limit    = max(1, (int)($_GET['limit']  ?? 100));
$offset   = max(0, (int)($_GET['offset'] ?? 0));

$items = [];

try {
    if ($type === 'all') {
        $sql = "
            SELECT *
            FROM (
                SELECT 
                    a.ACTIVITY_NO,
                    a.ACTIVITY_NAME,
                    COALESCE(a.ACTIVITY_IMG, '/img/default-activity.png') AS ACTIVITY_IMG,
                    a.ACTIVITY_STATUS,
                    a.ACTIVITY_DESCRIPTION,
                    a.ACTIVITY_START_DATE AS ACTIVITY_START_AT,
                    DATE_FORMAT(a.ACTIVITY_START_DATE, '%m/%d') AS ACTIVITY_START_TXT,
                    'participant' AS role
                FROM PARTICIPANT p
                JOIN ACTIVITY a ON a.ACTIVITY_NO = p.ACTIVITY_NO
                WHERE p.PARTICIPANT_ID = ?

                UNION ALL

                SELECT
                    a.ACTIVITY_NO,
                    a.ACTIVITY_NAME,
                    COALESCE(a.ACTIVITY_IMG, '/img/default-activity.png') AS ACTIVITY_IMG,
                    a.ACTIVITY_STATUS,
                    a.ACTIVITY_DESCRIPTION,
                    a.ACTIVITY_START_DATE AS ACTIVITY_START_AT,
                    DATE_FORMAT(a.ACTIVITY_START_DATE, '%m/%d') AS ACTIVITY_START_TXT,
                    'host' AS role
                FROM ACTIVITY a
                WHERE a.HOST_MEMBER_ID = ?
            ) AS t
            ORDER BY 
                (t.ACTIVITY_START_AT < NOW()) ASC,
                ABS(TIMESTAMPDIFF(SECOND, t.ACTIVITY_START_AT, NOW())) ASC
            LIMIT ? OFFSET ?";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) { throw new Exception("Prepare failed: ".$mysqli->error); }
        $stmt->bind_param('iiii', $memberId, $memberId, $limit, $offset);
        $stmt->execute();

        $stmt->bind_result(
            $activityNo,
            $activityName,
            $activityImg,
            $activityStatus,
            $activityDescription,
            $activityStartAt,
            $activityStartTxt,
            $role
        );

        while ($stmt->fetch()) {
            $items[] = [
                'ACTIVITY_NO'         => $activityNo,
                'ACTIVITY_NAME'       => $activityName,
                'ACTIVITY_IMG'        => $activityImg,
                'ACTIVITY_STATUS'     => $activityStatus,
                'ACTIVITY_DESCRIPTION'=> $activityDescription,
                'ACTIVITY_START_AT'   => $activityStartAt,
                'ACTIVITY_START_TXT'  => $activityStartTxt,
                'role'                => $role,
            ];
        }
        $stmt->close();

    } elseif ($type === 'joined') {
        $sql = "SELECT 
                    a.ACTIVITY_NO,
                    a.ACTIVITY_NAME,
                    COALESCE(a.ACTIVITY_IMG, '/img/default-activity.png') AS ACTIVITY_IMG,
                    a.ACTIVITY_STATUS,
                    a.ACTIVITY_DESCRIPTION,
                    a.ACTIVITY_START_DATE AS ACTIVITY_START_AT,
                    DATE_FORMAT(a.ACTIVITY_START_DATE, '%m/%d') AS ACTIVITY_START_TXT,
                    'participant' AS role
                FROM PARTICIPANT p
                JOIN ACTIVITY a ON a.ACTIVITY_NO = p.ACTIVITY_NO
                WHERE p.PARTICIPANT_ID = ?
                ORDER BY 
                  (a.ACTIVITY_START_DATE < NOW()) ASC,
                  ABS(TIMESTAMPDIFF(SECOND, a.ACTIVITY_START_DATE, NOW())) ASC
                LIMIT ? OFFSET ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) { throw new Exception("Prepare failed: ".$mysqli->error); }
        $stmt->bind_param('iii', $memberId, $limit, $offset);
        $stmt->execute();

        $stmt->bind_result(
            $activityNo,
            $activityName,
            $activityImg,
            $activityStatus,
            $activityDescription,
            $activityStartAt,
            $activityStartTxt,
            $role
        );
        while ($stmt->fetch()) {
            $items[] = [
                'ACTIVITY_NO'         => $activityNo,
                'ACTIVITY_NAME'       => $activityName,
                'ACTIVITY_IMG'        => $activityImg,
                'ACTIVITY_STATUS'     => $activityStatus,
                'ACTIVITY_DESCRIPTION'=> $activityDescription,
                'ACTIVITY_START_AT'   => $activityStartAt,
                'ACTIVITY_START_TXT'  => $activityStartTxt,
                'role'                => $role,
            ];
        }
        $stmt->close();

    } elseif ($type === 'hosted') {
        $sql = "SELECT 
                    a.ACTIVITY_NO,
                    a.ACTIVITY_NAME,
                    COALESCE(a.ACTIVITY_IMG, '/img/default-activity.png') AS ACTIVITY_IMG,
                    a.ACTIVITY_STATUS,
                    a.ACTIVITY_DESCRIPTION,
                    a.ACTIVITY_START_DATE AS ACTIVITY_START_AT,
                    DATE_FORMAT(a.ACTIVITY_START_DATE, '%m/%d') AS ACTIVITY_START_TXT,
                    'host' AS role
                FROM ACTIVITY a
                WHERE a.HOST_MEMBER_ID = ?
                ORDER BY 
                  (a.ACTIVITY_START_DATE < NOW()) ASC,
                  ABS(TIMESTAMPDIFF(SECOND, a.ACTIVITY_START_DATE, NOW())) ASC
                LIMIT ? OFFSET ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) { throw new Exception("Prepare failed: ".$mysqli->error); }
        $stmt->bind_param('iii', $memberId, $limit, $offset);
        $stmt->execute();

        $stmt->bind_result(
            $activityNo,
            $activityName,
            $activityImg,
            $activityStatus,
            $activityDescription,
            $activityStartAt,
            $activityStartTxt,
            $role
        );
        while ($stmt->fetch()) {
            $items[] = [
                'ACTIVITY_NO'         => $activityNo,
                'ACTIVITY_NAME'       => $activityName,
                'ACTIVITY_IMG'        => $activityImg,
                'ACTIVITY_STATUS'     => $activityStatus,
                'ACTIVITY_DESCRIPTION'=> $activityDescription,
                'ACTIVITY_START_AT'   => $activityStartAt,
                'ACTIVITY_START_TXT'  => $activityStartTxt,
                'role'                => $role,
            ];
        }
        $stmt->close();

    } elseif ($type === 'collection') {
        $sql = "SELECT 
                    a.ACTIVITY_NO,
                    a.ACTIVITY_NAME,
                    COALESCE(a.ACTIVITY_IMG, '/img/default-activity.png') AS ACTIVITY_IMG,
                    a.ACTIVITY_STATUS,
                    a.ACTIVITY_DESCRIPTION,
                    a.ACTIVITY_START_DATE AS ACTIVITY_START_AT,
                    DATE_FORMAT(a.ACTIVITY_START_DATE, '%m/%d') AS ACTIVITY_START_TXT,
                    'collection' AS role
                FROM favorite_activities f
                JOIN ACTIVITY a ON f.ACTIVITY_NO = a.ACTIVITY_NO
                WHERE f.MEMBER_ID = ?
                ORDER BY 
                  (a.ACTIVITY_START_DATE < NOW()) ASC,
                  ABS(TIMESTAMPDIFF(SECOND, a.ACTIVITY_START_DATE, NOW())) ASC
                LIMIT ? OFFSET ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) { throw new Exception("Prepare failed: ".$mysqli->error); }
        $stmt->bind_param('iii', $memberId, $limit, $offset);
        $stmt->execute();

        $stmt->bind_result(
            $activityNo,
            $activityName,
            $activityImg,
            $activityStatus,
            $activityDescription,
            $activityStartAt,
            $activityStartTxt,
            $role
        );
        while ($stmt->fetch()) {
            $items[] = [
                'ACTIVITY_NO'         => $activityNo,
                'ACTIVITY_NAME'       => $activityName,
                'ACTIVITY_IMG'        => $activityImg,
                'ACTIVITY_STATUS'     => $activityStatus,
                'ACTIVITY_DESCRIPTION'=> $activityDescription,
                'ACTIVITY_START_AT'   => $activityStartAt,
                'ACTIVITY_START_TXT'  => $activityStartTxt,
                'role'                => $role,
            ];
        }
        $stmt->close();

    } else {
        http_response_code(400);
        echo json_encode(['code'=>'0003','msg'=>'Bad Request: unknown type'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['code'=>'0000','data'=>['items'=>$items]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    error_log("[member-activities] ".$e->getMessage());
    http_response_code(500);
    echo json_encode(['code'=>'9999','msg'=>'Server Error'], JSON_UNESCAPED_UNICODE);
}
