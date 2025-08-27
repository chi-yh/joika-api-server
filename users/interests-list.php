<?php
// api/member-interest-tags.php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
session_start();

function json_out($status, $arr) {
    http_response_code($status);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_out(405, ['code'=>'0001','msg'=>'Method Not Allowed']);
    }

    // 會員來源：優先 querystring，其次 session（避免使用 ??）
    $memberId = 0;
    if (isset($_GET['member_id'])) {
        $memberId = (int) $_GET['member_id'];
    } elseif (isset($_SESSION['member_id'])) {
        $memberId = (int) $_SESSION['member_id'];
    }

    if (!$memberId) {
        json_out(400, ['code'=>'0002','msg'=>'缺少 member_id']);
    }

    $mysqli = db();

    // 老伺服器常見編碼問題：先試 utf8mb4，不行就退回 utf8
    if (function_exists('mysqli_set_charset')) {
        if (!@mysqli_set_charset($mysqli, 'utf8mb4')) {
            @mysqli_set_charset($mysqli, 'utf8');
        }
    }

    // ⚠️ 表名改為小寫並加反引號，避免 Linux 上大小寫問題
    $sql = "
        SELECT 
            c.CATEGORY_NO    AS id,
            c.CATEGORY_NAME  AS name,
            c.CATEGORY_COLOR AS color
        FROM `member_interest` mi
        JOIN `category` c ON c.CATEGORY_NO = mi.INTEREST_NO
        WHERE mi.MEMBER_ID = ?
        GROUP BY c.CATEGORY_NO, c.CATEGORY_NAME, c.CATEGORY_COLOR
        ORDER BY c.CATEGORY_NO
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $mysqli->error);
    }

    if (!$stmt->bind_param('i', $memberId)) {
        throw new Exception('Bind param failed: ' . $stmt->error);
    }

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    // 不用 get_result()，用 bind_result 逐列抓
    if (!$stmt->bind_result($id, $name, $color)) {
        throw new Exception('Bind result failed: ' . $stmt->error);
    }

    $rows = array();
    while ($stmt->fetch()) {
        $rows[] = array(
            'id'    => $id,
            'name'  => $name,
            'color' => $color,
        );
    }

    $stmt->close();

    json_out(200, ['code'=>'0000','msg'=>'success','data'=>$rows]);

} catch (Exception $e) {
    json_out(500, ['code'=>'9999','msg'=>'Server Error','debug'=>$e->getMessage()]);
}
