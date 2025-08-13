<?php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
  
  $db_host = '127.0.0.1';
  $db_user = 'root';
  $db_password = 'root';
  $db_dbname = 'joika';
  $db_port = 3306;

  try {
    $mysqli = new mysqli(
      hostname: $db_host,
      username: $db_user,
      password: $db_password,
      database: $db_dbname,
      port: $db_port
    );
  } catch (mysqli_sql_exception $e) {
    echo '資料庫線線錯誤：' . $e->getMessage() . '<br>';
    exit();
  }
?>

<?php
  // 允許的域名列表
  $allowed_origins = [
    "http://127.0.0.1:5500",
    "http://localhost:5500"
  ];
  // 抓到請求的來源網域
  $origin = $_SERVER['HTTP_ORIGIN'] ?? ''; // http://localhost:5500 或 http://127.0.0.1:5500
  // 檢查來源是否在允許列表中
  if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $origin);
  }
  header("Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS");
?>

<?php
  header('Content-Type: application/json; charset=utf-8');
  // 要執行的sql指令
  $sql='SELECT * FROM activity';
  // 執行sql指令
  $result = $mysqli->query($sql);
  // $result 不能直接echo
  $data = $result->fetch_all(MYSQLI_ASSOC);
  // 轉為JSON格式
  $json_data = json_encode($data);
  echo $json_data;
?>