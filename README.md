# 阿里云CDT流量监控
监控阿里云账户的流量使用情况，自动发出警报并停止相关实例。项目核心是通过阿里云API获取账户的流量信息，并集成各种通知功能。
此工具是在[大佬的镜像](https://hostloc.com/thread-1345929-1-1.html)基础上增加了`webhook`、`企业微信` 通知方式，感谢！

## 功能
- 5分钟检测一次，当使用流量达到95%时，自动禁用安全组的0.0.0.0/0入站规则，当流量重置时，自动恢复启用该规则
- 每天早上8点定时通知此账号流量使用情况
- 由于是安全组方式来断网保流量，请先删干净ecs的安全组配置，会自动添加端口允许规则
- 由于入站断网之后，所有端口都被禁用，如果想监控小鸡，可装nezha探针进行监控
- 如果非要在断网之后ssh连接小鸡等操作，请手动添加指定端口的规则组，因为全部端口的规则组会被自动删除掉，指定端口的规则不受影响

## 通知效果
<img width="500" alt="image" src="https://github.com/user-attachments/assets/d8f5f590-e9a3-44ae-90e3-a4a4de938ab5">






## 部署
```yaml
services:
  aliyun-cdt-check:
    container_name: aliyun-cdt-check
    image: alanoo/aliyun-cdt-check:latest
    network_mode: bridge
    volumes:
      - /appdata/aliyun-cdt-check/config.php:/app/config.php
    restart: always
```
config.php配置如下，按需修改
```php
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

        'enableWebhook' => false,          // webhook 通知开关
        'webhookId' => '1',               // 通知通道id，按需配置，没有则无需修改
        'webhookUrl' => 'https://mr.xxxx/api/plugins/notifyapi/send_notify?&access_key=xxxxxxx', 

        'enableQywx' => true,        // 企业微信 通知开关
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
```


## 使用方法
- 修改config.php文件内容
- 填写你的账号AK AS ，实例ID ，CDT总流量，通知方式，记得启用

## 检测是否运行成功
进入容器后，输入命令`php aliyun-cdt-check.php`检测是否执行成功，输入命令`php dailyjob.php`测试通知是否正常
