<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\SettingModel;
use App\Models\VerificationModel;
use App\Models\SystemMessageModel;

class VerificationController
{
    public function __construct()
    {
        AdminAuth::check();
        Permission::require('admin.verification');
    }

    public function index(): void
    {
        $tab = (string)($_GET['tab'] ?? 'requests');
        $status = (string)($_GET['status'] ?? '');
        $model = new VerificationModel();
        $cooldownHours = $this->cooldownHours();
        $types = $model->types(false, false);
        $requests = [];
        $activeUsers = [];
        $cooldownUsers = [];
        if ($tab === 'types') {
            
        } elseif ($tab === 'users') {
            $activeUsers = $model->activeUsers(300);
        } elseif ($tab === 'cooldown') {
            $cooldownUsers = $model->cooldownUsers($cooldownHours, 300);
        } else {
            $requests = $model->requests($status, 300);
        }
        require dirname(__DIR__, 2) . '/views/admin/content/verifications.php';
    }

    public function saveType(): void
    {
        csrf_verify();
        (new VerificationModel())->saveType($_POST);
        redirect_or_ajax('/admin.php?path=verifications&tab=types');
    }

    public function deleteType(): void
    {
        csrf_verify();
        (new VerificationModel())->deleteType((int)($_POST['id'] ?? 0));
        redirect_or_ajax('/admin.php?path=verifications&tab=types');
    }

    public function saveSettings(): void
    {
        csrf_verify();
        $hours = max(0, min(8760, (int)($_POST['verification_reapply_cooldown_hours'] ?? 72)));
        (new SettingModel())->set('verification_reapply_cooldown_hours', (string)$hours);
        $_SESSION['flash_success'] = '认证冷却时间已保存。';
        redirect_or_ajax('/admin.php?path=verifications&tab=cooldown');
    }

    public function review(): void
    {
        csrf_verify();
        $id = (int)($_POST['id'] ?? 0);
        $action = (string)($_POST['action'] ?? 'reject');
        $note = trim((string)($_POST['note'] ?? ''));
        $adminId = (int)($_SESSION['auth_user']['id'] ?? 0);
        $result = null;
        if ($id > 0 && in_array($action, ['approve','reject'], true)) {
            $result = (new VerificationModel())->reviewRequest($id, $action, $adminId, $note);
        }
        if ($result) {
            try {
                if (($result['status'] ?? '') === 'approved') {
                    (new SystemMessageModel())->createPersonal((int)$result['user_id'], '认证审核通过', '你的“' . (string)$result['verification_name'] . '”认证已通过。', 1);
                    try { (new \App\Services\TaskService())->recordAction((int)$result['user_id'], 'verification_approved', 'verification', (int)$result['id']); } catch (\Throwable $e) {}
                } else {
                    (new SystemMessageModel())->createPersonal((int)$result['user_id'], '认证审核未通过', '你的“' . (string)$result['verification_name'] . '”认证申请未通过。' . ($note !== '' ? "\n原因：" . $note : ''), 1);
                }
            } catch (\Throwable $e) {}
        }
        redirect_or_ajax('/admin.php?path=verifications&tab=requests');
    }

    public function revoke(): void
    {
        csrf_verify();
        $id = (int)($_POST['id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($id > 0) {
            $result = (new VerificationModel())->revoke($id, (int)($_SESSION['auth_user']['id'] ?? 0), $reason !== '' ? $reason : '后台撤销认证');
            if ($result) {
                try {
                    (new SystemMessageModel())->createPersonal((int)$result['user_id'], '认证已撤销', '你的“' . (string)($result['verification_name'] ?? '认证') . '”已被后台撤销。' . ($reason !== '' ? "\n原因：" . $reason : ''), 1);
                } catch (\Throwable $e) {}
            }
        }
        redirect_or_ajax('/admin.php?path=verifications&tab=users');
    }

    private function cooldownHours(): int
    {
        $value = (new SettingModel())->get('verification_reapply_cooldown_hours', '72');
        return max(0, min(8760, (int)$value));
    }
}
