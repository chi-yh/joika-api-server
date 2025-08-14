<?php
// 開啟所有錯誤訊息
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<?php
    require_once __DIR__ . '/constant.php';

// 讓 mysqli 連線錯誤丟出例外，交由上層 try/catch
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * 取得資料庫連線（成功回傳 mysqli，失敗丟出例外）
 */
function db(): mysqli {
    // 判斷是否本機（避免依賴 REMOTE_ADDR，保留 SERVER_NAME 判斷）
    $isLocal =
        in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true) ||
        in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

    if ($isLocal) {
        $host = DB_HOST_LOCAL;
        $user = DB_USER_LOCAL;
        $pass = DB_PSW_LOCAL;
        $name = DB_NAME_LOCAL;
        $port = DB_PORT_LOCAL;
    } else {
        $host = DB_HOST;        // 你的常數目前是 '127.0.0.1'，在主機上通常也是對的
        $user = DB_USER;
        $pass = DB_PSW;
        $name = DB_NAME;
        $port = DB_PORT;
    }

    try {
        $mysqli = new mysqli(
        $db_host,
        $db_user,
        $db_password,
        $db_dbname,
        $db_port
    );
    echo '<h1 style="color: green;">連線成功。</h1>';
    echo "<hr>";
    echo '主機資訊：' . $mysqli->host_info;
    echo '<br>';
    echo 'MySQL 版本資訊：' . $mysqli->server_info;

    return $mysqli;
}
}