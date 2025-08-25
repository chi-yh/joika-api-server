<?php
  // 取得第二步的下拉式選單資料
  // GET
  require_once __DIR__ . '/../config/cors.php';
  require_once __DIR__ . '/../config/db.php';
  header('Content-Type: application/json; charset=utf-8');
  $db = db();

  // 只允許 GET
  if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
    exit;
  }

  try {
    // 取得縣市資料
    $sql= "SELECT CITY_NO AS value, CITY_NAME AS label FROM city ORDER BY CITY_NO";
    $result = $db->query($sql);
  
    $cities = [];
    while ($row = $result->fetch_assoc()) {
      $cities[] = [
        "value" => intval($row["value"]),
        "label" => $row["label"]
      ];
    }

    // 取得職業資料
    $sql= "SELECT OCCUPATION_NO AS value, OCCUPATION AS label FROM occupation ORDER BY OCCUPATION_NO";
    $result = $db->query($sql);
  
    $occupations = [];
    while ($row = $result->fetch_assoc()) {
      $occupations[] = [
        "value" => intval($row["value"]),
        "label" => $row["label"]
      ];
    }

    // 取得興趣分類資料
    $sql= "SELECT CATEGORY_NO AS value, CATEGORY_NAME AS label FROM category ORDER BY CATEGORY_NO";
    $result = $db->query($sql);
  
    $interests = [];
    $colors = ["#6DE1D2", "#FFD63A", "#FFD63A", "#FF8C86", "#FFA955", "#6DE1D2", "#77BEF0", "#77BEF0", "#FF8C86", "#FFA955", "#6DE1D2", "#77BEF0", "#969696"];
    $index = 0;
    while ($row = $result->fetch_assoc()) {
      $interests[] = [
        "value" => intval($row["value"]),
        "label" => $row["label"],
        "color" => $colors[$index % count($colors)]
      ];
      $index++;
    }

    // 回傳所有選單的資料
    echo json_encode([
      "success" => true,
      "data" => [
        "cities" => $cities,
        "occupations" => $occupations,
        "interests" => $interests
      ]
    ], JSON_UNESCAPED_UNICODE);

  } catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "資料庫錯誤"], JSON_UNESCAPED_UNICODE);
  }
?>