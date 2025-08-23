<?php
// 開發期便於除錯
ini_set('display_errors', 1);
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: http://localhost:5173");
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Taipei');

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
session_start();

/* 工具 */
function json($data, int $code=200){
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}
function body(){ return json_decode(file_get_contents('php://input'), true) ?? []; }
function require_auth_user_id(): int {
  if (empty($_SESSION['user']['id'])) json(['error'=>'UNAUTHORIZED'], 401);
  return (int)$_SESSION['user']['id']
  ;
}

/* CORS & Method */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') { json(['error'=>'METHOD_NOT_ALLOWED'], 405); }

/* 入參 */
$activityNo = (int)($_GET['id'] ?? 0);
if ($activityNo <= 0) json(['error'=>'INVALID_ID'], 400);

$authUserId   = require_auth_user_id();
$b            = body();
$reasonNo     = (int)($b['reason_no'] ?? 0);
$reasonDetail = $b['reason_detail'] ?? null;
if (!$reasonNo) json(['error'=>'REASON_REQUIRED'], 400);
// 空字串想當作 NULL 存（可選）
if ($reasonDetail === '') $reasonDetail = null;

/* DB 交易 */
$mysqli = db();
$mysqli->query("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
$mysqli->begin_transaction();

try {
  // 1) 查並鎖定活動（避免競態）
  $sql1 = "SELECT ACTIVITY_NO, HOST_MEMBER_ID, ACTIVITY_STATUS, ACTIVITY_END_DATE
           FROM ACTIVITY
           WHERE ACTIVITY_NO = ? FOR UPDATE";
  $stmt1 = $mysqli->prepare($sql1);
  if (!$stmt1) throw new Exception('SQL1_PREPARE_FAILED: '.$mysqli->error);
  if (!$stmt1->bind_param('i', $activityNo)) throw new Exception('SQL1_BIND_FAILED: '.$stmt1->error);
  if (!$stmt1->execute()) throw new Exception('SQL1_EXECUTE_FAILED: '.$stmt1->error);

  // 用 bind_result/fetch（避免 get_result 依賴 mysqlnd）
  $stmt1->bind_result($r_no, $r_host, $r_status, $r_end);
  if (!$stmt1->fetch()) { $stmt1->close(); throw new Exception('NOT_FOUND'); }
  $stmt1->close();

  // 2) 驗證權限與狀態
  if ((int)$r_host !== $authUserId)            throw new Exception('FORBIDDEN');
  if ($r_status === '已取消')                  throw new Exception('ALREADY_CANCELLED');
  if (strtotime($r_end) < time())              throw new Exception('ENDED_CANNOT_CANCEL');
  if ($r_status !== '開團中')                  throw new Exception('NOT_ACTIVE');

  // 3) 更新取消
  $sql2 = "UPDATE ACTIVITY
           SET ACTIVITY_STATUS='已取消',
               HOSTER_CANCELLED_AT=NOW(),
               HOSTER_CANCEL_REASON_NO=?,
               HOSTER_CANCEL_DESCRIPTION=?
           WHERE ACTIVITY_NO=? AND ACTIVITY_STATUS='開團中'";
  $stmt2 = $mysqli->prepare($sql2);
  if (!$stmt2) throw new Exception('SQL2_PREPARE_FAILED: '.$mysqli->error);
  if (!$stmt2->bind_param('isi', $reasonNo, $reasonDetail, $activityNo))
    throw new Exception('SQL2_BIND_FAILED: '.$stmt2->error);
  if (!$stmt2->execute())
    throw new Exception('SQL2_EXECUTE_FAILED: '.$stmt2->error);

  if ($stmt2->affected_rows !== 1) throw new Exception('NOT_ACTIVE');
  $stmt2->close();


$sql3 = "INSERT INTO notification
          (MEMBER_ID, NOTIFICATION_TITLE, NOTIFICATION_CONTENT, CREATED_AT, NOTIFICATION_STATUS)
         SELECT
          p.PARTICIPANT_ID,
          LEFT(CONCAT('活動【', COALESCE(NULLIF(a.ACTIVITY_NAME,''), a.ACTIVITY_NO), '】已被主揪取消'), 50),
          LEFT(
            CONCAT(
              '您參加的活動【', COALESCE(NULLIF(a.ACTIVITY_NAME,''), a.ACTIVITY_NO), '】已被主揪取消。',
              IFNULL(CONCAT('（原因：', ?, '）'), '')
            ),
            1000
          ),
          NOW(),
          '未讀'
         FROM PARTICIPANT p
         JOIN ACTIVITY a ON a.ACTIVITY_NO = p.ACTIVITY_NO
         WHERE p.ACTIVITY_NO = ?";
  $stmt3 = $mysqli->prepare($sql3);
  if (!$stmt3) throw new Exception('SQL3_PREPARE_FAILED: '.$mysqli->error);
  if (!$stmt3->bind_param('si', $reasonDetail, $activityNo)) throw new Exception('SQL3_BIND_FAILED: '.$stmt3->error);
  if (!$stmt3->execute()) throw new Exception('SQL3_EXECUTE_FAILED: '.$stmt3->error);
  $stmt3->close();

  
  // 4) 回傳 DB 時間
  $rs = $mysqli->query("SELECT NOW() AS ts");
  if (!$rs) throw new Exception('SQL_NOW_FAILED: '.$mysqli->error);
  $ts = $rs->fetch_assoc()['ts'] ?? null;

  $mysqli->commit();
  json([
    'activity_no'         => $activityNo,
    'activity_status'     => '已取消',
    'hoster_cancelled_at' => $ts,
  ]);

} catch (Exception $e) {
  $mysqli->rollback();
  $map = [
    'NOT_FOUND'=>404, 'FORBIDDEN'=>403,
    'ALREADY_CANCELLED'=>409, 'ENDED_CANNOT_CANCEL'=>409,
    'NOT_ACTIVE'=>409
  ];
  $code = $map[$e->getMessage()] ?? 500;
  json(['error'=>$e->getMessage()], $code);
}
