<?php

namespace App\Services;

use App\Models\SettingModel;

class RegistrationGuard
{
    private RateLimiter $limiter;
    private string $ip;
    private array $settings;

    private array $blockedDomains = [
        '10minutemail.com',
        '20minutemail.com',
        '33mail.com',
        'anonaddy.com',
        'burnermail.io',
        'dispostable.com',
        'emailondeck.com',
        'fakeinbox.com',
        'getnada.com',
        'guerrillamail.com',
        'guerrillamail.net',
        'maildrop.cc',
        'mailinator.com',
        'moakt.com',
        'sharklasers.com',
        'tempmail.com',
        'temp-mail.org',
        'throwawaymail.com',
        'trashmail.com',
        'yopmail.com',
    ];

    public function __construct(?RateLimiter $limiter = null, ?array $settings = null)
    {
        $this->limiter = $limiter ?: new RateLimiter();
        $this->ip = $this->limiter->ip();
        $this->settings = $settings ?? (new SettingModel())->all();
    }

    public function ip(): string
    {
        return $this->ip;
    }

    

    public function checkWeb(array $post): ?string
    {
        $common = $this->checkCommon($post, false);
        if ($common !== null) {
            return $common;
        }

        $renderedAt = (int)($post['register_rendered_at'] ?? 0);
        if ($renderedAt <= 0 || time() - $renderedAt < 4) {
            return '提交过快，请稍后再试';
        }

        return null;
    }

    

    public function checkApi(array $post): ?string
    {
        return $this->checkCommon($post, true);
    }

    private function checkCommon(array $post, bool $api): ?string
    {
        if (($this->settings['register_enabled'] ?? '1') === '0') {
            return '注册暂时关闭';
        }

        $email = strtolower(trim((string)($post['email'] ?? '')));
        $username = trim((string)($post['username'] ?? ''));
        $nickname = trim((string)($post['nickname'] ?? ''));

        if (!$this->checkLimits($email)) {
            return '注册请求过于频繁，请稍后再试';
        }

        $honeypot = trim((string)($post['website'] ?? $post['homepage'] ?? $post['url'] ?? ''));
        if ($honeypot !== '') {
            return '注册信息异常，请重新提交';
        }

        if ($email !== '' && $this->isBlockedEmailDomain($email)) {
            return '该邮箱暂不支持注册，请更换常用邮箱';
        }

        if ($this->looksGeneratedName($username) && ($nickname === '' || $this->looksGeneratedName($nickname))) {
            return '用户名或昵称过于随机，请换一个更容易识别的名称';
        }

        if ($api && !$this->isTrustedApiClient()) {
            $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
            if ($ua === '' || preg_match('/(curl|python|httpclient|okhttp|scrapy|bot|spider|crawler)/i', $ua)) {
                return '客户端环境异常，请使用网页完成注册';
            }
        }

        return null;
    }

    private function checkLimits(string $email): bool
    {
        $emailKey = $email !== '' ? strtolower($email) : 'empty';
        $checks = [
            new RateLimiter(10, 300),     
            new RateLimiter(30, 86400),   
            new RateLimiter(3, 86400),    
        ];

        return $checks[0]->check('forum_register_ip_5m:' . $this->ip)
            && $checks[1]->check('forum_register_ip_day:' . $this->ip)
            && $checks[2]->check('forum_register_email_day:' . $emailKey);
    }

    private function isBlockedEmailDomain(string $email): bool
    {
        $domain = substr(strrchr($email, '@') ?: '', 1);
        if ($domain === '') {
            return false;
        }
        $domain = strtolower($domain);
        $extra = trim((string)($this->settings['register_blocked_email_domains'] ?? ''));
        $blocked = $this->blockedDomains;
        if ($extra !== '') {
            foreach (preg_split('/[\r\n,;\s]+/', $extra) ?: [] as $item) {
                $item = strtolower(trim($item));
                if ($item !== '') {
                    $blocked[] = $item;
                }
            }
        }
        foreach (array_unique($blocked) as $blockedDomain) {
            if ($domain === $blockedDomain || str_ends_with($domain, '.' . $blockedDomain)) {
                return true;
            }
        }
        return false;
    }

    private function looksGeneratedName(string $value): bool
    {
        $value = strtolower(trim($value));
        if (!preg_match('/^[a-z]{10,16}$/', $value)) {
            return false;
        }
        if (preg_match('/[aeiou]{2,}/', $value)) {
            return false;
        }
        if (preg_match('/(clay|bbs|admin|user|liu|sky)/', $value)) {
            return false;
        }
        return true;
    }

    private function isTrustedApiClient(): bool
    {
        $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        return $requestedWith === 'xmlhttprequest' || str_contains($accept, 'application/json');
    }
}
