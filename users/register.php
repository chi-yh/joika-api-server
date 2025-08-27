<?php
  // 前台註冊
  // POST
  require_once __DIR__ . '/../config/cors.php';
  require_once __DIR__ . '/../config/db.php';
  header('Content-Type: application/json; charset=utf-8');

  // 設定 session cookie 參數
  session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']), // 確保在HTTP和HTTPS環境下都能正常使用session
    'samesite' => 'Strict'                // 避免跨站請求偽造
  ]);
  session_start();                        // 啟動session

  $db = db();

  // 只允許 POST
  if ($_SERVER["REQUEST_METHOD"] !== "POST") {
      http_response_code(405);
      echo json_encode(["error" => "不支援的請求方法"], JSON_UNESCAPED_UNICODE);
      exit;
  }

  // 判斷是 JSON 還是 FormData
  $contentType = $_SERVER["CONTENT_TYPE"] ?? '';
  $isJson = strpos($contentType, 'application/json') !== false;

  if ($isJson) {
    // Step 1 - JSON 資料
    $input = json_decode(file_get_contents("php://input"), true);

    // 檢查解析是否成功
    if ($input === null) {
        http_response_code(400);
        echo json_encode(["error" => "輸入格式錯誤，請傳送 JSON"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // step 判斷
    $step = $input["step"] ?? 1;
  } else {
    // Step 2 - FormData
    $step = $_POST["step"] ?? 1;
  }

  if ($step == 1) {
    // === 第一步 暫存 email、手機、密碼 ===
    $memberEmail = $input["email"] ?? null;
    $memberPhone = $input["phone"] ?? null;
    $memberPassword = $input["password"] ?? null; // 長度 6 ~ 12 字元
    
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
    } elseif ((strlen($memberPassword) < 6 || strlen($memberPassword) > 12)) {
      http_response_code(400);
      echo json_encode(["error" => "密碼長度需介於 6 ~ 12 字元"], JSON_UNESCAPED_UNICODE);
    }
  
    try {
      // 檢查手機號碼或email是否已註冊
      $sql = "SELECT MEMBER_EMAIL, MEMBER_PHONE FROM member WHERE MEMBER_EMAIL = ? OR MEMBER_PHONE = ?";
      $stmt = $db->prepare($sql);
      $stmt->bind_param("ss", $memberEmail, $memberPhone);
      $stmt->execute();
      $stmt->store_result();
      $stmt->bind_result($email, $phone);
      $errors = [];

      while ($stmt->fetch()) {
        if ($email === $memberEmail) $errors["email"] = "此信箱已被註冊";
        if ($phone === $memberPhone) $errors["phone"] = "此手機號碼已被註冊";
      }
  
      if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(["success" => false, "errors" => $errors], JSON_UNESCAPED_UNICODE);
        exit;
      }

    } catch (mysqli_sql_exception $e) {
      error_log("Database error in step 1: " . $e->getMessage());
      http_response_code(500);
      echo json_encode([
        "success" => false,
        "error" => "系統錯誤，請稍後再試"
      ], JSON_UNESCAPED_UNICODE);
      exit;
    }
    
    // 密碼儲存置資料庫前先加密 (產生 60 字元的 hash)
    $hashedPassword = password_hash($memberPassword, PASSWORD_DEFAULT);

    // 產生 session ID
    $tmpId = bin2hex(random_bytes(16));

    // 暫存第一步資料到 session
    $_SESSION['step1'] = [
      "tmp_id" => $tmpId, 
      "email" => $memberEmail, 
      "phone" => $memberPhone, 
      "password" => $hashedPassword
    ];

    echo json_encode(["success" => true, "tmp_id" => $tmpId], JSON_UNESCAPED_UNICODE);
    exit;

  } elseif ($step == 2) {
    // 第二步 驗證基本資料並完成註冊
    // error_log("POST data: " . print_r($_POST, true)); // 診斷用，可以保留或註解掉
    // error_log("FILES data: " . print_r($_FILES, true)); // 診斷用，可以保留或註解掉

    // 檢查 session
    $tmpId = $_POST["tmp_id"] ?? null;

    if (!$tmpId || !isset($_SESSION["step1"]) || $_SESSION["step1"]["tmp_id"] != $tmpId) {
      http_response_code(400);
      echo json_encode([
        "success" => false, 
        "error" => "連線逾時，請重新開始註冊"
      ], JSON_UNESCAPED_UNICODE);
      exit;
    }

    // 取出第一步的暫存資料
    $memberEmail = $_SESSION["step1"]["email"];
    $memberPhone = $_SESSION["step1"]["phone"];
    $hashedPassword = $_SESSION["step1"]["password"];

    // 第二步要填寫的資料
    // $tmpId = $_POST["tmp_id"] ?? null; // 已檢查過，可註解
    $memberName = $_POST["name"] ?? null;
    $memberNickname = $_POST["nickname"] ?? null;
    $memberGender = $_POST["gender"] ?? "N";
    $memberBirthdate = $_POST["birthdate"] ?? null;
    $memberCity = $_POST["location"] ?? null;
    $memberOccupation = $_POST["occupation"] ?? null;
    $memberInterests = $_POST["interests"] ?? [];
    if (!is_array($memberInterests)) $memberInterests = [$memberInterests]; // 只有一個興趣時也轉成陣列

    $errors = [];

    // 驗證姓名
    if (empty($memberName)) {
      $errors["name"] = "請輸入姓名";
    } elseif (mb_strlen($memberName) < 2 || mb_strlen($memberName) > 10) {
      $errors["name"] = "姓名長度需在 2 ~ 10 個字元之間";
    } elseif (!preg_match("/^[\x{4e00}-\x{9fa5}a-zA-Z\s]+$/u", $memberName)) {
      $errors["name"] = "姓名只能包含中文、英文字母";
    }

    // 暱稱驗證
    if (empty($memberNickname)) {
      $errors["nickname"] = "請輸入暱稱";
    } elseif (mb_strlen($memberNickname) < 1 || mb_strlen($memberNickname) > 10) {
      $errors["nickname"] = "暱稱長度需在 1 ~ 10 個字元之間";
    }

    // 驗證性別
    $validGenders = ["M", "F", "O", "N"];
    if (!in_array($memberGender, $validGenders)) {
      $errors["gender"] = "無效的性別選項";
    }

    // 驗證生日
    if (empty($memberBirthdate)) {
      $errors["birthdate"] = "請選擇生日";
    } else {
      $date = DateTime::createFromFormat("Y-m-d", $memberBirthdate);
      if (!$date || $date->format("Y-m-d") !== $memberBirthdate) {
        $errors["birthdate"] = "生日格式錯誤";
      } else {
        $today = new DateTime();
        if ($date > $today) {
          $errors["birthdate"] = "生日不能晚於今天";
        } else {
          $age = $today->diff($date)->y;
          if ($age < 18) {
            $errors["birthdate"] = "您必須年滿18歲";
          } elseif ($age > 120) {
            $errors["birthdate"] = "請輸入有效的生日";
          }
        }
      }
    }

    // 驗證縣市
    if (empty($memberCity) || !is_numeric($memberCity)) {
      $errors["city"] = "請選擇居住城市";
    } else {
      // 檢查城市是否存在
      $sql = "SELECT CITY_NO FROM city WHERE CITY_NO = ?";
      $stmt = $db->prepare($sql);
      $stmt->bind_param("i", $memberCity);
      $stmt->execute();
      $stmt->store_result();

      if ($stmt->num_rows === 0) $errors["city"] = "無效的選項";
      $stmt->close(); // 關閉 statement
    }

    // 驗證職業
    if (empty($memberOccupation) || !is_numeric($memberOccupation)) {
      $errors["occupation"] = "請選擇職業";
    } else {
      // 檢查職業是否存在
      $sql = "SELECT OCCUPATION_NO FROM occupation WHERE OCCUPATION_NO = ?";
      $stmt = $db->prepare($sql);
      $stmt->bind_param("i", $memberOccupation);
      $stmt->execute();
      $stmt->store_result();

      if ($stmt->num_rows === 0) $errors["occupation"] = "無效的選項";
      $stmt->close(); // 關閉 statement
    }

    // 驗證興趣
    if (empty($memberInterests) || !is_array($memberInterests)) {
      $errors["interests"] = "請至少選擇一個興趣";
    } elseif (count($memberInterests) > 3) {
      $errors["interests"] = "最多只能選擇 3 個興趣";
    } else {
      // 檢查所有興趣是否有效
      foreach ($memberInterests as $interest) {
        if (!is_numeric($interest)) {
          $errors["interests"] = "格式錯誤";
          break;
        }

        $sql = "SELECT CATEGORY_NO FROM category WHERE CATEGORY_NO = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $interest);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
          $errors["interests"] = "包含無效的選項";
          break;
        }
        $stmt->close(); // 關閉 statement
      }
    }

    // 如果有錯誤，回傳錯誤訊息
    if (!empty($errors)) {
      http_response_code(400);
      echo json_encode([
        "success" => false,
        "errors" => $errors
      ], JSON_UNESCAPED_UNICODE);
      exit;
    }

    try {
      $db->begin_transaction();

      $uploadDir = __DIR__ . "/../upload/member/"; 
      if (!is_dir($uploadDir)) {
          if (!mkdir($uploadDir, 0755, true)) {
              throw new Exception("無法創建上傳目錄: " . $uploadDir);
          }
      }

      // 處理 avatar
      $avatarPath = "";

      // 限制上傳的圖片檔案大小及檔案格式
      $maxFileSize = 5 * 1024 * 1024; // 5MB

      if (isset($_FILES["avatar"]) && $_FILES["avatar"]["error"] === 0) {
        if ($_FILES["avatar"]["size"] > $maxFileSize) {
          http_response_code(400);
          echo json_encode([
            "error" => "檔案太大，最大限制為 5MB"
          ], JSON_UNESCAPED_UNICODE);
          exit;
        }

        $allowedTypes = ["image/jpeg", "image/png", "image/gif"];
        if (!in_array($_FILES["avatar"]["type"], $allowedTypes)) {
          http_response_code(400);
          echo json_encode([
            "error" => "僅允許 JPEG/PNG/GIF 檔案"
          ], JSON_UNESCAPED_UNICODE);
          exit;
        }

        $ext = strtolower(pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION)); // 副檔名
        $randomStr = bin2hex(random_bytes(4)); // 產生新檔名 (隨機字串 + 副檔名)
        $saveName = date("Ymd_His") . "_" . $randomStr . "." . $ext;
        
        // 最後要儲存的完整路徑
        $targetFile = $uploadDir . $saveName;

        // move_uploaded_file 失敗時的診斷日誌
        if (!move_uploaded_file($_FILES["avatar"]["tmp_name"], $targetFile)) {
          $lastError = error_get_last();
          $errorMessage = "檔案上傳失敗。可能的錯誤: " . ($lastError ? $lastError['message'] : '未知');
          error_log("Registration error: " . $errorMessage . " from temp: " . $_FILES["avatar"]["tmp_name"] . " to: " . $targetFile);
          throw new Exception($errorMessage);
        }
        $avatarPath = $saveName; // 只存檔名
      }

      $sql = "INSERT INTO member (
        MEMBER_EMAIL, 
        MEMBER_PHONE, 
        MEMBER_PASSWORD, 
        MEMBER_NAME, 
        MEMBER_NICKNAME, 
        MEMBER_GENDER, 
        MEMBER_BIRTHDATE, 
        MEMBER_CITY, 
        MEMBER_OCCUPATION,
        MEMBER_AVATAR,
        MEMBER_STATUS,
        REGISTRATION_DATE
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '待審核', NOW())";

      $stmt = $db->prepare($sql);
      $stmt->bind_param(
        "sssssssiss", 
        $memberEmail, 
        $memberPhone, 
        $hashedPassword, 
        $memberName, 
        $memberNickname,
        $memberGender, 
        $memberBirthdate, 
        $memberCity, 
        $memberOccupation,
        $avatarPath
      );

      if (!$stmt->execute()) throw new Exception("新增會員資料失敗: " . $stmt->error);
  
      // 取得此次新增至 member 資料表中所對應到的 member_id
      $memberId = $db->insert_id;
      $stmt->close(); // 關閉 statement

      // 新增會員興趣(代號)至 member_interest 資料表中
      if (!empty($memberInterests)) {
        $sql = "INSERT INTO member_interest (MEMBER_ID, INTEREST_NO) VALUES (?, ?)";
        $stmt = $db->prepare($sql);

        foreach ($memberInterests as $interestNo) {
          $stmt->bind_param("ii", $memberId, $interestNo);
          if (!$stmt->execute()) throw new Exception("新增興趣資料失敗: " . $stmt->error);
        }
        $stmt->close(); // 關閉 statement
      }

      $db->commit();
  
      // 完成後清除 session
      unset($_SESSION["step1"]);
  
      echo json_encode([
        "success" => true,
        "member_id" => $memberId,
        "avatar"=>$avatarPath,
        "message" => "註冊成功，請等候審核"
      ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
      $db->rollback();

      // 記錄錯誤
      error_log("Registration error in step 2: " . $e->getMessage());

      http_response_code(500);
      echo json_encode([
        "success" => false,
        "error" => "註冊失敗，請稍後再試"
      ], JSON_UNESCAPED_UNICODE);
    }
  }
?>