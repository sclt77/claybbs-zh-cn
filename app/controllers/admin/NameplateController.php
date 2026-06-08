<?php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\NameplateModel;
use App\Services\NameplateService;

class NameplateController
{
    private NameplateModel $model;
    private NameplateService $service;

    public function __construct()
    {
        AdminAuth::check();
        Permission::require('admin.badge');
        $this->service = new NameplateService();
        $this->service->ensureSchema();
        $this->service->seedDefaults();
        $this->model = new NameplateModel();
    }

    public function index(): void
    {
        $db = Database::connection();
        $error = '';
        $message = '';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            csrf_verify();
            $action = (string)($_POST['_action'] ?? '');
            try {
                if ($action === 'save_nameplate') {
                    $this->model->save([
                        'id' => (int)($_POST['id'] ?? 0),
                        'name' => (string)($_POST['name'] ?? ''),
                        'description' => (string)($_POST['description'] ?? ''),
                        'style_key' => (string)($_POST['style_key'] ?? 'aurora'),
                        'frame_color' => (string)($_POST['frame_color'] ?? '#38bdf8'),
                        'accent_color' => (string)($_POST['accent_color'] ?? '#a78bfa'),
                        'text_color' => (string)($_POST['text_color'] ?? '#0f172a'),
                        'custom_css' => (string)($_POST['custom_css'] ?? ''),
                        'obtain_method' => (string)($_POST['obtain_method'] ?? 'grant'),
                        'price_currency' => (string)($_POST['price_currency'] ?? ''),
                        'price_amount' => (float)($_POST['price_amount'] ?? 0),
                        'rule_value' => (int)($_POST['rule_value'] ?? 0),
                        'sort_order' => (int)($_POST['sort_order'] ?? 0),
                        'status' => (string)($_POST['status'] ?? 'active'),
                    ]);
                    redirect_or_ajax('/admin.php?path=nameplates');
                    return;
                } elseif ($action === 'delete_nameplate') {
                    $this->model->delete((int)($_POST['id'] ?? 0));
                    redirect_or_ajax('/admin.php?path=nameplates');
                    return;
                } elseif ($action === 'grant_nameplate') {
                    $this->service->grant(
                        (int)($_POST['user_id'] ?? 0),
                        (int)($_POST['nameplate_id'] ?? 0),
                        'manual',
                        mb_substr(trim((string)($_POST['note'] ?? '')), 0, 255),
                        (int)($_SESSION['auth_user']['id'] ?? 0),
                        true
                    );
                    redirect_or_ajax('/admin.php?path=nameplates');
                    return;
                } elseif ($action === 'revoke_nameplate') {
                    $this->service->revokeGrant((int)($_POST['id'] ?? 0), (int)($_SESSION['auth_user']['id'] ?? 0));
                    redirect_or_ajax('/admin.php?path=nameplates');
                    return;
                } elseif ($action === 'check_auto') {
                    $uid = (int)($_POST['user_id'] ?? 0);
                    if ($uid <= 0) throw new \RuntimeException('请选择用户');
                    $message = '自动检查完成，新增 ' . $this->service->checkAuto($uid, '后台手动检查') . ' 个名字特效';
                }
            } catch (\Throwable $e) {
                $error = '操作失败：' . $e->getMessage();
            }
        }
        $nameplates = $this->model->all([]);
        $grants = $this->model->grantRows(200);
        $users = $db->query("SELECT id,username,nickname,public_id FROM users WHERE status='active' ORDER BY id DESC LIMIT 300")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $styleKeys = NameplateModel::STYLE_KEYS;
        $currencies = [];
        try {
            $currencies = $db->query("SELECT code,name,symbol FROM currencies WHERE status='active' ORDER BY sort_order ASC, id ASC")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $currencies = [];
        }
        
        $tasks = [];
        try {
            $tasks = $db->query("SELECT id,title,status FROM tasks ORDER BY id ASC")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $tasks = [];
        }
        $levels = [];
        try {
            $levels = $db->query("SELECT level,name FROM levels ORDER BY level ASC")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $levels = [];
        }
        require dirname(__DIR__, 2) . '/views/admin/content/nameplates.php';
    }
}
