<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

// 除錯開關（完成後可移除）
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
define('APP_DEBUG', true);

// Session（相容）
if (PHP_VERSION_ID >= 70300) {
  session_set_cookie_params([
    'lifetime'=>60*60*24*7,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax',
  ]);
} else {
  session_set_cookie_params(60*60*24*7, '/', '', false, true);
}
session_start();

// Method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['code'=>'0001','msg'=>'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
  exit;
}

// Login
if (empty($_SESSION['member_id'])) {
  http_response_code(401);
  echo json_encode(['code'=>'0002','msg'=>'尚未登入'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $mysqli = db();
  $mysqli->set_charset('utf8mb4');

  $memberId = (int)$_SESSION['member_id'];
  $type   = isset($_GET['type'])   && $_GET['type']!=='' ? $_GET['type']   : 'hosted';
  $limit  = isset($_GET['limit'])  && (int)$_GET['limit']>0 ? (int)$_GET['limit'] : 100;
  $offset = isset($_GET['offset']) && (int)$_GET['offset']>=0 ? (int)$_GET['offset'] : 0;

  // ---- 重要：LIMIT/OFFSET 用整數直插（避免某些老版 MariaDB 的預處理限制） ----
  $L = (int)$limit;
  $O = (int)$offset;

  $items = [];

  if ($type === 'all') {
    $sql = "
        SELECT *
        FROM (
            SELECT 
            a.activity_no,
            a.activity_name,
            COALESCE(a.activity_img, '/img/default-activity.png') AS activity_img,
            a.activity_status,
            a.activity_description,
            a.activity_start_date AS activity_start_at,
            DATE_FORMAT(a.activity_start_date, '%m/%d') AS activity_start_txt,
            'participant' AS role
            FROM participant p
            JOIN activity a ON a.activity_no = p.activity_no
            WHERE p.participant_id = ?
            AND p.joiner_status = '已參加'

        UNION ALL

        SELECT
            a.activity_no,
            a.activity_name,
            COALESCE(a.activity_img, '/img/default-activity.png') AS activity_img,
            a.activity_status,
            a.activity_description,
            a.activity_start_date AS activity_start_at,
            DATE_FORMAT(a.activity_start_date, '%m/%d') AS activity_start_txt,
            'host' AS role
            FROM activity a
            WHERE a.host_member_id = ?
      ) AS t
      ORDER BY 
        (t.activity_start_at < NOW()) ASC,
        ABS(TIMESTAMPDIFF(SECOND, t.activity_start_at, NOW())) ASC
      LIMIT $L OFFSET $O";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ii', $memberId, $memberId);
  }
  elseif ($type === 'joined') {
    $sql = "
      SELECT 
        a.activity_no,
        a.activity_name,
        COALESCE(a.activity_img, '/img/default-activity.png') AS activity_img,
        a.activity_status,
        a.activity_description,
        a.activity_start_date AS activity_start_at,
        DATE_FORMAT(a.activity_start_date, '%m/%d') AS activity_start_txt,
        'participant' AS role
        FROM participant p
        JOIN activity a ON a.activity_no = p.activity_no
        WHERE p.participant_id = ? 
        AND p.joiner_status = '已參加'
        ORDER BY 
        (a.activity_start_date < NOW()) ASC,
        ABS(TIMESTAMPDIFF(SECOND, a.activity_start_date, NOW())) ASC
      LIMIT $L OFFSET $O";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $memberId);
  }
  elseif ($type === 'hosted') {
    $sql = "
      SELECT 
        a.activity_no,
        a.activity_name,
        COALESCE(a.activity_img, '/img/default-activity.png') AS activity_img,
        a.activity_status,
        a.activity_description,
        a.activity_start_date AS activity_start_at,
        DATE_FORMAT(a.activity_start_date, '%m/%d') AS activity_start_txt,
        'host' AS role
      FROM activity a
      WHERE a.host_member_id = ?
      ORDER BY 
        (a.activity_start_date < NOW()) ASC,
        ABS(TIMESTAMPDIFF(SECOND, a.activity_start_date, NOW())) ASC
      LIMIT $L OFFSET $O";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $memberId);
  }
  elseif ($type === 'collection') {
    $sql = "
      SELECT 
        a.activity_no,
        a.activity_name,
        COALESCE(a.activity_img, '/img/default-activity.png') AS activity_img,
        a.activity_status,
        a.activity_description,
        a.activity_start_date AS activity_start_at,
        DATE_FORMAT(a.activity_start_date, '%m/%d') AS activity_start_txt,
        'collection' AS role
      FROM favorite_activities f
      JOIN activity a ON f.activity_no = a.activity_no
      WHERE f.member_id = ?
      ORDER BY 
        (a.activity_start_date < NOW()) ASC,
        ABS(TIMESTAMPDIFF(SECOND, a.activity_start_date, NOW())) ASC
      LIMIT $L OFFSET $O";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $memberId);
  }
  else {
    http_response_code(400);
    echo json_encode(['code'=>'0003','msg'=>'Bad Request: unknown type'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stmt->execute();
  $stmt->bind_result($activityNo,$activityName,$activityImg,$activityStatus,$activityDescription,$activityStartAt,$activityStartTxt,$role);
  while ($stmt->fetch()) {
    $items[] = [
      'ACTIVITY_NO'          => $activityNo,
      'ACTIVITY_NAME'        => $activityName,
      'ACTIVITY_IMG'         => $activityImg,
      'ACTIVITY_STATUS'      => $activityStatus,
      'ACTIVITY_DESCRIPTION' => $activityDescription,
      'ACTIVITY_START_AT'    => $activityStartAt,
      'ACTIVITY_START_TXT'   => $activityStartTxt,
      'role'                 => $role,
    ];
  }
  $stmt->close();

  echo json_encode(['code'=>'0000','data'=>['items'=>$items]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'code'=>'9999',
    'msg'=>'Server Error',
    'debug'=> APP_DEBUG ? $e->getMessage() : null
  ], JSON_UNESCAPED_UNICODE);
}
