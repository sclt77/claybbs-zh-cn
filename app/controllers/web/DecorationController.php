<?php

namespace App\Controllers\Web;

use App\Middleware\Permission;
use App\Models\MedalModel;
use App\Models\AvatarFrameModel;
use App\Services\MedalService;
use App\Services\AvatarFrameService;
use App\Models\BubbleModel;
use App\Services\BubbleService;
use App\Models\NameplateModel;
use App\Services\NameplateService;

class DecorationController
{
    public function index(): void
    {
        $userId = (int)($_SESSION['auth_user']['id'] ?? 0);
        if ($userId <= 0) {
            header('Location: /index.php?path=login&redirect=' . urlencode('/index.php?path=decoration'));
            exit;
        }

        $tab = trim((string)($_GET['tab'] ?? 'medals'));
        if (!in_array($tab, ['medals', 'frames', 'bubbles', 'nameplates'], true)) $tab = 'medals';

        $message = '';
        $error = '';

        
        $medalModel = new MedalModel();
        $medalService = new MedalService();
        $medalService->checkAuto($userId, '访问装饰中心触发检查');
        $medals = $medalModel->all(['status' => 'active']);
        $qualities = $medalModel->qualityOptions(true);
        $owned = [];
        foreach ($medalModel->userMedals($userId, false, 200) as $row) {
            $owned[(int)$row['badge_id']] = $row;
        }
        $progress = [];
        foreach ($medals as $m) {
            $progress[(int)$m['id']] = $medalModel->progressFor($userId, $m);
        }
        
        $decoCurNames = [];
        try { foreach ((new \App\Models\WalletModel())->currencies() as $dc) { $decoCurNames[strtoupper((string)($dc['code'] ?? ''))] = (string)($dc['name'] ?? $dc['code'] ?? ''); } } catch (\Throwable $e) {}
        $decoBalances = [];
        try { foreach ((new \App\Models\WalletModel())->balances($userId) as $bb) { $bc = strtoupper((string)($bb['currency_code'] ?? $bb['code'] ?? '')); if ($bc !== '') $decoBalances[$bc] = (float)($bb['balance'] ?? 0); } } catch (\Throwable $e) {}
        $clientMedals = [];
        foreach ($medals as $m) {
            $id = (int)$m['id'];
            $own = $owned[$id] ?? null;
            $p = $progress[$id] ?? ['label' => '由管理员手动发放', 'percent' => 0];
            $mObtain = (string)($m['obtain_method'] ?? 'grant');
            $mCur = strtoupper((string)($m['price_currency'] ?? ''));
            $mAmt = (float)($m['price_amount'] ?? 0);
            $mAmtLabel = rtrim(rtrim(number_format($mAmt, 6, '.', ''), '0'), '.');
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
                'obtain_method' => $mObtain,
                'price_currency' => $mCur,
                'price_amount' => $mAmtLabel,
                'price_label' => $mAmtLabel . ' ' . ($decoCurNames[$mCur] ?? $mCur),
                'can_afford' => $mCur !== '' && ($decoBalances[$mCur] ?? 0) >= $mAmt,
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

        
        $frameService = new AvatarFrameService();
        $frameService->ensureSchema();
        $frameService->seedDefaults();
        $frameModel = new AvatarFrameModel();
        $frames = $frameModel->userFrames($userId, false);
        $equipped = $frameModel->equippedForUser($userId);

        
        $bubbleService = new BubbleService();
        $bubbleService->ensureSchema();
        $bubbleService->seedDefaults();
        $bubbleModel = new BubbleModel();
        $bubbles = $bubbleModel->userBubbles($userId, false);
        $equippedBubble = $bubbleModel->equippedForUser($userId);
        $bubbleOwnedCount = 0;
        foreach ($bubbles as $b) { if (!empty($b['is_equipped']) || !empty($b['granted_at'])) $bubbleOwnedCount++; }

        
        $nameplateService = new NameplateService();
        $nameplateService->ensureSchema();
        $nameplateService->seedDefaults();
        $nameplateService->checkAuto($userId, '访问装饰中心触发名字特效解锁');
        $nameplateModel = new NameplateModel();
        $nameplates = $nameplateModel->userNameplates($userId, false);
        $equippedNameplate = $nameplateModel->equippedForUser($userId);
        $nameplateProgress = [];
        foreach ($nameplates as $np) {
            $nameplateProgress[(int)$np['id']] = $nameplateModel->progressFor($userId, $np);
        }
        $nameplateOwnedCount = 0;
        foreach ($nameplates as $np) { if (!empty($np['owned'])) $nameplateOwnedCount++; }
        
        $walletBalances = [];
        try { $walletBalances = (new \App\Models\WalletModel())->balances($userId); } catch (\Throwable $e) { $walletBalances = []; }

        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Permission::requireLogin();
            csrf_verify();
            try {
                $action = (string)($_POST['_action'] ?? '');
                if ($action === 'layout') {
                    $rawSlots = (string)($_POST['slots'] ?? '');
                    $slots = json_decode($rawSlots, true);
                    if (!is_array($slots)) $slots = [];
                    $medalService->saveLayout($userId, $slots);
                    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
                        header('Content-Type: application/json; charset=UTF-8');
                        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
                        return;
                    }
                    $message = '展示位已保存';
                }
                if ($action === 'equip_frame') {
                    $frameService->equip($userId, (int)($_POST['frame_id'] ?? 0));
                    $message = '已装备头像框';
                }
                if ($action === 'claim_frame') {
                    $frameService->claimFree($userId, (int)($_POST['frame_id'] ?? 0));
                    $message = '领取成功';
                }
                if ($action === 'buy_frame') {
                    $frameService->purchase($userId, (int)($_POST['frame_id'] ?? 0));
                    $message = '购买成功';
                }
                if ($action === 'equip_bubble') {
                    $bubbleService->equip($userId, (int)($_POST['bubble_id'] ?? 0));
                    $message = '已装备气泡';
                }
                if ($action === 'claim_badge') {
                    $medalService->claimFree($userId, (int)($_POST['badge_id'] ?? 0));
                    $message = '领取成功';
                }
                if ($action === 'buy_badge') {
                    $medalService->purchase($userId, (int)($_POST['badge_id'] ?? 0));
                    $message = '购买成功';
                }
                if ($action === 'claim_bubble') {
                    $bubbleService->claimFree($userId, (int)($_POST['bubble_id'] ?? 0));
                    $message = '领取成功';
                }
                if ($action === 'buy_bubble') {
                    $bubbleService->purchase($userId, (int)($_POST['bubble_id'] ?? 0));
                    $message = '购买成功';
                }
                if ($action === 'equip_nameplate') {
                    $nameplateService->equip($userId, (int)($_POST['nameplate_id'] ?? 0));
                    $message = '已装备名字特效';
                }
                if ($action === 'claim_nameplate') {
                    $nameplateService->claimFree($userId, (int)($_POST['nameplate_id'] ?? 0));
                    $message = '领取成功';
                }
                if ($action === 'buy_nameplate') {
                    $nameplateService->purchase($userId, (int)($_POST['nameplate_id'] ?? 0));
                    $message = '购买成功';
                }
                redirect_or_ajax('/index.php?path=decoration&tab=' . $tab);
            } catch (\Throwable $e) {
                if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
                    http_response_code(422);
                    header('Content-Type: application/json; charset=UTF-8');
                    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $error = $e->getMessage();
            }
        }

        $csrf = csrf_token();
        $user = $_SESSION['auth_user'] ?? [];
        require dirname(__DIR__, 2) . '/views/web/decoration/index.php';
    }
}
