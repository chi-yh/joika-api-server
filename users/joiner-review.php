<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
session_start();

function out_json($status, $arr) {
    http_response_code($status);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out_json(405, ['code'=>'0001','msg'=>'Method Not Allowed']);
}

if (empty($_SESSION['member_id'])) {
    out_json(401, ['code'=>'0002','msg'=>'尚未登入']);
}

$mysqli = db();
// 編碼保險：舊機器有時不支援 utf8mb4
if (function_exists('mysqli_set_charset')) {
    if (!@mysqli_set_charset($mysqli, 'utf8mb4')) {
        @mysqli_set_charset($mysqli, 'utf8');
    }
}

/* ------------ 參數處理（避免 ??；轉型安全） ------------ */
$activityNo    = 0;
$participantId = 0;
$action        = '';

if (isset($_POST['activity_no']))    $activityNo    = (int)trim((string)$_POST['activity_no']);
if (isset($_POST['participant_id'])) $participantId = (int)trim((string)$_POST['participant_id']);
if (isset($_POST['action']))         $action        = trim((string)$_POST['action']);

$hostId = (int)$_SESSION['member_id'];

if ($activityNo === 0 || $participantId === 0 || $action === '') {
    out_json(400, ['code'=>'0005','msg'=>'缺少必要參數']);
}

/* ------------ 檢查是否主揪本人 ------------ */
$sql = "SELECT `HOST_MEMBER_ID` FROM `activity` WHERE `ACTIVITY_NO` = ?";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    out_json(500, ['code'=>'9998','msg'=>'Prepare failed','debug'=>$mysqli->error]);
}
$stmt->bind_param('i', $activityNo);
if (!$stmt->execute()) {
    $stmt->close();
    out_json(500, ['code'=>'9998','msg'=>'Execute failed','debug'=>$stmt->error]);
}
$stmt->bind_result($trueHostId);
$stmt->fetch();
$stmt->close();

$trueHostId = (int)$trueHostId;
if ($trueHostId === 0) {
    out_json(404, ['code'=>'0008','msg'=>'找不到活動']);
}

if ($trueHostId !== $hostId) {
    out_json(403, ['code'=>'0003','msg'=>'沒有權限操作']);
}

/* 防呆：避免對主揪本人操作（正常不會在 participant 出現） */
if ($participantId === $trueHostId) {
    out_json(400, ['code'=>'0006','msg'=>'不可操作主揪']);
}

/* ------------ 動作處理 ------------ */
if ($action === 'accept') {
    // 改為「已參加」
    $status = '已參加';
    $stmt2 = $mysqli->prepare("UPDATE `participant` SET `JOINER_STATUS`=? WHERE `ACTIVITY_NO`=? AND `PARTICIPANT_ID`=?");
    if (!$stmt2) out_json(500, ['code'=>'9999','msg'=>'Prepare failed','debug'=>$mysqli->error]);
    $stmt2->bind_param('sii', $status, $activityNo, $participantId);
    if (!$stmt2->execute()) {
        $err = $stmt2->error;
        $stmt2->close();
        out_json(500, ['code'=>'9999','msg'=>'更新失敗','debug'=>$err]);
    }
    $aff = $stmt2->affected_rows;
    $stmt2->close();
    if ($aff > 0) {
        out_json(200, ['code'=>'0000','msg'=>'success']);
    } else {
        out_json(404, ['code'=>'0007','msg'=>'找不到要更新的資料']);
    }

} elseif ($action === 'reject') {
    // 改為「已拒絕」（如果你的系統用別的字，調整這個字串即可）
    $status = '已拒絕';
    $stmt2 = $mysqli->prepare("UPDATE `participant` SET `JOINER_STATUS`=? WHERE `ACTIVITY_NO`=? AND `PARTICIPANT_ID`=?");
    if (!$stmt2) out_json(500, ['code'=>'9999','msg'=>'Prepare failed','debug'=>$mysqli->error]);
    $stmt2->bind_param('sii', $status, $activityNo, $participantId);
    if (!$stmt2->execute()) {
        $err = $stmt2->error;
        $stmt2->close();
        out_json(500, ['code'=>'9999','msg'=>'更新失敗','debug'=>$err]);
    }
    $aff = $stmt2->affected_rows;
    $stmt2->close();
    if ($aff > 0) {
        out_json(200, ['code'=>'0000','msg'=>'rejected']);
    } else {
        out_json(404, ['code'=>'0007','msg'=>'找不到要更新的資料']);
    }

} elseif ($action === 'delete') {
    // 刪掉這位參加者的紀錄
    $stmt2 = $mysqli->prepare("DELETE FROM `participant` WHERE `ACTIVITY_NO`=? AND `PARTICIPANT_ID`=? LIMIT 1");
    if (!$stmt2) out_json(500, ['code'=>'9998','msg'=>'Prepare failed','debug'=>$mysqli->error]);
    $stmt2->bind_param('ii', $activityNo, $participantId);
    if (!$stmt2->execute()) {
        $err = $stmt2->error;
        $stmt2->close();
        out_json(500, ['code'=>'9998','msg'=>'刪除失敗','debug'=>$err]);
    }
    $aff = $stmt2->affected_rows;
    $stmt2->close();
    if ($aff > 0) {
        out_json(200, ['code'=>'0000','msg'=>'deleted']);
    } else {
        out_json(404, ['code'=>'0007','msg'=>'找不到要刪除的資料']);
    }

} else {
    out_json(400, ['code'=>'0004','msg'=>'action 參數錯誤']);
}
