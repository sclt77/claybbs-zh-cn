<?php

namespace App\Services;

use App\Models\SettingModel;

class OAuthService
{
    public const PROVIDERS = [
        'qq' => ['name' => 'QQ', 'auth_url' => 'https://graph.qq.com/oauth2.0/authorize', 'token_url' => 'https://graph.qq.com/oauth2.0/token'],
        'github' => ['name' => 'GitHub', 'auth_url' => 'https://github.com/login/oauth/authorize', 'token_url' => 'https://github.com/login/oauth/access_token'],
        'wechat' => ['name' => '微信', 'auth_url' => 'https://open.weixin.qq.com/connect/qrconnect', 'token_url' => 'https://api.weixin.qq.com/sns/oauth2/access_token'],
        'rainbow' => ['name' => '彩虹聚合登录', 'auth_url' => '', 'token_url' => ''],
    ];

    public function providers(): array
    {
        $settings = (new SettingModel())->all();
        $out = [];
        foreach (self::PROVIDERS as $key => $meta) {
            $out[$key] = array_merge($meta, [
                'key' => $key,
                'enabled' => ($settings['oauth_' . $key . '_enabled'] ?? '0') === '1',
                'client_id' => (string)($settings['oauth_' . $key . '_client_id'] ?? ''),
                'client_secret' => (string)($settings['oauth_' . $key . '_client_secret'] ?? ''),
                'redirect_uri' => (string)($settings['oauth_' . $key . '_redirect_uri'] ?? ''),
                'base_url' => rtrim((string)($settings['oauth_' . $key . '_base_url'] ?? ''), '/'),
            ]);
        }
        return $out;
    }

    public function provider(string $provider): array
    {
        $providers = $this->providers();
        if (!isset($providers[$provider])) {
            throw new \RuntimeException('不支持的登录方式');
        }
        return $providers[$provider];
    }

    public function enabledProviders(): array
    {
        return array_filter($this->providers(), static fn(array $p) => !empty($p['enabled']) && trim((string)$p['client_id']) !== '');
    }

    public function authorizeUrl(string $provider, string $state): string
    {
        $cfg = $this->provider($provider);
        if (empty($cfg['enabled'])) throw new \RuntimeException('该登录方式未开启');
        $redirect = $this->redirectUri($provider, $cfg);
        if ($provider === 'rainbow') {
            $base = $cfg['base_url'] !== '' ? $cfg['base_url'] : $cfg['auth_url'];
            if ($base === '') throw new \RuntimeException('彩虹聚合登录地址未配置');
            return $base . '/connect.php?' . http_build_query(['act' => 'login', 'appid' => $cfg['client_id'], 'appkey' => $cfg['client_secret'], 'type' => 'qq', 'redirect_uri' => $redirect, 'state' => $state]);
        }
        $params = ['client_id' => $cfg['client_id'], 'redirect_uri' => $redirect, 'state' => $state, 'response_type' => 'code'];
        if ($provider === 'github') $params['scope'] = 'read:user user:email';
        if ($provider === 'qq') $params['scope'] = 'get_user_info';
        if ($provider === 'wechat') $params['scope'] = 'snsapi_login';
        return $cfg['auth_url'] . '?' . http_build_query($params);
    }

    public function fetchProfile(string $provider, string $code): array
    {
        $cfg = $this->provider($provider);
        if (empty($cfg['enabled'])) throw new \RuntimeException('该登录方式未开启');
        return match ($provider) {
            'github' => $this->fetchGithub($cfg, $code),
            'qq' => $this->fetchQQ($cfg, $code),
            'wechat' => $this->fetchWechat($cfg, $code),
            'rainbow' => $this->fetchRainbow($cfg, $code),
            default => throw new \RuntimeException('不支持的登录方式'),
        };
    }

    private function fetchGithub(array $cfg, string $code): array
    {
        $token = $this->httpJson($cfg['token_url'], [
            'client_id' => $cfg['client_id'], 'client_secret' => $cfg['client_secret'], 'code' => $code, 'redirect_uri' => $this->redirectUri('github', $cfg),
        ], ['Accept: application/json']);
        $access = (string)($token['access_token'] ?? '');
        if ($access === '') throw new \RuntimeException('GitHub 登录授权失败');
        $user = $this->httpGetJson('https://api.github.com/user', ['Authorization: Bearer ' . $access, 'User-Agent: ClayBBS']);
        $email = (string)($user['email'] ?? '');
        if ($email === '') {
            $emails = $this->httpGetJson('https://api.github.com/user/emails', ['Authorization: Bearer ' . $access, 'User-Agent: ClayBBS']);
            if (is_array($emails)) foreach ($emails as $row) if (!empty($row['primary']) && !empty($row['email'])) { $email = (string)$row['email']; break; }
        }
        return ['openid' => (string)($user['id'] ?? ''), 'nickname' => (string)($user['name'] ?: ($user['login'] ?? 'GitHub 用户')), 'avatar' => (string)($user['avatar_url'] ?? ''), 'email' => $email, 'token_json' => json_encode($token, JSON_UNESCAPED_UNICODE)];
    }

    private function fetchQQ(array $cfg, string $code): array
    {
        $raw = $this->httpGet($cfg['token_url'] . '?' . http_build_query(['grant_type' => 'authorization_code', 'client_id' => $cfg['client_id'], 'client_secret' => $cfg['client_secret'], 'code' => $code, 'redirect_uri' => $this->redirectUri('qq', $cfg)]));
        parse_str($raw, $token);
        $access = (string)($token['access_token'] ?? '');
        if ($access === '') throw new \RuntimeException('QQ 登录授权失败');
        $openidRaw = $this->httpGet('https://graph.qq.com/oauth2.0/me?' . http_build_query(['access_token' => $access]));
        if (!preg_match('/\{.*\}/s', $openidRaw, $m)) throw new \RuntimeException('QQ OpenID 获取失败');
        $openidData = json_decode($m[0], true) ?: [];
        $openid = (string)($openidData['openid'] ?? '');
        $info = $this->httpGetJson('https://graph.qq.com/user/get_user_info?' . http_build_query(['access_token' => $access, 'oauth_consumer_key' => $cfg['client_id'], 'openid' => $openid]));
        return ['openid' => $openid, 'nickname' => (string)($info['nickname'] ?? 'QQ 用户'), 'avatar' => (string)($info['figureurl_qq_2'] ?? ($info['figureurl_qq_1'] ?? '')), 'email' => '', 'token_json' => json_encode($token, JSON_UNESCAPED_UNICODE)];
    }

    private function fetchWechat(array $cfg, string $code): array
    {
        $token = $this->httpGetJson($cfg['token_url'] . '?' . http_build_query(['appid' => $cfg['client_id'], 'secret' => $cfg['client_secret'], 'code' => $code, 'grant_type' => 'authorization_code']));
        $access = (string)($token['access_token'] ?? ''); $openid = (string)($token['openid'] ?? '');
        if ($access === '' || $openid === '') throw new \RuntimeException('微信登录授权失败');
        $info = $this->httpGetJson('https://api.weixin.qq.com/sns/userinfo?' . http_build_query(['access_token' => $access, 'openid' => $openid, 'lang' => 'zh_CN']));
        return ['openid' => $openid, 'unionid' => (string)($token['unionid'] ?? ($info['unionid'] ?? '')), 'nickname' => (string)($info['nickname'] ?? '微信用户'), 'avatar' => (string)($info['headimgurl'] ?? ''), 'email' => '', 'token_json' => json_encode($token, JSON_UNESCAPED_UNICODE)];
    }

    private function fetchRainbow(array $cfg, string $code): array
    {
        $base = $cfg['base_url'];
        if ($base === '') throw new \RuntimeException('彩虹聚合登录地址未配置');
        $data = $this->httpGetJson($base . '/connect.php?' . http_build_query(['act' => 'callback', 'appid' => $cfg['client_id'], 'appkey' => $cfg['client_secret'], 'code' => $code]));
        $openid = (string)($data['social_uid'] ?? ($data['openid'] ?? ($data['uid'] ?? '')));
        if ($openid === '') throw new \RuntimeException('彩虹聚合登录返回信息不完整');
        return ['openid' => $openid, 'nickname' => (string)($data['nickname'] ?? $data['name'] ?? '第三方用户'), 'avatar' => (string)($data['faceimg'] ?? $data['avatar'] ?? ''), 'email' => (string)($data['email'] ?? ''), 'token_json' => json_encode($data, JSON_UNESCAPED_UNICODE)];
    }

    private function redirectUri(string $provider, array $cfg): string
    {
        if (!empty($cfg['redirect_uri'])) return (string)$cfg['redirect_uri'];
        $site = rtrim((new SettingModel())->get('site_url', '') ?: $this->currentOrigin(), '/');
        return $site . '/index.php?path=oauth/callback&provider=' . urlencode($provider);
    }

    private function currentOrigin(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        return ($https ? 'https://' : 'http://') . (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    private function httpJson(string $url, array $post, array $headers = []): array
    {
        $body = $this->httpRequest($url, $headers, http_build_query($post));
        $json = json_decode($body, true);
        if (is_array($json)) return $json;
        parse_str($body, $arr);
        return is_array($arr) ? $arr : [];
    }

    private function httpGetJson(string $url, array $headers = []): array
    {
        $json = json_decode($this->httpRequest($url, $headers), true);
        return is_array($json) ? $json : [];
    }

    private function httpGet(string $url): string { return $this->httpRequest($url); }

    private function httpRequest(string $url, array $headers = [], ?string $post = null): string
    {
        if (!function_exists('curl_init')) throw new \RuntimeException('服务器未启用 cURL，无法使用第三方登录');
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code >= 400) throw new \RuntimeException('第三方接口请求失败' . ($err ? '：' . $err : ''));
        return (string)$body;
    }
}
