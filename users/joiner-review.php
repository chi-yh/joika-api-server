<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$activityNo    = $_POST['activity_no']    ?? '';
$participantId = $_POST['participant_id'] ?? '';
$action        = $_POST['action']         ?? ''; // accept | reject | delete
$hostId        = $_SESSION['member_id'];

if ($activityNo === '' || $participantId === '' || $action === '') {
    http_response_code(400);
    echo json_encode(['code'=>'0005','msg'=>'缺少必要參數'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 檢查是不是主揪本人
$sql = "SELECT HOST_MEMBER_ID FROM ACTIVITY WHERE ACTIVITY_NO = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $activityNo);
$stmt->execute();
$stmt->bind_result($trueHostId);
$stmt->fetch();
$stmt->close();

if ($trueHostId !== $hostId) {
    http_response_code(403);
    echo json_encode(['code'=>'0003','msg'=>'沒有權限操作'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 防呆：避免刪到主揪本人（正常也不會在 PARTICIPANT 裡）
if ($participantId == $trueHostId) {
    http_response_code(400);
    echo json_encode(['code'=>'0006','msg'=>'不可操作主揪'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'accept') {
    // 將申請者改成「已參加」
    $status = '已參加';
    $stmt2 = $mysqli->prepare("UPDATE PARTICIPANT SET JOINER_STATUS=? WHERE ACTIVITY_NO=? AND PARTICIPANT_ID=?");
    $stmt2->bind_param('sss', $status, $activityNo, $participantId);

    if ($stmt2->execute()) {
        echo json_encode(['code'=>'0000','msg'=>'success'], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['code'=>'9999','msg'=>'更新失敗: '.$mysqli->error], JSON_UNESCAPED_UNICODE);
    }
    $stmt2->close();

} elseif ($action === 'delete') {
    // 直接刪掉這位參加者的那一筆申請/參加紀錄
    $stmt2 = $mysqli->prepare("DELETE FROM PARTICIPANT WHERE ACTIVITY_NO=? AND PARTICIPANT_ID=? LIMIT 1");
    $stmt2->bind_param('ss', $activityNo, $participantId);

    if ($stmt2->execute()) {
        if ($stmt2->affected_rows > 0) {
            echo json_encode(['code'=>'0000','msg'=>'deleted'], JSON_UNESCAPED_UNICODE);
        } else {
            // 沒有符合條件的資料
            http_response_code(404);
            echo json_encode(['code'=>'0007','msg'=>'找不到要刪除的資料'], JSON_UNESCAPED_UNICODE);
        }
    } else {
        http_response_code(500);
        echo json_encode(['code'=>'9998','msg'=>'刪除失敗: '.$mysqli->error], JSON_UNESCAPED_UNICODE);
    }
    $stmt2->close();

} else {
    http_response_code(400);
    echo json_encode(['code'=>'0004','msg'=>'action 參數錯誤'], JSON_UNESCAPED_UNICODE);
    exit;
}
