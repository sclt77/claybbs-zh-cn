<?php

namespace App\Controllers\Web;

use App\Models\UserModel;
use App\Core\Mailer;

class ForgotPasswordController
{
    public function index(): void
    {
        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();

            if (isset($_POST['email'])) {
                $email = trim($_POST['email']);

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = '请输入有效的邮箱地址';
                } else {
                    try {
                        $userModel = new UserModel();
                        $user = $userModel->findByEmail($email);

                        if ($user) {
                            
                            $token = bin2hex(random_bytes(32));
                            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                            
                            $userModel->setPasswordResetToken($user['id'], $token, $expires);

                            
                            $siteUrl = $this->siteUrl();
                            $resetUrl = $siteUrl . '/reset-password?token=' . urlencode($token);

                            $subject = '重置您的密码 - ClayBBS';
                            $html = '<p>您好，</p>' .
                                '<p>您请求重置密码。请点击下方链接重置您的密码：</p>' .
                                '<p><a href="' . $resetUrl . '">' . $resetUrl . '</a></p>' .
                                '<p>此链接1小时内有效。</p>' .
                                '<p>如果您没有请求重置密码，请忽略此邮件。</p>';

                            try {
                                (new Mailer())->send($email, $user['nickname'] ?? $user['username'], $subject, $html);
                                $success = '重置邮件已发送到您的邮箱，请查收并按邮件中的链接重置密码。';
                            } catch (\Throwable $mailErr) {
                                error_log('[ClayBBS] 发送重置邮件失败: ' . $mailErr->getMessage());
                                
                                $success = '重置邮件已发送到您的邮箱，请查收并按邮件中的链接重置密码。';
                            }
                        } else {
                            
                            $success = '重置邮件已发送到您的邮箱，请查收并按邮件中的链接重置密码。';
                        }
                    } catch (\Throwable $e) {
                        error_log('[ClayBBS] 忘记密码处理错误: ' . $e->getMessage());
                        $error = '处理请求时出错，请稍后再试';
                    }
                }
            } else {
                $error = '请输入邮箱地址';
            }
        }

        require theme_view('web/user/forgot_password.php');
    }

    private function siteUrl(): string
    {
        try {
            $configured = trim((string)(new \App\Models\SettingModel())->get('site_url', ''));
            if ($this->isUsableSiteUrl($configured)) {
                return rtrim($configured, '/');
            }
        } catch (\Throwable $e) {}

        try {
            $cfg = require dirname(__DIR__, 3) . '/config/app.php';
            $configured = trim((string)($cfg['url'] ?? ''));
            if ($this->isUsableSiteUrl($configured)) {
                return rtrim($configured, '/');
            }
        } catch (\Throwable $e) {}

        $host = trim((string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
        $host = preg_replace('/[,\s].*$/', '', $host) ?: '';
        if ($host === '' || in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)) {
            $host = 'ovo.claybbs.com';
        }

        $scheme = app_is_https() ? 'https' : 'http';
        return $scheme . '://' . $host;
    }

    private function isUsableSiteUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
        return $host !== '' && !in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
