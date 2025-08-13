<?php
    require_once __DIR__ . '/constant.php';

    // 讓 mysqli 連線錯誤丟出例外，才會被 try/catch 捕捉
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // 判斷是否本機：檢查 SERVER_NAME / REMOTE_ADDR（都可能不存在，所以用 ??）
    $isLocal =
        in_array($_SERVER['SERVER_NAME']  ?? '', ['localhost', '127.0.0.1'], true) ||
        in_array($_SERVER['REMOTE_ADDR']  ?? '', ['127.0.0.1', '::1'], true);

    if($isLocal){ // local
        
        $db_host = DB_HOST_LOCAL;
        $db_user = DB_USER_LOCAL;
        $db_password = DB_PSW_LOCAL;
        $db_dbname = DB_NAME_LOCAL;
        $db_port = DB_PORT_LOCAL;
    }else{ // remote

        $db_host = DB_HOST;
        $db_user = DB_USER;
        $db_password = DB_PSW;
        $db_dbname = DB_NAME;
        $db_port = DB_PORT;
    }
    

    try {
        $mysqli = new mysqli(
        $db_host,
        $db_user,
        $db_password,
        $db_dbname,
        $db_port
    );
    // echo '<h1 style="color: green;">連線成功。</h1>';
    // echo "<hr>";
    // echo '主機資訊：' . $mysqli->host_info;
    // echo '<br>';
    // echo 'MySQL 版本資訊：' . $mysqli->server_info;

    // $mysqli->close(); // 關閉資料庫連線

  } catch (mysqli_sql_exception $e) { // 如果 try 區塊裡的程式有錯，就會執行到這裡的 catch

    echo '<h1 style="color: red;">連線失敗。</h1>';
    echo "<hr>";
    echo '錯誤代碼：' . $e->getCode() . '<br>';
    echo '錯誤訊息：' . $e->getMessage() . '<br>';

    }
    
?>