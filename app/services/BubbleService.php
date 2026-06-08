<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Hook;
use App\Models\BubbleModel;
use App\Models\SystemMessageModel;
use PDO;

class BubbleService
{
    private PDO $db;
    private BubbleModel $model;
    private static bool $hooksBooted = false;

    public function __construct()
    {
        $this->db = Database::connection();
        $this->model = new BubbleModel();
    }

    public static function bootHooks(): void
    {
        if (self::$hooksBooted) return;
        self::$hooksBooted = true;
        Hook::listen('app.booted', function (array $payload): array {
            (new self())->ensureSchema();
            (new self())->seedDefaults();
            return $payload;
        }, 2);

        Hook::listen('user.chat_bubble', function (array $payload): array {
            $ctx = $payload['context'] ?? [];
            $uid = (int)($ctx['user_id'] ?? 0);
            if ($uid <= 0) return $payload;
            $payload['value'] = (new self())->equippedBubble($uid);
            return $payload;
        }, 10);
    }

    public function ensureSchema(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS `plugin_chat_bubbles` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(80) NOT NULL,
            `name` VARCHAR(80) NOT NULL,
            `description` VARCHAR(255) DEFAULT NULL,
            `image` VARCHAR(255) DEFAULT NULL,
            `quality` VARCHAR(40) NOT NULL DEFAULT 'standard',
            `quality_name` VARCHAR(40) NOT NULL DEFAULT '标准',
            `quality_color` VARCHAR(20) NOT NULL DEFAULT '#64748b',
            `grant_type` VARCHAR(20) NOT NULL DEFAULT 'manual',
            `rule_type` VARCHAR(40) NOT NULL DEFAULT 'manual',
            `rule_value` INT NOT NULL DEFAULT 0,
            `sort_order` INT NOT NULL DEFAULT 0,
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `effect_type` VARCHAR(40) DEFAULT NULL,
            `effect_params` TEXT DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_plugin_chat_bubbles_code` (`code`),
            KEY `idx_plugin_chat_bubbles_status_sort` (`status`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        
        $cols = [];
        $rs = $this->db->query("SHOW COLUMNS FROM plugin_chat_bubbles");
        while ($r = $rs->fetch(PDO::FETCH_ASSOC)) { $cols[] = $r['Field']; }
        if (!in_array('effect_type', $cols)) {
            $this->db->exec("ALTER TABLE `plugin_chat_bubbles` ADD COLUMN `effect_type` VARCHAR(40) DEFAULT NULL AFTER `image`");
        }
        if (!in_array('effect_params', $cols)) {
            $this->db->exec("ALTER TABLE `plugin_chat_bubbles` ADD COLUMN `effect_params` TEXT DEFAULT NULL AFTER `effect_type`");
        }
        if (!in_array('obtain_method', $cols)) {
            $this->db->exec("ALTER TABLE `plugin_chat_bubbles` ADD COLUMN `obtain_method` VARCHAR(30) NOT NULL DEFAULT 'grant'");
        }
        if (!in_array('price_currency', $cols)) {
            $this->db->exec("ALTER TABLE `plugin_chat_bubbles` ADD COLUMN `price_currency` VARCHAR(20) DEFAULT NULL");
        }
        if (!in_array('price_amount', $cols)) {
            $this->db->exec("ALTER TABLE `plugin_chat_bubbles` ADD COLUMN `price_amount` DECIMAL(18,6) NOT NULL DEFAULT 0");
        }

        $this->db->exec("CREATE TABLE IF NOT EXISTS `plugin_user_chat_bubbles` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `bubble_id` BIGINT UNSIGNED NOT NULL,
            `note` VARCHAR(255) DEFAULT NULL,
            `grant_source` VARCHAR(30) NOT NULL DEFAULT 'manual',
            `is_equipped` TINYINT(1) NOT NULL DEFAULT 0,
            `notice_sent` TINYINT(1) NOT NULL DEFAULT 0,
            `granted_by` BIGINT UNSIGNED DEFAULT NULL,
            `granted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_plugin_user_chat_bubble` (`user_id`, `bubble_id`),
            KEY `idx_plugin_user_chat_bubbles_user` (`user_id`),
            KEY `idx_plugin_user_chat_bubbles_bubble` (`bubble_id`),
            KEY `idx_plugin_user_chat_bubbles_equipped` (`user_id`, `is_equipped`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->exec("CREATE TABLE IF NOT EXISTS `plugin_bubble_qualities` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(40) NOT NULL,
            `name` VARCHAR(40) NOT NULL,
            `color` VARCHAR(20) NOT NULL DEFAULT '#64748b',
            `sort_order` INT NOT NULL DEFAULT 0,
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_plugin_bubble_qualities_code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->exec("CREATE TABLE IF NOT EXISTS `plugin_chat_bubble_logs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `bubble_id` BIGINT UNSIGNED NOT NULL,
            `action` VARCHAR(30) NOT NULL,
            `source` VARCHAR(30) DEFAULT NULL,
            `operator_id` BIGINT UNSIGNED DEFAULT NULL,
            `note` VARCHAR(255) DEFAULT NULL,
            `ip` VARCHAR(64) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_plugin_chat_bubble_logs_user` (`user_id`, `created_at`),
            KEY `idx_plugin_chat_bubble_logs_bubble` (`bubble_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function seedDefaults(): void
    {
        
        $qualities = [
            ['legend','传奇','#b91c1c',10],
            ['epic','史诗','#7c3aed',20],
            ['rare','稀有','#2563eb',30],
            ['standard','标准','#64748b',40],
        ];
        foreach ($qualities as $q) {
            [$code,$name,$color,$sort] = $q;
            $this->db->prepare("INSERT INTO plugin_bubble_qualities (code,name,color,sort_order,status,created_at,updated_at) VALUES (:code,:name,:color,:sort,'active',NOW(),NOW()) ON DUPLICATE KEY UPDATE updated_at=updated_at")
                ->execute([':code'=>$code, ':name'=>$name, ':color'=>$color, ':sort'=>$sort]);
        }

        $defaults = [
            [
                'code' => 'galaxy-dream',
                'name' => '星河幻想',
                'description' => '论坛专属聊天气泡：深空渐变、星轨光环与星尘粒子，不使用旧气泡样式。',
                'image' => '/assets/img/bubbles/star-sparkle.svg',
                'effect_type' => 'galaxy',
                'effect_params' => json_encode(['color' => '#22d3ee', 'count' => 18, 'speed' => 0.85, 'size' => 3], JSON_UNESCAPED_UNICODE),
                'quality' => 'epic',
                'quality_name' => '史诗',
                'quality_color' => '#7c3aed',
                'obtain_method' => 'grant',
                'sort_order' => 10,
            ],
            [
                'code' => 'sweet-cat',
                'name' => '甜心喵咪',
                'description' => '论坛专属聊天气泡：奶橘渐变、猫脸挂件与猫爪粒子，轻盈可爱风。',
                'image' => '/assets/img/bubbles/cat-paw.svg',
                'effect_type' => 'cat',
                'effect_params' => json_encode(['color' => '#fb923c', 'count' => 12, 'speed' => 0.75, 'size' => 13], JSON_UNESCAPED_UNICODE),
                'quality' => 'rare',
                'quality_name' => '稀有',
                'quality_color' => '#2563eb',
                'obtain_method' => 'grant',
                'sort_order' => 20,
            ],
        ];
        foreach ($defaults as $row) {
            $stmt = $this->db->prepare('SELECT id FROM plugin_chat_bubbles WHERE code=:code LIMIT 1');
            $stmt->execute([':code' => $row['code']]);
            $id = (int)$stmt->fetchColumn();
            $payload = [
                ':code' => $row['code'], ':name' => $row['name'], ':description' => $row['description'], ':image' => $row['image'],
                ':effect_type' => $row['effect_type'], ':effect_params' => $row['effect_params'],
                ':quality' => $row['quality'], ':quality_name' => $row['quality_name'], ':quality_color' => $row['quality_color'],
                ':obtain_method' => $row['obtain_method'], ':price_currency' => null, ':price_amount' => '0.000000',
                ':grant_type' => 'manual', ':rule_type' => 'manual', ':rule_value' => 0,
                ':sort_order' => $row['sort_order'], ':status' => 'active',
            ];
            if ($id > 0) {
                $payload[':id'] = $id;
                $this->db->prepare('UPDATE plugin_chat_bubbles SET code=:code,name=:name,description=:description,image=:image,effect_type=:effect_type,effect_params=:effect_params,quality=:quality,quality_name=:quality_name,quality_color=:quality_color,obtain_method=:obtain_method,price_currency=:price_currency,price_amount=:price_amount,grant_type=:grant_type,rule_type=:rule_type,rule_value=:rule_value,sort_order=:sort_order,status=:status,updated_at=NOW() WHERE id=:id')
                    ->execute($payload);
            } else {
                $this->db->prepare('INSERT INTO plugin_chat_bubbles (code,name,description,image,effect_type,effect_params,quality,quality_name,quality_color,obtain_method,price_currency,price_amount,grant_type,rule_type,rule_value,sort_order,status,created_at,updated_at) VALUES (:code,:name,:description,:image,:effect_type,:effect_params,:quality,:quality_name,:quality_color,:obtain_method,:price_currency,:price_amount,:grant_type,:rule_type,:rule_value,:sort_order,:status,NOW(),NOW())')
                    ->execute($payload);
            }
        }
    }

    public function grant(int $userId, int $bubbleId, string $note = '', int $operatorId = 0, string $source = 'manual', bool $notify = true): bool
    {
        if ($userId <= 0 || $bubbleId <= 0) throw new \RuntimeException('请选择用户和气泡');
        $bubble = $this->model->find($bubbleId);
        if (!$bubble) throw new \RuntimeException('气泡不存在');
        $stmt = $this->db->prepare('SELECT id FROM plugin_user_chat_bubbles WHERE user_id=:uid AND bubble_id=:bid LIMIT 1');
        $stmt->execute([':uid'=>$userId, ':bid'=>$bubbleId]);
        $existingId = (int)$stmt->fetchColumn();
        if ($existingId > 0) {
            $this->db->prepare('UPDATE plugin_chat_bubble_logs SET note=:note, grant_source=:source, granted_by=:operator, granted_at=NOW(), updated_at=NOW() WHERE id=:id')
                ->execute([':note'=>$note, ':source'=>$source, ':operator'=>$operatorId ?: null, ':id'=>$existingId]);
            $this->log($userId, $bubbleId, 'refresh', $source, $operatorId, $note);
            return false;
        }
        $this->db->prepare('INSERT INTO plugin_user_chat_bubbles (user_id,bubble_id,note,grant_source,is_equipped,notice_sent,granted_by,granted_at,updated_at) VALUES (:uid,:bid,:note,:source,0,0,:operator,NOW(),NOW())')
            ->execute([':uid'=>$userId, ':bid'=>$bubbleId, ':note'=>$note, ':source'=>$source, ':operator'=>$operatorId ?: null]);
        $this->log($userId, $bubbleId, 'grant', $source, $operatorId, $note);
        if ($notify) $this->notifyGrant($userId, $bubble);
        return true;
    }

    public function revokeGrant(int $grantId, int $operatorId = 0): void
    {
        $stmt = $this->db->prepare('SELECT * FROM plugin_user_chat_bubbles WHERE id=:id LIMIT 1');
        $stmt->execute([':id'=>$grantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return;
        $this->db->prepare('DELETE FROM plugin_user_chat_bubbles WHERE id=:id')->execute([':id'=>$grantId]);
        $this->log((int)$row['user_id'], (int)$row['bubble_id'], 'revoke', 'manual', $operatorId, (string)($row['note'] ?? ''));
    }

    public function equip(int $userId, int $bubbleId): void
    {
        if ($userId <= 0) throw new \RuntimeException('请先登录');
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE plugin_user_chat_bubbles SET is_equipped=0,updated_at=NOW() WHERE user_id=:uid')->execute([':uid'=>$userId]);
            if ($bubbleId > 0) {
                $stmt = $this->db->prepare('SELECT * FROM plugin_user_chat_bubbles WHERE user_id=:uid AND bubble_id=:bid LIMIT 1');
                $stmt->execute([':uid'=>$userId, ':bid'=>$bubbleId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) { $this->db->rollBack(); throw new \RuntimeException('你尚未获得该气泡'); }
                $this->db->prepare('UPDATE plugin_user_chat_bubbles SET is_equipped=1,updated_at=NOW() WHERE id=:id')->execute([':id'=>(int)$row['id']]);
                $this->log($userId, $bubbleId, 'equip', 'user', $userId, '');
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function equippedBubble(int $userId): ?array
    {
        return $this->model->equippedForUser($userId);
    }

    
    public function checkAuto(int $userId, string $reason = ''): int
    {
        if ($userId <= 0) return 0;
        $count = 0;
        foreach ($this->model->all(['status'=>'active']) as $bubble) {
            $obtain = (string)($bubble['obtain_method'] ?? 'grant');
            if (!in_array($obtain, ['task','level'], true)) continue;
            $progress = $this->bubbleProgress($userId, $bubble);
            if (!empty($progress['done'])) {
                if ($this->grant($userId, (int)$bubble['id'], $reason ?: ('自动解锁：' . (string)$progress['label']), 0, $obtain, true)) $count++;
            }
        }
        return $count;
    }

    
    public function claimFree(int $userId, int $bubbleId): void
    {
        if ($userId <= 0) throw new \RuntimeException('请先登录');
        $bubble = $this->model->find($bubbleId);
        if (!$bubble || (string)$bubble['status'] !== 'active') throw new \RuntimeException('气泡不存在或已下架');
        if ((string)($bubble['obtain_method'] ?? '') !== 'free') throw new \RuntimeException('该气泡不是免费领取');
        $stmt = $this->db->prepare('SELECT id FROM plugin_user_chat_bubbles WHERE user_id=:uid AND bubble_id=:bid LIMIT 1');
        $stmt->execute([':uid'=>$userId, ':bid'=>$bubbleId]);
        if ((int)$stmt->fetchColumn() > 0) throw new \RuntimeException('你已拥有该气泡');
        
        $equipped = $this->model->equippedForUser($userId) ? 0 : 1;
        $this->db->prepare('INSERT INTO plugin_user_chat_bubbles (user_id,bubble_id,note,grant_source,is_equipped,notice_sent,granted_by,granted_at,updated_at) VALUES (:uid,:bid,:note,:source,:equipped,0,NULL,NOW(),NOW())')
            ->execute([':uid'=>$userId, ':bid'=>$bubbleId, ':note'=>'免费领取', ':source'=>'free', ':equipped'=>$equipped]);
        $this->log($userId, $bubbleId, 'claim', 'free', 0, '免费领取');
    }

    
    public function purchase(int $userId, int $bubbleId): void
    {
        if ($userId <= 0) throw new \RuntimeException('请先登录');
        $bubble = $this->model->find($bubbleId);
        if (!$bubble || (string)$bubble['status'] !== 'active') throw new \RuntimeException('气泡不存在或已下架');
        if ((string)($bubble['obtain_method'] ?? '') !== 'shop') throw new \RuntimeException('该气泡不支持购买');
        $stmt = $this->db->prepare('SELECT id FROM plugin_user_chat_bubbles WHERE user_id=:uid AND bubble_id=:bid LIMIT 1');
        $stmt->execute([':uid'=>$userId, ':bid'=>$bubbleId]);
        if ((int)$stmt->fetchColumn() > 0) throw new \RuntimeException('你已拥有该气泡');
        $currency = (string)($bubble['price_currency'] ?? '');
        $amount = (float)($bubble['price_amount'] ?? 0);
        if ($currency === '' || $amount <= 0) throw new \RuntimeException('该气泡未正确配置价格');
        $this->db->beginTransaction();
        try {
            (new \App\Models\WalletModel())->addTransaction($userId, $currency, '-' . number_format($amount, 6, '.', ''), 'bubble_purchase', '购买气泡「' . (string)$bubble['name'] . '」', '', null, null, 'bubble', $bubbleId);
            
            $equipped = $this->model->equippedForUser($userId) ? 0 : 1;
            $this->db->prepare('INSERT INTO plugin_user_chat_bubbles (user_id,bubble_id,note,grant_source,is_equipped,notice_sent,granted_by,granted_at,updated_at) VALUES (:uid,:bid,:note,:source,:equipped,0,NULL,NOW(),NOW())')
                ->execute([':uid'=>$userId, ':bid'=>$bubbleId, ':note'=>'商城购买', ':source'=>'shop', ':equipped'=>$equipped]);
            $this->log($userId, $bubbleId, 'purchase', 'shop', 0, '商城购买');
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    
    public function bubbleProgress(int $userId, array $bubble): array
    {
        $obtain = (string)($bubble['obtain_method'] ?? 'grant');
        $threshold = (int)($bubble['price_amount'] ?? 0);
        if ($obtain === 'level') {
            $current = 0;
            try {
                $stmt = $this->db->prepare('SELECT current_level FROM user_growth_stats WHERE user_id=:uid LIMIT 1');
                $stmt->execute([':uid'=>$userId]);
                $current = (int)$stmt->fetchColumn();
            } catch (\Throwable $e) { $current = 0; }
            $pct = $threshold > 0 ? min(100, (int)floor($current * 100 / $threshold)) : 0;
            return ['current'=>$current, 'target'=>$threshold, 'percent'=>$pct, 'label'=>'等级达到 Lv.' . $threshold, 'done'=>$current >= $threshold && $threshold > 0];
        }
        if ($obtain === 'task') {
            $done = false;
            try {
                $stmt = $this->db->prepare("SELECT 1 FROM user_task_progress WHERE user_id=:uid AND task_id=:tid AND status IN ('completed','claimed') LIMIT 1");
                $stmt->execute([':uid'=>$userId, ':tid'=>$threshold]);
                $done = (bool)$stmt->fetchColumn();
            } catch (\Throwable $e) { $done = false; }
            $taskTitle = '';
            try {
                $stmt = $this->db->prepare('SELECT title FROM tasks WHERE id=:tid LIMIT 1');
                $stmt->execute([':tid'=>$threshold]);
                $taskTitle = (string)$stmt->fetchColumn();
            } catch (\Throwable $e) {}
            return ['current'=>$done?1:0, 'target'=>1, 'percent'=>$done?100:0, 'label'=>$taskTitle !== '' ? ('完成任务：' . $taskTitle) : ('完成指定任务 #' . $threshold), 'done'=>$done];
        }
        return ['current'=>0, 'target'=>0, 'percent'=>0, 'label'=>'由管理员发放', 'done'=>false];
    }

    private function notifyGrant(int $userId, array $bubble): void
    {
        try {
            $bubbleId = (int)$bubble['id'];
            (new SystemMessageModel())->createPersonal($userId, '获得新气泡', '你获得了「' . (string)$bubble['name'] . '」聊天气泡。', 1, 'system', '/index.php?path=decoration&tab=bubbles', 'bubble', $bubbleId);
            $this->db->prepare('UPDATE plugin_user_chat_bubbles SET notice_sent=1 WHERE user_id=:uid AND bubble_id=:bid')->execute([':uid'=>$userId, ':bid'=>$bubbleId]);
        } catch (\Throwable $e) {}
    }

    private function log(int $userId, int $bubbleId, string $action, string $source, int $operatorId, string $note): void
    {
        try {
            $this->db->prepare('INSERT INTO plugin_chat_bubble_logs (user_id,bubble_id,action,source,operator_id,note,ip,created_at) VALUES (:uid,:bid,:action,:source,:operator,:note,:ip,NOW())')
                ->execute([':uid'=>$userId, ':bid'=>$bubbleId, ':action'=>$action, ':source'=>$source, ':operator'=>$operatorId ?: null, ':note'=>mb_substr($note,0,255), ':ip'=>$_SERVER['REMOTE_ADDR'] ?? null]);
        } catch (\Throwable $e) {}
    }
}

BubbleService::bootHooks();
