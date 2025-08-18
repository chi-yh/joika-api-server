<?php
  // 前台註冊
  // POST
  require_once __DIR__ . '/../config/db.php';
  header('Content-Type: application/json; charset=utf-8');

  // 設定 session cookie 參數
  session_set_cookie_params([
    'httponly' => true,
    'secure' => true,      // 如果網站是 HTTPS
    'samesite' => 'Strict' // 避免跨站請求偽造
  ]);
  session_start();         // 啟動session

  $db = db();

  // 只允許 POST
  if ($_SERVER["REQUEST_METHOD"] !== "POST") {
      http_response_code(405);
      echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
      exit;
  }

  // 取得前端傳送的 JSON
  $input = json_decode(file_get_contents("php://input"), true);

  // 檢查解析是否成功
  if ($input === null) {
      http_response_code(400);
      echo json_encode(["error" => "輸入格式錯誤，請傳送 JSON"], JSON_UNESCAPED_UNICODE);
      exit;
  }

  // step 判斷
  $step = $input["step"] ?? 1;

  if ($step == 1) {
    // === 第一步 暫存 email、手機、密碼 ===
    $memberEmail = $input["email"] ?? null;
    $memberPhone = $input["phone"] ?? null;
    $memberPassword = $input["password"] ?? null;
    
    // 檢查必填欄位是否有填寫
    if (!$memberEmail) {
      http_response_code(400);
      echo json_encode(["error" => "Email未填寫"], JSON_UNESCAPED_UNICODE);
      exit;
    }
  
    if (!$memberPhone) {
      http_response_code(400);
      echo json_encode(["error" => "手機號碼未填寫"], JSON_UNESCAPED_UNICODE);
      exit;
    }
  
    if (!$memberPassword) {
      http_response_code(400);
      echo json_encode(["error" => "密碼未填寫"], JSON_UNESCAPED_UNICODE);
      exit;
    }
  
    // 密碼儲存置資料庫前先加密
    $hashedPassword = password_hash($memberPassword, PASSWORD_DEFAULT);
  
    try {
      // 查詢手機號碼或email是否已註冊
      $sql = "SELECT member_email, member_phone FROM member WHERE member_email = ? OR member_phone = ?";
      $stmt = $db->prepare($sql);
      $stmt->bind_param("ss", $memberEmail, $memberPhone);
      $stmt->execute();
      $result = $stmt->get_result();
  
      $existEmail = false;
      $existPhone = false;
  
      while ($row = $result->fetch_assoc()) {
        if ($row["member_email"] === $memberEmail) {
          $existEmail = true;
        }
  
        if ($row["member_phone"] === $memberPhone) {
          $existPhone = true;
        }
      }
  
      if ($existEmail || $existPhone) {
        if ($existEmail) $message[] = "email 已註冊";
        if ($existPhone) $message[] = "手機號碼已註冊";
        
        echo json_encode([
          "exists" => true,
          "message" => implode("，", $message)
        ], JSON_UNESCAPED_UNICODE);
        exit;
      }
    } catch (mysqli_sql_exception $e) {
      http_response_code(500);
      echo json_encode(["error" => "資料庫錯誤", "message"=>$e->getMessage()]);
      exit;
    }
    
    // 產生 tmpId 並存在 session 中
    $tmpId = bin2hex(random_bytes(16));

    // 暫存第一步資料到 session
    $_SESSION['step1'] = [
      "tmp_id" => $tmpId, 
      "email" => $memberEmail, 
      "phone" => $memberPhone, 
      "password" => $hashedPassword
    ];
    echo json_encode(["success" => true, "tmp_id" => $tmpId]);
    exit;

  } elseif ($step == 2) {
    // 第二步 取得第一步資料 及 新增基本資料，最後再一起寫入資料庫中
    $tmpId = $input["tmp_id"] ?? null;
    if (!$tmpId || !isset($_SESSION["step1"]) || $_SESSION["step1"]["tmp_id"] != $tmpId) {
      echo json_encode(["error" => "找不到第一步資料"]);
      exit;
    }

    // 取出第一步的暫存資料
    $memberEmail = $_SESSION["step1"]["email"];
    $memberPhone = $_SESSION["step1"]["phone"];
    $hashedPassword = $_SESSION["step1"]["password"];
    
    // === 第二步 基本資料 ===
    $memberName = $input["name"] ?? null;
    $memberNickname = $input["nickname"] ?? null;
    $memberGender = $input["gender"] ?? "N";
    $memberBirthdate = $input["birthdate"] ?? null;
    $memberCity = $input["location"] ?? null;
    $memberOccupation = $input["occupation"] ?? null;
    $memberInterests = $input["interests"] ?? [];

    try {
      $sql = "INSERT INTO member (member_email, member_phone, member_password, member_name, member_nickname, member_gender, member_birthdate, member_city, member_occupation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
      $stmt = $db->prepare($sql);
      $stmt->bind_param("sssssssss", $memberEmail, $memberPhone, $hashedPassword, $memberName, $memberNickname, $memberGender, $memberBirthdate, $memberCity, $memberOccupation);
      $stmt->execute();
  
      // 取得此次新增至 member 資料表中所對應到的 member_id
      $memberId = $db->insert_id;
  
      // 新增會員興趣(代號)至 member_interest 資料表中
      if (!empty($memberInterests)) {
        $values = [];
        $types = "";
        $params = [];

        foreach($memberInterests as $interestNo) {
          $values[] = "(?, ?)";
          $types .= "ii";
          $params[] = $memberId;
          $params[] = $interestNo;
        }
        $sqlInterest = "INSERT INTO member_interest (MEMBER_ID, INTEREST_NO) VALUES " . implode(",", $values);
        $stmtInterest = $db->prepare($sqlInterest);
        $stmtInterest->bind_param($types, ...$params);
        $stmtInterest->execute();
      }
  
      // 完成後清除 session
      unset($_SESSION["step1"]);
  
      echo json_encode(["success" => true]);
    
    } catch (mysqli_sql_exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
  }
?>