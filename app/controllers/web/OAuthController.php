<?php

namespace App\Controllers\Web;

use App\Models\UserModel;
use App\Models\UserOAuthModel;
use App\Services\OAuthService;
use App\Services\TaskService;

class OAuthController
{
    public function redirect(): void
    {
        $provider = trim((string)($_GET['provider'] ?? ''));
        try {
            $state = bin2hex(random_bytes(16));
            $_SESSION['oauth_state'][$provider] = $state;
            $_SESSION['oauth_redirect_after'] = $this->normalizeLocalRedirect((string)($_GET['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? '/index.php?path=me')));
            header('Location: ' . (new OAuthService())->authorizeUrl($provider, $state));
            exit;
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: /index.php?path=login');
            exit;
        }
    }

    public function callback(): void
    {
        $provider = trim((string)($_GET['provider'] ?? ''));
        $code = trim((string)($_GET['code'] ?? ''));
        $state = trim((string)($_GET['state'] ?? ''));
        $expected = (string)($_SESSION['oauth_state'][$provider] ?? '');
        unset($_SESSION['oauth_state'][$provider]);
        if ($provider === '' || $code === '' || $expected === '' || !hash_equals($expected, $state)) {
            $_SESSION['flash_error'] = '第三方登录状态已失效，请重试。';
            header('Location: /index.php?path=login');
            exit;
        }

        try {
            $service = new OAuthService();
            $profile = $service->fetchProfile($provider, $code);
            $oauth = new UserOAuthModel();
            $bound = $oauth->byProviderAccount($provider, (string)$profile['openid']);
            $tokenJson = (string)($profile['token_json'] ?? '');
            unset($profile['token_json']);

            if (auth_check()) {
                $userId = (int)auth_user()['id'];
                if ($bound && (int)$bound['user_id'] !== $userId) {
                    throw new \RuntimeException('该第三方账号已绑定其他用户。');
                }
                $oauth->bind($userId, $provider, $profile, $tokenJson);
                $this->afterBind($userId, $provider);
                $_SESSION['flash_success'] = '登录方式已绑定。';
                header('Location: /index.php?path=oauth/bindings');
                exit;
            }

            if ($bound) {
                $oauth->touchLogin((int)$bound['id'], $tokenJson);
                $user = (new UserModel())->refreshAuthUser((int)$bound['user_id']);
                if (!$user || ($user['status'] ?? '') !== 'active') throw new \RuntimeException('账号不可用，请联系站点工作人员。');
                auth_login($user);
                try { (new TaskService())->syncStateTasks((int)$user['id']); } catch (\Throwable $e) {}
                $redirect = $this->normalizeLocalRedirect((string)($_SESSION['oauth_redirect_after'] ?? '/index.php?path=me'));
                unset($_SESSION['oauth_redirect_after']);
                header('Location: ' . $redirect);
                exit;
            }

            $_SESSION['oauth_pending'] = ['provider' => $provider, 'profile' => $profile, 'token_json' => $tokenJson, 'created_at' => time()];
            header('Location: /index.php?path=oauth/complete');
            exit;
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = '第三方登录失败：' . $e->getMessage();
            header('Location: /index.php?path=login');
            exit;
        }
    }

    public function complete(): void
    {
        if (auth_check()) { header('Location: /index.php?path=oauth/bindings'); exit; }
        $pending = $_SESSION['oauth_pending'] ?? null;
        if (!$this->validPending($pending)) { header('Location: /index.php?path=login'); exit; }
        $error = '';
        $provider = (string)$pending['provider'];
        $profile = (array)$pending['profile'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $mode = (string)($_POST['mode'] ?? 'register');
            try {
                if ($mode === 'bind') {
                    $account = trim((string)($_POST['account'] ?? ''));
                    $password = trim((string)($_POST['password'] ?? ''));
                    $user = (new UserModel())->findByAccount($account);
                    if (!$user || (!hash_equals((string)$user['password'], $password) && !password_verify($password, (string)$user['password']))) {
                        throw new \RuntimeException('账号或密码错误');
                    }
                    $userId = (int)$user['id'];
                } else {
                    $nickname = trim((string)($_POST['nickname'] ?? ($profile['nickname'] ?? '')));
                    if ($nickname === '') $nickname = '第三方用户';
                    $username = $this->uniqueUsername($provider);
                    $email = trim((string)($profile['email'] ?? ''));
                    if ($email === '' || (new UserModel())->existsByEmail($email)) {
                        $email = $username . '@oauth.local';
                    }
                    $userId = (new UserModel())->create(['username' => $username, 'nickname' => $nickname, 'email' => $email, 'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'bio' => '新注册用户', 'email_verified' => 1]);
                }
                (new UserOAuthModel())->bind($userId, $provider, $profile, (string)($pending['token_json'] ?? ''));
                $this->afterBind($userId, $provider);
                $user = (new UserModel())->refreshAuthUser($userId);
                if ($user) auth_login($user);
                unset($_SESSION['oauth_pending']);
                header('Location: /index.php?path=me');
                exit;
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }
        $providers = OAuthService::PROVIDERS;
        require theme_view('web/user/oauth_complete.php');
    }

    public function bindings(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        $service = new OAuthService();
        $providers = $service->providers();
        $bindings = [];
        foreach ((new UserOAuthModel())->byUser((int)auth_user()['id']) as $row) $bindings[(string)$row['provider']] = $row;
        $success = (string)($_SESSION['flash_success'] ?? ''); unset($_SESSION['flash_success']);
        $error = (string)($_SESSION['flash_error'] ?? ''); unset($_SESSION['flash_error']);
        require theme_view('web/user/oauth_bindings.php');
    }

    public function unbind(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        csrf_verify();
        $provider = trim((string)($_POST['provider'] ?? ''));
        (new UserOAuthModel())->unbind((int)auth_user()['id'], $provider);
        $_SESSION['flash_success'] = '登录方式已解绑。';
        header('Location: /index.php?path=oauth/bindings');
        exit;
    }

    private function afterBind(int $userId, string $provider): void
    {
        try { (new TaskService())->recordAction($userId, 'oauth_bound', 'oauth', $userId); } catch (\Throwable $e) {}
        try { (new \App\Models\SystemMessageModel())->createPersonal($userId, '登录方式已绑定', '你已成功绑定第三方登录方式，可在账号设置中管理。', 0); } catch (\Throwable $e) {}
    }

    private function validPending(mixed $pending): bool
    {
        return is_array($pending) && !empty($pending['provider']) && !empty($pending['profile']['openid']) && (time() - (int)($pending['created_at'] ?? 0) < 1800);
    }

    private function uniqueUsername(string $provider): string
    {
        $model = new UserModel();
        do { $username = 'oauth_' . preg_replace('/[^a-z0-9]/', '', strtolower($provider)) . '_' . strtolower(bin2hex(random_bytes(4))); } while ($model->existsByUsername($username));
        return $username;
    }

    private function normalizeLocalRedirect(string $redirect): string
    {
        return normalize_local_redirect($redirect, '/index.php?path=me');
    }
}
