<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\AdminThreadModel;
use App\Models\AdminBannerModel;
use App\Models\SectionModel;
use App\Models\ThreadModel;
use App\Models\ThreadEditLogModel;
use App\Services\ThreadPermission;
use App\Models\RecycleBinModel;
use App\Models\AdminAuditLogModel;

class ThreadManageController
{
    public function __construct()
    {
        AdminAuth::check();
        if (!Permission::can('thread.edit_any') && !Permission::can('thread.delete_any') && !Permission::can('thread.hide') && !Permission::can('thread.pin') && !Permission::can('thread.feature') && !Permission::can('thread.recommend') && !Permission::can('thread.lock')) {
            Permission::require('thread.edit_any');
        }
    }

    public function index(): void
    {
        $keyword = trim($_GET['kw'] ?? '');
        $filters = [
            'status' => trim((string)($_GET['status'] ?? '')),
            'section_id' => (int)($_GET['section_id'] ?? 0),
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = 20;
        $model = new AdminThreadModel();
        $threads = [];
        $total = 0;
        $sections = [];
        $userId = (int) ($_SESSION['auth_user']['id'] ?? 0);

        try {
            $threads = $model->list($keyword, $userId, $filters, $page, $pageSize);
            $total = $model->count($keyword, $userId, $filters);
            $sections = (new SectionModel())->list();
        } catch (\Throwable $e) {
            $threads = [];
        }
        $totalPages = max(1, (int)ceil($total / $pageSize));

        require dirname(__DIR__, 2) . '/views/admin/content/threads.php';
    }

    public function edit(): void
    {
        Permission::require('thread.edit_any');

        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $thread = $id > 0 ? (new ThreadModel())->find($id) : null;
        $sections = [];
        $currencies = [];
        $error = '';

        if (!$thread) {
            redirect_or_ajax('/admin.php?path=threads');
        }

        try {
            $sections = (new SectionModel())->list();
        } catch (\Throwable $e) {
            $sections = [];
        }
        try {
            $currencies = (new \App\Models\WalletModel())->currencies();
        } catch (\Throwable $e) {
            $currencies = [];
        }

        require dirname(__DIR__, 2) . '/views/admin/content/thread_edit.php';
    }

    public function update(): void
    {
        Permission::require('thread.edit_any');
        csrf_verify();

        $id = (int) ($_POST['id'] ?? 0);
        $model = new ThreadModel();
        $thread = $id > 0 ? $model->find($id) : null;
        $sections = [];
        $currencies = [];
        $error = '';

        if (!$thread) {
            redirect_or_ajax('/admin.php?path=threads');
        }

        try {
            $sections = (new SectionModel())->list();
        } catch (\Throwable $e) {
            $sections = [];
        }
        try {
            $currencies = (new \App\Models\WalletModel())->currencies();
        } catch (\Throwable $e) {
            $currencies = [];
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        $content = safe_html($content);
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $status = trim((string)($_POST['status'] ?? ($thread['status'] ?? 'published')));
        $paidEnabled = !empty($_POST['paid_visible_enabled']);
        $paidPrice = trim((string)($_POST['paid_visible_price'] ?? ''));
        $paidCurrency = strtoupper(trim((string)($_POST['paid_visible_currency'] ?? '')));
        $currencyCodes = array_map(static fn($currency) => strtoupper((string)($currency['code'] ?? '')), $currencies);
        $plainContent = trim(strip_tags($content));
        $validSectionIds = array_map(static fn($section) => (int)$section['id'], $sections);

        if ($title === '' || $content === '') {
            $error = '请填写标题和内容';
        } elseif (mb_strlen($title) < 2 || mb_strlen($title) > 100) {
            $error = '标题长度需在 2-100 字之间';
        } elseif (mb_strlen($plainContent) < 5 || mb_strlen($plainContent) > 20000) {
            $error = '正文长度需在 5-20000 字之间';
        } elseif ($sectionId <= 0 || !in_array($sectionId, $validSectionIds, true)) {
            $error = '请选择有效板块';
        } elseif ($paidEnabled && ((float)$paidPrice <= 0 || $paidCurrency === '' || ($currencyCodes && !in_array($paidCurrency, $currencyCodes, true)))) {
            $error = '付费查看需要填写大于 0 的价格并选择有效货币';
        } elseif ($paidEnabled) {
            try {
                $paidPrice = (string)currency_validate_amount($paidPrice, $paidCurrency, '付费查看价格');
            } catch (\RuntimeException $e) {
                $error = $e->getMessage();
            }
        }

        if ($error === '' && !in_array($status, ['published', 'pending', 'hidden', 'deleted'], true)) {
            $error = '帖子状态无效';
        }

        if ($error === '') {
            $oldThread = $thread;
            $model->updateByAdmin($id, [
                'section_id' => $sectionId,
                'title' => $title,
                'content' => $content,
                'summary' => mb_substr(strip_tags($content), 0, 120),
                'status' => $status,
                'paid_visible_enabled' => $paidEnabled ? 1 : 0,
                'paid_visible_price' => $paidEnabled ? $paidPrice : null,
                'paid_visible_currency' => $paidEnabled ? $paidCurrency : null,
            ]);
            (new AdminAuditLogModel())->record('thread.update', 'thread', $id, ['title'=>$title, 'status'=>$status]);
            (new ThreadEditLogModel())->create($id, (int)($_SESSION['auth_user']['id'] ?? 0), 'admin', $oldThread, [
                'title' => $title,
                'content' => $content,
                'section_id' => $sectionId,
                'status' => $status,
            ]);
            redirect_or_ajax('/admin.php?path=threads');
        }

        require dirname(__DIR__, 2) . '/views/admin/content/thread_edit.php';
    }

    public function action(): void
    {
        csrf_verify();

        $id = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $moderationAction = (string)($_POST['moderation_action'] ?? '');
        $targetSectionId = (int)($_POST['target_section_id'] ?? 0);

        if ($id > 0 && ($moderationAction !== '' || in_array($status, ['published', 'hidden', 'deleted'], true))) {
            $thread = (new ThreadModel())->find($id);
            if ($thread) {
                $sectionId = (int) ($thread['section_id'] ?? 0);
                $model = new AdminThreadModel();
                if ($moderationAction !== '') {
                    $fieldMap = ['featured'=>'is_featured','recommended'=>'is_recommended','locked'=>'is_locked'];
                    if ($moderationAction === 'move' && ThreadPermission::can('thread.edit_any', $thread, true)) {
                        $validSectionIds = array_map(static fn($section) => (int)$section['id'], (new SectionModel())->list());
                        if ($targetSectionId > 0 && in_array($targetSectionId, $validSectionIds, true) && $targetSectionId !== (int)($thread['section_id'] ?? 0)) {
                            $model->move($id, $targetSectionId);
                            (new AdminAuditLogModel())->record('thread.move', 'thread', $id, ['from'=>(int)($thread['section_id'] ?? 0), 'to'=>$targetSectionId]);
                        }
                    } elseif (in_array($moderationAction, ['broadcast_section', 'broadcast_cancel'], true) && $this->canModerateThread($thread, 'recommended')) {
                        $bannerModel = new AdminBannerModel();
                        if ($moderationAction === 'broadcast_cancel') {
                            $bannerModel->cancelThreadBroadcast($id);
                        } else {
                            $bannerModel->createFromThread($thread);
                        }
                    } elseif (in_array($moderationAction, ['top_global','top_section','top_cancel'], true) && $this->canModerateThread($thread, 'top')) {
                        $scope = $moderationAction === 'top_global' ? 'global' : ($moderationAction === 'top_section' ? 'section' : 'none');
                        $model->updateModeration($id, ['is_top' => $scope === 'none' ? 0 : 1, 'top_scope' => $scope]);
                        (new AdminAuditLogModel())->record('thread.top', 'thread', $id, ['scope'=>$scope]);
                    } elseif (isset($fieldMap[$moderationAction]) && $this->canModerateThread($thread, $moderationAction)) {
                        $field = $fieldMap[$moderationAction];
                        $next = empty($thread[$field]) ? 1 : 0;
                        $flags = [$field => $next];
                        if ($moderationAction === 'featured') {
                            $flags['featured_reason'] = $next ? trim((string)($_POST['featured_reason'] ?? '')) : '';
                        }
                        if ($moderationAction === 'recommended') {
                            $flags['recommended_reason'] = $next ? trim((string)($_POST['recommended_reason'] ?? '')) : '';
                        }
                        $model->updateModeration($id, $flags);
                        (new AdminAuditLogModel())->record('thread.' . $moderationAction, 'thread', $id, ['enabled'=>$next]);
                    } else {
                        Permission::require('thread.edit_any');
                    }
                } elseif ($this->canChangeStatus($thread, $status)) {
                    if ($status === 'published' && ($thread['status'] ?? '') !== 'published') { try { (new \App\Services\TaskService())->recordAction((int)$thread['user_id'], 'thread_publish', 'thread', $id); } catch (\Throwable $e) {} }
                    if ($status === 'deleted') {
                        (new RecycleBinModel())->add('thread', $id, (string)($thread['title'] ?? ''), $thread);
                        $model->updateStatus($id, 'deleted');
                        (new AdminAuditLogModel())->record('thread.recycle', 'thread', $id, ['title'=>$thread['title'] ?? '']);
                    } else {
                        $model->updateStatus($id, $status);
                        (new AdminAuditLogModel())->record('thread.status', 'thread', $id, ['status'=>$status]);
                    }
                } else {
                    Permission::require('thread.edit_any');
                }
            }
        }

        redirect_or_ajax('/admin.php?path=threads');
    }

    public function bulk(): void
    {
        csrf_verify();
        $ids = array_values(array_filter(array_map('intval', $_POST['ids'] ?? [])));
        $bulkAction = (string)($_POST['bulk_action'] ?? '');
        $targetSectionId = (int)($_POST['target_section_id'] ?? 0);
        $model = new AdminThreadModel();

        foreach ($ids as $id) {
            $thread = (new ThreadModel())->find($id);
            if (!$thread) {
                continue;
            }
            if ($bulkAction === 'move' && $targetSectionId > 0) {
                if (ThreadPermission::can('thread.edit_any', $thread, true)) {
                    $model->move($id, $targetSectionId);
                }
                continue;
            }
            if (in_array($bulkAction, ['published', 'hidden', 'deleted'], true) && $this->canChangeStatus($thread, $bulkAction)) {
                if ($bulkAction === 'published' && ($thread['status'] ?? '') !== 'published') { try { (new \App\Services\TaskService())->recordAction((int)$thread['user_id'], 'thread_publish', 'thread', $id); } catch (\Throwable $e) {} }
                if ($bulkAction === 'deleted') {
                    (new RecycleBinModel())->add('thread', $id, (string)($thread['title'] ?? ''), $thread);
                    $model->updateStatus($id, 'deleted');
                    (new AdminAuditLogModel())->record('thread.recycle', 'thread', $id, ['bulk'=>true, 'title'=>$thread['title'] ?? '']);
                } else {
                    $model->updateStatus($id, $bulkAction);
                    (new AdminAuditLogModel())->record('thread.status', 'thread', $id, ['bulk'=>true, 'status'=>$bulkAction]);
                }
            }
        }

        redirect_or_ajax('/admin.php?path=threads');
    }

    private function canChangeStatus(array $thread, string $status): bool
    {
        $thread['category_id'] = $thread['category_id'] ?? $this->getCategoryIdBySection((int)($thread['section_id'] ?? 0));
        return ThreadPermission::canChangeStatus($thread, $status, true);
    }

    private function canModerateThread(array $thread, string $action): bool
    {
        $thread['category_id'] = $thread['category_id'] ?? $this->getCategoryIdBySection((int)($thread['section_id'] ?? 0));
        return ThreadPermission::canModerate($thread, $action, true);
    }

    private function getCategoryIdBySection(int $sectionId): ?int
    {
        if ($sectionId <= 0) {
            return null;
        }
        $section = (new \App\Models\SectionModel())->findById($sectionId);
        return $section ? (int) ($section['category_id'] ?? 0) : null;
    }
}
