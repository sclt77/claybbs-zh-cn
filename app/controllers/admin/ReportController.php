<?php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\AdminAuditLogModel;
use App\Models\AdminPostModel;
use App\Models\AdminReportModel;
use App\Models\AdminThreadModel;
use App\Models\SystemMessageModel;
use App\Models\UserCreditModel;

class ReportController
{
    public function __construct()
    {
        AdminAuth::check();
        if (!$this->canEnterReports()) { Permission::require('admin.report'); }
    }


    private function canEnterReports(): bool
    {
        return Permission::can('admin.report')
            || Permission::canAnyScope('moderator.report.handle')
            || Permission::canAnyScope('thread.hide')
            || Permission::canAnyScope('thread.delete_any')
            || Permission::canAnyScope('post.delete_any')
            || Permission::canAnyScope('review.post');
    }

    public function index(): void
    {
        $status = trim((string)($_GET['status'] ?? ''));
        $model = new AdminReportModel();
        $reports = $model->all($status);
        $stats = $model->stats();
        require dirname(__DIR__, 2) . '/views/admin/content/reports.php';
    }

    public function handle(): void
    {
        csrf_verify();
        $id = (int)($_POST['id'] ?? 0);
        $status = trim((string)($_POST['status'] ?? 'pending'));
        $note = trim((string)($_POST['admin_note'] ?? ''));
        if ($id > 0) {
            $reportModel = new AdminReportModel();
            $report = $reportModel->find($id);
            if ($report && $this->canHandleReport($report)) {
                if ($this->isFinalReport($report)) { redirect_or_ajax('/admin.php?path=reports' . $this->statusQuery()); }
                $reportModel->handle($id, $status, $note, (int)($_SESSION['auth_user']['id'] ?? 0), 'manual_status');
                (new AdminAuditLogModel())->record('report.status', 'report', $id, ['status'=>$status, 'note'=>$note]);
                if (in_array($status, ['resolved', 'rejected'], true)) {
                    $finalNote = $note !== '' ? $note : ($status === 'resolved' ? '举报已处理' : '举报已驳回');
                    $this->applyCreditForReport($report, $status, 'manual_status', $finalNote, (int)($_SESSION['auth_user']['id'] ?? 0), $id);
                    $this->notifyReporter($report, $status, $finalNote);
                }
            }
        }
        redirect_or_ajax('/admin.php?path=reports' . $this->statusQuery());
    }

    public function targetAction(): void
    {
        csrf_verify();
        $reportId = (int)($_POST['report_id'] ?? 0);
        $targetType = (string)($_POST['target_type'] ?? '');
        $targetId = (int)($_POST['target_id'] ?? 0);
        $action = (string)($_POST['target_action'] ?? '');
        $note = trim((string)($_POST['admin_note'] ?? ''));
        $adminId = (int)($_SESSION['auth_user']['id'] ?? 0);

        if ($reportId <= 0 || $targetId <= 0 || !in_array($targetType, ['thread', 'post', 'private_message'], true)) {
            redirect_or_ajax('/admin.php?path=reports' . $this->statusQuery());
        }

        $reportModel = new AdminReportModel();
        $report = $reportModel->find($reportId);
        if (!$report || !$this->canHandleReport($report) || (string)($report['target_type'] ?? '') !== $targetType || (int)($report['target_id'] ?? 0) !== $targetId) {
            redirect_or_ajax('/admin.php?path=reports' . $this->statusQuery());
        }
        if ($this->isFinalReport($report)) {
            redirect_or_ajax('/admin.php?path=reports' . $this->statusQuery());
        }

        if ($targetType === 'thread') {
            $this->handleThreadTarget($targetId, $action, $report);
        } elseif ($targetType === 'post') {
            $this->handlePostTarget($targetId, $action, $report);
        } else {
            $this->handlePrivateMessageTarget($targetId, $action);
        }

        $status = $action === 'reject' ? 'rejected' : 'resolved';
        $defaultNote = $this->actionLabel($action);
        $finalNote = $note !== '' ? $note : $defaultNote;
        $reportModel->handle($reportId, $status, $finalNote, $adminId, $action);
        (new AdminAuditLogModel())->record('report.target_action', 'report', $reportId, ['target_type'=>$targetType, 'target_id'=>$targetId, 'action'=>$action, 'note'=>$finalNote]);
        $this->applyCreditForReport($report, $status, $action, $finalNote, $adminId, $reportId);
        $this->notifyReporter($report, $status, $finalNote);
        if ($status === 'resolved' && $action !== 'reject') {
            $this->notifyTargetAuthor($report, $action, $finalNote);
        }
        redirect_or_ajax('/admin.php?path=reports' . $this->statusQuery());
    }


    private function isFinalReport(array $report): bool
    {
        return in_array((string)($report['status'] ?? ''), ['resolved','rejected'], true);
    }

    private function handleThreadTarget(int $threadId, string $action, array $report): void
    {
        $thread = (new \App\Models\ThreadModel())->find($threadId);
        if (!$thread) return;
        $sectionId = (int)($thread['section_id'] ?? 0);
        $model = new AdminThreadModel();
        if ($action === 'thread_hide') {
            $this->requireThreadModeration($sectionId, 'thread.hide');
            $model->updateStatus($threadId, 'hidden');
        } elseif ($action === 'thread_restore') {
            $this->requireThreadModeration($sectionId, 'thread.hide');
            $model->updateStatus($threadId, 'published');
        } elseif ($action === 'thread_delete') {
            $this->requireThreadModeration($sectionId, 'thread.delete_any');
            $model->deleteHard($threadId);
        } elseif ($action !== 'reject') {
            Permission::require('admin.report');
        }
    }

    private function handlePostTarget(int $postId, string $action, array $report): void
    {
        $sectionId = (int)($report['post_section_id'] ?? 0);
        $model = new AdminPostModel();
        if ($action === 'post_hide') {
            $this->requirePostModeration($sectionId, 'post.delete_any');
            $model->updateStatus($postId, 'hidden');
        } elseif ($action === 'post_restore') {
            $this->requirePostModeration($sectionId, 'review.post');
            $model->updateStatus($postId, 'published');
        } elseif ($action === 'post_delete') {
            $this->requirePostModeration($sectionId, 'post.delete_any');
            $model->delete($postId);
        } elseif ($action !== 'reject') {
            Permission::require('admin.report');
        }
    }

    private function handlePrivateMessageTarget(int $messageId, string $action): void
    {
        if ($action === 'private_hide') {
            Permission::require('admin.report');
            Database::connection()->prepare("UPDATE private_messages SET status='rejected', review_reason='举报处理', updated_at=NOW() WHERE id=:id")
                ->execute([':id'=>$messageId]);
        } elseif ($action !== 'reject') {
            Permission::require('admin.report');
        }
    }

    private function canHandleReport(array $report): bool
    {
        if (Permission::can('admin.report')) return true;
        $type = (string)($report['target_type'] ?? '');
        if ($type === 'thread') {
            $sectionId = (int)($report['thread_section_id'] ?? 0);
            return $this->canThreadModerate($sectionId, 'thread.hide') || $this->canThreadModerate($sectionId, 'thread.delete_any');
        }
        if ($type === 'post') {
            $sectionId = (int)($report['post_section_id'] ?? 0);
            return $this->canPostModerate($sectionId, 'post.delete_any') || $this->canPostModerate($sectionId, 'review.post');
        }
        return false;
    }

    private function requireThreadModeration(int $sectionId, string $perm): void
    {
        if (!$this->canThreadModerate($sectionId, $perm)) Permission::require($perm);
    }

    private function requirePostModeration(int $sectionId, string $perm): void
    {
        if (!$this->canPostModerate($sectionId, $perm)) Permission::require($perm);
    }

    private function canThreadModerate(int $sectionId, string $perm): bool
    {
        return Permission::can($perm, 'global')
            || Permission::can($perm, 'section', $sectionId)
            || Permission::can($perm, 'category', $this->categoryIdBySection($sectionId));
    }

    private function canPostModerate(int $sectionId, string $perm): bool
    {
        return Permission::can($perm, 'global')
            || Permission::can($perm, 'section', $sectionId)
            || Permission::can($perm, 'category', $this->categoryIdBySection($sectionId));
    }

    private function categoryIdBySection(int $sectionId): ?int
    {
        if ($sectionId <= 0) return null;
        try {
            $section = (new \App\Models\SectionModel())->findById($sectionId);
            return $section ? (int)($section['category_id'] ?? 0) : null;
        } catch (\Throwable $e) { return null; }
    }

    private function actionLabel(string $action): string
    {
        return [
            'thread_hide' => '已屏蔽被举报帖子',
            'thread_restore' => '已恢复被举报帖子',
            'thread_delete' => '已彻底删除被举报帖子',
            'post_hide' => '已屏蔽被举报回复',
            'post_restore' => '已恢复被举报回复',
            'post_delete' => '已删除被举报回复',
            'private_hide' => '已隐藏被举报私聊消息',
            'reject' => '举报已驳回',
        ][$action] ?? '已处理';
    }


    private function applyCreditForReport(array $report, string $status, string $action, string $note, int $adminId, int $reportId): void
    {
        try {
            $credit = new UserCreditModel();
            if (!$credit->settings()['enabled']) return;
            $reporterId = (int)($report['user_id'] ?? 0);
            if ($status === 'resolved') {
                $credit->rewardValidReport($reporterId, $reportId, $adminId, '举报核实有效：' . $note);
                $authorId = $this->reportedAuthorId($report);
                $affectedActions = ['thread_hide', 'thread_delete', 'post_hide', 'post_delete', 'private_hide'];
                if ($authorId > 0 && $authorId !== $reporterId && ($action === 'manual_status' || in_array($action, $affectedActions, true))) {
                    $credit->penalizeReportedValid($authorId, $reportId, $adminId, '被举报内容核实违规：' . $note);
                }
            } elseif ($status === 'rejected') {
                $credit->penalizeFalseReport($reporterId, $reportId, $adminId, '举报未核实有效：' . $note);
            }
        } catch (\Throwable $e) {
            error_log('[ClayBBS] user credit report hook failed: ' . $e->getMessage());
        }
    }

    private function reportedAuthorId(array $report): int
    {
        $type = (string)($report['target_type'] ?? '');
        if ($type === 'post') return (int)($report['post_user_id'] ?? 0);
        if ($type === 'private_message') return (int)($report['private_sender_id'] ?? 0);
        return (int)($report['thread_user_id'] ?? 0);
    }

    private function notifyReporter(array $report, string $status, string $note): void
    {
        $reporterId = (int)($report['user_id'] ?? 0);
        if ($reporterId <= 0) return;
        $typeLabel = $this->typeLabel((string)($report['target_type'] ?? ''));
        $targetTitle = $this->targetTitle($report);
        $title = $status === 'rejected' ? '你的举报已驳回' : '你的举报已处理';
        $content = '你举报的' . $typeLabel . ($targetTitle !== '' ? '《' . $targetTitle . '》' : '') . '已有处理结果：' . $note . '。';
        (new SystemMessageModel())->createPersonal($reporterId, $title, $content, 0, 'review', $this->targetUrl($report), 'report', (int)($report['id'] ?? 0));
    }

    private function notifyTargetAuthor(array $report, string $action, string $note): void
    {
        $authorId = (int)(($report['target_type'] ?? '') === 'post' ? ($report['post_user_id'] ?? 0) : (($report['target_type'] ?? '') === 'private_message' ? ($report['private_sender_id'] ?? 0) : ($report['thread_user_id'] ?? 0)));
        $reporterId = (int)($report['user_id'] ?? 0);
        if ($authorId <= 0 || $authorId === $reporterId) return;
        $affectedActions = ['thread_hide', 'thread_delete', 'post_hide', 'post_delete', 'private_hide'];
        if (!in_array($action, $affectedActions, true)) return;
        $typeLabel = $this->typeLabel((string)($report['target_type'] ?? ''));
        $targetTitle = $this->targetTitle($report);
        $title = $typeLabel . '被举报并已处理';
        $content = '你的' . $typeLabel . ($targetTitle !== '' ? '《' . $targetTitle . '》' : '') . '因举报被管理员处理：' . $note . '。如有疑问，请联系管理员。';
        (new SystemMessageModel())->createPersonal($authorId, $title, $content, 0, 'review', $this->targetUrl($report), (string)($report['target_type'] ?? 'report'), (int)($report['target_id'] ?? 0));
    }


    private function targetUrl(array $report): string
    {
        $type = (string)($report['target_type'] ?? '');
        if ($type === 'thread') return '/index.php?path=thread&id=' . (int)($report['target_id'] ?? 0);
        if ($type === 'post') return '/index.php?path=thread&id=' . (int)($report['post_thread_id'] ?? 0) . '#post-' . (int)($report['target_id'] ?? 0);
        return '/index.php?path=messages&type=private';
    }

    private function typeLabel(string $type): string
    {
        return $type === 'post' ? '回复' : ($type === 'private_message' ? '私聊消息' : '帖子');
    }

    private function targetTitle(array $report): string
    {
        $title = (string)(($report['target_type'] ?? '') === 'post' ? ($report['post_thread_title'] ?? '') : (($report['target_type'] ?? '') === 'private_message' ? ('私聊消息 #' . (int)($report['target_id'] ?? 0)) : ($report['thread_title'] ?? '')));
        $title = trim($title);
        if (function_exists('mb_strlen') && mb_strlen($title) > 60) return mb_substr($title, 0, 60) . '...';
        if (!function_exists('mb_strlen') && strlen($title) > 60) return substr($title, 0, 60) . '...';
        return $title;
    }

    private function statusQuery(): string
    {
        $status = trim((string)($_POST['return_status'] ?? $_GET['status'] ?? ''));
        return $status !== '' ? '&status=' . rawurlencode($status) : '';
    }
}
