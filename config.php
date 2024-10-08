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

        'title' => 'CDT流量统计',         // 标题

        'enableEmail' => false,         // 邮件通知开关
        'email' => 'your-notification-email@example.com',
        'host' => 'smtp.example.com',
        'username' => 'your-email-username',
        'password' => 'your-email-password',
        'port' => 587,
        'secure' => 'tls',
       
        'enableBark' => false,          // Bark 通知开关
        'barkUrl' => 'https://api.day.app/XXXXXXXX', 

        'enableWebhook' => true,          // webhook 通知开关
        'webhookId' => '1',               // 通知通道id，按需配置，没有则无需修改
        'webhookUrl' => 'https://mr.xxxx/api/plugins/notifyapi/send_notify?&access_key=xxxxxxx', 


        'enableTG' => false,            // Telegram 通知开关
        'tgBotToken' => 'your-telegram-bot-token',
        'tgChatId' => 'your-telegram-chat-id'
    ]
];