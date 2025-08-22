<?php
/**
 * posts/delete.php
 * 刪除文章 API
 * - 預設軟刪：POST_STATUS='刪除'
 * - 硬刪：DELETE 資料列，順便嘗試刪除首圖檔案
 * 權限：
 * - 作者本人可刪（軟/硬）
 * - 管理員（$_SESSION['is_admin']=true）可刪任何文章
 */

session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$db = db();

/** 檔案實體存放路徑（硬刪時刪首圖） */
$UPLOAD_DIR_ABS = __DIR__ . '/../upload/article-img';

/** 僅允許 POST 或 DELETE */
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['POST', 'DELETE'], true)) {
    http_response_code(405);
    echo json_encode(['ok'=>false, 'error'=>'只允許 POST 或 DELETE 請求'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** 必須已登入 */
if (empty($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false, 'error'=>'請先登入會員'], JSON_UNESCAPED_UNICODE);
    exit;
}
$login_member_id = (int)$_SESSION['member_id'];
$is_admin = !empty($_SESSION['is_admin']); // 若有後台角色，用這個判斷

/** 讀取輸入參數（支援 form-data / x-www-form-urlencoded / JSON / query） */
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
$soft    = (int) inparam('soft', 1);   // 1=軟刪(預設), 0=硬刪

if ($post_no <= 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'缺少或不合法的 post_no'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** 取文章，驗證存在與擁有者 */
$stmt = $db->prepare("SELECT POST_NO, MEMBER_ID, POST_IMG, POST_STATUS FROM post WHERE POST_NO=? LIMIT 1");
$stmt->bind_param('i', $post_no);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo json_encode(['ok'=>false, 'error'=>'找不到文章'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** 權限檢查：作者本人或管理員 */
$owner_id = (int)$row['MEMBER_ID'];
if ($owner_id !== $login_member_id && !$is_admin) {
    http_response_code(403);
    echo json_encode(['ok'=>false, 'error'=>'沒有刪除權限'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** 軟刪：POST_STATUS='刪除' */
if ($soft === 1) {
    if ($is_admin) {
        // 管理員可刪任何文章
        $stmt = $db->prepare("UPDATE post SET POST_STATUS='刪除' WHERE POST_NO=?");
        $stmt->bind_param('i', $post_no);
    } else {
        // 作者本人：帶入 MEMBER_ID 避免誤刪他人文章
        $stmt = $db->prepare("UPDATE post SET POST_STATUS='刪除' WHERE POST_NO=? AND MEMBER_ID=?");
        $stmt->bind_param('ii', $post_no, $login_member_id);
    }

    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $err = $stmt->error;
    $stmt->close();

    if (!$ok || $affected < 1) {
        http_response_code(500);
        echo json_encode(['ok'=>false, 'error'=>'軟刪失敗或無變更', 'details'=>$err], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok'=>true, 'deleted'=>'soft', 'POST_NO'=>$post_no], JSON_UNESCAPED_UNICODE);
    $db->close();
    exit;
}

/** 硬刪：DELETE + 刪首圖檔案（若存在） */
$db->begin_transaction();
try {
    // 先刪資料列
    if ($is_admin) {
        $stmt = $db->prepare("DELETE FROM post WHERE POST_NO=?");
        $stmt->bind_param('i', $post_no);
    } else {
        $stmt = $db->prepare("DELETE FROM post WHERE POST_NO=? AND MEMBER_ID=?");
        $stmt->bind_param('ii', $post_no, $login_member_id);
    }
    $okDel = $stmt->execute();
    $affected = $stmt->affected_rows;
    $errDel = $stmt->error;
    $stmt->close();

    if (!$okDel || $affected < 1) {
        throw new RuntimeException($errDel ?: '硬刪失敗或沒有權限刪除此文章');
    }

    // 嘗試刪除首圖檔案（安全起見只用檔名）
    if (!empty($row['POST_IMG'])) {
        $basename = basename($row['POST_IMG']);
        $absPath  = rtrim($UPLOAD_DIR_ABS, '/\\') . DIRECTORY_SEPARATOR . $basename;
        if (is_file($absPath)) {
            @unlink($absPath); // 刪檔失敗不致命
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
