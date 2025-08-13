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
        header("Access-Control-Allow-Origin: " . $origin); //修改表頭設定
    }
    header("Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS");
?>