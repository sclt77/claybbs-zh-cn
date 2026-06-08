<?php

namespace App\Controllers\Web;

use App\Models\UserModel;
use App\Models\FollowModel;
use App\Models\WalletModel;
use App\Models\NotificationSettingModel;
use App\Models\UserPrivacyModel;
use App\Models\LoginDeviceModel;
use App\Services\RateLimiter;
use App\Middleware\Permission;
use App\Core\Mailer;

class UserController
{
    public function login(): void
    {
        $error = (string)($_SESSION['flash_error'] ?? '');
        unset($_SESSION['flash_error']);
        if (($_GET['device'] ?? '') === 'revoked') {
            $error = '该设备登录已失效，请重新登录。';
        } elseif (($_GET['device'] ?? '') === 'banned') {
            $error = '该账号已被限制登录，请联系管理员。';
        }
        $oauthProviders = [];
        try { $oauthProviders = (new \App\Services\OAuthService())->enabledProviders(); } catch (\Throwable $e) {}

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $limiter = new RateLimiter(5, 300);
            $ip = $limiter->ip();
            $account = trim($_POST['account'] ?? '');
            if (!$limiter->check('forum_login_ip:' . $ip) || ($account !== '' && !$limiter->check('forum_login_account:' . strtolower($account)))) {
                http_response_code(429);
                $error = '登录过于频繁，请稍后再试';
            } else {
            $password = trim($_POST['password'] ?? '');

            try {
                $user = (new UserModel())->findByAccount($account);
                $legacyPasswordMatched = $user && hash_equals((string)$user['password'], $password);
                $hashedPasswordMatched = $user && password_verify($password, (string)$user['password']);
                if ($user && ($legacyPasswordMatched || $hashedPasswordMatched)) {
                    if (($user['status'] ?? 'active') !== 'active') {
                        $error = '该账号已被限制登录，请联系管理员。';
                    } elseif (!empty($user['banned_until']) && strtotime((string)$user['banned_until']) > time()) {
                        $error = '该账号暂时无法登录，解封时间：' . (string)$user['banned_until'];
                    } else {
                    
                    $settings = (new \App\Models\SettingModel())->all();
                    if (($settings['email_verify_required'] ?? '0') === '1' && empty($user['email_verified'])) {
                        $error = '请先验证你的邮箱后再登录，请检查注册邮件。';
                    } else {
                        if ($legacyPasswordMatched || ($hashedPasswordMatched && password_needs_rehash((string)$user['password'], PASSWORD_DEFAULT))) {
                            (new UserModel())->updatePasswordHash((int)$user['id'], password_hash($password, PASSWORD_DEFAULT));
                        }
                        $freshUser = (new UserModel())->refreshAuthUser((int) $user['id']);
                        auth_login($freshUser ?: $user);
                        try { (new \App\Services\TaskService())->syncStateTasks((int)$user['id']); } catch (\Throwable $e) {}
                        $redirect = $this->normalizeLocalRedirect((string)($_GET['redirect'] ?? '/index.php?path=me'));
                        header('Location: ' . $redirect);
                        exit;
                    }
                    }
                } else {
                    $error = '账号或密码错误';
                }
            } catch (\Throwable $e) {
                $error = '登录失败，请检查数据库配置';
            }
            }
        }

        require theme_view('web/user/login.php');
    }

    private function normalizeLocalRedirect(string $redirect): string
    {
        $redirect = trim($redirect);
        if ($redirect === '' || preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $redirect) || str_starts_with($redirect, '//')) {
            return '/index.php?path=me';
        }

        $parts = parse_url($redirect);
        $path = (string)($parts['path'] ?? '');
        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string)$parts['query'], $query);
        }

        if ($path === '' || $path === '/') {
            return '/index.php';
        }
        if (in_array($path, ['/index.php', '/admin.php', '/api.php', '/install.php'], true)) {
            return $redirect;
        }

        $route = ltrim($path, '/');
        $query = array_merge(['path' => $route], $query);
        return '/index.php?' . http_build_query($query);
    }

    public function register(): void
    {
        $error = '';
        $settings = (new \App\Models\SettingModel())->all();
        $verifyEnabled = ($settings['email_verify_required'] ?? '0') === '1';
        $old = [
            'username' => trim((string)($_POST['username'] ?? '')),
            'nickname' => trim((string)($_POST['nickname'] ?? '')),
            'email' => trim((string)($_POST['email'] ?? '')),
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $guard = new \App\Services\RegistrationGuard();
            $guardError = $guard->checkWeb($_POST);
            if ($guardError !== null) {
                http_response_code($guardError === '注册请求过于频繁，请稍后再试' ? 429 : 422);
                $error = $guardError;
            } else {
                $username = trim($_POST['username'] ?? '');
                $email    = trim($_POST['email'] ?? '');
                $nickname = trim($_POST['nickname'] ?? '');
                $password = trim($_POST['password'] ?? '');
                $emailCode = preg_replace('/\D+/', '', (string)($_POST['email_code'] ?? ''));

                if ($username === '' || $nickname === '' || $email === '' || $password === '') {
                    $error = '请填写完整注册信息';
                } elseif ($verifyEnabled && $emailCode === '') {
                    $error = '请填写邮箱验证码';
                } elseif (!preg_match('/^[A-Za-z0-9_]{2,30}$/', $username)) {
                    $error = '用户名只能包含字母、数字、下划线，长度2-30位';
                } elseif ($verifyEnabled && !$this->verifyRegisterCode($email, $emailCode)) {
                    $error = '邮箱验证码错误或已过期，请重新获取';
                } else {
                    try {
                        $userModel = new UserModel();
                        if ($userModel->existsByUsername($username)) {
                            $error = '该用户名已被注册';
                        } elseif ($userModel->existsByEmail($email)) {
                            $error = '该邮箱已被注册';
                        } else {
                            $id = $userModel->create([
                                'username'                => $username,
                                'nickname'               => $nickname,
                                'email'                  => $email,
                                'password'               => password_hash($password, PASSWORD_DEFAULT),
                                'bio'                    => '新注册用户',
                                'email_verified'         => 1,
                                'email_verify_token'     => null,
                                'email_verify_expires_at' => null,
                            ]);

                            unset($_SESSION['register_email_codes'][strtolower($email)]);
                            try { (new \App\Services\TaskService())->syncRegistrationStateTasks((int)$id); } catch (\Throwable $e) {}
                            $fresh = $userModel->find($id);
                            if ($fresh) auth_login($fresh);
                            header('Location: /index.php?path=me');
                            exit;
                        }
                    } catch (\Throwable $e) {
                        $error = '注册失败：' . $e->getMessage();
                    }
                }
            }
        }

        require theme_view('web/user/register.php');
    }

    public function sendRegisterCode(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => '请求方式错误'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $settings = (new \App\Models\SettingModel())->all();
        if (($settings['email_verify_required'] ?? '0') !== '1') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => '当前未开启邮箱验证'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => '请先填写正确的邮箱地址'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $userModel = new UserModel();
            if ($userModel->existsByEmail($email)) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'message' => '该邮箱已被注册'], JSON_UNESCAPED_UNICODE);
                return;
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => '邮箱检查失败，请稍后再试'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $limiter = new RateLimiter(3, 600);
        $ip = $limiter->ip();
        $emailKey = strtolower($email);
        if (!$limiter->check('register_email_code_ip:' . $ip) || !(new RateLimiter(3, 600))->check('register_email_code_email:' . $emailKey)) {
            http_response_code(429);
            echo json_encode(['ok' => false, 'message' => '验证码发送过于频繁，请稍后再试'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $now = time();
        $existing = $_SESSION['register_email_codes'][$emailKey] ?? null;
        if (is_array($existing) && isset($existing['sent_at']) && $now - (int)$existing['sent_at'] < 60) {
            http_response_code(429);
            echo json_encode(['ok' => false, 'message' => '请稍等 60 秒后再重新获取验证码'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $code = (string) random_int(100000, 999999);
        $_SESSION['register_email_codes'][$emailKey] = [
            'hash' => password_hash($code, PASSWORD_DEFAULT),
            'expires_at' => $now + 600,
            'sent_at' => $now,
            'attempts' => 0,
        ];

        $html = '<div style="font-family:Arial,Helvetica,sans-serif;line-height:1.8;color:#0f172a;">'
            . '<h2 style="margin:0 0 12px;">ClayBBS 注册验证码</h2>'
            . '<p>您的注册验证码为：</p>'
            . '<div style="font-size:28px;font-weight:800;letter-spacing:6px;color:#2563eb;margin:16px 0;">' . htmlspecialchars($code) . '</div>'
            . '<p>验证码 10 分钟内有效，请勿转发给他人。</p>'
            . '</div>';

        try {
            $sent = (new Mailer())->send($email, $email, 'ClayBBS 注册验证码', $html);
            if (!$sent) {
                unset($_SESSION['register_email_codes'][$emailKey]);
                http_response_code(500);
                echo json_encode(['ok' => false, 'message' => '邮件发送失败，请检查邮箱或稍后再试'], JSON_UNESCAPED_UNICODE);
                return;
            }
        } catch (\Throwable $e) {
            unset($_SESSION['register_email_codes'][$emailKey]);
            error_log('[ClayBBS] 注册验证码发送失败: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => '邮件发送失败，请稍后再试'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode(['ok' => true, 'message' => '验证码已发送，请查收邮箱'], JSON_UNESCAPED_UNICODE);
    }

    private function verifyRegisterCode(string $email, string $code): bool
    {
        $emailKey = strtolower(trim($email));
        if ($emailKey === '' || $code === '') {
            return false;
        }

        $data = $_SESSION['register_email_codes'][$emailKey] ?? null;
        if (!is_array($data)) {
            return false;
        }

        if ((int)($data['expires_at'] ?? 0) < time()) {
            unset($_SESSION['register_email_codes'][$emailKey]);
            return false;
        }

        $attempts = (int)($data['attempts'] ?? 0);
        if ($attempts >= 5) {
            unset($_SESSION['register_email_codes'][$emailKey]);
            return false;
        }

        if (!password_verify($code, (string)($data['hash'] ?? ''))) {
            $_SESSION['register_email_codes'][$emailKey]['attempts'] = $attempts + 1;
            return false;
        }

        return true;
    }

    public function verifyEmail(): void
    {
        $token = trim($_GET['token'] ?? '');
        if ($token === '') {
            header('Location: /index.php');
            exit;
        }
        $userModel = new UserModel();
        $user = $userModel->findByVerifyToken($token);
        if (!$user) {
            $error = '验证链接无效或已过期';
            require theme_view('web/user/verify_result.php');
            return;
        }
        
        if (!empty($user['email_verify_expires_at']) && strtotime($user['email_verify_expires_at']) < time()) {
            $error = '验证链接已过期，请重新注册或联系站点工作人员';
            require theme_view('web/user/verify_result.php');
            return;
        }
        $userModel->markEmailVerified((int) $user['id']);
        try { (new \App\Services\TaskService())->syncStateTasks((int)$user['id']); } catch (\Throwable $e) {}
        $fresh = $userModel->find((int) $user['id']);
        if ($fresh) auth_login($fresh);
        $success = '邮箱验证成功！';
        require theme_view('web/user/verify_result.php');
    }

    public function profile(): void
    {
        $userId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $model = new UserModel();
        $profileUser = null;
        $threads = [];
        $threadTotal = 0;
        $replyTotal = 0;
        $roleLabel = '普通用户';
        $followStats = ['followers' => 0, 'following' => 0, 'is_following' => false, 'can_follow' => true, 'can_view_following' => true, 'can_view_followers' => true];
        $isBlocked = false;

        try {
            $profileUser = $userId > 0 ? $model->find($userId) : null;
            if ($profileUser) {
                $threads = $model->threadsByUserId($userId, 12, 0);
                $threadTotal = $model->countThreadsByUserId($userId);
                $replyTotal = $model->countRepliesByUserId($userId);
                $roles = Permission::getUserRoles($userId);
                if (!empty($roles)) {
                    usort($roles, static fn($a, $b) => (int)($b['level'] ?? 0) <=> (int)($a['level'] ?? 0));
                    $roleLabel = (string)($roles[0]['name'] ?? '普通用户');
                } elseif (!empty($profileUser['role'])) {
                    $roleMap = ['superadmin' => '超级管理员', 'admin' => '管理员', 'moderator' => '版主', 'reviewer' => '审核员', 'user' => '普通用户'];
                    $roleLabel = $roleMap[(string)$profileUser['role']] ?? (string)$profileUser['role'];
                }
                $profileUser['role_label'] = $roleLabel;
                $verification = (new \App\Models\VerificationModel())->activeForUser($userId);
                if ($verification) { $profileUser['verification_name'] = $verification['verification_name']; $profileUser['verification_color'] = $verification['verification_color']; $profileUser['verification_description'] = $verification['verification_description'] ?? ''; }
                $followModel = new FollowModel();
                $viewerId = (int)(auth_user()['id'] ?? 0);
                $privacyModel = new UserPrivacyModel();
                $canViewFollowing = $privacyModel->canViewFollowing($userId, $viewerId);
                $canViewFollowers = $privacyModel->canViewFollowers($userId, $viewerId);
                $followStats = [
                    'followers' => $canViewFollowers ? $followModel->followerCount($userId) : 0,
                    'following' => $canViewFollowing ? $followModel->followingCount($userId) : 0,
                    'is_following' => $viewerId > 0 ? $followModel->isFollowing($viewerId, $userId) : false,
                    'can_follow' => $privacyModel->canFollow($userId, $viewerId),
                    'can_view_following' => $canViewFollowing,
                    'can_view_followers' => $canViewFollowers,
                ];
                $isBlocked = $viewerId > 0 ? (new \App\Models\BlockModel())->isBlocked($viewerId, $userId) : false;
            }
        } catch (\Throwable $e) {
            
        }

        require theme_view('web/user/profile.php');
    }

    public function center(): void
    {
        $authUser = auth_user();
        if (!$authUser) {
            header('Location: /index.php?path=login');
            exit;
        }

        $model = new UserModel();
        $tab   = $_GET['tab'] ?? 'threads';
        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 15;
        $offset = ($page - 1) * $limit;

        $userId   = (int) $authUser['id'];
        $threads  = [];
        $replies  = [];
        $total    = 0;
        $fullUser = null;
        $roleLabel = '普通用户';
        $threadCount = 0;
        $replyCount = 0;
        $favoriteCount = 0;
        $blockedUsers = [];
        $feedItems = [];
        $myBadges = [];
        $laterThreads = [];
        $laterCount = 0;
        $followStats = ['followers' => 0, 'following' => 0];
        $activeVerification = null;
        $latestVerificationRequest = null;

        try {
            $fullUser = $model->find($userId);
            $threadCount = $model->countThreadsByUserId($userId);
            $replyCount = $model->countRepliesByUserId($userId);
            $roles = Permission::getUserRoles($userId);
            if (!empty($roles)) {
                usort($roles, static fn($a, $b) => (int)($b['level'] ?? 0) <=> (int)($a['level'] ?? 0));
                $roleLabel = (string)($roles[0]['name'] ?? '普通用户');
            } elseif (!empty($fullUser['role'])) {
                $roleMap = ['superadmin' => '超级管理员', 'admin' => '管理员', 'moderator' => '版主', 'reviewer' => '审核员', 'user' => '普通用户'];
                $roleLabel = $roleMap[(string)$fullUser['role']] ?? (string)$fullUser['role'];
            }
            $fullUser['role_label'] = $roleLabel;
            $fullUser['thread_count'] = $threadCount;
            $fullUser['reply_count_stat'] = $replyCount;
            $verificationModel = new \App\Models\VerificationModel();
            $activeVerification = $verificationModel->activeForUser($userId);
            $latestVerificationRequest = $verificationModel->latestRequest($userId);
            if ($activeVerification) { $fullUser['verification_name'] = $activeVerification['verification_name']; $fullUser['verification_color'] = $activeVerification['verification_color']; $fullUser['verification_description'] = $activeVerification['verification_description'] ?? ''; }
            try { if (function_exists('clay_badges_for_user')) $myBadges = clay_badges_for_user($userId, 30); } catch (\Throwable $e) {}
            $followModel = new FollowModel();
            $followStats = ['followers' => $followModel->followerCount($userId), 'following' => $followModel->followingCount($userId)];
            if ($tab === 'replies') {
                $replies = $model->repliesByUserId($userId, $limit, $offset);
                $total   = $replyCount;
            } elseif ($tab === 'pending') {
                $threads = $model->pendingThreadsByUserId($userId, $limit, $offset);
                $total = $model->countPendingThreadsByUserId($userId);
            } elseif ($tab === 'favorites') {
                $engagementModel = new \App\Models\EngagementModel();
                $readingModel = new \App\Models\ReadingListModel();
                $favSubTab = (string)($_GET['fav'] ?? 'favorites');
                $favSubTab = in_array($favSubTab, ['favorites','later'], true) ? $favSubTab : 'favorites';
                $favoriteCount = $engagementModel->countFavoritesByUser($userId);
                $laterCount = $readingModel->countByUser($userId, 'later');
                if ($favSubTab === 'later') {
                    $threads = $readingModel->listByUser($userId, 'later', $limit, $offset);
                    $total = $laterCount;
                } else {
                    $threads = $engagementModel->favoritesByUser($userId, $limit, $offset);
                    $total = $favoriteCount;
                }
            } elseif ($tab === 'following_feed') {
                $feedModel = new \App\Models\FeedModel();
                $feedItems = $feedModel->followingFeed($userId, $limit, $offset);
                $total = $feedModel->countFollowingFeed($userId);
            } elseif ($tab === 'blocks') {
                $blockedUsers = (new \App\Models\BlockModel())->blocksByUser($userId, 100);
                $total = count($blockedUsers);
            } else {
                $threads = $model->threadsByUserId($userId, $limit, $offset);
                $total   = $threadCount;
            }
        } catch (\Throwable $e) {
            
        }

        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;

        require theme_view('web/user/center.php');
    }


    public function follows(): void
    {
        $userId = (int)($_GET['id'] ?? 0);
        $type = (string)($_GET['type'] ?? 'followers');
        $model = new UserModel();
        $profileUser = $userId > 0 ? $model->find($userId) : null;
        if (!$profileUser) {
            http_response_code(404);
            exit('用户不存在');
        }
        $followModel = new FollowModel();
        $kw = trim((string)($_GET['kw'] ?? ''));
        $viewer = auth_user();
        $viewerId = (int)($viewer['id'] ?? 0);
        $privacyModel = new UserPrivacyModel();
        $canView = $type === 'following' ? $privacyModel->canViewFollowing($userId, $viewerId) : $privacyModel->canViewFollowers($userId, $viewerId);
        if (!$canView) {
            $users = [];
            $privacyHidden = true;
            require theme_view('web/user/follows.php');
            return;
        }
        $users = $type === 'following' ? $followModel->following($userId, 100, $kw) : $followModel->followers($userId, 100, $kw);
        $followedUserMap = [];
        $mutualUserMap = [];
        if ($viewer && $users) {
            $ids = array_map(static fn($u) => (int)($u['user_id'] ?? 0), $users);
            $followedUserMap = $followModel->followingMap((int)$viewer['id'], $ids);
            $mutualUserMap = $followModel->followingMap($userId, $ids);
        }
        require theme_view('web/user/follows.php');
    }

    public function follow(): void
    {
        $authUser = auth_user();
        if (!$authUser) {
            header('Location: /index.php?path=login');
            exit;
        }
        csrf_verify();
        $targetId = (int)($_POST['user_id'] ?? 0);
        $action = (string)($_POST['action'] ?? 'follow');
        $model = new FollowModel();
        if ($action !== 'unfollow' && !(new UserPrivacyModel())->canFollow($targetId, (int)$authUser['id'])) {
            if (is_ajax_request()) ajax_error('对方暂不允许被关注');
            $_SESSION['flash_error'] = '对方暂不允许被关注';
            header('Location: ' . normalize_local_redirect((string)($_SERVER['HTTP_REFERER'] ?? ''), '/index.php?path=user&id=' . $targetId));
            exit;
        }
        if ($action === 'unfollow') {
            $model->unfollow((int)$authUser['id'], $targetId);
        } else {
            $model->follow((int)$authUser['id'], $targetId);
            try { (new \App\Services\TaskService())->recordAction((int)$authUser['id'], 'follow_user', 'user', $targetId); } catch (\Throwable $e) {}
            try {
                if ((new NotificationSettingModel())->enabled($targetId, 'fans')) {
                    (new \App\Models\SystemMessageModel())->createPersonal($targetId, '你有新的粉丝', user_display_name($authUser, '用户') . ' 关注了你。', 0);
                }
            } catch (\Throwable $e) {}
        }
        if (is_ajax_request()) {
            ajax_ok(['reload' => true]);
        }
        header('Location: ' . normalize_local_redirect((string)($_SERVER['HTTP_REFERER'] ?? ''), '/index.php?path=user&id=' . $targetId));
        exit;
    }

    public function settings(): void
    {
        $authUser = auth_user();
        if (!$authUser) { header('Location: /index.php?path=login'); exit; }
        require theme_view('web/user/settings.php');
    }


    public function privacySettings(): void
    {
        $authUser = auth_user();
        if (!$authUser) { header('Location: /index.php?path=login'); exit; }
        $model = new UserPrivacyModel();
        $userId = (int)$authUser['id'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $model->update($userId, $_POST);
            $_SESSION['flash_success'] = '隐私设置已保存。';
            header('Location: /index.php?path=settings/privacy');
            exit;
        }
        $settings = $model->get($userId);
        $flashSuccess = (string)($_SESSION['flash_success'] ?? '');
        unset($_SESSION['flash_success']);
        require theme_view('web/user/privacy_settings.php');
    }

    public function notificationSettings(): void
    {
        $authUser = auth_user();
        if (!$authUser) { header('Location: /index.php?path=login'); exit; }
        $model = new NotificationSettingModel();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $model->update((int)$authUser['id'], $_POST);
            header('Location: /index.php?path=notification-settings');
            exit;
        }
        $settings = $model->get((int)$authUser['id']);
        require theme_view('web/user/notification_settings.php');
    }

    public function devices(): void
    {
        $authUser = auth_user();
        if (!$authUser) { header('Location: /index.php?path=login'); exit; }
        $model = new LoginDeviceModel();
        $userId = (int)$authUser['id'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $model->revoke($userId, $id);
                $_SESSION['flash_success'] = '设备已退出登录。';
            }
            header('Location: /index.php?path=settings/devices');
            exit;
        }
        $devices = $model->rowsForUser($userId);
        $success = (string)($_SESSION['flash_success'] ?? ''); unset($_SESSION['flash_success']);
        require theme_view('web/user/devices.php');
    }

    public function wallet(): void
    {
        $authUser = auth_user();
        if (!$authUser) {
            header('Location: /index.php?path=login');
            exit;
        }
        $walletModel = new WalletModel();
        $balances = [];
        $transactions = [];
        $walletSummary = [];
        try {
            $userId = (int)$authUser['id'];
            $balances = $walletModel->balances($userId);
            $walletSummary = $walletModel->summary($userId);
            $walletCurrency = (string)($_GET['currency'] ?? '');
            $walletType = (string)($_GET['type'] ?? '');
            $transactions = $walletModel->transactions($userId, 100, $walletCurrency, $walletType);
        } catch (\Throwable $e) {}
        require theme_view('web/user/wallet.php');
    }

    public function editProfile(): void
    {
        $authUser = auth_user();
        if (!$authUser) {
            header('Location: /index.php?path=login');
            exit;
        }

        $error   = '';
        $success = '';
        $model   = new UserModel();
        $userId  = (int) $authUser['id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $nickname = trim($_POST['nickname'] ?? '');
            $bio      = trim($_POST['bio'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $confirm  = trim($_POST['confirm'] ?? '');
            $quickUpload = !empty($_POST['quick_upload']);
            $action = trim((string)($_POST['_action'] ?? ''));

            if ($action === 'remove_avatar' || $action === 'remove_cover') {
                try {
                    $oldPath = (string)($authUser[$action === 'remove_avatar' ? 'avatar' : 'cover'] ?? '');
                    $model->update($userId, [$action === 'remove_avatar' ? 'avatar' : 'cover' => '']);
                    $this->deleteLocalProfileImage($oldPath);
                    $fresh = $model->find($userId);
                    if ($fresh) auth_login($fresh);
                    header('Location: /index.php?path=me');
                    exit;
                } catch (\Throwable $e) {
                    $error = '更新失败：' . $e->getMessage();
                }
            }

            if ($nickname === '' && !$quickUpload) {
                $error = '昵称不能为空';
            } elseif ($password !== '' && $password !== $confirm) {
                $error = '两次密码不一致';
            } else {
                $data = ['nickname' => $nickname !== '' ? $nickname : (string)($authUser['nickname'] ?? ''), 'bio' => $bio];
                if ($password !== '') {
                    $data['password'] = password_hash($password, PASSWORD_DEFAULT);
                }
                try {
                    $avatar = $this->uploadProfileImage('avatar');
                    $cover = $this->uploadProfileImage('cover');
                    $oldAvatar = (string)($authUser['avatar'] ?? '');
                    $oldCover = (string)($authUser['cover'] ?? '');
                    if ($avatar !== '') { $data['avatar'] = $avatar; }
                    if ($cover !== '') { $data['cover'] = $cover; }
                    $model->update($userId, $data);
                    if ($avatar !== '') { $this->deleteLocalProfileImage($oldAvatar); }
                    if ($cover !== '') { $this->deleteLocalProfileImage($oldCover); }
                    
                    $fresh = $model->find($userId);
                    if ($fresh) auth_login($fresh);
                    if ($fresh && trim((string)($fresh['avatar'] ?? '')) !== '' && trim((string)($fresh['bio'] ?? '')) !== '') {
                        try { (new \App\Services\TaskService())->recordAction($userId, 'profile_completed', 'user', $userId); } catch (\Throwable $e) {}
                    }
                    if ($quickUpload) { header('Location: /index.php?path=me'); exit; }
                    $success = '资料已更新';
                } catch (\Throwable $e) {
                    $error = '更新失败：' . $e->getMessage();
                }
            }
        }

        $fullUser = $model->find($userId);
        require theme_view('web/user/edit.php');
    }

    private function uploadProfileImage(string $field): string
    {
        if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
            return '';
        }
        $size = (int)($_FILES[$field]['size'] ?? 0);
        if ($size <= 0 || $size > 2 * 1024 * 1024) {
            throw new \RuntimeException('图片大小不能超过 2MB');
        }
        $tmp = (string)$_FILES[$field]['tmp_name'];
        $info = @getimagesize($tmp);
        if ($info === false) {
            throw new \RuntimeException('上传文件不是有效图片');
        }
        $mimeToExt = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $mime = (string)($info['mime'] ?? '');
        if (!isset($mimeToExt[$mime])) {
            throw new \RuntimeException('只支持 png、jpg、jpeg、gif、webp 图片');
        }
        $ext = $mimeToExt[$mime];
        $dir = dirname(__DIR__, 3) . '/uploads/profiles';
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        $name = $field . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $name)) {
            throw new \RuntimeException('图片上传失败');
        }
        return '/uploads/profiles/' . $name;
    }

    private function deleteLocalProfileImage(string $path): void
    {
        if ($path === '') {
            return;
        }
        $root = dirname(__DIR__, 3);
        $baseDir = str_starts_with($path, '/uploads/profiles/') ? '/uploads/profiles' : (str_starts_with($path, '/storage/uploads/profiles/') ? '/storage/uploads/profiles' : '');
        if ($baseDir === '') {
            return;
        }
        $full = $root . $path;
        $base = realpath($root . $baseDir);
        $target = realpath($full);
        if ($base && $target && str_starts_with($target, $base . DIRECTORY_SEPARATOR) && is_file($target)) {
            @unlink($target);
        }
    }

    public function logout(): void
    {
        auth_logout();
        header('Location: /index.php?path=login');
        exit;
    }
}
