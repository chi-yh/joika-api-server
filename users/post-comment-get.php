<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['code' => '0000', 'msg' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['code' => '0001', 'msg' => '尚未登入'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mysqli   = db();
$memberId = (int) $_SESSION['member_id'];

$sql = "
SELECT DISTINCT
    p.POST_NO,
    c.CATEGORY_NAME,
    p.POST_TITLE,
    p.CREATED_AT,
    p.POST_CONTENT,
    p.POST_IMG
FROM post AS p
JOIN post_comment AS pc ON pc.POST_NO = p.POST_NO
JOIN category AS c ON c.CATEGORY_NO = p.CATEGORY_NO
WHERE pc.MEMBER_ID = ?
  AND pc.COMMENT_STATUS = '顯示'
ORDER BY p.CREATED_AT DESC
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['code' => '9999', 'msg' => 'Prepare failed: ' . $mysqli->error], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param('i', $memberId);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['code' => '9999', 'msg' => 'Execute failed: ' . $stmt->error], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->store_result();

/** ⚠️ 這裡一定要跟 SELECT 順序對齊，數量也要一樣 */
$stmt->bind_result(
    $post_no,
    $category_name,
    $title,
    $created_at,
    $post_content,
    $post_img
);

$data = [];
while ($stmt->fetch()) {
    $data[] = [
        'POST_NO'       => $post_no,
        'CATEGORY_NAME' => $category_name,
        'POST_TITLE'    => $title,
        'CREATED_AT'    => $created_at,
        'POST_CONTENT'  => $post_content,
        'POST_IMG'      => $post_img,
    ];
}

echo json_encode(['code' => '0000', 'data' => $data], JSON_UNESCAPED_UNICODE);
