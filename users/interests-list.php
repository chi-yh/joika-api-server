<?php
// api/member-interest-tags.php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
session_start();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['code'=>'0001','msg'=>'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 會員來源：優先用 querystring，其次用 session
    $memberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : (int)($_SESSION['member_id'] ?? 0);
    if (!$memberId) {
        http_response_code(400);
        echo json_encode(['code'=>'0002','msg'=>'缺少 member_id'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $mysqli = db();

    $sql = "
        SELECT 
            c.CATEGORY_NO    AS id,
            c.CATEGORY_NAME  AS name,
            c.CATEGORY_COLOR AS color
        FROM MEMBER_INTEREST mi
        JOIN CATEGORY c ON c.CATEGORY_NO = mi.INTEREST_NO
        WHERE mi.MEMBER_ID = ?
        GROUP BY c.CATEGORY_NO, c.CATEGORY_NAME, c.CATEGORY_COLOR
        ORDER BY c.CATEGORY_NO
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception($mysqli->error);

    $stmt->bind_param('i', $memberId);
    $stmt->execute();
    $res   = $stmt->get_result();
    $rows  = $res->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['code'=>'0000','msg'=>'success','data'=>$rows], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['code'=>'9999','msg'=>'Server Error','debug'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
