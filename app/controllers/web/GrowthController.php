<?php

namespace App\Controllers\Web;

use App\Services\GrowthService;
use App\Services\TaskService;
use App\Services\CheckinService;
use RuntimeException;

class GrowthController
{
    public function index(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        $userId = (int)auth_user()['id'];
        $growthService = new GrowthService();
        $taskService = new TaskService();
        $summary = $growthService->summary($userId);
        $tasks = $taskService->availableTasksForUser($userId);
        $logs = $growthService->logs($userId, 30);
        $checkin = (new CheckinService())->summary($userId);
        $message = (string)($_SESSION['flash_success'] ?? ''); unset($_SESSION['flash_success']);
        $error = (string)($_SESSION['flash_error'] ?? ''); unset($_SESSION['flash_error']);
        require theme_view('web/growth/index.php');
    }


    public function checkin(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        csrf_verify();
        try {
            $result = (new CheckinService())->checkin((int)auth_user()['id']);
            $_SESSION['flash_success'] = '签到成功，获得 +' . (int)$result['reward_exp'] . ' 经验' . (!empty($result['reward_currency_code']) ? ('、+' . currency_pay_label((float)$result['reward_currency_amount'], (string)$result['reward_currency_code'])) : '') . '。';
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = '签到失败，请稍后再试。';
        }
        redirect_or_ajax('/index.php?path=growth&tab=overview');
    }

    public function claim(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        csrf_verify();
        try {
            (new TaskService())->claim((int)auth_user()['id'], (int)($_POST['task_id'] ?? 0));
            $_SESSION['flash_success'] = '任务奖励已领取。';
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = '领取失败，请稍后再试。';
        }
        $base = ((string)($_POST['path'] ?? ($_GET['path'] ?? 'growth')) === 'tasks') ? '/index.php?path=tasks&tab=tasks' : '/index.php?path=growth&tab=tasks';
        redirect_or_ajax($base);
    }

    public function submit(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        csrf_verify();
        try {
            (new TaskService())->submitManual((int)auth_user()['id'], (int)($_POST['task_id'] ?? 0), (string)($_POST['content'] ?? ''));
            $_SESSION['flash_success'] = '任务已提交，请等待审核。';
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = '提交失败，请稍后再试。';
        }
        $base = ((string)($_POST['path'] ?? ($_GET['path'] ?? 'growth')) === 'tasks') ? '/index.php?path=tasks&tab=tasks' : '/index.php?path=growth&tab=tasks';
        redirect_or_ajax($base);
    }
}
