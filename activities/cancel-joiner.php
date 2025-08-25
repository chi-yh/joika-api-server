<?php
    #參團者取消活動-PATCH
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Taipei');

/* === CORS（配合前端 withCredentials）=== */
require_once __DIR__ . '/../config/cors.php';

/* === DB & Session === */
require_once __DIR__ . '/../config/db.php';
session_start();

/* === 小工具 === */
function json($data, int $code = 200) {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}
function body() {
  $raw = file_get_contents('php://input');
  $d = json_decode($raw, true);
  return is_array($d) ? $d : [];
}
function require_auth_user_id(): int {
  if (empty($_SESSION['user']['id'])) {
    json(['error' => true, 'message' => 'UNAUTHORIZED'], 401);
  }
  return (int)$_SESSION['user']['id'];
}

/* === Method / Preflight === */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') { json(['error'=>true,'message'=>'METHOD_NOT_ALLOWED'], 405); }

/* === 取得參數 ===
   支援 ?id= 與 JSON body: { "activity_no": 1, "reason_no": 3, "reason_desc": "臨時有事" } */
$input       = body();
$activityNo  = isset($_GET['id']) ? (int)$_GET['id'] : (int)($input['activity_no'] ?? 0);
$reasonNo    = array_key_exists('reason_no', $input)   ? (int)$input['reason_no']   : null; // 可為 null
$reasonDesc  = array_key_exists('reason_desc', $input) ? trim((string)$input['reason_desc']) : null; // 可為 null

if ($activityNo <= 0) json(['error'=>true,'message'=>'缺少或不合法的 activity_no'], 400);

$userId = require_auth_user_id();
$db = db();

/* === 讀取活動 === */
$sql = "SELECT 
          ACTIVITY_NO, ACTIVITY_STATUS, HOST_MEMBER_ID,
          REGISTRATION_DEADLINE, ACTIVITY_START_DATE
        FROM activity
        WHERE ACTIVITY_NO = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $activityNo);
$stmt->execute();

$stmt->bind_result(
  $ACTIVITY_NO,
  $ACTIVITY_STATUS,
  $HOST_MEMBER_ID,
  $REGISTRATION_DEADLINE,
  $ACTIVITY_START_DATE
);

$act = null;
if ($stmt->fetch()) {
  $act = [
    'ACTIVITY_NO'            => $ACTIVITY_NO,
    'ACTIVITY_STATUS'        => $ACTIVITY_STATUS,
    'HOST_MEMBER_ID'         => $HOST_MEMBER_ID,
    'REGISTRATION_DEADLINE'  => $REGISTRATION_DEADLINE,
    'ACTIVITY_START_DATE'    => $ACTIVITY_START_DATE,
  ];
}
$stmt->close();

if (!$act) json(['error'=>true,'message'=>'活動不存在'], 404);

/* === 規則檢查 === */
// 1) 主揪請用另一支 API
if ((int)$act['HOST_MEMBER_ID'] === $userId) {
  json(['error'=>true,'message'=>'主揪請使用主揪取消 API'], 403);
}

// 2) 活動狀態檢查
if (in_array($act['ACTIVITY_STATUS'], ['已取消','已完成'], true)) {
  json(['error'=>true,'message'=>'活動已取消或已完成，無法取消參加'], 400);
}

// 3) 開始前一天起不可取消
$now = new DateTime('now');
$startAt = !empty($act['ACTIVITY_START_DATE']) ? new DateTime($act['ACTIVITY_START_DATE']) : null;
if ($startAt) {
  $oneDayBefore = (clone $startAt)->modify('-1 day');
  if ($now >= $oneDayBefore) {
    json(['error'=>true,'message'=>'活動開始前一天起不可取消'], 400);
  }
}

/* === 確認是參與者，且尚未取消 ===
   依你的表：PK = (ACTIVITY_NO, PARTICIPANT_ID)
   取消時間欄位：JOINER_CANCEL_AT */
$sql = "SELECT 1
        FROM participant
        WHERE ACTIVITY_NO = ? AND PARTICIPANT_ID = ? AND (JOINER_CANCEL_AT IS NULL)";
$stmt = $db->prepare($sql);
$stmt->bind_param("ii", $activityNo, $userId);
$stmt->execute();

$stmt->bind_result($dummy);
$isJoiner = $stmt->fetch();

$stmt->close();

if (!$isJoiner) {
  json(['error'=>true,'message'=>'你未參加此活動或已取消，無法取消'], 403);
}

/* === 交易開始 === */
$db->begin_transaction();

try {
  // 1) 寫入 participant 的取消紀錄
  $sql = "UPDATE participant
          SET JOINER_CANCEL_REASON_NO = ?,
              JOINER_CANCEL_DESCRIPTION = ?,
              JOINER_CANCEL_AT = NOW()
          WHERE ACTIVITY_NO = ? AND PARTICIPANT_ID = ? AND JOINER_CANCEL_AT IS NULL";
  $stmt = $db->prepare($sql);

  // 允許 null
  if ($reasonDesc === '') $reasonDesc = null;
  // 綁定：i s i i
  $stmt->bind_param("isii", $reasonNo, $reasonDesc, $activityNo, $userId);
  $stmt->execute();
  $affected1 = $stmt->affected_rows;
  $stmt->close();

  if ($affected1 <= 0) {
    throw new Exception('取消失敗或已取消過');
  }

  // 2) activity.current_participant - 1（避免負數）
  $sql = "UPDATE activity
          SET CURRENT_PARTICIPANT = CASE 
            WHEN CURRENT_PARTICIPANT > 0 THEN CURRENT_PARTICIPANT - 1
            ELSE 0 END
          WHERE ACTIVITY_NO = ?";
  $stmt = $db->prepare($sql);
  $stmt->bind_param("i", $activityNo);
  $stmt->execute();
  $stmt->close();

  $db->commit();

  json([
    'ok' => true,
    'role' => 'joiner',
    'message' => '已取消參加',
    'activity_no' => $activityNo
  ]);

} catch (Throwable $e) {
  $db->rollback();
  json(['error'=>true,'message'=>'取消失敗：'.$e->getMessage()], 500);
}
?>