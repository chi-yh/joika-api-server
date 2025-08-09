<?php
/*
// config/db.php
declare(strict_types=1);

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists(Dotenv\Dotenv::class)) {
        Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
    }
}

$DB_HOST = $_ENV['DB_HOST'] ?? '127.0.0.1';
$DB_PORT = $_ENV['DB_PORT'] ?? '8889';           // MAMP 預設常是 8889
$DB_NAME = $_ENV['DB_NAME'] ?? 'my_db';
$DB_USER = $_ENV['DB_USER'] ?? 'root';
$DB_PASS = $_ENV['DB_PASS'] ?? 'root';
$DB_CHAR = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

$dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset={$DB_CHAR}";

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    } catch (PDOException $e) {
    $isProd = (($_ENV['APP_ENV'] ?? 'production') === 'production');
    http_response_code(500);
    exit($isProd ? 'Database connection failed' : $e->getMessage());
}
?>
//老師教的寫法
<?php
    $db_host = '127.0.0.1';
    $db_user = 'root';
    $db_password = 'root';
    $db_dbname = 'my_db';
    $db_port = 8889;

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

    $mysqli->close(); // 關閉資料庫連線

  } catch (mysqli_sql_exception $e) { // 如果 try 區塊裡的程式有錯，就會執行到這裡的 catch

    echo '<h1 style="color: red;">連線失敗。</h1>';
    echo "<hr>";
    echo '錯誤代碼：' . $e->getCode() . '<br>';
    echo '錯誤訊息：' . $e->getMessage() . '<br>';

    }
    */
?>