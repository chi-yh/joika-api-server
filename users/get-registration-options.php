<?php
  // 取得第二步的下拉式選單資料
  // GET
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
    $sql= "SELECT city_no AS value, city_name AS label FROM city ORDER BY city_no";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
  
    $cities = [];
    while ($row = $result->fetch_assoc()) {
      $cities[] = [
        "value" => intval($row["value"]),
        "label" => $row["label"]
      ];
    }

    // 取得職業資料
    $sql= "SELECT occupation_no AS value, occupation AS label FROM occupation ORDER BY occupation_no";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
  
    $occupations = [];
    while ($row = $result->fetch_assoc()) {
      $occupations[] = [
        "value" => intval($row["value"]),
        "label" => $row["label"]
      ];
    }

    // 取得興趣分類資料
    $sql= "SELECT category_no AS value, category_name AS label FROM category ORDER BY category_no";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
  
    $interests = [];
    while ($row = $result->fetch_assoc()) {
      $interests[] = [
        "value" => intval($row["value"]),
        "label" => $row["label"]
      ];
    }

    // 回傳所有選單的資料
    echo json_encode([
      "success" => "true",
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