<?php
    require_once __DIR__ . '/../config/cors.php';
    require_once __DIR__ . '/../config/db.php';

    session_start();
    
    $_SESSION = [];
    session_destroy();
    echo json_encode(["ok"=>true]);
?>