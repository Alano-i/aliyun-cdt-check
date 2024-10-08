<?php
require 'vendor/autoload.php';
require_once('vendor/PHPMailer/phpmailer.class.php');
date_default_timezone_set('Asia/Shanghai');

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;

class AliyunTrafficCheck
{
    private $accounts;
    private $notificationEmail;
    private $notificationTitle;
    private $notificationToken;
    private $notificationHost;
    private $notificationUsername;
    private $notificationPassword;
    private $notificationPort;
    private $notificationSecure;

    public function __construct()
    {
        $config = include 'config.php';
        $this->accounts = $config['Accounts'];
        $this->notificationEmail = $config['Notification']['email'];
        $this->notificationTitle = $config['Notification']['title'];
        $this->notificationHost = $config['Notification']['host'];
        $this->notificationUsername = $config['Notification']['username'];
        $this->notificationPassword = $config['Notification']['password'];
        $this->notificationPort = $config['Notification']['port'];
        $this->notificationSecure = $config['Notification']['secure'];
    }

    public function getTraffic($accessKeyId, $accessKeySecret)
    {
        try {
            AlibabaCloud::accessKeyClient($accessKeyId, $accessKeySecret)
                ->regionId('cn-hongkong')
                ->asDefaultClient();

            $result = AlibabaCloud::rpc()
                ->product('CDT')
                ->version('2021-08-13')
                ->action('ListCdtInternetTraffic')
                ->method('POST')
                ->host('cdt.aliyuncs.com')
                ->request();

            $total = array_sum(array_column($result['TrafficDetails'], 'Traffic'));
            return $total / (1024 * 1024 * 1024); // Convert to GB
        } catch (ClientException $e) {
            echo '客户端异常: ' . $e->getErrorMessage() . PHP_EOL;
        } catch (ServerException $e) {
            echo '服务器异常: ' . $e->getErrorMessage() . PHP_EOL;
        }
        return 0;
    }
    private function getSecurityGroupId($instanceId, $accessKeyId, $accessKeySecret, $regionId)
{
    try {
        AlibabaCloud::accessKeyClient($accessKeyId, $accessKeySecret)
            ->regionId($regionId)
            ->asDefaultClient();

        // 调用DescribeInstanceAttribute API获取实例信息
        $result = AlibabaCloud::rpc()
            ->product('Ecs')
            ->version('2014-05-26')
            ->action('DescribeInstanceAttribute')
            ->method('POST')
            ->host("ecs.{$regionId}.aliyuncs.com")
            ->options([
                'query' => [
                    'RegionId' => $regionId,
                    'InstanceId' => $instanceId
                ]
            ])
            ->request();

        // 获取安全组ID
        $securityGroupId = $result['SecurityGroupIds']['SecurityGroupId'][0];
        return $securityGroupId;

    } catch (ClientException $e) {
        echo '客户端异常: ' . $e->getErrorMessage() . PHP_EOL;
    } catch (ServerException $e) {
        echo '服务器异常: ' . $e->getErrorMessage() . PHP_EOL;
    }
    return null;
}

private function disableSecurityGroupRule($securityGroupId, $accessKeyId, $accessKeySecret, $regionId)
{
    try {
        AlibabaCloud::accessKeyClient($accessKeyId, $accessKeySecret)
            ->regionId($regionId)
            ->asDefaultClient();

        // 调用RevokeSecurityGroup API禁用安全组规则
        AlibabaCloud::rpc()
            ->product('Ecs')
            ->version('2014-05-26')
            ->action('RevokeSecurityGroup')
            ->method('POST')
            ->host("ecs.{$regionId}.aliyuncs.com")
            ->options([
                'query' => [
                    'RegionId' => $regionId,
                    'SecurityGroupId' => $securityGroupId,
                    'IpProtocol' => 'all',
                    'PortRange' => '-1/-1',
                    'SourceCidrIp' => '0.0.0.0/0'
                ]
            ])
            ->request();

        echo "已禁用0.0.0.0/0的全部协议规则\n";
    } catch (ClientException $e) {
        echo '客户端异常: ' . $e->getErrorMessage() . PHP_EOL;
    } catch (ServerException $e) {
        echo '服务器异常: ' . $e->getErrorMessage() . PHP_EOL;
    }
}

private function enableSecurityGroupRule($securityGroupId, $accessKeyId, $accessKeySecret, $regionId)
{
    try {
        AlibabaCloud::accessKeyClient($accessKeyId, $accessKeySecret)
            ->regionId($regionId)
            ->asDefaultClient();

        // 调用AuthorizeSecurityGroup API允许安全组规则
        AlibabaCloud::rpc()
            ->product('Ecs')
            ->version('2014-05-26')
            ->action('AuthorizeSecurityGroup')
            ->method('POST')
            ->host("ecs.{$regionId}.aliyuncs.com")
            ->options([
                'query' => [
                    'RegionId' => $regionId,
                    'SecurityGroupId' => $securityGroupId,
                    'IpProtocol' => 'all',
                    'PortRange' => '-1/-1',
                    'SourceCidrIp' => '0.0.0.0/0'
                ]
            ])
            ->request();

        echo "已恢复0.0.0.0/0的全部协议规则\n";
    } catch (ClientException $e) {
        echo '客户端异常: ' . $e->getErrorMessage() . PHP_EOL;
    } catch (ServerException $e) {
        echo '服务器异常: ' . $e->getErrorMessage() . PHP_EOL;
    }
}

private function isSecurityGroupRuleEnabled($securityGroupId, $accessKeyId, $accessKeySecret, $regionId)
{
    try {
        AlibabaCloud::accessKeyClient($accessKeyId, $accessKeySecret)
            ->regionId($regionId)
            ->asDefaultClient();

        // 调用 DescribeSecurityGroupAttribute API 来获取安全组规则
        $result = AlibabaCloud::rpc()
            ->product('Ecs')
            ->version('2014-05-26')
            ->action('DescribeSecurityGroupAttribute')
            ->method('POST')
            ->host("ecs.{$regionId}.aliyuncs.com")
            ->options([
                'query' => [
                    'RegionId' => $regionId,
                    'SecurityGroupId' => $securityGroupId
                ]
            ])
            ->request();

        // 打印所有安全组规则以进行调试
        //print_r($result['Permissions']['Permission']);

        // 遍历安全组规则，检查是否存在 0.0.0.0/0 规则且规则允许全部协议（all），并检查网络接口类型和方向
        foreach ($result['Permissions']['Permission'] as $rule) {
            if (strtoupper($rule['IpProtocol']) == 'ALL' &&
                $rule['SourceCidrIp'] == '0.0.0.0/0' &&
                $rule['Policy'] == 'Accept' &&
                $rule['NicType'] == 'intranet' && // 确保是内网规则
                $rule['Direction'] == 'ingress') // 确保是入站规则
            {
                return true;  // 规则存在并启用
            }
        }

        return false;  // 规则不存在或未启用
    } catch (ClientException $e) {
        echo '客户端异常: ' . $e->getErrorMessage() . PHP_EOL;
    } catch (ServerException $e) {
        echo '服务器异常: ' . $e->getErrorMessage() . PHP_EOL;
    }
    return false;
}


private function validateCredentialsAndInstance($account)
{
    try {
        // 检查 AK 和 SK 是否正确
        AlibabaCloud::accessKeyClient($account['AccessKeyId'], $account['AccessKeySecret'])
            ->regionId($account['regionId'])
            ->asDefaultClient();

        // 调用 DescribeInstances API 来验证 AK 和 SK 是否有效
        $result = AlibabaCloud::rpc()
            ->product('Ecs')
            ->version('2014-05-26')
            ->action('DescribeInstances')
            ->method('POST')
            ->host("ecs.{$account['regionId']}.aliyuncs.com")
            ->options([
                'query' => [
                    'RegionId' => $account['regionId'],
                ]
            ])
            ->request();

        // 如果 `InstanceId` 无效，将抛出异常
        $instanceValid = false;
        foreach ($result['Instances']['Instance'] as $instance) {
            if ($instance['InstanceId'] == $account['instanceId']) {
                $instanceValid = true;
                break;
            }
        }

        if (!$instanceValid) {
            throw new Exception("指定的实例ID不存在: {$account['instanceId']}");
        }

        // 如果验证通过，返回 true
        return true;
    } catch (ClientException $e) {
        // 捕获客户端异常 (如 AK、SK 错误)
        $log = [
            '服务器' => $account['accountName'],
            '错误信息' => '客户端异常: ' . $e->getErrorMessage()
        ];

        // 输出到控制台
        echo '客户端异常: ' . $e->getErrorMessage() . PHP_EOL;

        // 发送通知
        $this->sendNotification($log);
        return false;
    } catch (ServerException $e) {
        // 捕获服务器异常 (如 `InstanceId` 错误)
        $log = [
            '实例ID' => $account['instanceId'],
            '服务器' => $account['accountName'],
            '错误信息' => '服务器异常: ' . $e->getErrorMessage()
        ];

        // 输出到控制台
        echo '服务器异常: ' . $e->getErrorMessage() . PHP_EOL;

        // 发送通知
        $this->sendNotification($log);
        return false;
    } catch (Exception $e) {
        // 捕获所有其他类型的异常
        $log = [
            '服务器' => $account['accountName'],
            '实例ID' => $account['instanceId'],
            '错误信息' => '未知错误: ' . $e->getMessage()
        ];

        // 输出到控制台
        echo '未知错误: ' . $e->getMessage() . PHP_EOL;

        // 发送通知
        $this->sendNotification($log);
        return false;
    }
}


private function getInstanceDetails($accessKeyId, $accessKeySecret, $instanceId, $regionId)
{
    try {
        // 创建阿里云客户端
        AlibabaCloud::accessKeyClient($accessKeyId, $accessKeySecret)
            ->regionId($regionId)
            ->asDefaultClient();

        // 调用 DescribeInstances API 来获取实例的详细信息
        $result = AlibabaCloud::rpc()
            ->product('Ecs')
            ->version('2014-05-26')
            ->action('DescribeInstances')
            ->method('POST')
            ->host("ecs.{$regionId}.aliyuncs.com")
            ->options([
                'query' => [
                    'RegionId' => $regionId,
                    'InstanceIds' => json_encode([$instanceId])  // 使用 json_encode 将实例 ID 转为数组格式
                ]
            ])
            ->request();

        // 检查实例是否存在
        if (!empty($result['Instances']['Instance'])) {
            $instance = $result['Instances']['Instance'][0];

            // 获取到期时间
            $expirationTime = isset($instance['ExpiredTime']) ? $instance['ExpiredTime'] : '无到期时间';

            // 获取公网 IP 地址 (通过 EIP 获取)
            $publicIpAddress = '无公网 IP 地址';
            if (!empty($instance['EipAddress']['IpAddress'])) {
                $publicIpAddress = $instance['EipAddress']['IpAddress'];
            }

            // 返回所有获取到的信息
            return [
                '到期时间' => $expirationTime,
                '公网IP地址' => $publicIpAddress
            ];
        }

        // 如果未找到实例，返回默认值
        return [
            '到期时间' => '无到期时间',
            '公网IP地址' => '无公网 IP 地址'
        ];

    } catch (ClientException $e) {
        echo '客户端异常: ' . $e->getErrorMessage() . PHP_EOL;
    } catch (ServerException $e) {
        echo '服务器异常: ' . $e->getErrorMessage() . PHP_EOL;
    }

    // 如果出现异常，返回错误信息
    return [
        '到期时间' => '查询失败',
        '公网IP地址' => '查询失败'
    ];
}


public function check()
{
    $logs = [];
    foreach ($this->accounts as $account) {
        // 先验证 AK, SK 和 InstanceId
        if (!$this->validateCredentialsAndInstance($account)) {
            continue;  // 如果验证失败，跳过当前账号的进一步处理
        }

        try {
            // 获取当前账号的流量信息
            $traffic = $this->getTraffic($account['AccessKeyId'], $account['AccessKeySecret']);
            $accountName = isset($account['accountName']) ? $account['accountName'] : substr($account['AccessKeyId'], 0, 7) . '***';
            $usagePercentage = round(($traffic / $account['maxTraffic']) * 100, 2);
            $regionName = $this->getRegionName($account['regionId']);

            // 获取实例的安全组ID
            $securityGroupId = $this->getSecurityGroupId($account['instanceId'], $account['AccessKeyId'], $account['AccessKeySecret'], $account['regionId']);

            // 获取实例的详细信息（到期时间、公网 IP 地址）
            $instanceDetails = $this->getInstanceDetails($account['AccessKeyId'], $account['AccessKeySecret'], $account['instanceId'], $account['regionId']);

            // 记录日志信息
            $log = [
                '实例ID' => $account['instanceId'],
                '服务器' => $accountName,
                '总流量' => $account['maxTraffic'] . 'GB',
                '已使用流量' => round($traffic, 2) . 'GB',
                '使用百分比' => $usagePercentage . '%',
                '地区' => $regionName,
                '实例到期时间' => $instanceDetails['到期时间'],
                '公网IP地址' => $instanceDetails['公网IP地址'],
                '使用率达到95%' => $usagePercentage >= 95 ? '是' : '否'
            ];

            // 检查当前是否启用了 0.0.0.0/0 的规则
            $isEnabled = $this->isSecurityGroupRuleEnabled($securityGroupId, $account['AccessKeyId'], $account['AccessKeySecret'], $account['regionId']);

            // 如果流量超限且 0.0.0.0/0 的规则还没有被禁用，禁用规则并发送通知
            if ($usagePercentage >= 95) {
                if ($isEnabled) {
                    // 禁用规则
                    $this->disableSecurityGroupRule($securityGroupId, $account['AccessKeyId'], $account['AccessKeySecret'], $account['regionId']);
                    $log['安全组状态'] = "已禁用 0.0.0.0/0 访问规则";
                    $notificationResult = $this->sendNotification($log);
                    $log['通知发送'] = $notificationResult === true ? '成功' : "失败: {$notificationResult}";
                } else {
                    // 已经禁用，不需要操作
                    $log['安全组状态'] = "规则已禁用，无需操作";
                    $log['通知发送'] = '不需要';
                }
            }

            // 如果流量恢复且 0.0.0.0/0 的规则还没有被启用，启用规则并发送通知
            if ($usagePercentage < 95) {
                if (!$isEnabled) {
                    // 启用规则
                    $this->enableSecurityGroupRule($securityGroupId, $account['AccessKeyId'], $account['AccessKeySecret'], $account['regionId']);
                    $log['安全组状态'] = "已恢复 0.0.0.0 访问规则";
                    $notificationResult = $this->sendNotification($log);
                    $log['通知发送'] = $notificationResult === true ? '成功' : "失败: {$notificationResult}";
                } else {
                    // 已经启用，不需要操作
                    $log['安全组状态'] = "规则已启用，无需操作";
                    $log['通知发送'] = '不需要';
                }
            }

            // 将当前账号的日志信息添加到总日志
            $logs[] = $log;
        } catch (ClientException $e) {
            // 处理客户端异常（例如 AccessKey 不正确）
            echo '客户端异常: ' . $e->getErrorMessage() . PHP_EOL;  // 输出到控制台
            $log = [
                '服务器' => $accountName,
                '错误信息' => '客户端异常: ' . $e->getErrorMessage()
            ];
            $this->sendNotification($log);  // 发送错误通知
        } catch (ServerException $e) {
            // 处理服务器异常（例如 InstanceId 错误）
            echo '服务器异常: ' . $e->getErrorMessage() . PHP_EOL;  // 输出到控制台
            $log = [
                '实例ID' => $account['instanceId'],
                '服务器' => $accountName,
                '错误信息' => '服务器异常: ' . $e->getErrorMessage()
            ];
            $this->sendNotification($log);  // 发送错误通知
        }
    }

    // 写入日志信息到文件并输出到浏览器
    $this->writeLog($logs);
}

    private function writeLog($logs)
    {
        $data = [
            '获取时间' => date('Y-m-d H:i:s'),
            '日志' => $logs
        ];

        // 将数据写入 data.json 文件
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        file_put_contents('data.json', $jsonData);

        // 输出数据到浏览器
        header('Content-Type: application/json');
        echo $jsonData;
    }


    private function sendNotification($log)
    {
        $config = include 'config.php';
        $emailConfig = $config['Notification'];
    
        // 组装通知内容
        if (isset($log['错误信息'])) {
            // 如果有错误信息，发送错误通知
            $message = "⚠️ 错误通知\n";
            $message .= "服务器: {$log['服务器']}\n";
            $message .= "错误信息: {$log['错误信息']}\n";
            if (isset($log['实例ID'])) {
                $message .= "实例ID: {$log['实例ID']}\n";
            }
        } else {
            // 正常的通知内容，按照指定顺序
            $message = "服务器: {$log['服务器']}\n";
            $message .= "实例ID: {$log['实例ID']}\n";
            $message .= "实例IP: {$log['公网IP地址']}\n";
            $message .= "到期时间: {$log['实例到期时间']}\n";
            $message .= "CDT总流量: {$log['总流量']}\n";
            $message .= "已使用流量: {$log['已使用流量']}\n";
            $message .= "使用百分比: {$log['使用百分比']}\n";
            $message .= "地区: {$log['地区']}\n";
            $message .= "安全组状态: {$log['安全组状态']}\n";
        }
    
        // 初始化结果数组
        $results = [];
    
        // 发送邮件通知
        if ($emailConfig['enableEmail']) {
            $results['email'] = $this->send_mail(
                $this->notificationEmail,
                '',
                $this->notificationTitle,
                nl2br($message),  // 使用 nl2br 保持换行格式
                null,
                $emailConfig
            );
        }
    
        // 发送 Bark 通知
        if ($emailConfig['enableBark']) {
            $results['bark'] = $this->sendBarkNotification($message, $emailConfig['barkUrl']);
        }
    
        // 发送 Telegram 通知
        if ($emailConfig['enableTG']) {
            $results['tg'] = $this->sendTGNotification($message, $emailConfig['tgBotToken'], $emailConfig['tgChatId']);
        }
    
        // 发送 Webhook 通知
        if ($emailConfig['enableWebhook']) {
            $results['webhook'] = $this->sendWebhookNotification($message, $emailConfig['webhookUrl'], $emailConfig['title'],$emailConfig['webhookId']);
        }

        // 发送 企业微信 通知
        if ($emailConfig['enableQywx']) {
            $results['qywx'] = $this->sendQywxNotification($message, $emailConfig['title'],$emailConfig['touser'], $emailConfig['corpid'],$emailConfig['corpsecret'],$emailConfig['agentid'],$emailConfig['baseApiUrl'],$emailConfig['picUrl']);
        }
    
        // 检查是否所有通知都成功
        foreach ($results as $result) {
            if ($result !== true) {
                return "发送失败: " . json_encode($results);
            }
        }
    
        return true;
    }

    
    private function sendWebhookNotification($message, $webhookUrl,$title,$id)
    {
        $fullWebhookUrl = "{$webhookUrl}&id={$id}&title=" . rawurlencode($title) . "&content=" . urlencode($message);
        $result = file_get_contents($fullWebhookUrl);
        return $result !== false;
    }

    // 企业微信通知
    private function sendQywxNotification($message, $title, $touser,$corpid,$corpsecret,$agentid,$baseApiUrl,$picUrl)
    {
        $postdata = array(
            'touser' => $touser,
            'msgtype' => 'news',
            'agentid' => $agentid,
            'news' => array(
                'articles' => array(
                    array(
                        'title' => $title,
                        'description' => $message,
                        'url' => '',
                        'picurl' => $picUrl,
                    )
                )
            ),
            'enable_id_trans' => 0,
            'enable_duplicate_check' => 0,
            'duplicate_check_interval' => 1800
        );

        $url = "{$baseApiUrl}/cgi-bin/gettoken?corpid={$corpid}&corpsecret={$corpsecret}";

        // curl 请求处理函数
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $out = curl_exec($ch);
        curl_close($ch);

        $access_token_Arr =  json_decode($out, true);
        $access_token = $access_token_Arr['access_token'];

        // 发送应用消息
        $sendUrl = "{$baseApiUrl}/cgi-bin/message/send?access_token={$access_token}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $sendUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        // 将返回的 JSON 解析为数组
        $response = json_decode($result, true);
        // 如果返回结果中 errcode 为 0，表示操作成功，返回 true，否则返回 false
        if ($response && $response['errcode'] === 0) {
            return true;
        } else {
            return false;
        }
        
    }

    
    private function sendBarkNotification($message, $barkUrl)
    {
        // 将消息追加到用户自定义的 Bark URL
        $fullBarkUrl = "{$barkUrl}/流量告警/" . urlencode($message);
        $result = file_get_contents($fullBarkUrl);

        return $result !== false;
    }

    private function sendTGNotification($message, $botToken, $chatId)
    {
        $tgUrl = "https://api.telegram.org/bot{$botToken}/sendMessage?chat_id={$chatId}&text=" . urlencode($message);
        $result = file_get_contents($tgUrl);

        return $result !== false;
    }

    private function send_mail($to, $name, $subject = '', $body = '', $attachment = null, $config = '')
    {
        $config = is_array($config) ? $config : array();
        $mail = new PHPMailer();
        $mail->CharSet = 'UTF-8';
        $mail->IsSMTP();
        $mail->SMTPDebug = 0;
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = $this->notificationSecure;
        $mail->Host = $this->notificationHost;
        $mail->Port = $this->notificationPort;
        $mail->Username = $this->notificationUsername;
        $mail->Password = $this->notificationPassword;
        $mail->SetFrom($this->notificationUsername, '阿里云CDT告警');
        $mail->Subject = $subject;
        $mail->MsgHTML($body);
        $mail->AddAddress($to, $name);
        return $mail->Send() ? true : $mail->ErrorInfo;
    }

    public function performAction($account, $action)
    {
        try {
            AlibabaCloud::accessKeyClient($account['AccessKeyId'], $account['AccessKeySecret'])
                ->regionId($account['regionId'])
                ->asDefaultClient();

            $result = AlibabaCloud::rpc()
                ->product('Ecs')
                ->version('2014-05-26')
                ->action('DescribeInstanceStatus')
                ->method('POST')
                ->host("ecs.{$account['regionId']}.aliyuncs.com")
                ->options([
                    'query' => [
                        'RegionId' => $account['regionId'],
                        'InstanceId' => $account['instanceId']
                    ]
                ])
                ->request();

            $instanceStatus = $result['InstanceStatuses']['InstanceStatus'][0]['Status'];

            if ($action === 'stop') {
                if ($instanceStatus !== 'Stopped') {
                    $this->stopInstance($account['instanceId'], $account['regionId'], $account['AccessKeyId'], $account['AccessKeySecret']);
                    return "实例 {$account['accountName']} 正在关闭...";
                } else {
                    return "实例 {$account['accountName']} 已经关闭";
                }
            } elseif ($action === 'start') {
                if ($instanceStatus !== 'Running') {
                    $this->startInstance($account['instanceId'], $account['regionId'], $account['AccessKeyId'], $account['AccessKeySecret']);
                    return "实例 {$account['accountName']} 正在启动...";
                } else {
                    return "实例 {$account['accountName']} 已经运行";
                }
            }
        } catch (ClientException $e) {
            return '客户端异常: ' . $e->getErrorMessage() . PHP_EOL;
        } catch (ServerException $e) {
            return '服务器异常: ' . $e->getErrorMessage() . PHP_EOL;
        }
    }

    private function stopInstance($instanceId, $regionId, $accessKeyId, $accessKeySecret)
    {
        try {
            AlibabaCloud::accessKeyClient($accessKeyId, $accessKeySecret)
                ->regionId($regionId)
                ->asDefaultClient();

            AlibabaCloud::rpc()
                ->product('Ecs')
                ->version('2014-05-26')
                ->action('StopInstance')
                ->method('POST')
                ->host("ecs.{$regionId}.aliyuncs.com")
                ->options([
                    'query' => [
                        'RegionId' => $regionId,
                        'InstanceId' => $instanceId
                    ]
                ])
                ->request();

            echo "实例已关机，ID: {$instanceId}\n";
        } catch (ClientException $e) {
            echo '客户端异常: ' . $e->getErrorMessage() . PHP_EOL;
        } catch (ServerException $e) {
            echo '服务器异常: ' . $e->getErrorMessage() . PHP_EOL;
        }
    }

    private function startInstance($instanceId, $regionId, $accessKeyId, $accessKeySecret)
    {
        try {
            AlibabaCloud::accessKeyClient($accessKeyId, $accessKeySecret)
                ->regionId($regionId)
                ->asDefaultClient();

            AlibabaCloud::rpc()
                ->product('Ecs')
                ->version('2014-05-26')
                ->action('StartInstance')
                ->method('POST')
                ->host("ecs.{$regionId}.aliyuncs.com")
                ->options([
                    'query' => [
                        'RegionId' => $regionId,
                        'InstanceId' => $instanceId
                    ]
                ])
                ->request();

            echo "实例已启动，ID: {$instanceId}\n";
        } catch (ClientException $e) {
            echo '客户端异常: ' . $e->getErrorMessage() . PHP_EOL;
        } catch (ServerException $e) {
            echo '服务器异常: ' . $e->getErrorMessage() . PHP_EOL;
        }
    }

    private function getRegionName($regionId)
    {
        $regions = [
            'cn-qingdao' => '华北1(青岛)',
            'cn-beijing' => '华北2(北京)',
            'cn-zhangjiakou' => '华北3(张家口)',
            'cn-huhehaote' => '华北5(呼和浩特)',
            'cn-wulanchabu' => '华北6(乌兰察布)',
            'cn-hangzhou' => '华东1(杭州)',
            'cn-shanghai' => '华东2(上海)',
            'cn-nanjing' => '华东5 (南京-本地地域)',
            'cn-fuzhou' => '华东6(福州-本地地域)',
            'cn-wuhan-lr' => '华中1(武汉-本地地域)',
            'cn-shenzhen' => '华南1(深圳)',
            'cn-heyuan' => '华南2(河源)',
            'cn-guangzhou' => '华南3(广州)',
            'cn-chengdu' => '西南1(成都)',
            'cn-hongkong' => '中国香港',
            'ap-southeast-1' => '新加坡',
            'ap-southeast-2' => '澳大利亚(悉尼)',
            'ap-southeast-3' => '马来西亚(吉隆坡)',
            'ap-southeast-5' => '印度尼西亚(雅加达)',
            'ap-southeast-6' => '菲律宾(马尼拉)',
            'ap-southeast-7' => '泰国(曼谷)',
            'ap-northeast-1' => '日本(东京)',
            'ap-northeast-2' => '韩国(首尔)',
            'us-west-1' => '美国(硅谷)',
            'us-east-1' => '美国(弗吉尼亚)',
            'eu-central-1' => '德国(法兰克福)',
            'eu-west-1' => '英国(伦敦)',
            'me-east-1' => '阿联酋(迪拜)',
            'me-central-1' => '沙特(利雅得)'
        ];

        return isset($regions[$regionId]) ? $regions[$regionId] : '未知地区';
    }
}

$aliyunTrafficCheck = new AliyunTrafficCheck();
$aliyunTrafficCheck->check();