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
                // $usagePercentage =0.45;

                // 进度条
                $progress = round($usagePercentage, 0);
                $progress_all_num = 20;
                // 黑白进度条
                $progress_do_text = "■";
                $progress_undo_text = "□";

                $progress_do_num = min($progress_all_num, round(0.5 + (($progress_all_num * intval($progress)) / 100)));

                // 处理96%-100%进度时进度条展示，正常计算时，进度大于等于96%就已是满条，需单独处理
                if (95 < intval($progress) && intval($progress) < 100) {
                    $progress_do_num = $progress_all_num - 1;
                }

                // 计算未完成部分
                $progress_undo_num = $progress_all_num - $progress_do_num;

                // 生成黑白进度条
                $progress_do = str_repeat($progress_do_text, $progress_do_num);
                $progress_undo = str_repeat($progress_undo_text, $progress_undo_num);
                $progress = $progress_do . $progress_undo;

                $formattedDateTime = (new DateTime($instanceDetails['到期时间']))->format('Y-m-d H:i:s');

                // 准备通知内容
                $message = "{$account['accountName']}（{$instanceDetails['公网IP地址']}）\n";
                $message .= "{$progress} {$usagePercentage}%\n";
                $message .= "已使用流量: " . round($traffic, 2) . "GB / {$account['maxTraffic']}GB\n";
                $message .= "实例地区: {$this->getRegionName($account['regionId'])}\n";
                $message .= "到期时间: {$formattedDateTime}\n";
                $message .= "实例ID: {$account['instanceId']}\n";
                $message .= "安全组状态: 启用\n";

                $traffic = round($traffic, 2)."GB";
                
                // 发送通知
                $this->sendNotification($message,$traffic);
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

    private function sendNotification($message,$traffic)
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

        // 发送 Webhook 通知
        if ($emailConfig['enableWebhook']) {
            $results['webhook'] = $this->sendWebhookNotification($message, $emailConfig['webhookUrl'], $emailConfig['title'],$emailConfig['webhookId'],$traffic);
        }

        // 发送 企业微信 通知
        if ($emailConfig['enableQywx']) {
            $results['qywx'] = $this->sendQywxNotification($message, $emailConfig['title'],$emailConfig['touser'], $emailConfig['corpid'],$emailConfig['corpsecret'],$emailConfig['agentid'],$emailConfig['baseApiUrl'],$emailConfig['picUrl'],$traffic);
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

    
    private function sendWebhookNotification($message, $webhookUrl,$title,$id,$traffic)
    {
        $title = "已使用{$traffic} - {$title}";
        $fullWebhookUrl = "{$webhookUrl}&id={$id}&title=" . rawurlencode($title) . "&content=" . urlencode($message);
        $result = file_get_contents($fullWebhookUrl);
        return $result !== false;
    }


    // 企业微信通知
    private function sendQywxNotification($message, $title, $touser,$corpid,$corpsecret,$agentid,$baseApiUrl,$picUrl,$traffic)
    {
        $title = "已使用{$traffic} - {$title}";
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