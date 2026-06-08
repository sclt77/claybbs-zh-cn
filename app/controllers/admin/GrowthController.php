<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Services\GrowthService;
use App\Services\TaskService;
use App\Services\CheckinService;
use App\Models\WalletModel;

class GrowthController
{
    public function __construct()
    {
        AdminAuth::check();
        Permission::require('admin.growth');
    }

    public function index(): void
    {
        $defaultTab = ((string)($_GET['path'] ?? '') === 'tasks') ? 'tasks' : 'levels';
        $tab = (string)($_GET['tab'] ?? $defaultTab);
        $growth = new GrowthService();
        $taskService = new TaskService();
        $levels = [];
        $tasks = [];
        $users = [];
        $expLogs = [];
        $submissions = [];
        $checkinSettings = [];
        $checkinRows = [];
        $checkinStats = [];
        $currencies = [];
        $actions = TaskService::ACTIONS;
        $categories = TaskService::CATEGORIES;
        $kw = trim((string)($_GET['kw'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));
        if ($tab === 'checkin') {
            $checkin = new CheckinService();
            $checkinSettings = $checkin->settings();
            $checkinStats = $checkin->adminStats();
            $checkinRows = $checkin->adminRecent(120);
            $currencies = (new WalletModel())->currencies();
        } elseif ($tab === 'tasks') {
            $category = trim((string)($_GET['category'] ?? ''));
            if (!array_key_exists($category, $categories)) { $category = ''; }
            $tasks = $taskService->tasks('', $category);
            $currencies = (new WalletModel())->currencies();
        } elseif ($tab === 'users') {
            $users = $growth->userRows($kw, 150);
        } elseif ($tab === 'logs') {
            $uid = (int)($_GET['user_id'] ?? 0);
            $expLogs = $uid > 0 ? $growth->logs($uid, 200) : $this->allLogs(200);
        } elseif ($tab === 'submissions') {
            $submissions = $taskService->submissions($status, 300);
        } else {
            $levels = $growth->levelRows();
        }
        require dirname(__DIR__, 2) . '/views/admin/content/growth.php';
    }

    public function saveLevel(): void
    {
        csrf_verify();
        (new GrowthService())->saveLevel($_POST);
        redirect_or_ajax('/admin.php?path=growth&tab=levels');
    }

    public function deleteLevel(): void
    {
        csrf_verify();
        (new GrowthService())->deleteLevel((int)($_POST['id'] ?? 0));
        redirect_or_ajax('/admin.php?path=growth&tab=levels');
    }


    public function saveCheckin(): void
    {
        csrf_verify();
        (new CheckinService())->saveSettings($_POST);
        redirect_or_ajax('/admin.php?path=growth&tab=checkin');
    }

    public function saveTask(): void
    {
        csrf_verify();
        (new TaskService())->saveTask($_POST);
        redirect_or_ajax('/admin.php?path=tasks&tab=tasks');
    }

    public function deleteTask(): void
    {
        csrf_verify();
        (new TaskService())->deleteTask((int)($_POST['id'] ?? 0));
        redirect_or_ajax('/admin.php?path=tasks&tab=tasks');
    }

    public function adjust(): void
    {
        csrf_verify();
        $userId = (int)($_POST['user_id'] ?? 0);
        $exp = (int)($_POST['exp'] ?? 0);
        $remark = trim((string)($_POST['remark'] ?? ''));
        if ($userId > 0 && $exp !== 0 && $remark !== '') {
            (new GrowthService())->adjust($userId, $exp, $remark, (int)($_SESSION['auth_user']['id'] ?? 0));
        }
        redirect_or_ajax('/admin.php?path=growth&tab=users&kw=' . urlencode((string)$userId));
    }

    public function reviewSubmission(): void
    {
        csrf_verify();
        $id = (int)($_POST['id'] ?? 0);
        $action = (string)($_POST['action'] ?? 'reject');
        $note = trim((string)($_POST['note'] ?? ''));
        if ($id > 0 && in_array($action, ['approve','reject'], true)) {
            (new TaskService())->reviewSubmission($id, $action, (int)($_SESSION['auth_user']['id'] ?? 0), $note);
        }
        redirect_or_ajax('/admin.php?path=tasks&tab=submissions');
    }

    private function allLogs(int $limit): array
    {
        $stmt = \App\Core\Database::connection()->prepare("SELECT l.*,u.username,u.nickname FROM user_exp_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.id DESC LIMIT :limit");
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
