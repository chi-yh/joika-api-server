<?php
// JOIKA_PHP/cors.php

$allowed_origins = [
  'http://localhost:5173',
  'http://127.0.0.1:5173',
  'http://localhost:5174',
  'http://127.0.0.1:5174',
  'http://localhost:5175',
  'http://127.0.0.1:5175',
  'http://localhost:5176',
  'http://127.0.0.1:5176',
  'http://localhost:5177',
  'http://127.0.0.1:5177',
  'http://localhost:5178',
  'http://127.0.0.1:5178',
  'http://localhost:5179',
  'http://127.0.0.1:5179',
  'http://localhost:5180',
  'http://127.0.0.1:5180',
  'http://localhost:5181',
  'http://127.0.0.1:5181',
  'http://localhost:5182',
  'http://127.0.0.1:5182'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins, true)) {
  header("Access-Control-Allow-Origin: $origin");
  header('Vary: Origin');
  header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
header('Content-Type: application/json; charset=UTF-8');

// 測試cors.php有沒有執行，後續可以拔掉
header('X-Debug-CORS: hit');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}
