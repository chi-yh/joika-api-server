<?php
    require_once __DIR__ . '/../config/cors.php';
    require_once __DIR__ . '/../config/db.php';
    session_start();

    echo json_encode([
        "authenticated" => isset($_SESSION['user']),
        "user" => $_SESSION['user'] ?? null
    ]);
?>