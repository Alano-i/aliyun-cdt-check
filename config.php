<?php
return [
    'Accounts' => [
        [
            'accountName' => 'xxxx',
            'AccessKeyId' => '***',
            'AccessKeySecret' => '***',
            'regionId' => 'cn-hongkong',
            'instanceId' => 'i-***',
            'maxTraffic' => 0.01 // 设置流量限制
        ]
		//可多个配置
    ],
    'Notification' => [
        'email' => 'your-notification-email@example.com',
        'title' => '流量使用警告',
        'host' => 'smtp.example.com',
        'username' => 'your-email-username',
        'password' => 'your-email-password',
        'port' => 587,
        'secure' => 'tls',
        'enableEmail' => false,         // 邮件通知开关
        'enableBark' => true,          // Bark 通知开关
        'barkUrl' => 'https://api.day.app/****', 
        'enableTG' => false,            // Telegram 通知开关
        'tgBotToken' => 'your-telegram-bot-token',
        'tgChatId' => 'your-telegram-chat-id'
    ]
];