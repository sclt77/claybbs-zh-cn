<?php

namespace App\Controllers\Web;

use App\Models\SettingModel;
use App\Models\SystemMessageModel;
use App\Models\VerificationModel;

class VerificationController
{
    public function apply(): void
    {
        if (!auth_check()) {
            header('Location: /index.php?path=login');
            exit;
        }
        $user = auth_user();
        $userId = (int)($user['id'] ?? 0);
        $model = new VerificationModel();
        $cooldownHours = $this->cooldownHours();
        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $typeId = (int)($_POST['type_id'] ?? 0);
            $displayName = trim((string)($_POST['display_name'] ?? ''));
            $realName = trim((string)($_POST['real_name'] ?? ''));
            $material = trim((string)($_POST['material'] ?? ''));
            $activeVerification = $model->activeForUser($userId);
            $cooldown = $model->cooldownInfo($userId, $cooldownHours);
            if ($activeVerification) {
                $error = '你已经拥有认证，如需重新认证，请先在本页右上角撤销当前认证。';
            } elseif (!empty($cooldown['active'])) {
                $error = '认证撤销冷却中，剩余 ' . $this->formatRemaining((int)$cooldown['remaining_seconds']) . ' 后可重新申请。';
            } elseif ($typeId <= 0 || $displayName === '' || $material === '') {
                $error = '请选择认证类型、填写认证名称和申请说明';
            } elseif (mb_strlen($displayName) > 8) {
                $error = '认证名称不能超过 8 个字';
            } elseif ($model->pendingRequest($userId)) {
                $error = '你已有认证申请正在审核中，请等待处理';
            } else {
                try {
                    $ok = $model->createRequest($userId, $typeId, $displayName, $realName, $material, $cooldownHours);
                    $success = $ok ? '认证申请已提交，请等待审核。' : '当前状态暂不能提交认证申请';
                } catch (\Throwable $e) {
                    $error = '提交失败，请稍后再试';
                }
            }
        }

        $types = $model->types(true, true);
        $activeVerification = $model->activeForUser($userId);
        $latestRequest = $model->latestRequest($userId);
        $cooldown = $model->cooldownInfo($userId, $cooldownHours);
        require theme_view('web/verification/apply.php');
    }

    public function revoke(): void
    {
        if (!auth_check()) {
            header('Location: /index.php?path=login');
            exit;
        }
        csrf_verify();
        $userId = (int)(auth_user()['id'] ?? 0);
        $model = new VerificationModel();
        $cooldownHours = $this->cooldownHours();
        $revoked = $model->revokeByUser($userId, '用户主动撤销认证');
        if ($revoked) {
            try {
                (new SystemMessageModel())->createPersonal($userId, '认证已撤销', '你已主动撤销“' . (string)($revoked['verification_name'] ?? '认证') . '”。' . ($cooldownHours > 0 ? '冷却结束后可重新申请。' : '你可以重新提交认证申请。'), 1);
            } catch (\Throwable $e) {}
            $_SESSION['flash_success'] = '认证已撤销' . ($cooldownHours > 0 ? '，冷却结束后可重新申请。' : '，现在可以重新申请。');
        } else {
            $_SESSION['flash_error'] = '当前没有可撤销的认证。';
        }
        unset($_SESSION['auth_user']['verification_name'], $_SESSION['auth_user']['verification_color']);
        header('Location: /index.php?path=verification/apply');
        exit;
    }

    private function cooldownHours(): int
    {
        $value = (new SettingModel())->get('verification_reapply_cooldown_hours', '72');
        return max(0, min(8760, (int)$value));
    }

    private function formatRemaining(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $days = intdiv($seconds, 86400);
        $seconds %= 86400;
        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;
        $minutes = intdiv($seconds, 60);
        if ($days > 0) return $days . '天' . $hours . '小时';
        if ($hours > 0) return $hours . '小时' . $minutes . '分钟';
        return max(1, $minutes) . '分钟';
    }
}
