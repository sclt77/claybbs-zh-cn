<?php

namespace App\Controllers\Web;

use App\Middleware\Permission;
use App\Models\MedalModel;
use App\Services\MedalService;

class MedalController
{
    public function index(): void
    {
        $model = new MedalModel();
        $service = new MedalService();
        $userId = (int)($_SESSION['auth_user']['id'] ?? 0);
        if ($userId > 0) {
            $service->checkAuto($userId, '访问勋章中心触发检查');
        }
        $category = trim((string)($_GET['category'] ?? ''));
        $scope = trim((string)($_GET['scope'] ?? ''));
        if ($scope === '') {
            $legacyPath = '/' . trim((string)($_GET['path'] ?? ''), '/');
            if ($legacyPath === '/me/medals') $scope = 'mine';
        }
        if ($scope === 'mine' && $userId <= 0) {
            header('Location: /index.php?path=login&redirect=' . urlencode('/index.php?path=medals&scope=mine'));
            exit;
        }
        $message = '';
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Permission::requireLogin();
            csrf_verify();
            try {
                $badgeId = (int)($_POST['badge_id'] ?? 0);
                $action = (string)($_POST['_action'] ?? '');
                if ($action === 'layout') {
                    $rawSlots = (string)($_POST['slots'] ?? '');
                    $slots = json_decode($rawSlots, true);
                    if (!is_array($slots)) $slots = [];
                    $service->saveLayout($userId, $slots);
                    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
                        header('Content-Type: application/json; charset=UTF-8');
                        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
                        return;
                    }
                }
                if ($action === 'equip') { $service->equip($userId, $badgeId, true); redirect_or_ajax('/index.php?path=medals' . ($scope === 'mine' ? '&scope=mine&msg=equipped' : '')); }
                if ($action === 'unequip') { $service->equip($userId, $badgeId, false); redirect_or_ajax('/index.php?path=medals' . ($scope === 'mine' ? '&scope=mine&msg=unequipped' : '')); }
                redirect_or_ajax('/index.php?path=medals' . ($scope === 'mine' ? '&scope=mine' : ''));
            } catch (\Throwable $e) {
                if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
                    http_response_code(422);
                    header('Content-Type: application/json; charset=UTF-8');
                    echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $error = $e->getMessage();
            }
        }
        $filters = ['status'=>'active'];
        if ($category !== '') $filters['category'] = $category;
        if ($scope === 'mine') $filters = ['status' => 'active'];
        $medals = $model->all($filters);
        $qualities = $model->qualityOptions(true);
        $owned = [];
        foreach ($userId > 0 ? $model->userMedals($userId, false, 200) : [] as $row) {
            $owned[(int)$row['badge_id']] = $row;
        }
        $progress = [];
        if ($userId > 0) {
            foreach ($medals as $m) $progress[(int)$m['id']] = $model->progressFor($userId, $m);
        }
        $clientMedals = [];
        foreach ($medals as $m) {
            $id = (int)$m['id'];
            $own = $owned[$id] ?? null;
            $p = $progress[$id] ?? ['label' => '由管理员手动发放', 'percent' => 0];
            $clientMedals[] = [
                'id' => $id,
                'name' => (string)($m['name'] ?? ''),
                'description' => (string)($m['description'] ?? ''),
                'icon' => (string)($m['icon'] ?? ''),
                'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($m['color'] ?? '')) ? (string)$m['color'] : '#2563eb',
                'level' => (string)($m['level'] ?? 'standard'),
                'level_name' => (string)($m['level_name'] ?? $m['level'] ?? '标准'),
                'level_color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($m['level_color'] ?? '')) ? (string)$m['level_color'] : '#64748b',
                'category' => (string)($m['category'] ?? 'manual'),
                'grant_type' => (string)($m['grant_type'] ?? 'manual'),
                'rule' => (string)($p['label'] ?? '由管理员手动发放'),
                'progress' => (int)($p['percent'] ?? 0),
                'grant_count' => (int)($m['grant_count'] ?? 0),
                'owned' => (bool)$own,
                'equipped' => $own ? !empty($own['is_equipped']) : false,
                'slot' => $own && !empty($own['is_equipped']) ? (int)($own['equip_slot'] ?? 0) : 0,
                'granted_at' => $own ? (string)($own['granted_at'] ?? '') : '',
            ];
        }
        $equippedSlots = array_fill(1, 5, null);
        $equippedCount = 0;
        foreach ($owned as $badgeId => $row) {
            if (!empty($row['is_equipped'])) {
                $slot = (int)($row['equip_slot'] ?? 0);
                if ($slot >= 1 && $slot <= 5) {
                    $equippedSlots[$slot] = (int)$badgeId;
                    $equippedCount++;
                }
            }
        }
        $slotValues = array_values($equippedSlots);
        $ownedCount = count($owned);
        $csrf = csrf_token();
        require dirname(__DIR__, 2) . '/views/web/medals/index.php';
    }

    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $model = new MedalModel();
        $medal = $model->find($id);
        if (!$medal || ($medal['status'] ?? '') !== 'active') {
            http_response_code(404);
            echo '勋章不存在';
            return;
        }
        $userId = (int)($_SESSION['auth_user']['id'] ?? 0);
        $owned = [];
        foreach ($userId > 0 ? $model->userMedals($userId, false, 200) : [] as $row) {
            $owned[(int)$row['badge_id']] = $row;
        }
        $progress = $userId > 0 ? $model->progressFor($userId, $medal) : ['label'=>$model->ruleLabel((string)$medal['rule_type'], (int)$medal['rule_value']), 'current'=>0, 'target'=>(int)$medal['rule_value'], 'percent'=>0];
        $recent = \App\Core\Database::connection()->prepare("SELECT ub.granted_at,u.id,u.username,u.nickname,u.public_id,u.avatar FROM plugin_user_badges ub JOIN users u ON u.id=ub.user_id WHERE ub.badge_id=:id ORDER BY ub.granted_at DESC LIMIT 30");
        $recent->execute([':id'=>$id]);
        $recentUsers = $recent->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        require dirname(__DIR__, 2) . '/views/web/medals/show.php';
    }

    public function mine(): void
    {
        Permission::requireLogin();
        $userId = (int)($_SESSION['auth_user']['id'] ?? 0);
        $model = new MedalModel();
        $service = new MedalService();
        $service->checkAuto($userId, '访问我的勋章触发检查');
        $message = '';
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            try {
                $badgeId = (int)($_POST['badge_id'] ?? 0);
                $action = (string)($_POST['_action'] ?? '');
                if ($action === 'equip') { $service->equip($userId, $badgeId, true); redirect_or_ajax('/index.php?path=me/medals&msg=equipped'); }
                if ($action === 'unequip') { $service->equip($userId, $badgeId, false); redirect_or_ajax('/index.php?path=me/medals&msg=unequipped'); }
                redirect_or_ajax('/index.php?path=me/medals');
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }
        $medals = $model->userMedals($userId, false, 200);
        require dirname(__DIR__, 2) . '/views/web/medals/mine.php';
    }
}
