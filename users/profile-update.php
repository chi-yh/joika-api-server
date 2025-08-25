<?php
  // 前台註冊
  // POST
  require_once __DIR__ . '/../config/cors.php';
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

  // 取得會員 ID
  $memberId = $_POST["member_id"] ?? null;
  if (!$memberId || !is_numeric($memberId)) {
    http_response_code(400);
    echo json_encode(["error" => "缺少會員 ID"], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // 檢查會員是否存在
  $sql = "SELECT MEMBER_ID FROM member WHERE MEMBER_ID = ?";
  $stmt = $db->prepare($sql);
  $stmt->bind_param("i", $memberId);
  $stmt->execute();
  $stmt->store_result();
  if ($stmt->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["error" => "會員不存在"], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // 更新欄位
  $memberName = $_POST["name"] ?? null;
  $memberNickname = $_POST["nickname"] ?? null;
  $memberGender = $_POST["gender"] ?? "N";
  $memberBirthdate = $_POST["birthdate"] ?? null;
  $memberCity = $_POST["location"] ?? null;
  $memberOccupation = $_POST["occupation"] ?? null;
  $memberInterests = $_POST["interests"] ?? [];
  if (!is_array($memberInterests)) $memberInterests = [$memberInterests]; // 只有一個興趣時也轉成陣列

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

    // 處理 avatar
    $avatarPath = "";

    // 限制上傳的圖片檔案大小及檔案格式
    $maxFileSize = 5 * 1024 * 1024; // 5MB

    if (!empty($_FILES["avatar"]) && $_FILES["avatar"]["error"] === 0) {

      if ($_FILES["avatar"]["size"] > $maxFileSize) {
        http_response_code(400);
        echo json_encode([
          "error" => "檔案太大，最大限制為 5MB"
        ]);
        exit;
      }

      $allowedTypes = ["image/jpeg", "image/png", "image/gif"];
      if (!in_array($_FILES["avatar"]["type"], $allowedTypes)) {
        http_response_code(400);
        echo json_encode([
          "error" => "僅允許 JPEG/PNG/GIF 檔案"
        ]);
        exit;
      }

      $ext = strtolower(pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION)); // 副檔名
      $randomStr = bin2hex(random_bytes(4)); // 產生新檔名 (隨機字串 + 副檔名)
      $saveName = date("Ymd_His") . "_" . $randomStr . "." . $ext;
      $uploadDir = __DIR__ . "/uploads/avatar/";
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
      }

      // 最後要儲存的完整路徑
      $targetFile = $uploadDir . $saveName;

      if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $targetFile)) {
        $avatarPath = $saveName; // 只存檔名
      }
    }

    $sql = "UPDATE member 
            SET MEMBER_NAME=?, 
                MEMBER_NICKNAME=?, 
                MEMBER_GENDER=?, 
                MEMBER_BIRTHDATE=?, 
                MEMBER_CITY=?, 
                MEMBER_OCCUPATION=? " . ($avatarPath ? ", MEMBER_AVATAR=? " : "") .
           "WHERE MEMBER_ID=?";

    if ($avatarPath) {
        $stmt = $db->prepare($sql);
        $stmt->bind_param("ssssissi", $memberName, $memberNickname, $memberGender, $memberBirthdate,
                          $memberCity, $memberOccupation, $avatarPath, $memberId);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->bind_param("ssssisi", $memberName, $memberNickname, $memberGender, $memberBirthdate,
                          $memberCity, $memberOccupation, $memberId);
    }

    if (!$stmt->execute()) throw new Exception("更新會員資料失敗");

    // 更新興趣：先刪除再新增
    $sql = "DELETE FROM member_interest WHERE MEMBER_ID=?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $memberId);
    $stmt->execute();

    if (!empty($memberInterests)) {
      $sql = "INSERT INTO member_interest (MEMBER_ID, INTEREST_NO) VALUES (?, ?)";
      $stmt = $db->prepare($sql);
      foreach ($memberInterests as $interestNo) {
        $stmt->bind_param("ii", $memberId, $interestNo);
        if (!$stmt->execute()) throw new Exception("更新興趣資料失敗");
      }
    }

    $db->commit();

    echo json_encode([
      "success" => true,
      "message" => "會員資料更新成功",
      "member_id" => $memberId,
      "avatar" => $avatarPath
    ], JSON_UNESCAPED_UNICODE);

  } catch (Exception $e) {
    $db->rollback();

    error_log("Update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "更新失敗，請稍後再試"], JSON_UNESCAPED_UNICODE);
  }
?>