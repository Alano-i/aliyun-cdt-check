<?php
require 'vendor/autoload.php';
require_once('vendor/PHPMailer/phpmailer.class.php');
date_default_timezone_set('Asia/Shanghai');

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;

class DailyTrafficNotification
{
    private $accounts;
    private $notificationEmail;
    private $notificationTitle;
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

    public function sendDailyNotification()
    {
        foreach ($this->accounts as $account) {
            // 验证 AK, SK 和 InstanceId
            if (!$this->validateCredentialsAndInstance($account)) {
                continue;
            }

            try {
                // 获取当前账号的流量信息
                $traffic = $this->getTraffic($account['AccessKeyId'], $account['AccessKeySecret']);
                $usagePercentage = round(($traffic / $account['maxTraffic']) * 100, 2);

                // 获取实例的详细信息
                $instanceDetails = $this->getInstanceDetails($account['AccessKeyId'], $account['AccessKeySecret'], $account['instanceId'], $account['regionId']);

                // 检查当前是否启用了 0.0.0.0/0 的规则
                $securityGroupId = $this->getSecurityGroupId($account['instanceId'], $account['AccessKeyId'], $account['AccessKeySecret'], $account['regionId']);
                $isEnabled = $this->isSecurityGroupRuleEnabled($securityGroupId, $account['AccessKeyId'], $account['AccessKeySecret'], $account['regionId']);

                if (!$isEnabled) {
                    continue; // 如果安全组已禁用，不发送通知
                }

                // 准备通知内容
                $message = "账号名称: {$account['accountName']}\n";
                $message .= "实例ID: {$account['instanceId']}\n";
                $message .= "实例IP: {$instanceDetails['公网IP地址']}\n";
                $message .= "到期时间: {$instanceDetails['到期时间']}\n";
                $message .= "CDT总流量: {$account['maxTraffic']}GB\n";
                $message .= "已使用流量: {$traffic}GB\n";
                $message .= "使用百分比: {$usagePercentage}%\n";
                $message .= "地区: {$this->getRegionName($account['regionId'])}\n";
                $message .= "安全组状态: 启用\n";

                // 发送通知
                $this->sendNotification($message);
            } catch (ClientException $e) {
                echo '客户端异常: ' . $e->getErrorMessage() . PHP_EOL;
            } catch (ServerException $e) {
                echo '服务器异常: ' . $e->getErrorMessage() . PHP_EOL;
            }
        }
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

            // 检查实例是否存在
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

            return true;
        } catch (ClientException $e) {
            echo '客户端异常: ' . $e->getErrorMessage() . PHP_EOL;
            return false;
        } catch (ServerException $e) {
            echo '服务器异常: ' . $e->getErrorMessage() . PHP_EOL;
            return false;
        } catch (Exception $e) {
            echo '未知错误: ' . $e->getMessage() . PHP_EOL;
            return false;
        }
    }

    private function getTraffic($accessKeyId, $accessKeySecret)
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
            return $total / (1024 * 1024 * 1024); // 转换为 GB
        } catch (ClientException $e) {
            echo '客户端异常: ' . $e->getErrorMessage() . PHP_EOL;
        } catch (ServerException $e) {
            echo '服务器异常: ' . $e->getErrorMessage() . PHP_EOL;
        }
        return 0;
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

            // 遍历安全组规则，检查是否存在 0.0.0.0/0 规则且规则允许全部协议（all）
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

    private function sendNotification($message)
    {
        $config = include 'config.php';
        $emailConfig = $config['Notification'];

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

        // 检查是否所有通知都成功
        foreach ($results as $result) {
            if ($result !== true) {
                echo "通知发送失败: " . json_encode($results) . PHP_EOL;
                return false;
            }
        }

        echo "通知发送成功" . PHP_EOL;
        return true;
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

// 运行每日通知
$dailyTrafficNotification = new DailyTrafficNotification();
$dailyTrafficNotification->sendDailyNotification();
