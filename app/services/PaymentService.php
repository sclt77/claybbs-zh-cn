<?php

namespace App\Services;

use App\Models\PaymentModel;
use App\Models\SettingModel;

class PaymentService
{
    public function paymentPayload(array $order, array $channel): array
    {
        $code = (string)($order['channel'] ?? '');
        $config = json_decode((string)($channel['config_json'] ?? '{}'), true) ?: [];
        if ($code === 'epay') {
            return ['mode'=>'redirect', 'url'=>$this->epayUrl($order, $config), 'note'=>'将跳转到易支付收银台完成支付。'];
        }
        if ($code === 'alipay_qrcode') {
            return $this->alipayPrecreatePayload($order, $config, 'alipay_qrcode');
        }
        if ($code === 'alipay_official') {
            return ['mode'=>'pending', 'url'=>'', 'note'=>'支付宝电脑网站支付已创建订单；当前先保留配置与回调，后续可继续接入页面跳转支付请求签名。'];
        }
        return ['mode'=>'pending', 'url'=>'', 'note'=>'该官方支付通道已创建订单，请在后台补充商户参数后接入官方签名发起流程。'];
    }

    private function epayUrl(array $order, array $config): string
    {
        $gateway = rtrim((string)($config['gateway'] ?? ''), '/');
        if ($gateway === '') return '';
        $pid = (string)($config['pid'] ?: ($config['merchant_id'] ?? ''));
        $key = (string)($config['api_key'] ?? '');
        $params = [
            'pid'=>$pid,
            'type'=>(string)($config['pay_type'] ?? 'alipay'),
            'out_trade_no'=>(string)$order['order_no'],
            'notify_url'=>$config['notify_url'] ?: $this->origin() . '/index.php?path=payment/notify&channel=epay',
            'return_url'=>$config['return_url'] ?: $this->origin() . '/index.php?path=payment/return&order_no=' . urlencode((string)$order['order_no']),
            'name'=>(string)($order['title'] ?? '钱包充值'),
            'money'=>number_format((float)$order['pay_amount'], 2, '.', ''),
        ];
        if ($key !== '') $params['sign'] = $this->epaySign($params, $key);
        if ($key !== '') $params['sign_type'] = 'MD5';
        return $gateway . '/submit.php?' . http_build_query($params);
    }

    public function verifyEpay(array $params, string $key): bool
    {
        if ($key === '' || empty($params['sign'])) return false;
        $sign = (string)$params['sign'];
        unset($params['sign'], $params['sign_type'], $params['path'], $params['channel']);
        return hash_equals($sign, $this->epaySign($params, $key));
    }

    public function verifyAlipay(array $params, string $publicKey): bool
    {
        if ($publicKey === '' || empty($params['sign'])) return false;
        $sign = (string)$params['sign'];
        unset($params['sign'], $params['sign_type'], $params['path'], $params['channel']);
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value !== '' && $value !== null && is_scalar($value)) {
                $pairs[] = $key . '=' . $value;
            }
        }
        $pub = $this->formatPublicKey($publicKey);
        return openssl_verify(implode('&', $pairs), base64_decode($sign), $pub, OPENSSL_ALGO_SHA256) === 1;
    }

    public function syncAlipayOrder(array $order, array $channel): bool
    {
        $config = json_decode((string)($channel['config_json'] ?? '{}'), true) ?: [];
        $code = (string)($order['channel'] ?? 'alipay_qrcode');
        $appId = (string)($config['app_id'] ?? '');
        $privateKey = (string)($config['app_private_key'] ?? '');
        $gateway = (string)($config['gateway'] ?? 'https://openapi.alipay.com/gateway.do');
        if ($appId === '' || $privateKey === '' || ($order['status'] ?? '') !== 'pending') return false;
        $params = [
            'app_id' => $appId,
            'method' => 'alipay.trade.query',
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => (string)($config['sign_type'] ?? 'RSA2') ?: 'RSA2',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'biz_content' => json_encode(['out_trade_no'=>(string)$order['order_no']], JSON_UNESCAPED_UNICODE),
        ];
        $sign = $this->alipaySign($params, $privateKey, $params['sign_type']);
        if ($sign === '') return false;
        $params['sign'] = $sign;
        $body = $this->postForm($this->alipayGatewayUrl($gateway, (string)$params['charset']), $params, $httpError);
        $json = json_decode($this->safeText($body), true) ?: [];
        $response = $json['alipay_trade_query_response'] ?? [];
        $status = (string)($response['trade_status'] ?? '');
        if (in_array($status, ['TRADE_SUCCESS','TRADE_FINISHED'], true)) {
            (new PaymentModel())->markPaid((string)$order['order_no'], (string)($response['trade_no'] ?? ''), json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return true;
        }
        if ($status !== '' || !empty($response['sub_code']) || $httpError !== '') {
            $this->logPaymentError($code, (string)$order['order_no'], '支付宝主动查单未支付', ['http_error'=>$httpError, 'response'=>$response]);
        }
        return false;
    }

    private function alipayPrecreatePayload(array $order, array $config, string $channel): array
    {
        $appId = (string)($config['app_id'] ?? '');
        $privateKey = (string)($config['app_private_key'] ?? '');
        $gateway = (string)($config['gateway'] ?? 'https://openapi.alipay.com/gateway.do');
        if ($appId === '' || $privateKey === '') {
            return ['mode'=>'pending', 'url'=>'', 'note'=>'支付宝当面付需要先在后台配置 AppID、应用私钥、支付宝公钥和异步通知地址。'];
        }
        $params = [
            'app_id' => $appId,
            'method' => 'alipay.trade.precreate',
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => (string)($config['sign_type'] ?? 'RSA2') ?: 'RSA2',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'notify_url' => (string)($config['notify_url'] ?: $this->origin() . '/index.php?path=payment/notify&channel=' . rawurlencode($channel)),
            'biz_content' => json_encode([
                'out_trade_no' => (string)$order['order_no'],
                'total_amount' => number_format((float)$order['pay_amount'], 2, '.', ''),
                'subject' => (string)($order['title'] ?? '钱包充值'),
            ], JSON_UNESCAPED_UNICODE),
        ];
        $sign = $this->alipaySign($params, $privateKey, $params['sign_type']);
        if ($sign === '') {
            $message = '支付宝当面付签名失败：请检查应用私钥格式是否正确，且与 AppID 对应。';
            $this->logPaymentError($channel, (string)$order['order_no'], $message, ['gateway'=>$gateway]);
            return ['mode'=>'pending', 'url'=>'', 'note'=>$message];
        }
        $params['sign'] = $sign;
        $body = $this->postForm($this->alipayGatewayUrl($gateway, (string)$params['charset']), $params, $httpError);
        if ($body === '') {
            $message = '支付宝当面付请求失败：' . ($httpError ?: '网关无响应');
            $this->logPaymentError($channel, (string)$order['order_no'], $message, ['gateway'=>$gateway]);
            return ['mode'=>'pending', 'url'=>'', 'note'=>$message];
        }
        $decodedBody = $this->safeText($body);
        $json = json_decode($decodedBody, true) ?: [];
        if (!$json) {
            $message = '支付宝当面付返回内容无法解析';
            $this->logPaymentError($channel, (string)$order['order_no'], $message, [
                'gateway' => $gateway,
                'http_error' => $httpError,
                'body_preview' => mb_substr($decodedBody, 0, 1200),
                'body_base64_preview' => substr(base64_encode(substr($body, 0, 900)), 0, 1200),
            ]);
            return ['mode'=>'pending', 'url'=>'', 'note'=>$message . '，请到后台回调日志查看网关原始返回。'];
        }
        $response = $json['alipay_trade_precreate_response'] ?? [];
        if (($response['code'] ?? '') === '10000' && !empty($response['qr_code'])) {
            return ['mode'=>'qrcode_text', 'url'=>(string)$response['qr_code'], 'note'=>'请使用支付宝扫码完成支付，到账结果以支付宝异步通知为准。'];
        }
        $message = '支付宝当面付预下单失败';
        if (!empty($response['sub_code']) || !empty($response['sub_msg'])) {
            $message .= '：' . trim((string)($response['sub_code'] ?? '') . ' ' . (string)($response['sub_msg'] ?? ''));
        } elseif (!empty($response['code']) || !empty($response['msg'])) {
            $message .= '：' . trim((string)($response['code'] ?? '') . ' ' . (string)($response['msg'] ?? ''));
        }
        $this->logPaymentError($channel, (string)$order['order_no'], $message, ['response'=>$response, 'raw'=>$json]);
        return ['mode'=>'pending', 'url'=>'', 'note'=>$message];
    }

    private function alipaySign(array $params, string $privateKey, string $signType = 'RSA2'): string
    {
        unset($params['sign']);
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value !== '' && $value !== null) {
                $pairs[] = $key . '=' . $value;
            }
        }
        $algo = strtoupper($signType) === 'RSA' ? OPENSSL_ALGO_SHA1 : OPENSSL_ALGO_SHA256;
        $ok = @openssl_sign(implode('&', $pairs), $signature, $this->formatPrivateKey($privateKey), $algo);
        return $ok ? base64_encode($signature) : '';
    }

    private function alipayGatewayUrl(string $gateway, string $charset): string
    {
        $separator = str_contains($gateway, '?') ? '&' : '?';
        if (preg_match('/(?:^|[?&])charset=/i', $gateway)) {
            return $gateway;
        }
        return $gateway . $separator . 'charset=' . rawurlencode($charset ?: 'utf-8');
    }

    private function postForm(string $url, array $params, ?string &$error = null): string
    {
        $error = '';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($params, '', '&', PHP_QUERY_RFC3986),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $body = curl_exec($ch);
            if ($body === false) {
                $error = curl_error($ch);
                curl_close($ch);
                return '';
            }
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($status >= 400) {
                $error = 'HTTP ' . $status;
                return (string)$body;
            }
            return (string)$body;
        }
        $context = stream_context_create(['http'=>[
            'method'=>'POST',
            'header'=>'Content-Type: application/x-www-form-urlencoded',
            'content'=>http_build_query($params, '', '&', PHP_QUERY_RFC3986),
            'timeout'=>12,
            'ignore_errors'=>true,
        ]]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            $last = error_get_last();
            $error = (string)($last['message'] ?? '请求失败');
            return '';
        }
        return (string)$body;
    }

    private function logPaymentError(string $channel, string $orderNo, string $message, array $context = []): void
    {
        try {
            (new PaymentModel())->logCallback($channel, $orderNo, 'failed', $message . "\n" . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        } catch (\Throwable $e) {}
    }

    private function safeText(string $text): string
    {
        if (mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }
        return mb_convert_encoding($text, 'UTF-8', 'UTF-8,GBK,GB2312,BIG5,ISO-8859-1');
    }

    private function formatPrivateKey(string $key): string
    {
        $key = trim($key);
        if (str_contains($key, 'BEGIN')) return $key;
        return "-----BEGIN PRIVATE KEY-----\n" . chunk_split($key, 64, "\n") . "-----END PRIVATE KEY-----";
    }

    private function formatPublicKey(string $key): string
    {
        $key = trim($key);
        if (str_contains($key, 'BEGIN')) return $key;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split($key, 64, "\n") . "-----END PUBLIC KEY-----";
    }

    private function epaySign(array $params, string $key): string
    {
        ksort($params);
        $pairs=[];
        foreach ($params as $k=>$v) if ($v !== '' && $v !== null) $pairs[] = $k . '=' . $v;
        return md5(implode('&', $pairs) . $key);
    }

    private function origin(): string
    {
        try {
            $siteUrl = trim((string)(new SettingModel())->get('site_url', ''));
            if ($siteUrl !== '') {
                return rtrim($siteUrl, '/');
            }
        } catch (\Throwable $e) {}
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        return ($https ? 'https://' : 'http://') . (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}
