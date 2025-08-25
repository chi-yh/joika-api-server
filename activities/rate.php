<?php
# 團主與團員評分
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
session_start();

function json_out($d,$c=200){ http_response_code($c); echo json_encode($d,JSON_UNESCAPED_UNICODE); exit; }
function me_id(){ return $_SESSION['user']['id'] ?? null; }
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error'=>'Method not allowed'], 405);

$payload = json_decode(file_get_contents('php://input'), true);
$actNo   = (int)($payload['activity_no'] ?? 0);
$items   = $payload['items'] ?? [];
$me      = me_id();

if (!$me)         json_out(['error'=>'未登入'], 401);
if (!$actNo)      json_out(['error'=>'缺少 activity_no'], 400);
if (!is_array($items) || !count($items)) json_out(['error'=>'沒有評分項目'], 400);

$db = db();

/* 1) 取活動 + 判斷 7 天內 */
$sql = "SELECT ACTIVITY_STATUS, ACTIVITY_END_DATE, HOST_MEMBER_ID
        FROM activity WHERE ACTIVITY_NO = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $actNo);
$stmt->execute();
$stmt->bind_result($ACTIVITY_STATUS, $ACTIVITY_END_DATE, $HOST_MEMBER_ID);
$act = null;
if ($stmt->fetch()) {
  $act = [
    'ACTIVITY_STATUS'  => $ACTIVITY_STATUS,
    'ACTIVITY_END_DATE'=> $ACTIVITY_END_DATE,
    'HOST_MEMBER_ID'   => $HOST_MEMBER_ID,
  ];
}
$stmt->close();

if (!$act) json_out(['error'=>'活動不存在'], 404);
if ($act['ACTIVITY_STATUS'] !== '已完成') json_out(['error'=>'活動尚未完成，不能評分'], 400);

$finishRef = $act['ACTIVITY_END_DATE'];
if (!$finishRef) json_out(['error'=>'缺少完成時間，無法判定是否逾期'], 400);
$deadline = (new DateTime($finishRef))->modify('+7 days');
if (new DateTime('now') > $deadline) json_out(['error'=>'超過評分期限（完成後7天）'], 400);

/* 2) 判斷我是不是主揪或團員 */
$isHost = ((int)$act['HOST_MEMBER_ID'] === (int)$me);

$partIds = [];
$stmt = $db->prepare("SELECT PARTICIPANT_ID FROM participant WHERE ACTIVITY_NO = ? AND JOINER_CANCEL_AT IS NULL");
$stmt->bind_param("i", $actNo);
$stmt->execute();
$stmt->bind_result($PID);
while ($stmt->fetch()) {
  $partIds[] = (int)$PID;
}
$stmt->close();

$isJoiner = in_array((int)$me, $partIds, true);
if (!$isHost && !$isJoiner) json_out(['error'=>'你不是此活動主揪/團員，不能評分'], 403);

/* 3) 逐項驗證與插入 */
$db->begin_transaction();
try {
  $ins = $db->prepare("INSERT INTO rating (ACTIVITY_NO, RATER_ID, RATEE_ID, RATEE_ROLE, RATING_SCORE, RATING_DATE)
                       VALUES (?, ?, ?, ?, ?, NOW())");

  foreach ($items as $i) {
    $rateeId   = (int)($i['ratee_id'] ?? 0);
    $rateeRole = $i['ratee_role'] ?? '';
    $score     = (int)($i['rating_score'] ?? 0);

    if (!$rateeId || !in_array($rateeRole, ['主揪','參與者'], true)) continue;
    if ($score < 1 || $score > 5) continue;

    // 身分規則
    if ($isHost) {
      // 主揪只能評「團員」
      if ($rateeRole !== '參與者' || !in_array($rateeId, $partIds, true)) continue;
    } else {
      // 團員可以評「主揪」或「其他團員（不得評自己）」
      if ($rateeRole === '主揪') {
        if ($rateeId !== (int)$act['HOST_MEMBER_ID']) continue;
      } else { // 參與者
        if ($rateeId === (int)$me) continue; // 不能評自己
        if (!in_array($rateeId, $partIds, true)) continue;
      }
    }

    // 嘗試插入（唯一鍵會擋第二次）
    try {
      $ins->bind_param("iiisi", $actNo, $me, $rateeId, $rateeRole, $score);
      $ins->execute();
    } catch (mysqli_sql_exception $e) {
      if ($e->getCode() == 1062) {
        throw new Exception("你已經評過此對象（ratee_id=$rateeId, role=$rateeRole）");
      }
      throw $e;
    }
  }

  $db->commit();
  json_out(['message'=>'評分完成']);
} catch (Throwable $e) {
  $db->rollback();
  json_out(['error'=>$e->getMessage()], 400);
}