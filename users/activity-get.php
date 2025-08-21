<?php
    require_once __DIR__ . '/../config/db.php';
    session_start();

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['code'=>'0001','msg'=>'Method Not Allowed']);
        exit;
    }

    if (empty($_SESSION['member_id'])) {
        http_response_code(401);
        echo json_encode(['code' => '0002', 'msg' => '尚未登入'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $mysqli = db();
    $memberId = (int) $_SESSION['member_id'];
    $type = $_GET['type'] ?? 'hosted'; 
    $limit  = max(1, (int)($_GET['limit']  ?? 100)); // 最多一次抓幾筆
    $offset = max(0, (int)($_GET['offset'] ?? 0));   // 從第幾筆開始抓

    try{
        if($type === 'joined'){
            $sql = "SELECT 
                    a.ACTIVITY_NO,
                    a.ACTIVITY_NAME,
                    a.ACTIVITY_IMG,
                    a.ACTIVITY_STATUS,
                    'participant' AS role
                FROM PARTICIPANT p
                JOIN ACTIVITY a ON a.ACTIVITY_NO = p.ACTIVITY_NO
                WHERE p.PARTICIPANT_ID = ?
                LIMIT ? OFFSET ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('iii', $memberId, $limit, $offset);
        }else{
            $sql = "SELECT 
                    a.ACTIVITY_NO,
                    a.ACTIVITY_NAME,
                    a.ACTIVITY_IMG,
                    a.ACTIVITY_STATUS,
                    'host' AS role
                    FROM ACTIVITY a
                WHERE a.HOST_MEMBER_ID = ?
                LIMIT ? OFFSET ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('iii', $memberId, $limit, $offset);
        }

        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['code'=>'0000','data'=>['items'=>$items]], JSON_UNESCAPED_UNICODE);
        
    }catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['code'=>'9999','msg'=>'Server Error'], JSON_UNESCAPED_UNICODE);
    }
?>