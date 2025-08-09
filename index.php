<?php
//以下設定先確認後再打開

/*
declare(strict_types=1);

// 載入全域設定 & 回應工具
require __DIR__ . '/config/app.php';
require __DIR__ . '/utils/response.php';

use App\Utils\Response;

// API 基本資訊
$apiInfo = [
    'name' => '揪團系統 API',
    'version' => '1.0',
    'base_url' => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']),
    'description' => '這是後端 API 服務的根入口，請依照下方端點呼叫對應功能。',
    'endpoints' => [
        'activities' => [
            'list'      => '/activities/list.php',
            'detail'    => '/activities/detail.php',
            'create'    => '/activities/create.php',
            'update'    => '/activities/update.php',
            'join'      => '/activities/join.php',
            'favorite'  => '/favorites/toggle.php?target_type=activity',
        ],
        'posts' => [
            'list'      => '/posts/list.php',
            'detail'    => '/posts/detail.php',
            'create'    => '/posts/create.php',
            'update'    => '/posts/update.php',
            'like'      => '/reactions/toggle.php?target_type=post',
        ],
        'comments' => [
            'list'      => '/comments/list.php',
            'create'    => '/comments/create.php',
            'like'      => '/reactions/toggle.php?target_type=comment',
        ],
        'chat' => [
            'get_messages'   => '/chat/get-messages.php',
            'send_message'   => '/chat/send-message.php',
            'upload_attachment' => '/chat/upload-attachment.php',
            'emoji_list'     => '/chat/emoji-list.php',
        ],
        'users' => [
            'login'     => '/users/login.php',
            'register'  => '/users/register.php',
            'profile'   => '/users/profile-get.php',
            'update'    => '/users/profile-update.php',
        ]
    ]
];

// 輸出 JSON
Response::jsonOk($apiInfo);
*/