<?php
require_once __DIR__ . '/constant.php';

// 讓 mysqli 連線錯誤丟出例外
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * 取得資料庫連線（成功回傳 mysqli，失敗結束程式）
 */
function db(): mysqli
{
    // 判斷是否本機
    $serverName = $_SERVER['SERVER_NAME'] ?? '';
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    $isLocal = in_array($serverName, ['localhost', '127.0.0.1'], true)
            || in_array($remoteAddr, ['127.0.0.1', '::1'], true);

    if ($isLocal) {
        $host = DB_HOST_LOCAL;
        $user = DB_USER_LOCAL;
        $pass = DB_PSW_LOCAL;
        $name = DB_NAME_LOCAL;
        $port = DB_PORT_LOCAL;
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    } else {
        $host = DB_HOST;
        $user = DB_USER;
        $pass = DB_PSW;
        $name = DB_NAME;
        $port = DB_PORT;
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        error_reporting(E_ALL);
    }

    try {
        $mysqli = new mysqli($host, $user, $pass, $name, (int)$port);
        // 推薦設定字元集
        $mysqli->set_charset('utf8mb4');

        // 如需本機顯示連線資訊可解除註解
        // if ($isLocal) {
        //     echo '<h1 style="color:green">連線成功</h1><hr>';
        //     echo '主機資訊：' . $mysqli->host_info . '<br>';
        //     echo 'MySQL 版本：' . $mysqli->server_info . '<br>';
        // }

        return $mysqli;
    } catch (mysqli_sql_exception $e) {
        // 寫到伺服器 error log（不要把細節曝露給使用者）
        error_log('DB connect error: ' . $e->getMessage());

        http_response_code(500);
        exit('資料庫連線失敗，請稍後再試。');
    }
}
