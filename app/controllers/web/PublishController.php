<?php

namespace App\Controllers\Web;

use App\Models\SectionModel;
use App\Models\ThreadModel;
use App\Models\DraftModel;
use App\Models\AttachmentModel;
use App\Middleware\Permission;
use App\Services\AiReviewService;
use App\Services\ReviewNotificationService;
use App\Models\SettingModel;

class PublishController
{
    public function index(): void
    {
        $error = '';
        $success = '';
        $sections = [];
        $selectedSectionId = (int)($_GET['section_id'] ?? $_GET['section'] ?? $_POST['section_id'] ?? 0);
        $draftId = (int)($_GET['draft_id'] ?? $_POST['draft_id'] ?? 0);
        $draft = null;
        $currencies = auth_check() ? (new \App\Models\WalletModel())->currencies() : [];
        if (auth_check() && $draftId > 0) {
            $draft = (new DraftModel())->findForUser($draftId, (int)auth_user()['id']);
            if ($draft) {
                $selectedSectionId = (int)($draft['section_id'] ?? $selectedSectionId);
            }
        } elseif (auth_check() && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $draft = (new DraftModel())->findAutosaveForUser((int)auth_user()['id']);
            if ($draft) {
                $draftId = (int)$draft['id'];
                $selectedSectionId = (int)($draft['section_id'] ?? $selectedSectionId);
            }
        }

        try {
            $sections = array_values(array_filter((new SectionModel())->list(), fn(array $section): bool => $this->canPostInSection($section)));
        } catch (\Throwable $e) {
            $sections = [];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!auth_check()) {
                header('Location: /index.php?path=login');
                exit;
            }
            csrf_verify();

            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $content = safe_html($content);
            $sectionId = (int) ($_POST['section_id'] ?? 0);
            $paidEnabled = !empty($_POST['paid_visible_enabled']);
            $paidPrice = (float)($_POST['paid_visible_price'] ?? 0);
            $paidCurrency = function_exists('currency_resolve_code') ? currency_resolve_code((string)($_POST['paid_visible_currency'] ?? '')) : strtoupper(trim((string)($_POST['paid_visible_currency'] ?? '')));
            $currencyCodes = array_map(static fn($currency) => strtoupper((string)($currency['code'] ?? '')), $currencies);
            $sectionForQuestion = $sectionId > 0 ? (new SectionModel())->findById($sectionId) : null;
            $isQuestionSection = !empty($sectionForQuestion['is_question']);
            $bountyEnabled = $isQuestionSection && !empty($_POST['bounty_enabled']);
            $bountyCurrency = function_exists('currency_resolve_code') ? currency_resolve_code((string)($_POST['bounty_currency'] ?? '')) : strtoupper(trim((string)($_POST['bounty_currency'] ?? '')));
            $bountyAmount = (float)($_POST['bounty_amount'] ?? 0);

            $plainContent = trim(strip_tags($content));
            if ($title === '' || $content === '' || $sectionId <= 0) {
                $error = '请完整填写标题、板块和内容';
            } elseif (mb_strlen($title) < 2 || mb_strlen($title) > 100) {
                $error = '标题长度需在 2-100 字之间';
            } elseif (mb_strlen($plainContent) < 5 || mb_strlen($plainContent) > 20000) {
                $error = '正文长度需在 5-20000 字之间';
            } elseif ($paidEnabled && ($paidPrice <= 0 || $paidCurrency === '' || ($currencyCodes && !in_array($paidCurrency, $currencyCodes, true)))) {
                $error = '请填写付费阅读价格并选择有效货币';
            } elseif ($bountyEnabled && ($bountyAmount <= 0 || $bountyCurrency === '' || ($currencyCodes && !in_array($bountyCurrency, $currencyCodes, true)))) {
                $error = '请填写悬赏金额并选择有效货币';
            } elseif ($bountyEnabled) {
                try {
                    $bountyAmount = currency_validate_amount($_POST['bounty_amount'] ?? 0, $bountyCurrency, '悬赏金额');
                } catch (\RuntimeException $e) {
                    $error = $e->getMessage();
                }
            } elseif ($paidEnabled) {
                try {
                    $paidPrice = currency_validate_amount($_POST['paid_visible_price'] ?? 0, $paidCurrency, '付费阅读价格');
                } catch (\RuntimeException $e) {
                    $error = $e->getMessage();
                }
            }

            if ($error === '') {
                $section = (new SectionModel())->findById($sectionId);
                if (!$section || !$this->canPostInSection($section)) {
                    $error = '你没有权限在该板块发帖';
                } else {
                try {
                    $settingModel = new \App\Models\SettingModel();
                    $userId = (int)auth_user()['id'];
                    $ai = new AiReviewService();
                    $aiIntervened = false;
                    $reviewRequired = $settingModel->getBool('thread_review_required', false) && !Permission::can('review.thread');
                    if ($ai->enabledFor('thread') && !Permission::can('review.thread')) {
                        $aiIntervened = true;
                        $aiResult = $ai->review('thread', $userId, $title, $content);
                        if (($aiResult['status'] ?? '') === 'rejected') {
                            (new DraftModel())->saveRejected($userId, $draftId ?: null, $sectionId, $title, $content, $aiResult);
                            $success = 'AI 审核未通过，内容已退回草稿箱，请修改后重新提交。';
                            $_POST = [];
                            require theme_view('web/thread/publish.php');
                            return;
                        }
                        if (($aiResult['status'] ?? '') === 'error') {
                            $reviewRequired = true;
                        }
                    }
                    if ($bountyEnabled) {
                        (new \App\Models\WalletModel())->lockBalance($userId, $bountyCurrency, number_format($bountyAmount, 6, '.', ''), 'question_bounty_lock', '问答悬赏冻结', '发布问答悬赏《' . $title . '》', 'thread', null);
                    }
                    $threadId = (new ThreadModel())->create([
                        'user_id' => $userId,
                        'section_id' => $sectionId,
                        'title' => $title,
                        'summary' => mb_substr(strip_tags($content), 0, 120),
                        'content' => $content,
                        'status' => $reviewRequired ? 'pending' : 'published',
                        'paid_visible_enabled' => $paidEnabled,
                        'paid_visible_price' => $paidPrice,
                        'paid_visible_currency' => $paidCurrency,
                        'question_status' => $isQuestionSection ? 'open' : 'none',
                        'bounty_currency' => $bountyEnabled ? $bountyCurrency : null,
                        'bounty_amount' => $bountyEnabled ? $bountyAmount : null,
                    ]);
                    if ($bountyEnabled) { try { \App\Core\Database::connection()->prepare("UPDATE wallet_transactions SET ref_id=:thread_id WHERE user_id=:uid AND ref_type='thread' AND ref_id IS NULL AND type='question_bounty_lock' ORDER BY id DESC LIMIT 1")->execute([':thread_id'=>$threadId, ':uid'=>$userId]); } catch (\Throwable $e) {} }
                    (new \App\Models\MentionModel())->notifyMentioned($content, $userId, $threadId, null, $title);
                    if (!$reviewRequired) {
                        try { (new \App\Services\TaskService())->recordAction($userId, 'thread_publish', 'thread', $threadId); } catch (\Throwable $e) {}
                        $followerIds = (new \App\Models\FollowModel())->followingIds($userId);
                        foreach ($followerIds as $followerId) {
                            try {
                                if ((new \App\Models\NotificationSettingModel())->enabled($followerId, 'follow_post')) {
                                    (new \App\Models\SystemMessageModel())->createPersonal($followerId, '关注的人发布了新帖', user_display_name(auth_user(), '用户') . ' 发布了新帖《' . $title . '》。', 0);
                                }
                            } catch (\Throwable $e) {}
                        }
                    }
                    if ($draftId > 0) {
                        (new DraftModel())->deleteForUser($draftId, $userId);
                    }
                    (new DraftModel())->clearAutosaveForUser($userId);
                    if ($reviewRequired) {
                        if (!$aiIntervened) {
                            try { (new ReviewNotificationService())->notifyThreadPending($sectionId, $threadId, $title); } catch (\Throwable $e) {}
                        }
                        $success = '帖子已提交，等待审核通过后展示';
                        $_POST = [];
                    } else {
                        header('Location: /index.php?path=thread&id=' . $threadId);
                        exit;
                    }
                } catch (\Throwable $e) {
                    $error = '帖子发布失败，请检查数据库配置';
                }
                }
            }
        }

        require theme_view('web/thread/publish.php');
    }

    public function drafts(): void
    {
        if (!auth_check()) {
            header('Location: /index.php?path=login');
            exit;
        }
        $userId = (int)auth_user()['id'];
        $keyword = trim((string)($_GET['q'] ?? ''));
        $sectionFilter = (int)($_GET['section_id'] ?? 0);
        $draftModel = new DraftModel();
        $replyDraftModel = new \App\Models\ReplyDraftModel();
        $drafts = $draftModel->searchByUser($userId, $keyword, $sectionFilter);
        $replyDrafts = $replyDraftModel->searchByUser($userId, $keyword);
        $draftSections = $draftModel->sectionsForUser($userId);
        require theme_view('web/thread/drafts.php');
    }



    public function discardAutosave(): void
    {
        if (!auth_check()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => '请先登录'], JSON_UNESCAPED_UNICODE);
            return;
        }
        csrf_verify();
        (new DraftModel())->clearAutosaveForUser((int)auth_user()['id']);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    }

    public function batchDeleteDrafts(): void
    {
        if (!auth_check()) {
            header('Location: /index.php?path=login');
            exit;
        }
        csrf_verify();
        $type = (string)($_POST['type'] ?? 'thread');
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $userId = (int)auth_user()['id'];
        if ($type === 'reply') {
            (new \App\Models\ReplyDraftModel())->deleteManyForUser($ids, $userId);
        } else {
            (new DraftModel())->deleteManyForUser($ids, $userId);
        }
        redirect_or_ajax('/index.php?path=drafts');
    }

    public function saveDraft(): void
    {
        $isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        if (!auth_check()) {
            http_response_code(401);
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => '请先登录'], JSON_UNESCAPED_UNICODE);
                return;
            }
            exit('请先登录');
        }
        csrf_verify();
        $id = (int)($_POST['draft_id'] ?? 0);
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        $mode = trim((string)($_POST['mode'] ?? ($isAjax ? 'autosave' : 'manual')));
        if ($title === '' && trim(strip_tags($content)) === '') {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'skipped' => true, 'message' => '空草稿未保存'], JSON_UNESCAPED_UNICODE);
                return;
            }
            redirect_or_ajax('/index.php?path=publish', ['message' => '空草稿未保存']);
        }
        $draftModel = new DraftModel();
        $userId = (int)auth_user()['id'];
        if ($mode === 'autosave') {
            $draftId = $draftModel->saveAutosave($userId, $sectionId, $title, $content);
        } elseif ($mode === 'manual') {
            $draftId = $draftModel->promoteAutosaveToDraft($userId, $id, $sectionId, $title, $content);
        } else {
            $draftId = $draftModel->save($userId, $id ?: null, $sectionId, $title, $content);
        }
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'draft_id' => $draftId, 'saved_at' => date('H:i')], JSON_UNESCAPED_UNICODE);
            return;
        }
        redirect_or_ajax('/index.php?path=drafts');
    }

    public function deleteDraft(): void
    {
        if (!auth_check()) {
            header('Location: /index.php?path=login');
            exit;
        }
        csrf_verify();
        $id = (int)($_POST['id'] ?? 0);
        $type = (string)($_POST['type'] ?? 'thread');
        if ($id > 0) {
            if ($type === 'reply') {
                (new \App\Models\ReplyDraftModel())->deleteForUser($id, (int)auth_user()['id']);
            } else {
                (new DraftModel())->deleteForUser($id, (int)auth_user()['id']);
            }
        }
        redirect_or_ajax('/index.php?path=drafts');
    }

    public function uploadImage(): void
    {
        if (!auth_check()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => '请先登录'], JSON_UNESCAPED_UNICODE);
            return;
        }
        try {
            csrf_verify();
        } catch (\Throwable $e) {
            http_response_code(419);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => '页面已过期，请刷新后重试'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => '未收到图片'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $file = $_FILES['image'];
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => '图片上传中断，请重新选择文件'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => '图片大小不能超过 5MB'], JSON_UNESCAPED_UNICODE);
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
            $imageInfo = @getimagesize($file['tmp_name']);
            if (is_array($imageInfo) && !empty($imageInfo['mime'])) {
                $mime = (string)$imageInfo['mime'];
            }
        }
        $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
        $imageSize = @getimagesize($file['tmp_name']);
        if (!is_array($imageSize)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => '图片内容无效或已损坏'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (($imageSize[0] ?? 0) > 8000 || ($imageSize[1] ?? 0) > 8000) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => '图片尺寸过大，最长边不能超过 8000px'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!isset($extMap[$mime])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => '仅支持 jpg/png/gif/webp 图片'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $relativeDir = '/uploads/thread-images/' . date('Ymd');
        $dir = dirname(__DIR__, 3) . $relativeDir;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $extMap[$mime];
        $target = $dir . '/' . $stored;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => '保存失败'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $url = $relativeDir . '/' . $stored;
        try {
            $settings = new SettingModel();
            if ($settings->getBool('ai_review_enabled', false) && $settings->getBool('ai_review_images', false) && !Permission::can('review.thread')) {
                $review = (new AiReviewService())->reviewImage('thread_image', (int)auth_user()['id'], '帖子图片', $url);
                if (empty($review['passed'])) {
                    @unlink($target);
                    http_response_code(422);
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => false, 'error' => (string)($review['reason'] ?? '图片未通过 AI 审核')], JSON_UNESCAPED_UNICODE);
                    return;
                }
            }
        } catch (\Throwable $e) {
            @unlink($target);
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $e->getMessage() ?: '图片 AI 审核失败'], JSON_UNESCAPED_UNICODE);
            return;
        }
        try {
            (new AttachmentModel())->create([
                'user_id' => auth_user()['id'],
                'original_name' => (string)($file['name'] ?? $stored),
                'stored_name' => $stored,
                'path' => $url,
                'mime' => $mime,
                'size' => (int)$file['size'],
                'kind' => 'image',
            ]);
        } catch (\Throwable $e) {}
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'url' => $url], JSON_UNESCAPED_UNICODE);
    }

    private function canPostInSection(array $section): bool
    {
        $permission = (string)($section['post_permission'] ?? 'login');
        $sectionId = (int)($section['id'] ?? 0);

        if (!auth_check()) {
            return false;
        }

        return match ($permission) {
            'admin' => Permission::can('admin.access'),
            'role' => Permission::can('thread.create') || Permission::can('thread.create', 'section', $sectionId),
            'section_role' => Permission::can('thread.create', 'section', $sectionId),
            'login', '' => auth_check(),
            default => auth_check(),
        };
    }
}
