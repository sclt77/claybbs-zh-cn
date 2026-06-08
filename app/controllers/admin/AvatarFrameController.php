<?php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\AvatarFrameModel;
use App\Services\AvatarFrameService;

class AvatarFrameController
{
    private AvatarFrameModel $model;
    private AvatarFrameService $service;

    public function __construct()
    {
        AdminAuth::check();
        Permission::require('admin.badge');
        $this->service = new AvatarFrameService();
        $this->service->ensureSchema();
        $this->service->seedDefaults();
        $this->model = new AvatarFrameModel();
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
                if ($action === 'save_frame') {
                    $this->saveFrame();
                    redirect_or_ajax('/admin.php?path=avatar-frames');
                    return;
                } elseif ($action === 'delete_frame') {
                    $this->model->delete((int)($_POST['id'] ?? 0));
                    redirect_or_ajax('/admin.php?path=avatar-frames');
                    return;
                } elseif ($action === 'grant_frame') {
                    $this->grantFrame();
                    redirect_or_ajax('/admin.php?path=avatar-frames');
                    return;
                } elseif ($action === 'revoke_frame') {
                    $this->service->revokeGrant((int)($_POST['id'] ?? 0), (int)($_SESSION['auth_user']['id'] ?? 0));
                    redirect_or_ajax('/admin.php?path=avatar-frames');
                    return;
                } elseif ($action === 'check_auto') {
                    $uid = (int)($_POST['user_id'] ?? 0);
                    if ($uid <= 0) throw new \RuntimeException('请选择用户');
                    $message = '自动检查完成，新增 ' . $this->service->checkAuto($uid, '后台手动检查') . ' 个头像框';
                } elseif ($action === 'save_quality') {
                    $this->model->saveQuality([
                        'code' => (string)($_POST['code'] ?? ''),
                        'name' => (string)($_POST['name'] ?? ''),
                        'color' => (string)($_POST['color'] ?? '#64748b'),
                        'sort_order' => (int)($_POST['sort_order'] ?? 0),
                        'status' => (string)($_POST['status'] ?? 'active'),
                    ]);
                    $message = '品质已保存';
                } elseif ($action === 'delete_quality') {
                    $this->model->deleteQuality((string)($_POST['code'] ?? ''));
                    $message = '品质已删除';
                }
            } catch (\Throwable $e) {
                $error = '操作失败：' . $e->getMessage();
            }
        }
        $frames = $this->model->all([]);
        $grants = $this->model->grantRows(200);
        $users = $db->query("SELECT id,username,nickname,public_id FROM users WHERE status='active' ORDER BY id DESC LIMIT 300")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $qualities = $this->model->qualityOptions(false);
        $currencies = [];
        try { $currencies = $db->query("SELECT code,name,symbol FROM currencies WHERE status='active' ORDER BY sort_order ASC, id ASC")->fetchAll(\PDO::FETCH_ASSOC) ?: []; } catch (\Throwable $e) { $currencies = []; }
        $tasks = [];
        try { $tasks = $db->query("SELECT id,title,status FROM tasks ORDER BY id ASC")->fetchAll(\PDO::FETCH_ASSOC) ?: []; } catch (\Throwable $e) { $tasks = []; }
        $levels = [];
        try { $levels = $db->query("SELECT level,name FROM levels ORDER BY level ASC")->fetchAll(\PDO::FETCH_ASSOC) ?: []; } catch (\Throwable $e) { $levels = []; }
        require dirname(__DIR__, 2) . '/views/admin/content/avatar_frames.php';
    }

    private function saveFrame(): void
    {
        $image = trim((string)($_POST['image_existing'] ?? $_POST['image'] ?? ''));
        if (!empty($_FILES['image_file']) && (int)($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $image = upload_image($_FILES['image_file'], 'avatar-frames', 2 * 1024 * 1024);
        }
        $this->model->save([
            'id' => (int)($_POST['id'] ?? 0),
            'code' => trim((string)($_POST['code'] ?? '')),
            'name' => trim((string)($_POST['name'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'image' => $image,
            'quality' => trim((string)($_POST['quality'] ?? 'standard')),
            'quality_name' => trim((string)($_POST['quality_name'] ?? '标准')),
            'quality_color' => trim((string)($_POST['quality_color'] ?? '#64748b')),
            'grant_type' => (string)($_POST['grant_type'] ?? 'manual'),
            'rule_type' => (string)($_POST['rule_type'] ?? 'manual'),
            'rule_value' => (int)($_POST['rule_value'] ?? 0),
            'obtain_method' => (string)($_POST['obtain_method'] ?? 'grant'),
            'price_currency' => (string)($_POST['price_currency'] ?? ''),
            'price_amount' => (float)($_POST['price_amount'] ?? 0),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'status' => (string)($_POST['status'] ?? 'active'),
        ]);
    }

    private function grantFrame(): void
    {
        $expires = trim((string)($_POST['expires_at'] ?? ''));
        $this->service->grant(
            (int)($_POST['user_id'] ?? 0),
            (int)($_POST['frame_id'] ?? 0),
            mb_substr(trim((string)($_POST['note'] ?? '')), 0, 255),
            (int)($_SESSION['auth_user']['id'] ?? 0),
            'manual',
            $expires !== '' ? $expires : null,
            true
        );
    }
}
