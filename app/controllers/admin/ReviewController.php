<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\AdminReviewModel;
use App\Models\AiProviderModel;
use App\Models\AiReviewLogModel;
use App\Models\DraftModel;
use App\Models\ReplyDraftModel;
use App\Models\SettingModel;
use App\Models\SystemMessageModel;
use App\Models\ThreadRevisionModel;
use App\Services\AiReviewService;

class ReviewController
{
    public function __construct()
    {
        AdminAuth::check();
        if (!Permission::canAnyScope('review.thread') && !Permission::canAnyScope('review.post') && !Permission::can('review.settings')) {
            $legacyRole = (string)($_SESSION['auth_user']['role'] ?? '');
            if (!in_array($legacyRole, ['moderator','reviewer'], true)) {
                Permission::require('review.thread');
            }
        }
    }

    public function index(): void
    {
        $this->renderReview();
    }

    public function moderatorWorkbench(): void
    {
        $this->renderReview('moderator');
    }

    public function reviewerWorkbench(): void
    {
        $this->renderReview('reviewer');
    }

    private function renderReview(string $workbench = ''): void
    {
        $model = new AdminReviewModel();
        $tab = $_GET['tab'] ?? 'threads';
        if ($workbench !== '' && !in_array($tab, ['threads','revisions','posts'], true)) $tab = 'threads';
        $pendingThreads = [];
        $pendingPosts = [];
        $pendingRevisions = [];
        $userId = (int) ($_SESSION['auth_user']['id'] ?? 0);
        try {
            $pendingThreads = $model->pendingThreads($userId);
            $pendingPosts = $model->pendingPosts($userId);
            $pendingRevisions = (new ThreadRevisionModel())->pending($userId);
        } catch (\Throwable $e) {}
        $settingModel = new SettingModel();
        $settings = $settingModel->all();
        $providerModel = new AiProviderModel();
        $providers = $providerModel->all(false);
        $editingProvider = isset($_GET['provider_id']) ? $providerModel->find((int)$_GET['provider_id']) : null;
        $logModel = new AiReviewLogModel();
        $logs = $logModel->latest(80);
        $logDetail = isset($_GET['log_id']) ? $logModel->find((int)$_GET['log_id']) : null;
        $defaultPrompt = (new AiReviewService())->defaultPrompt();
        $limitedWorkbench = $workbench !== '';
        $workspaceTitle = $workbench === 'moderator' ? '版主工作台' : ($workbench === 'reviewer' ? '审核员工作台' : '审核中心');
        $workspaceSubtitle = $workbench === 'moderator' ? '处理你负责板块内的待审帖子、修改和回复。' : ($workbench === 'reviewer' ? '处理分配给你的板块内的待审内容。' : '人工审核、AI 审核、模型提供商、审核日志统一管理。');
        require dirname(__DIR__, 2) . '/views/admin/content/review.php';
    }

    public function saveSettings(): void
    {
        Permission::require('review.settings');
        csrf_verify();
        (new SettingModel())->saveMany([
            'thread_review_required' => ($_POST['thread_review_required'] ?? '0') === '1' ? '1' : '0',
            'post_review_required' => ($_POST['post_review_required'] ?? '0') === '1' ? '1' : '0',
            'ai_review_enabled' => ($_POST['ai_review_enabled'] ?? '0') === '1' ? '1' : '0',
            'ai_review_threads' => ($_POST['ai_review_threads'] ?? '0') === '1' ? '1' : '0',
            'ai_review_posts' => ($_POST['ai_review_posts'] ?? '0') === '1' ? '1' : '0',
            'private_chat_review_enabled' => ($_POST['private_chat_review_enabled'] ?? '0') === '1' ? '1' : '0',
            'group_chat_review_enabled' => ($_POST['group_chat_review_enabled'] ?? '0') === '1' ? '1' : '0',
            'ai_review_moments' => ($_POST['ai_review_moments'] ?? '0') === '1' ? '1' : '0',
            'ai_review_images' => ($_POST['ai_review_images'] ?? '0') === '1' ? '1' : '0',
            'ai_review_provider_id' => (string)(int)($_POST['ai_review_provider_id'] ?? 0),
            'ai_review_strictness' => in_array(($_POST['ai_review_strictness'] ?? 'standard'), ['loose','standard','strict'], true) ? (string)$_POST['ai_review_strictness'] : 'standard',
            'ai_review_prompt' => trim((string)($_POST['ai_review_prompt'] ?? '')),
            'ai_review_image_prompt' => trim((string)($_POST['ai_review_image_prompt'] ?? '')),
        ]);
        redirect_or_ajax('/admin.php?path=review&tab=settings');
    }

    public function saveProvider(): void
    {
        Permission::require('review.settings');
        csrf_verify();
        (new AiProviderModel())->save($_POST);
        redirect_or_ajax('/admin.php?path=review&tab=providers');
    }

    public function deleteProvider(): void
    {
        Permission::require('review.settings');
        csrf_verify();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) (new AiProviderModel())->delete($id);
        redirect_or_ajax('/admin.php?path=review&tab=providers');
    }

    public function testProvider(): void
    {
        Permission::require('review.settings');
        csrf_verify();
        $id = (int)($_POST['id'] ?? 0);
        $provider = (new AiProviderModel())->find($id);
        if ($provider) {
            (new SettingModel())->set('ai_review_provider_id', (string)$id);
            $result = (new AiReviewService())->review('thread', (int)($_SESSION['auth_user']['id'] ?? 0), 'AI 审核测试', '这是一条正常的测试内容，用于验证 AI 审核接口是否可用。');
            $_SESSION['flash_success'] = (($result['status'] ?? '') === 'error') ? ('测试失败：' . ($result['error'] ?? $result['reason'] ?? '未知错误')) : ('测试完成：' . ($result['passed'] ? '通过' : '不通过') . '，' . ($result['reason'] ?? ''));
        }
        redirect_or_ajax('/admin.php?path=review&tab=providers');
    }

    public function applyPreset(): void
    {
        
    }

    public function aiRecheck(): void
    {
        csrf_verify();
        $type = (string)($_POST['type'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !in_array($type, ['thread','post'], true)) redirect_or_ajax('/admin.php?path=review');
        if ($type === 'thread') {
            $thread = (new \App\Models\ThreadModel())->find($id);
            if (!$thread || !$this->canReviewThreadSection((int)($thread['section_id'] ?? 0))) Permission::require('review.thread');
            if ($thread) $this->aiRecheckThread($thread);
            redirect_or_ajax('/admin.php?path=review&tab=threads');
        }
        $post = (new \App\Models\PostModel())->find($id);
        $context = $this->getPostReviewContext($id);
        if (!$post || !$context || !$this->canReviewPostSection((int)($context['section_id'] ?? 0))) Permission::require('review.post');
        if ($post) $this->aiRecheckPost($post);
        redirect_or_ajax('/admin.php?path=review&tab=posts');
    }

    private function aiRecheckThread(array $thread): void
    {
        $result = (new AiReviewService())->review('thread', (int)($thread['user_id'] ?? 0), (string)($thread['title'] ?? ''), (string)($thread['content'] ?? ''));
        if (($result['status'] ?? '') === 'passed') {
            (new \App\Models\ThreadModel())->updateStatus((int)$thread['id'], 'published');
            try { (new \App\Services\TaskService())->recordAction((int)$thread['user_id'], 'thread_publish', 'thread', (int)$thread['id']); } catch (\Throwable $e) {}
            (new SystemMessageModel())->createPersonal((int)$thread['user_id'], '帖子审核通过', '你的帖子《' . (string)$thread['title'] . '》已通过 AI 复审并发布。', 0);
        } elseif (($result['status'] ?? '') === 'rejected') {
            (new DraftModel())->saveRejected((int)$thread['user_id'], null, (int)$thread['section_id'], (string)$thread['title'], (string)$thread['content'], $result);
            (new \App\Models\ThreadModel())->deleteHard((int)$thread['id']);
            (new SystemMessageModel())->createPersonal((int)$thread['user_id'], '帖子已退回草稿箱', '你的帖子《' . (string)$thread['title'] . '》AI 复审未通过，已退回草稿箱。原因：' . (string)($result['reason'] ?? ''), 0);
        } else {
            $_SESSION['flash_success'] = 'AI 复审异常，内容保留在人工审核队列。';
        }
    }

    private function aiRecheckPost(array $post): void
    {
        $threadTitle = $this->threadTitle((int)($post['thread_id'] ?? 0));
        $result = (new AiReviewService())->review('post', (int)($post['user_id'] ?? 0), $threadTitle ?: '回复', (string)($post['content'] ?? ''));
        if (($result['status'] ?? '') === 'passed') {
            (new AdminReviewModel())->reviewPost((int)$post['id'], 'approve');
            try { (new \App\Services\TaskService())->recordAction((int)$post['user_id'], 'post_publish', 'post', (int)$post['id']); } catch (\Throwable $e) {}
            (new SystemMessageModel())->createPersonal((int)$post['user_id'], '回复审核通过', '你在帖子《' . $threadTitle . '》中的回复已通过 AI 复审并发布。', 0);
        } elseif (($result['status'] ?? '') === 'rejected') {
            (new ReplyDraftModel())->saveRejected((int)$post['user_id'], (int)$post['thread_id'], !empty($post['parent_id']) ? (int)$post['parent_id'] : null, (string)$post['content'], $result);
            (new \App\Models\AdminPostModel())->delete((int)$post['id']);
            (new SystemMessageModel())->createPersonal((int)$post['user_id'], '回复已退回草稿箱', '你在帖子《' . $threadTitle . '》中的回复 AI 复审未通过，已退回草稿箱。原因：' . (string)($result['reason'] ?? ''), 0);
        } else {
            $_SESSION['flash_success'] = 'AI 复审异常，内容保留在人工审核队列。';
        }
    }

    private function threadTitle(int $threadId): string
    {
        $thread = $threadId > 0 ? (new \App\Models\ThreadModel())->find($threadId) : null;
        return (string)($thread['title'] ?? '');
    }


    public function bulkAction(): void
    {
        csrf_verify();
        $type = (string)($_POST['type'] ?? '');
        $action = (string)($_POST['action'] ?? '');
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
        if (!$ids || !in_array($type, ['thread','revision','post'], true) || !in_array($action, ['approve','reject'], true)) {
            redirect_or_ajax((string)($_POST['redirect'] ?? '/admin.php?path=review'));
        }
        $model = new AdminReviewModel();
        $reviewerId = (int)($_SESSION['auth_user']['id'] ?? 0);
        foreach ($ids as $id) {
            if ($type === 'thread') {
                $thread = (new \App\Models\ThreadModel())->find($id);
                $sectionId = $thread ? (int)($thread['section_id'] ?? 0) : 0;
                if ($thread && $this->canReviewThreadSection($sectionId)) {
                    $model->reviewThread($id, $action);
                    if ($action === 'approve') { try { (new \App\Services\TaskService())->recordAction((int)$thread['user_id'], 'thread_publish', 'thread', $id); } catch (\Throwable $e) {} }
                }
            } elseif ($type === 'revision') {
                $revision = (new ThreadRevisionModel())->find($id);
                $sectionId = $revision ? (int)($revision['section_id'] ?? 0) : 0;
                if ($revision && $this->canReviewThreadSection($sectionId)) {
                    if ($action === 'approve') {
                        (new ThreadRevisionModel())->approve($id, $reviewerId);
                        (new SystemMessageModel())->createPersonal((int)$revision['user_id'], '帖子修改审核通过', '你的帖子《' . (string)$revision['title'] . '》修改已审核通过并更新。', 0);
                    } else {
                        (new ThreadRevisionModel())->reject($id, $reviewerId, '人工审核未通过');
                        (new SystemMessageModel())->createPersonal((int)$revision['user_id'], '帖子修改审核未通过', '你的帖子《' . (string)$revision['title'] . '》修改未通过审核，原帖内容未受影响。', 0);
                    }
                }
            } elseif ($type === 'post') {
                $post = $this->getPostReviewContext($id);
                $sectionId = $post ? (int)($post['section_id'] ?? 0) : 0;
                if ($post && $this->canReviewPostSection($sectionId)) {
                    $postRow = (new \App\Models\PostModel())->find($id);
                    $model->reviewPost($id, $action);
                    if ($action === 'approve' && $postRow) { try { (new \App\Services\TaskService())->recordAction((int)$postRow['user_id'], 'post_publish', 'post', $id); } catch (\Throwable $e) {} }
                }
            }
        }
        $redirect = (string)($_POST['redirect'] ?? '/admin.php?path=review');
        if (!str_starts_with($redirect, '/admin.php?path=review') && !str_starts_with($redirect, '/admin.php?path=moderator-workbench') && !str_starts_with($redirect, '/admin.php?path=reviewer-workbench')) {
            $redirect = '/admin.php?path=review';
        }
        redirect_or_ajax($redirect);
    }

    public function action(): void
    {
        csrf_verify();
        $type = $_POST['type'] ?? '';
        $id = (int) ($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? '';
        if ($id > 0 && in_array($action, ['approve', 'reject'], true)) {
            $model = new AdminReviewModel();
            $sectionId = null;
            if ($type === 'thread') {
                $thread = (new \App\Models\ThreadModel())->find($id);
                $sectionId = $thread ? (int) ($thread['section_id'] ?? 0) : null;
                if ($this->canReviewThreadSection((int)$sectionId)) { $model->reviewThread($id, $action); if ($action === 'approve' && $thread) { try { (new \App\Services\TaskService())->recordAction((int)$thread['user_id'], 'thread_publish', 'thread', $id); } catch (\Throwable $e) {} } } else Permission::require('review.thread');
            } elseif ($type === 'revision') {
                $revision = (new ThreadRevisionModel())->find($id);
                $sectionId = $revision ? (int)($revision['section_id'] ?? 0) : null;
                if ($this->canReviewThreadSection((int)$sectionId)) {
                    if ($action === 'approve') {
                        (new ThreadRevisionModel())->approve($id, (int)($_SESSION['auth_user']['id'] ?? 0));
                        if ($revision) (new SystemMessageModel())->createPersonal((int)$revision['user_id'], '帖子修改审核通过', '你的帖子《' . (string)$revision['title'] . '》修改已审核通过并更新。', 0);
                    } else {
                        (new ThreadRevisionModel())->reject($id, (int)($_SESSION['auth_user']['id'] ?? 0), '人工审核未通过');
                        if ($revision) (new SystemMessageModel())->createPersonal((int)$revision['user_id'], '帖子修改审核未通过', '你的帖子《' . (string)$revision['title'] . '》修改未通过审核，原帖内容未受影响。', 0);
                    }
                } else Permission::require('review.thread');
            } elseif ($type === 'post') {
                $post = $this->getPostReviewContext($id);
                $sectionId = $post ? (int) ($post['section_id'] ?? 0) : null;
                if ($this->canReviewPostSection((int)$sectionId)) { $postRow = (new \App\Models\PostModel())->find($id); $model->reviewPost($id, $action); if ($action === 'approve' && $postRow) { try { (new \App\Services\TaskService())->recordAction((int)$postRow['user_id'], 'post_publish', 'post', $id); } catch (\Throwable $e) {} } } else Permission::require('review.post');
            }
        }
        $redirect = (string)($_POST['redirect'] ?? '/admin.php?path=review');
        if (!str_starts_with($redirect, '/admin.php?path=review') && !str_starts_with($redirect, '/admin.php?path=moderator-workbench') && !str_starts_with($redirect, '/admin.php?path=reviewer-workbench')) {
            $redirect = '/admin.php?path=review';
        }
        redirect_or_ajax($redirect);
    }

    private function canReviewThreadSection(int $sectionId): bool
    {
        return Permission::can('review.thread', 'global')
            || Permission::can('review.thread', 'section', $sectionId)
            || Permission::can('review.thread', 'category', $this->getCategoryIdBySection($sectionId));
    }

    private function canReviewPostSection(int $sectionId): bool
    {
        return Permission::can('review.post', 'global')
            || Permission::can('review.post', 'section', $sectionId)
            || Permission::can('review.post', 'category', $this->getCategoryIdBySection($sectionId));
    }

    private function getCategoryIdBySection(int $sectionId): ?int
    {
        if ($sectionId <= 0) return null;
        $section = (new \App\Models\SectionModel())->findById($sectionId);
        return $section ? (int) ($section['category_id'] ?? 0) : null;
    }

    private function getPostReviewContext(int $postId): ?array
    {
        $db = \App\Core\Database::connection();
        $stmt = $db->prepare("SELECT p.id, t.section_id FROM posts p LEFT JOIN threads t ON t.id = p.thread_id WHERE p.id = :id LIMIT 1");
        $stmt->execute([':id' => $postId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
