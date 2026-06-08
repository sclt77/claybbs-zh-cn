<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\BubbleModel;
use App\Services\BubbleService;
use App\Core\Database;
use PDO;

class BubbleController
{
    public function index(): void
    {
        AdminAuth::check();
        Permission::require('admin.badge');
        $model = new BubbleModel();
        $service = new BubbleService();
        $db = Database::connection();
        $message = '';
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $action = (string)($_POST['_action'] ?? '');
            try {
                if ($action === 'save_bubble') {
                    $data = [
                        'id' => (int)($_POST['id'] ?? 0),
                        'code' => trim((string)($_POST['code'] ?? '')),
                        'name' => trim((string)($_POST['name'] ?? '')),
                        'description' => trim((string)($_POST['description'] ?? '')),
                        'image' => trim((string)($_POST['image'] ?? '')),
                        'quality' => trim((string)($_POST['quality'] ?? 'standard')),
                        'quality_name' => trim((string)($_POST['quality_name'] ?? '标准')),
                        'quality_color' => trim((string)($_POST['quality_color'] ?? '#64748b')),
                        'grant_type' => trim((string)($_POST['grant_type'] ?? 'manual')),
                        'rule_type' => trim((string)($_POST['rule_type'] ?? 'manual')),
                        'rule_value' => (int)($_POST['rule_value'] ?? 0),
                        'obtain_method' => trim((string)($_POST['obtain_method'] ?? 'grant')),
                        'price_currency' => trim((string)($_POST['price_currency'] ?? '')),
                        'price_amount' => (float)($_POST['price_amount'] ?? 0),
                        'sort_order' => (int)($_POST['sort_order'] ?? 0),
                        'status' => trim((string)($_POST['status'] ?? 'active')),
                        'effect_type' => trim((string)($_POST['effect_type'] ?? '')),
                        'effect_params' => trim((string)($_POST['effect_params'] ?? '')),
                    ];
                    
                    if (!empty($data['effect_params'])) {
                        $pj = @json_decode($data['effect_params'], true);
                        if (is_array($pj)) {
                            if (empty($data['effect_type']) && !empty($pj['type'])) $data['effect_type'] = (string)$pj['type'];
                        } else {
                            $rawCss = $data['effect_params'];
                            $cssType = '';
                            if (preg_match('/\\.chat-msg\\.([a-zA-Z][a-zA-Z0-9_-]*)/', $rawCss, $m)) {
                                $cssType = $m[1];
                            } elseif (preg_match('/\\.([a-zA-Z][a-zA-Z0-9_-]*)\\s*\\{/', $rawCss, $m)) {
                                $cssType = $m[1];
                            }
                            if ($cssType !== '') {
                                $data['effect_type'] = $data['effect_type'] ?: $cssType;
                                $data['effect_params'] = json_encode(['type' => $cssType, 'css' => $rawCss], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            }
                        }
                    }
                    $model->save($data);
                    $message = $data['id'] > 0 ? '气泡已更新' : '气泡已创建';
                }
                if ($action === 'delete_bubble') {
                    $model->delete((int)($_POST['id'] ?? 0));
                    $message = '气泡已删除';
                }
                if ($action === 'grant_bubble') {
                    $uid = (int)($_POST['user_id'] ?? 0);
                    $bid = (int)($_POST['bubble_id'] ?? 0);
                    $note = trim((string)($_POST['note'] ?? ''));
                    $service->grant($uid, $bid, $note, (int)($_SESSION['admin_user']['id'] ?? 0));
                    $message = '已授予';
                }
                if ($action === 'revoke_bubble') {
                    $service->revokeGrant((int)($_POST['id'] ?? 0), (int)($_SESSION['admin_user']['id'] ?? 0));
                    $message = '已收回';
                }
                if ($action === 'save_quality') {
                    $model->saveQuality([
                        'code' => (string)($_POST['code'] ?? ''),
                        'name' => (string)($_POST['name'] ?? ''),
                        'color' => (string)($_POST['color'] ?? '#64748b'),
                        'sort_order' => (int)($_POST['sort_order'] ?? 0),
                        'status' => (string)($_POST['status'] ?? 'active'),
                    ]);
                    $message = '品质已保存';
                }
                if ($action === 'delete_quality') {
                    $model->deleteQuality((string)($_POST['code'] ?? ''));
                    $message = '品质已删除';
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        $bubbles = $model->all();
        $grants = $model->grantRows();
        $users = $db->query("SELECT id,username,nickname,public_id FROM users WHERE status='active' ORDER BY id DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $qualities = $model->qualityOptions(false);
        $effectTypes = BubbleModel::effectTypes();
        $currencies = [];
        try { $currencies = $db->query("SELECT code,name,symbol FROM currencies WHERE status='active' ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch (\Throwable $e) { $currencies = []; }
        $tasks = [];
        try { $tasks = $db->query("SELECT id,title,status FROM tasks ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch (\Throwable $e) { $tasks = []; }
        $levels = [];
        try { $levels = $db->query("SELECT level,name FROM levels ORDER BY level ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch (\Throwable $e) { $levels = []; }
        require dirname(__DIR__, 2) . '/views/admin/content/bubbles.php';
    }
}
