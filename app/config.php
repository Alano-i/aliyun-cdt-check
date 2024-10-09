<?php
return [
    'Accounts' => [
        [
            'accountName' => 'xxxx',         // 随意填，用于区分每个服务器
            'AccessKeyId' => '***',          // 阿里云 AccessKeyId
            'AccessKeySecret' => '***',      // 阿里云 AccessKeySecret
            'regionId' => 'cn-hongkong',     // 阿里云 regionId, cityId 请参考 https://help.aliyun.com/document_detail/40654.html
            'instanceId' => 'i-***',         // 阿里云实例ID, https://ecs.console.aliyun.com/server/region/cn-hongkong?accounttraceid#/ 查看
            'maxTraffic' => 180              // 设置流量限制，单位为G
        ]
        // [
        //     'accountName' => 'xxxx',
        //     'AccessKeyId' => 'AK',
        //     'AccessKeySecret' => 'AS',
        //     'regionId' => 'cn-hongkong',
        //     'instanceId' => 'i-j6cj3uXXX',
        //     'maxTraffic' => 200 // 设置流量限制
        // ]
		//可配置多个账户（每个数组之间用,分割），不用请删掉。
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

        'enableQywx' => false,        // 企业微信 通知开关
        'touser' => '@all',           // 企业微信通知用户
        'corpid' => 'xxx',            // 企业微信企业ID
        'corpsecret' => 'xxx',        // 企业微信应用Secret
        'agentid' => '1000002',       // 企业微信应用AgentId
        'picUrl' => 'https://raw.githubusercontent.com/Alano-i/aliyun-cdt-check/refs/heads/main/aliyuncdt.png',        // 企业微信通知封面图
        'baseApiUrl' => 'https://qyapi.weixin.qq.com',     // 企业微信API地址，如有代理填代理地址，默认：https://qyapi.weixin.qq.com


        'enableTG' => false,            // Telegram 通知开关
        'tgBotToken' => 'your-telegram-bot-token',
        'tgChatId' => 'your-telegram-chat-id'
    ]
];