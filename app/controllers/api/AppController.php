<?php

namespace App\Controllers\Api;

use App\Core\Mailer;
use App\Middleware\Permission;
use App\Models\AnnouncementModel;
use App\Models\AttachmentModel;
use App\Models\BannerModel;
use App\Models\DraftModel;
use App\Models\PostModel;
use App\Models\SectionModel;
use App\Models\SettingModel;
use App\Models\SystemMessageModel;
use App\Models\ThreadModel;
use App\Models\UserModel;
use App\Services\AiReviewService;
use App\Services\RateLimiter;
use App\Services\ReviewNotificationService;

class AppController
{
    public function bootstrap(): void
    {
        $user = $this->currentUser();
        $unread = 0;
        if ($user) {
            try {
                $unread = (new SystemMessageModel())->unreadCountSimple((int)$user['id']);
            } catch (\Throwable $e) {
                try { $unread = (new SystemMessageModel())->unreadCount((int)$user['id']); } catch (\Throwable $e) { $unread = 0; }
            }
        }

        $this->ok([
            'site' => (new SettingModel())->getSiteConfig(),
            'user' => $this->userPayload($user),
            'login' => $user !== null,
            'unread_count' => $unread,
            'features' => [
                'feeds' => ['latest', 'hot', 'featured', 'bounty', 'following'],
                'messages' => true,
                'publish' => true,
            ],
        ]);
    }

    public function login(): void
    {
        if (!$this->verifyCsrf()) return;

        $account = trim((string)($_POST['account'] ?? ''));
        $password = trim((string)($_POST['password'] ?? ''));
        $limiter = new RateLimiter(5, 300);
        $ip = $limiter->ip();
        if (!$limiter->check('api_login_ip:' . $ip) || ($account !== '' && !$limiter->check('api_login_account:' . strtolower($account)))) {
            $this->fail('登录过于频繁，请稍后再试', 429);
            return;
        }
        if ($account === '' || $password === '') {
            $this->fail('请输入账号和密码', 422);
            return;
        }

        try {
            $model = new UserModel();
            $user = $model->findByAccount($account);
            $legacyPasswordMatched = $user && hash_equals((string)$user['password'], $password);
            $hashedPasswordMatched = $user && password_verify($password, (string)$user['password']);
            if (!$user || (!$legacyPasswordMatched && !$hashedPasswordMatched)) {
                $this->fail('账号或密码错误', 422);
                return;
            }
            if (($user['status'] ?? 'active') !== 'active') {
                $this->fail('账号已被禁用', 403);
                return;
            }
            if (!empty($user['banned_until']) && strtotime((string)$user['banned_until']) > time()) {
                $this->fail('该账号暂时无法登录，解封时间：' . (string)$user['banned_until'], 403);
                return;
            }
            $settings = (new SettingModel())->all();
            if (($settings['email_verify_required'] ?? '0') === '1' && empty($user['email_verified'])) {
                $this->fail('请先验证你的邮箱后再登录，请检查注册邮件', 403);
                return;
            }
            if ($legacyPasswordMatched || ($hashedPasswordMatched && password_needs_rehash((string)$user['password'], PASSWORD_DEFAULT))) {
                try { $model->updatePasswordHash((int)$user['id'], password_hash($password, PASSWORD_DEFAULT)); } catch (\Throwable $e) {}
            }
            $freshUser = $model->refreshAuthUser((int)$user['id']);
            auth_login($freshUser ?: $user);
            try { (new \App\Services\TaskService())->syncStateTasks((int)$user['id']); } catch (\Throwable $e) {}
            $this->ok(['user' => $this->userPayload($this->currentUser()), 'login' => true]);
        } catch (\Throwable $e) {
            $this->fail('登录失败，请稍后重试', 500);
        }
    }

    public function register(): void
    {
        if (!$this->verifyCsrf()) return;

        $username = trim((string)($_POST['username'] ?? ''));
        $nickname = trim((string)($_POST['nickname'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = trim((string)($_POST['password'] ?? ''));
        $guard = new \App\Services\RegistrationGuard();
        $guardError = $guard->checkApi($_POST);
        if ($guardError !== null) {
            $this->fail($guardError, $guardError === '注册请求过于频繁，请稍后再试' ? 429 : 422);
            return;
        }
        if ($nickname === '') $nickname = $username;

        if (!preg_match('/^[A-Za-z0-9_]{2,30}$/', $username)) {
            $this->fail('用户名只能包含字母、数字、下划线，长度2-30位', 422);
            return;
        }
        if ($nickname === '' || mb_strlen($nickname) > 30) {
            $this->fail('昵称不能为空且不超过 30 字', 422);
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('邮箱格式不正确', 422);
            return;
        }
        if (mb_strlen($password) < 6) {
            $this->fail('密码至少 6 位', 422);
            return;
        }

        try {
            $model = new UserModel();
            if ($model->existsByUsername($username)) {
                $this->fail('用户名已存在', 422);
                return;
            }
            if ($model->existsByEmail($email)) {
                $this->fail('邮箱已被注册', 422);
                return;
            }

            $settings = (new SettingModel())->all();
            $verifyRequired = !empty($settings['email_verify_required']) && in_array(strtolower((string)$settings['email_verify_required']), ['1','true','yes','on'], true);
            $token = $verifyRequired ? bin2hex(random_bytes(32)) : null;
            $id = $model->create([
                'username' => $username,
                'nickname' => $nickname,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'bio' => '新注册用户',
                'email_verified' => $verifyRequired ? 0 : 1,
                'email_verify_token' => $token,
                'email_verify_expires_at' => $verifyRequired ? date('Y-m-d H:i:s', strtotime('+24 hours')) : null,
            ]);
            $user = $model->refreshAuthUser($id);
            if (!$verifyRequired && $user) {
                auth_login($user);
                try { (new \App\Services\TaskService())->syncRegistrationStateTasks((int)$id); } catch (\Throwable $e) {}
            } else {
                $this->sendVerifyMail($email, $nickname, (string)$token, $settings);
            }
            $this->ok([
                'user' => $verifyRequired ? null : $this->userPayload($this->currentUser()),
                'login' => !$verifyRequired,
                'message' => $verifyRequired ? '注册成功，请先完成邮箱验证' : '注册成功',
                'email_verify_required' => $verifyRequired,
            ]);
        } catch (\Throwable $e) {
            $this->fail('注册失败，请稍后重试', 500);
        }
    }

    public function logout(): void
    {
        if (!$this->verifyCsrf()) return;
        auth_logout();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->ok(['login' => false]);
    }

    public function home(): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = max(1, min(30, (int)($_GET['page_size'] ?? 20)));
        $feed = (string)($_GET['feed'] ?? 'latest');
        $viewerId = (int)($this->currentUser()['id'] ?? 0);
        if (!in_array($feed, ['latest', 'hot', 'featured', 'bounty', 'following'], true)) $feed = 'latest';
        if ($feed === 'following' && $viewerId <= 0) $feed = 'latest';
        $offset = ($page - 1) * $pageSize;

        try {
            $threadModel = new ThreadModel();
            $sectionModel = new SectionModel();
            $bannerModel = new BannerModel();
            $announcementModel = new AnnouncementModel();
            $this->ok([
                'feed' => $feed,
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $threadModel->countPublished($feed, $viewerId),
                'threads' => array_map([$this, 'threadListPayload'], $threadModel->latest($pageSize, $offset, $feed, $viewerId)),
                'top_threads' => $page === 1 && $feed === 'latest' ? array_map([$this, 'threadListPayload'], $threadModel->topGlobal(8)) : [],
                'hot_sections' => $sectionModel->hot(5),
                'sections' => $sectionModel->list(),
                'banners' => $bannerModel->active('home'),
                'announcements' => $announcementModel->active(6),
                'active_users' => $threadModel->activeAuthors(8),
            ]);
        } catch (\Throwable $e) {
            $this->fail('首页加载失败', 500);
        }
    }

    public function sections(): void
    {
        try {
            $model = new SectionModel();
            $sections = $model->list();
            $this->ok([
                'sections' => $sections,
                'grouped' => $this->groupSections($sections),
                'hot_sections' => $model->hot(8),
                'section_broadcasts' => (new BannerModel())->sectionBroadcasts(8),
                'totals' => $this->sectionTotals($sections),
            ]);
        } catch (\Throwable $e) {
            $this->fail('板块加载失败', 500);
        }
    }

    public function section(): void
    {
        $sectionId = (int)($_GET['id'] ?? $_GET['section_id'] ?? 0);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = max(1, min(30, (int)($_GET['page_size'] ?? 20)));
        $filter = (string)($_GET['filter'] ?? 'all');
        if (!in_array($filter, ['all', 'hot', 'recommended', 'featured'], true)) $filter = 'all';

        try {
            $sectionModel = new SectionModel();
            $section = $sectionModel->findById($sectionId);
            if (!$section) {
                $this->fail('板块不存在', 404);
                return;
            }
            $threadModel = new ThreadModel();
            $offset = ($page - 1) * $pageSize;
            $this->ok([
                'section' => $section,
                'filter' => $filter,
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $threadModel->countBySectionId($sectionId, $filter),
                'top_threads' => $page === 1 && $filter === 'all' ? array_map([$this, 'threadListPayload'], array_merge($threadModel->topGlobal(8), $threadModel->topForSection($sectionId, 8))) : [],
                'threads' => array_map([$this, 'threadListPayload'], $threadModel->bySectionId($sectionId, $pageSize, $offset, $filter)),
            ]);
        } catch (\Throwable $e) {
            $this->fail('板块内容加载失败', 500);
        }
    }

    public function thread(): void
    {
        $threadId = (int)($_GET['id'] ?? 0);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = max(1, min(30, (int)($_GET['page_size'] ?? 20)));
        try {
            $threadModel = new ThreadModel();
            $postModel = new PostModel();
            $thread = $threadId > 0 ? $threadModel->find($threadId) : null;
            if (!$thread || (string)($thread['status'] ?? '') !== 'published') {
                $this->fail('帖子不存在或暂不可见', 404);
                return;
            }
            $threadModel->incrementViewCount($threadId);
            $total = $postModel->countByThreadId($threadId);
            $posts = $postModel->byThreadId($threadId, $pageSize, ($page - 1) * $pageSize);
            $this->ok([
                'thread' => $this->threadDetailPayload($thread),
                'posts' => array_map([$this, 'postPayload'], $posts),
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
            ]);
        } catch (\Throwable $e) {
            $this->fail('帖子加载失败', 500);
        }
    }

    public function publish(): void
    {
        if (!$this->requireLogin() || !$this->verifyCsrf()) return;

        $title = trim((string)($_POST['title'] ?? ''));
        $content = safe_html(trim((string)($_POST['content'] ?? '')));
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $plainContent = trim(strip_tags($content));
        if ($title === '' || $content === '' || $sectionId <= 0) {
            $this->fail('请完整填写标题、板块和内容', 422);
            return;
        }
        if (mb_strlen($title) < 2 || mb_strlen($title) > 100) {
            $this->fail('标题长度需在 2-100 字之间', 422);
            return;
        }
        if (mb_strlen($plainContent) < 5 || mb_strlen($plainContent) > 20000) {
            $this->fail('正文长度需在 5-20000 字之间', 422);
            return;
        }

        try {
            $section = (new SectionModel())->findById($sectionId);
            if (!$section || !$this->canPostInSection($section)) {
                $this->fail('你没有权限在该板块发帖', 403);
                return;
            }
            $userId = (int)$this->currentUser()['id'];
            $settings = new SettingModel();
            $ai = new AiReviewService();
            $aiIntervened = false;
            $reviewRequired = $settings->getBool('thread_review_required', false) && !Permission::can('review.thread');
            if ($ai->enabledFor('thread') && !Permission::can('review.thread')) {
                $aiIntervened = true;
                $aiResult = $ai->review('thread', $userId, $title, $content);
                if (($aiResult['status'] ?? '') === 'rejected') {
                    (new DraftModel())->saveRejected($userId, null, $sectionId, $title, $content, $aiResult);
                    $this->fail('AI 审核未通过，内容已退回草稿箱，请修改后重新提交', 422);
                    return;
                }
                if (($aiResult['status'] ?? '') === 'error') $reviewRequired = true;
            }
            $threadId = (new ThreadModel())->create([
                'user_id' => $userId,
                'section_id' => $sectionId,
                'title' => $title,
                'summary' => mb_substr(strip_tags($content), 0, 120),
                'content' => $content,
                'status' => $reviewRequired ? 'pending' : 'published',
                'question_status' => !empty($section['is_question']) ? 'open' : 'none',
            ]);
            (new \App\Models\MentionModel())->notifyMentioned($content, $userId, $threadId, null, $title);
            if (!$reviewRequired) {
                try { (new \App\Services\TaskService())->recordAction($userId, 'thread_publish', 'thread', $threadId); } catch (\Throwable $e) {}
            } elseif (!$aiIntervened) {
                try { (new ReviewNotificationService())->notifyThreadPending($sectionId, $threadId, $title); } catch (\Throwable $e) {}
            }
            $this->ok([
                'thread_id' => $threadId,
                'status' => $reviewRequired ? 'pending' : 'published',
                'message' => $reviewRequired ? '帖子已提交，等待审核通过后展示' : '发布成功',
            ]);
        } catch (\Throwable $e) {
            $this->fail('帖子发布失败，请检查数据库配置', 500);
        }
    }

    public function uploadImage(): void
    {
        if (!$this->requireLogin() || !$this->verifyCsrf()) return;
        if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            $this->fail('未收到图片', 400);
            return;
        }
        $file = $_FILES['image'];
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $this->fail('图片上传中断，请重新选择文件', 400);
            return;
        }
        if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            $this->fail('图片大小不能超过 5MB', 400);
            return;
        }
        $imageSize = @getimagesize($file['tmp_name']);
        if (!is_array($imageSize)) {
            $this->fail('图片内容无效或已损坏', 400);
            return;
        }
        if (($imageSize[0] ?? 0) > 8000 || ($imageSize[1] ?? 0) > 8000) {
            $this->fail('图片尺寸过大，最长边不能超过 8000px', 400);
            return;
        }
        $mime = '';
        if (class_exists('finfo')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file($file['tmp_name']);
        } elseif (function_exists('mime_content_type')) {
            $mime = (string)mime_content_type($file['tmp_name']);
        }
        if ($mime === '' || $mime === 'application/octet-stream') {
            $mime = (string)($imageSize['mime'] ?? '');
        }
        $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
        if (!isset($extMap[$mime])) {
            $this->fail('仅支持 jpg/png/gif/webp 图片', 400);
            return;
        }
        $relativeDir = '/uploads/thread-images/' . date('Ymd');
        $dir = dirname(__DIR__, 3) . $relativeDir;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $stored = bin2hex(random_bytes(16)) . '.' . $extMap[$mime];
        $target = $dir . '/' . $stored;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            $this->fail('保存失败', 500);
            return;
        }
        $url = $relativeDir . '/' . $stored;
        try {
            (new AttachmentModel())->create([
                'user_id' => $this->currentUser()['id'],
                'original_name' => (string)($file['name'] ?? $stored),
                'stored_name' => $stored,
                'path' => $url,
                'mime' => $mime,
                'size' => (int)$file['size'],
                'kind' => 'image',
            ]);
        } catch (\Throwable $e) {}
        $this->ok(['url' => $url]);
    }

    public function messages(): void
    {
        if (!$this->requireLogin()) return;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = max(1, min(50, (int)($_GET['page_size'] ?? 20)));
        try {
            $userId = (int)$this->currentUser()['id'];
            $model = new SystemMessageModel();
            $this->ok([
                'messages' => $model->listForUserByCategory($userId, '', 'all', $pageSize, ($page - 1) * $pageSize),
                'unread_count' => $model->unreadCount($userId),
                'page' => $page,
                'page_size' => $pageSize,
            ]);
        } catch (\Throwable $e) {
            $this->fail('消息加载失败', 500);
        }
    }

    public function me(): void
    {
        if (!$this->requireLogin()) return;
        $user = $this->currentUser();
        try {
            $userId = (int)$user['id'];
            $userModel = new UserModel();
            $fresh = $userModel->find($userId) ?: $user;
            $this->ok([
                'user' => $this->userPayload($fresh),
                'stats' => [
                    'threads' => $userModel->countThreadsByUserId($userId),
                    'replies' => $userModel->countRepliesByUserId($userId),
                    'unread_messages' => (new SystemMessageModel())->unreadCount($userId),
                ],
            ]);
        } catch (\Throwable $e) {
            $this->ok(['user' => $this->userPayload($user), 'stats' => ['threads' => 0, 'replies' => 0, 'unread_messages' => 0]]);
        }
    }

    private function ok(array $data = []): void
    {
        $this->json(['ok' => true, 'data' => $data, 'csrf_token' => csrf_token(), 'app_session_id' => session_id()]);
    }

    private function fail(string $error, int $status = 400, array $extra = []): void
    {
        http_response_code($status);
        $this->json(['ok' => false, 'error' => $error, 'csrf_token' => csrf_token(), 'app_session_id' => session_id()] + $extra);
    }

    private function json(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function verifyCsrf(): bool
    {
        $submitted = (string)($_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $appSessionId = (string)($_POST['app_session_id'] ?? ($_SERVER['HTTP_X_APP_SESSION_ID'] ?? ''));
        if ($appSessionId !== '' && preg_match('/^[A-Za-z0-9,-]{16,128}$/', $appSessionId) && session_id() !== $appSessionId) {
            session_write_close();
            session_id($appSessionId);
            session_start();
        }
        $expected = (string)($_SESSION['_csrf_token'] ?? '');
        if ($expected === '' || $submitted === '' || !hash_equals($expected, $submitted)) {
            $this->fail('请求验证失败，请刷新后重试', 403);
            return false;
        }
        return true;
    }

    private function requireLogin(): bool
    {
        if (!$this->currentUser()) {
            $this->fail('请先登录', 401, ['login' => false]);
            return false;
        }
        return true;
    }

    private function currentUser(): ?array
    {
        if (function_exists('auth_user')) {
            return auth_user();
        }
        return $_SESSION['auth_user'] ?? null;
    }

    private function sendVerifyMail(string $email, string $nickname, string $token, array $settings): void
    {
        if ($token === '') return;
        $siteUrl = rtrim($settings['site_url'] ?? (function () {
            $cfg = require dirname(__DIR__, 3) . '/config/app.php';
            return $cfg['url'] ?? 'http://localhost';
        })(), '/');
        $verifyUrl = $siteUrl . '/index.php?path=verify-email&token=' . urlencode($token);
        $html = '<p>你好 ' . htmlspecialchars($nickname) . '，</p>'
            . '<p>请点击下方链接验证你的邮箱：</p>'
            . '<p><a href="' . $verifyUrl . '">' . $verifyUrl . '</a></p>'
            . '<p>链接24小时内有效。</p>';
        try {
            (new Mailer())->send($email, $nickname, '验证你的邮箱 - ClayBBS', $html);
        } catch (\Throwable $e) {
            error_log('[ClayBBS] App 验证邮件发送失败: ' . $e->getMessage());
        }
    }

    private function userPayload(?array $user): ?array
    {
        if (!$user) return null;
        return [
            'id' => (int)($user['id'] ?? 0),
            'username' => (string)($user['username'] ?? ''),
            'public_id' => (string)($user['public_id'] ?? ''),
            'nickname' => (string)($user['nickname'] ?? ''),
            'email' => (string)($user['email'] ?? ''),
            'avatar' => (string)($user['avatar'] ?? ''),
            'cover' => (string)($user['cover'] ?? ''),
            'bio' => (string)($user['bio'] ?? ''),
        ];
    }

    private function threadListPayload(array $thread): array
    {
        return [
            'id' => (int)($thread['id'] ?? 0),
            'section_id' => (int)($thread['section_id'] ?? 0),
            'section_name' => (string)($thread['section_name'] ?? ''),
            'title' => (string)($thread['title'] ?? ''),
            'summary' => (string)($thread['summary'] ?? ''),
            'images' => $this->threadImages($thread, 9),
            'cover_image' => $this->threadCover($thread),
            'author_name' => (string)($thread['author_name'] ?? ''),
            'author_avatar' => (string)($thread['author_avatar'] ?? ''),
            'reply_count' => (int)($thread['reply_count'] ?? 0),
            'view_count' => (int)($thread['view_count'] ?? 0),
            'like_count' => (int)($thread['like_count'] ?? 0),
            'is_top' => !empty($thread['is_top']),
            'is_featured' => !empty($thread['is_featured']),
            'is_recommended' => !empty($thread['is_recommended']),
            'is_locked' => !empty($thread['is_locked']),
            'question_status' => (string)($thread['question_status'] ?? 'none'),
            'bounty_currency' => $thread['bounty_currency'] ?? null,
            'bounty_amount' => $thread['bounty_amount'] ?? null,
            'created_at' => (string)($thread['created_at'] ?? ''),
            'last_reply_at' => (string)($thread['last_reply_at'] ?? ''),
        ];
    }

    private function threadDetailPayload(array $thread): array
    {
        return $this->threadListPayload($thread) + [
            'user_id' => (int)($thread['user_id'] ?? 0),
            'content' => (string)($thread['content'] ?? ''),
        ];
    }

    private function postPayload(array $post): array
    {
        return [
            'id' => (int)($post['id'] ?? 0),
            'thread_id' => (int)($post['thread_id'] ?? 0),
            'user_id' => (int)($post['user_id'] ?? 0),
            'parent_id' => isset($post['parent_id']) ? (int)$post['parent_id'] : null,
            'author_name' => (string)($post['author_name'] ?? ''),
            'author_avatar' => (string)($post['author_avatar'] ?? ''),
            'content' => (string)($post['content'] ?? ''),
            'created_at' => (string)($post['created_at'] ?? ''),
            'parent_author_name' => (string)($post['parent_author_name'] ?? ''),
            'parent_content' => (string)($post['parent_content'] ?? ''),
        ];
    }

    private function groupSections(array $sections): array
    {
        $grouped = [];
        foreach ($sections as $section) {
            $name = trim((string)($section['category_name'] ?? '默认分类'));
            if ($name === '') $name = '默认分类';
            if (!isset($grouped[$name])) $grouped[$name] = [];
            $grouped[$name][] = $section;
        }
        return $grouped;
    }

    private function sectionTotals(array $sections): array
    {
        $threads = 0;
        foreach ($sections as $section) {
            $threads += (int)($section['thread_count'] ?? 0);
        }
        return ['sections' => count($sections), 'threads' => $threads];
    }

    private function threadCover(array $thread): string
    {
        $cover = trim((string)($thread['cover'] ?? ''));
        if ($cover !== '') return $this->safeImageUrl($cover);
        $images = $this->threadImages($thread, 1);
        return $images[0] ?? '';
    }

    private function threadImages(array $thread, int $limit = 9): array
    {
        $images = [];
        $content = (string)($thread['content'] ?? '');
        if ($content !== '' && preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches)) {
            foreach (($matches[1] ?? []) as $src) {
                $safe = $this->safeImageUrl((string)$src);
                if ($safe !== '' && !in_array($safe, $images, true)) $images[] = $safe;
                if (count($images) >= $limit) break;
            }
        }
        $cover = trim((string)($thread['cover'] ?? ''));
        if ($cover !== '') {
            $safeCover = $this->safeImageUrl($cover);
            if ($safeCover !== '' && !in_array($safeCover, $images, true)) array_unshift($images, $safeCover);
        }
        return array_slice($images, 0, $limit);
    }

    private function safeImageUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if (preg_match('#^https?://#i', $url)) return $url;
        if (str_starts_with($url, '//')) return 'https:' . $url;
        if ($url[0] === '/') return $url;
        return '/' . ltrim($url, '/');
    }

    private function canPostInSection(array $section): bool
    {
        if (!$this->currentUser()) return false;
        $permission = (string)($section['post_permission'] ?? 'login');
        $sectionId = (int)($section['id'] ?? 0);
        return match ($permission) {
            'admin' => Permission::can('admin.access'),
            'role' => Permission::can('thread.create') || Permission::can('thread.create', 'section', $sectionId),
            'section_role' => Permission::can('thread.create', 'section', $sectionId),
            'login', '' => auth_check(),
            default => auth_check(),
        };
    }
}
