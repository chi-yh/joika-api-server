<?php
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$db = db();
$UPLOAD_DIR_ABS = __DIR__ . '/../upload/article-img';

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['POST', 'DELETE'], true)) {
    http_response_code(405);
    echo json_encode(['ok'=>false, 'error'=>'只允許 POST 或 DELETE 請求'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false, 'error'=>'請先登入會員'], JSON_UNESCAPED_UNICODE);
    exit;
}
$login_member_id = (int)$_SESSION['member_id'];
$is_admin = !empty($_SESSION['is_admin']);

function inparam($key, $default=null) {
    static $json = null;
    if ($json === null) {
        $raw = file_get_contents('php://input');
        $tmp = json_decode($raw, true);
        $json = is_array($tmp) ? $tmp : [];
    }
    return $_POST[$key] ?? $_GET[$key] ?? $json[$key] ?? $default;
}

$post_no = (int) inparam('post_no', 0);
$soft    = (int) inparam('soft', 1);

if ($post_no <= 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'缺少或不合法的 post_no'], JSON_UNESCAPED_UNICODE);
    exit;
}

<<<<<<< HEAD
/** 取文章 */
$sql = "SELECT POST_NO, MEMBER_ID, POST_IMG, POST_STATUS 
        FROM post 
        WHERE POST_NO = $post_no 
        LIMIT 1";
$result = $db->query($sql);
$row = $result ? $result->fetch_assoc() : null;
=======
/** 取文章，驗證存在與擁有者 */

$stmt = $db->prepare("SELECT POST_NO, MEMBER_ID, POST_IMG, POST_STATUS FROM post WHERE POST_NO=? LIMIT 1");
$stmt->bind_param('i', $post_no);
$stmt->execute();
$stmt->bind_result($f_post_no, $f_member_id, $f_post_img, $f_post_status);
if ($stmt->fetch()) {
    $row = [
        'POST_NO' => $f_post_no,
        'MEMBER_ID' => $f_member_id,
        'POST_IMG' => $f_post_img,
        'POST_STATUS' => $f_post_status
    ];
} else {
    $row = false;
}
$stmt->close();
>>>>>>> feature/zz

if (!$row) {
    http_response_code(404);
    echo json_encode(['ok'=>false, 'error'=>'找不到文章'], JSON_UNESCAPED_UNICODE);
    exit;
}

$owner_id = (int)$row['MEMBER_ID'];
if ($owner_id !== $login_member_id && !$is_admin) {
    http_response_code(403);
    echo json_encode(['ok'=>false, 'error'=>'沒有刪除權限'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** 軟刪 */
if ($soft === 1) {
    if ($is_admin) {
        $sql = "UPDATE post SET POST_STATUS='刪除' WHERE POST_NO=$post_no";
    } else {
        $sql = "UPDATE post SET POST_STATUS='刪除' 
                WHERE POST_NO=$post_no AND MEMBER_ID=$login_member_id";
    }

    $result = $db->query($sql);
    if (!$result || $db->affected_rows < 1) {
        http_response_code(500);
        echo json_encode(['ok'=>false, 'error'=>'軟刪失敗或無變更'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok'=>true, 'deleted'=>'soft', 'POST_NO'=>$post_no], JSON_UNESCAPED_UNICODE);
    $db->close();
    exit;
}

/** 硬刪 */
$db->begin_transaction();
try {
    if ($is_admin) {
        $sql = "DELETE FROM post WHERE POST_NO=$post_no";
    } else {
        $sql = "DELETE FROM post WHERE POST_NO=$post_no AND MEMBER_ID=$login_member_id";
    }
    $result = $db->query($sql);
    if (!$result || $db->affected_rows < 1) {
        throw new RuntimeException('硬刪失敗或沒有權限刪除此文章');
    }

    if (!empty($row['POST_IMG'])) {
        $basename = basename($row['POST_IMG']);
        $absPath  = rtrim($UPLOAD_DIR_ABS, '/\\') . DIRECTORY_SEPARATOR . $basename;
        if (is_file($absPath)) {
            @unlink($absPath);
        }
    }

    $db->commit();
    echo json_encode(['ok'=>true, 'deleted'=>'hard', 'POST_NO'=>$post_no], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'硬刪失敗', 'details'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
} finally {
    $db->close();
}
