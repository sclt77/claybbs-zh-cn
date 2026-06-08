<?php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\MedalModel;
use App\Services\MedalService;

class BadgeController
{
    private MedalModel $model;
    private MedalService $service;

    public function __construct()
    {
        AdminAuth::check();
        Permission::require('admin.badge');
        $this->service = new MedalService();
        $this->service->ensureSchema();
        $this->service->seedDefaults();
        $this->model = new MedalModel();
    }

    public function index(): void
    {
        $db = Database::connection();
        $error = '';
        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $action = (string)($_POST['_action'] ?? '');
            try {
                if ($action === 'save_badge') {
                    $this->saveBadge();
                    redirect_or_ajax('/admin.php?path=badges');
                } elseif ($action === 'delete_badge') {
                    $this->model->delete((int)($_POST['id'] ?? 0));
                    redirect_or_ajax('/admin.php?path=badges');
                } elseif ($action === 'save_quality') {
                    $this->model->saveQuality($_POST);
                    redirect_or_ajax('/admin.php?path=badges');
                } elseif ($action === 'delete_quality') {
                    $this->model->deleteQuality((string)($_POST['code'] ?? ''));
                    redirect_or_ajax('/admin.php?path=badges');
                } elseif ($action === 'grant_badge') {
                    $this->grantBadge();
                    redirect_or_ajax('/admin.php?path=badges');
                } elseif ($action === 'revoke_badge') {
                    $this->service->revokeGrant((int)($_POST['id'] ?? 0), (int)($_SESSION['auth_user']['id'] ?? 0));
                    redirect_or_ajax('/admin.php?path=badges');
                } elseif ($action === 'check_auto') {
                    $uid = (int)($_POST['user_id'] ?? 0);
                    if ($uid <= 0) throw new \RuntimeException('请选择用户');
                    $message = '自动检查完成，新增 ' . $this->service->checkAuto($uid, '后台手动检查') . ' 枚勋章';
                }
            } catch (\Throwable $e) {
                $error = '操作失败：' . $e->getMessage();
            }
        }

        $badges = $this->model->all([]);
        $qualities = $this->model->qualityOptions(false);
        $grants = $this->model->grantRows(200);
        $users = $db->query("SELECT id,username,nickname,public_id FROM users WHERE status='active' ORDER BY id DESC LIMIT 300")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $currencies = [];
        try { $currencies = $db->query("SELECT code,name,symbol FROM currencies WHERE status='active' ORDER BY sort_order ASC, id ASC")->fetchAll(\PDO::FETCH_ASSOC) ?: []; } catch (\Throwable $e) { $currencies = []; }
        $tasks = [];
        try { $tasks = $db->query("SELECT id,title,status FROM tasks ORDER BY id ASC")->fetchAll(\PDO::FETCH_ASSOC) ?: []; } catch (\Throwable $e) { $tasks = []; }
        $levels = [];
        try { $levels = $db->query("SELECT level,name FROM levels ORDER BY level ASC")->fetchAll(\PDO::FETCH_ASSOC) ?: []; } catch (\Throwable $e) { $levels = []; }
        $pluginView = dirname(__DIR__, 3) . '/plugins/badges/views/admin.php';
        require is_file($pluginView) ? $pluginView : dirname(__DIR__, 2) . '/views/admin/content/badges.php';
    }

    private function saveBadge(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $icon = trim((string)($_POST['icon_existing'] ?? $_POST['icon'] ?? ''));
        if (!empty($_FILES['icon_file']) && (int)($_FILES['icon_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $icon = upload_image($_FILES['icon_file'], 'badges', 1024 * 1024);
        }
        $this->model->save([
            'id' => $id,
            'code' => trim((string)($_POST['code'] ?? '')),
            'name' => trim((string)($_POST['name'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'icon' => $icon,
            'color' => trim((string)($_POST['color'] ?? '#f59e0b')),
            'category' => trim((string)($_POST['category'] ?? 'manual')),
            'level' => $this->normalizeLevel((string)($_POST['level'] ?? 'standard')),
            'grant_type' => (string)($_POST['grant_type'] ?? 'manual'),
            'rule_type' => (string)($_POST['rule_type'] ?? 'manual'),
            'rule_value' => (int)($_POST['rule_value'] ?? 0),
            'max_equipped' => 1,
            'obtain_method' => (string)($_POST['obtain_method'] ?? 'grant'),
            'price_currency' => (string)($_POST['price_currency'] ?? ''),
            'price_amount' => (float)($_POST['price_amount'] ?? 0),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'status' => (string)($_POST['status'] ?? 'active'),
        ]);
    }

    private function normalizeLevel(string $level): string
    {
        $level = strtolower(trim($level));
        if ($level === '') return 'standard';
        foreach ($this->model->qualityOptions(false) as $q) {
            if ((string)($q['code'] ?? '') === $level) return $level;
        }
        return 'standard';
    }

    private function grantBadge(): void
    {
        $userId = (int)($_POST['user_id'] ?? 0);
        $badgeId = (int)($_POST['badge_id'] ?? 0);
        $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 255);
        $this->service->grant($userId, $badgeId, $note, (int)($_SESSION['auth_user']['id'] ?? 0), 'manual', true);
    }
}
