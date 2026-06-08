<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\AdminAuditLogModel;
use App\Models\AdminUserModel;
use App\Models\UserCreditModel;
use App\Models\SystemMessageModel;

class UserCreditController
{
    public function __construct()
    {
        AdminAuth::check();
        Permission::require('user.ban');
    }

    public function index(): void
    {
        $tab = (string)($_GET['tab'] ?? 'users');
        $kw = trim((string)($_GET['kw'] ?? ''));
        $credit = new UserCreditModel();
        $settings = $credit->settings();
        $rows = $credit->rankRows($kw, 120);
        $logs = $credit->recent(120);
        require dirname(__DIR__, 2) . '/views/admin/content/user_credit.php';
    }

    public function settings(): void
    {
        csrf_verify();
        Permission::require('user.ban');
        $credit = new UserCreditModel();
        $tab = in_array((string)($_POST['tab'] ?? 'settings'), ['settings','limits','recovery'], true) ? (string)$_POST['tab'] : 'settings';
        $credit->saveSettings($_POST);
        (new AdminAuditLogModel())->record('credit.settings', 'credit', 0, ['tab' => $tab, 'settings' => $_POST]);
        redirect_or_ajax('/admin.php?path=user-credit&tab=' . $tab);
    }

    public function adjust(): void
    {
        csrf_verify();
        Permission::require('user.ban');
        $userId = (int)($_POST['user_id'] ?? 0);
        $delta = (int)($_POST['score_change'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));
        $adminId = (int)($_SESSION['auth_user']['id'] ?? 0);
        if ($userId > 0 && $delta !== 0) {
            $target = (new AdminUserModel())->find($userId);
            if ($target) {
                $reason = $reason !== '' ? $reason : '后台手动调整信用分';
                $result = (new UserCreditModel())->adjust($userId, $delta, $reason, $adminId, 'manual_adjust', 'admin', $adminId, false);
                (new AdminAuditLogModel())->record('credit.adjust', 'user', $userId, ['delta'=>$delta, 'reason'=>$reason, 'result'=>$result]);
                if ($result) {
                    $changeText = ((int)$result['change'] > 0 ? '+' : '') . (int)$result['change'];
                    (new SystemMessageModel())->createPersonal($userId, '信用分已调整', '管理员调整了你的信用分：' . $changeText . '，当前信用分 ' . (int)$result['after'] . '。原因：' . $reason . '。', 0, 'review');
                }
            }
        }
        $return = trim((string)($_POST['return_to'] ?? '/admin.php?path=user-credit'));
        redirect_or_ajax($return !== '' ? $return : '/admin.php?path=user-credit');
    }
}
