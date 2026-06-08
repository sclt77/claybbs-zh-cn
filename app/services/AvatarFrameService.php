<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Hook;
use App\Models\AvatarFrameModel;
use App\Models\SystemMessageModel;
use PDO;

class AvatarFrameService
{
    private PDO $db;
    private AvatarFrameModel $model;
    private static bool $hooksBooted = false;

    public function __construct()
    {
        $this->db = Database::connection();
        $this->model = new AvatarFrameModel();
    }

    public static function bootHooks(): void
    {
        if (self::$hooksBooted) return;
        self::$hooksBooted = true;
        Hook::listen('app.booted', function (array $payload): array {
            $svc = new self();
            $svc->ensureSchema();
            $svc->seedDefaults();
            return $payload;
        }, 2);
        Hook::listen('user.avatar_frame', function (array $payload): array {
            $ctx = $payload['context'] ?? [];
            $uid = (int)($ctx['user_id'] ?? 0);
            if ($uid <= 0) return $payload;
            $payload['value'] = (new self())->equippedFrame($uid);
            return $payload;
        }, 10);
    }

    public function ensureSchema(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS `plugin_avatar_frames` (
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
            `obtain_method` VARCHAR(30) NOT NULL DEFAULT 'grant',
            `price_currency` VARCHAR(20) DEFAULT NULL,
            `price_amount` DECIMAL(18,6) NOT NULL DEFAULT 0,
            `sort_order` INT NOT NULL DEFAULT 0,
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_plugin_avatar_frames_code` (`code`),
            KEY `idx_plugin_avatar_frames_status_sort` (`status`, `sort_order`),
            KEY `idx_plugin_avatar_frames_grant` (`grant_type`, `rule_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->exec("CREATE TABLE IF NOT EXISTS `plugin_user_avatar_frames` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `frame_id` BIGINT UNSIGNED NOT NULL,
            `note` VARCHAR(255) DEFAULT NULL,
            `grant_source` VARCHAR(30) NOT NULL DEFAULT 'manual',
            `is_equipped` TINYINT(1) NOT NULL DEFAULT 0,
            `notice_sent` TINYINT(1) NOT NULL DEFAULT 0,
            `granted_by` BIGINT UNSIGNED DEFAULT NULL,
            `granted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `expires_at` DATETIME DEFAULT NULL,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_plugin_user_avatar_frame` (`user_id`, `frame_id`),
            KEY `idx_plugin_user_avatar_frames_user` (`user_id`),
            KEY `idx_plugin_user_avatar_frames_frame` (`frame_id`),
            KEY `idx_plugin_user_avatar_frames_equipped` (`user_id`, `is_equipped`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->exec("CREATE TABLE IF NOT EXISTS `plugin_avatar_frame_qualities` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(40) NOT NULL,
            `name` VARCHAR(40) NOT NULL,
            `color` VARCHAR(20) NOT NULL DEFAULT '#64748b',
            `sort_order` INT NOT NULL DEFAULT 0,
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_plugin_avatar_frame_qualities_code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->exec("CREATE TABLE IF NOT EXISTS `plugin_avatar_frame_logs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `frame_id` BIGINT UNSIGNED NOT NULL,
            `action` VARCHAR(30) NOT NULL,
            `source` VARCHAR(30) DEFAULT NULL,
            `operator_id` BIGINT UNSIGNED DEFAULT NULL,
            `note` VARCHAR(255) DEFAULT NULL,
            `ip` VARCHAR(64) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_plugin_avatar_frame_logs_user` (`user_id`, `created_at`),
            KEY `idx_plugin_avatar_frame_logs_frame` (`frame_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->ensureColumns('plugin_avatar_frames', [
            'grant_type' => "ALTER TABLE `plugin_avatar_frames` ADD COLUMN `grant_type` VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER `quality_color`",
            'rule_type' => "ALTER TABLE `plugin_avatar_frames` ADD COLUMN `rule_type` VARCHAR(40) NOT NULL DEFAULT 'manual' AFTER `grant_type`",
            'rule_value' => "ALTER TABLE `plugin_avatar_frames` ADD COLUMN `rule_value` INT NOT NULL DEFAULT 0 AFTER `rule_type`",
            'obtain_method' => "ALTER TABLE `plugin_avatar_frames` ADD COLUMN `obtain_method` VARCHAR(30) NOT NULL DEFAULT 'grant' AFTER `rule_value`",
            'price_currency' => "ALTER TABLE `plugin_avatar_frames` ADD COLUMN `price_currency` VARCHAR(20) DEFAULT NULL AFTER `obtain_method`",
            'price_amount' => "ALTER TABLE `plugin_avatar_frames` ADD COLUMN `price_amount` DECIMAL(18,6) NOT NULL DEFAULT 0 AFTER `price_currency`",
        ]);
        $this->ensureColumns('plugin_user_avatar_frames', [
            'grant_source' => "ALTER TABLE `plugin_user_avatar_frames` ADD COLUMN `grant_source` VARCHAR(30) NOT NULL DEFAULT 'manual' AFTER `note`",
            'notice_sent' => "ALTER TABLE `plugin_user_avatar_frames` ADD COLUMN `notice_sent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_equipped`",
            'expires_at' => "ALTER TABLE `plugin_user_avatar_frames` ADD COLUMN `expires_at` DATETIME DEFAULT NULL AFTER `granted_at`",
        ]);
    }

    private function ensureColumns(string $table, array $columns): void
    {
        $existing = [];
        $rs = $this->db->query("SHOW COLUMNS FROM `{$table}`");
        while ($row = $rs->fetch(PDO::FETCH_ASSOC)) {
            $existing[] = (string)$row['Field'];
        }
        foreach ($columns as $column => $sql) {
            if (!in_array($column, $existing, true)) {
                $this->db->exec($sql);
            }
        }
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
            $this->db->prepare("INSERT INTO plugin_avatar_frame_qualities (code,name,color,sort_order,status,created_at,updated_at) VALUES (:code,:name,:color,:sort,'active',NOW(),NOW()) ON DUPLICATE KEY UPDATE updated_at=updated_at")
                ->execute([':code'=>$code, ':name'=>$name, ':color'=>$color, ':sort'=>$sort]);
        }

        $defaults = [
            [
                'code' => 'dragonfire-crown',
                'name' => '龙焰王冠',
                'description' => '论坛默认头像框：传奇龙焰王冠环，旋转火焰光环与王冠动效。',
                'image' => '/assets/img/avatar-frames/legend-dragonfire.svg',
                'quality' => 'legend',
                'quality_name' => '传奇',
                'quality_color' => '#b91c1c',
                'obtain_method' => 'grant',
                'sort_order' => 10,
            ],
            [
                'code' => 'star-orbit',
                'name' => '星轨幻环',
                'description' => '论坛默认头像框：史诗星轨幻环，双层星轨与闪烁星点。',
                'image' => '/assets/img/avatar-frames/epic-starorbit.svg',
                'quality' => 'epic',
                'quality_name' => '史诗',
                'quality_color' => '#7c3aed',
                'obtain_method' => 'grant',
                'sort_order' => 20,
            ],
            [
                'code' => 'aqua-halo',
                'name' => '碧波光环',
                'description' => '论坛默认头像框：稀有碧波光环，青蓝能量环与流动光点。',
                'image' => '/assets/img/avatar-frames/rare-aquahalo.svg',
                'quality' => 'rare',
                'quality_name' => '稀有',
                'quality_color' => '#2563eb',
                'obtain_method' => 'grant',
                'sort_order' => 30,
            ],
            [
                'code' => 'sprout-ring',
                'name' => '青藤新芽',
                'description' => '论坛默认头像框：标准青藤新芽，清新绿色叶片环绕。',
                'image' => '/assets/img/avatar-frames/standard-sprout.svg',
                'quality' => 'standard',
                'quality_name' => '标准',
                'quality_color' => '#64748b',
                'obtain_method' => 'grant',
                'sort_order' => 40,
            ],
        ];
        foreach ($defaults as $row) {
            $stmt = $this->db->prepare('SELECT id FROM plugin_avatar_frames WHERE code=:code LIMIT 1');
            $stmt->execute([':code' => $row['code']]);
            $id = (int)$stmt->fetchColumn();
            $payload = [
                ':code' => $row['code'], ':name' => $row['name'], ':description' => $row['description'], ':image' => $row['image'],
                ':quality' => $row['quality'], ':quality_name' => $row['quality_name'], ':quality_color' => $row['quality_color'],
                ':obtain_method' => $row['obtain_method'], ':price_currency' => null, ':price_amount' => '0.000000',
                ':grant_type' => 'manual', ':rule_type' => 'manual', ':rule_value' => 0,
                ':sort_order' => $row['sort_order'], ':status' => 'active',
            ];
            if ($id > 0) {
                $payload[':id'] = $id;
                $this->db->prepare('UPDATE plugin_avatar_frames SET code=:code,name=:name,description=:description,image=:image,quality=:quality,quality_name=:quality_name,quality_color=:quality_color,obtain_method=:obtain_method,price_currency=:price_currency,price_amount=:price_amount,grant_type=:grant_type,rule_type=:rule_type,rule_value=:rule_value,sort_order=:sort_order,status=:status,updated_at=NOW() WHERE id=:id')
                    ->execute($payload);
            } else {
                $this->db->prepare('INSERT INTO plugin_avatar_frames (code,name,description,image,quality,quality_name,quality_color,obtain_method,price_currency,price_amount,grant_type,rule_type,rule_value,sort_order,status,created_at,updated_at) VALUES (:code,:name,:description,:image,:quality,:quality_name,:quality_color,:obtain_method,:price_currency,:price_amount,:grant_type,:rule_type,:rule_value,:sort_order,:status,NOW(),NOW())')
                    ->execute($payload);
            }
        }
    }

    public function grant(int $userId, int $frameId, string $note = '', int $operatorId = 0, string $source = 'manual', ?string $expiresAt = null, bool $notify = true): bool
    {
        if ($userId <= 0 || $frameId <= 0) throw new \RuntimeException('请选择用户和头像框');
        $frame = $this->model->find($frameId);
        if (!$frame) throw new \RuntimeException('头像框不存在');
        $stmt = $this->db->prepare('SELECT id FROM plugin_user_avatar_frames WHERE user_id=:uid AND frame_id=:fid LIMIT 1');
        $stmt->execute([':uid'=>$userId, ':fid'=>$frameId]);
        $existingId = (int)$stmt->fetchColumn();
        if ($existingId > 0) {
            $this->db->prepare('UPDATE plugin_user_avatar_frames SET note=:note, grant_source=:source, granted_by=:operator, expires_at=:expires, granted_at=NOW(), updated_at=NOW() WHERE id=:id')
                ->execute([':note'=>$note, ':source'=>$source, ':operator'=>$operatorId ?: null, ':expires'=>$expiresAt ?: null, ':id'=>$existingId]);
            $this->log($userId, $frameId, 'refresh', $source, $operatorId, $note);
            return false;
        }
        $equipped = $this->model->equippedForUser($userId) ? 0 : 1;
        $this->db->prepare('INSERT INTO plugin_user_avatar_frames (user_id,frame_id,note,grant_source,is_equipped,notice_sent,granted_by,granted_at,expires_at,updated_at) VALUES (:uid,:fid,:note,:source,:equipped,0,:operator,NOW(),:expires,NOW())')
            ->execute([':uid'=>$userId, ':fid'=>$frameId, ':note'=>$note, ':source'=>$source, ':equipped'=>$equipped, ':operator'=>$operatorId ?: null, ':expires'=>$expiresAt ?: null]);
        $this->log($userId, $frameId, 'grant', $source, $operatorId, $note);
        if ($notify) $this->notifyGrant($userId, $frame);
        return true;
    }

    public function revokeGrant(int $grantId, int $operatorId = 0): void
    {
        $stmt = $this->db->prepare('SELECT * FROM plugin_user_avatar_frames WHERE id=:id LIMIT 1');
        $stmt->execute([':id'=>$grantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return;
        $this->db->prepare('DELETE FROM plugin_user_avatar_frames WHERE id=:id')->execute([':id'=>$grantId]);
        $this->log((int)$row['user_id'], (int)$row['frame_id'], 'revoke', 'manual', $operatorId, (string)($row['note'] ?? ''));
    }

    public function equip(int $userId, int $frameId): void
    {
        if ($userId <= 0) throw new \RuntimeException('请先登录');
        if ($frameId <= 0) {
            $this->db->prepare('UPDATE plugin_user_avatar_frames SET is_equipped=0,updated_at=NOW() WHERE user_id=:uid')->execute([':uid'=>$userId]);
            return;
        }
        $stmt = $this->db->prepare('SELECT id FROM plugin_user_avatar_frames WHERE user_id=:uid AND frame_id=:fid AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1');
        $stmt->execute([':uid'=>$userId, ':fid'=>$frameId]);
        $grantId = (int)$stmt->fetchColumn();
        if ($grantId <= 0) throw new \RuntimeException('你尚未获得该头像框');
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE plugin_user_avatar_frames SET is_equipped=0,updated_at=NOW() WHERE user_id=:uid')->execute([':uid'=>$userId]);
            $this->db->prepare('UPDATE plugin_user_avatar_frames SET is_equipped=1,updated_at=NOW() WHERE id=:id')->execute([':id'=>$grantId]);
            $this->db->commit();
            $this->log($userId, $frameId, 'equip', 'user', $userId, '');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function checkAuto(int $userId, string $reason = ''): int
    {
        if ($userId <= 0) return 0;
        $count = 0;
        foreach ($this->model->all(['status'=>'active']) as $frame) {
            $obtain = (string)($frame['obtain_method'] ?? 'grant');
            if (!in_array($obtain, ['task','level'], true)) continue;
            $progress = $this->frameProgress($userId, $frame);
            if (!empty($progress['done'])) {
                if ($this->grant($userId, (int)$frame['id'], $reason ?: ('自动解锁：' . (string)$progress['label']), 0, $obtain, null, true)) $count++;
            }
        }
        return $count;
    }

    
    public function claimFree(int $userId, int $frameId): void
    {
        if ($userId <= 0) throw new \RuntimeException('请先登录');
        $frame = $this->model->find($frameId);
        if (!$frame || (string)$frame['status'] !== 'active') throw new \RuntimeException('头像框不存在或已下架');
        if ((string)($frame['obtain_method'] ?? '') !== 'free') throw new \RuntimeException('该头像框不是免费领取');
        $stmt = $this->db->prepare('SELECT id FROM plugin_user_avatar_frames WHERE user_id=:uid AND frame_id=:fid LIMIT 1');
        $stmt->execute([':uid'=>$userId, ':fid'=>$frameId]);
        if ((int)$stmt->fetchColumn() > 0) throw new \RuntimeException('你已拥有该头像框');
        $this->grant($userId, $frameId, '免费领取', 0, 'free', null, false);
    }

    
    public function purchase(int $userId, int $frameId): void
    {
        if ($userId <= 0) throw new \RuntimeException('请先登录');
        $frame = $this->model->find($frameId);
        if (!$frame || (string)$frame['status'] !== 'active') throw new \RuntimeException('头像框不存在或已下架');
        if ((string)($frame['obtain_method'] ?? '') !== 'shop') throw new \RuntimeException('该头像框不支持购买');
        $stmt = $this->db->prepare('SELECT id FROM plugin_user_avatar_frames WHERE user_id=:uid AND frame_id=:fid LIMIT 1');
        $stmt->execute([':uid'=>$userId, ':fid'=>$frameId]);
        if ((int)$stmt->fetchColumn() > 0) throw new \RuntimeException('你已拥有该头像框');
        $currency = (string)($frame['price_currency'] ?? '');
        $amount = (float)($frame['price_amount'] ?? 0);
        if ($currency === '' || $amount <= 0) throw new \RuntimeException('该头像框未正确配置价格');
        $this->db->beginTransaction();
        try {
            (new \App\Models\WalletModel())->addTransaction($userId, $currency, '-' . number_format($amount, 6, '.', ''), 'avatar_frame_purchase', '购买头像框「' . (string)$frame['name'] . '」', '', null, null, 'avatar_frame', $frameId);
            $equipped = $this->model->equippedForUser($userId) ? 0 : 1;
            $this->db->prepare('INSERT INTO plugin_user_avatar_frames (user_id,frame_id,note,grant_source,is_equipped,notice_sent,granted_by,granted_at,expires_at,updated_at) VALUES (:uid,:fid,:note,:source,:equipped,0,NULL,NOW(),NULL,NOW())')
                ->execute([':uid'=>$userId, ':fid'=>$frameId, ':note'=>'商城购买', ':source'=>'shop', ':equipped'=>$equipped]);
            $this->log($userId, $frameId, 'purchase', 'shop', 0, '商城购买');
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    
    public function frameProgress(int $userId, array $frame): array
    {
        $obtain = (string)($frame['obtain_method'] ?? 'grant');
        $threshold = (int)($frame['price_amount'] ?? 0);
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

    public function equippedFrame(int $userId): ?array
    {
        return $this->model->equippedForUser($userId);
    }

    private function notifyGrant(int $userId, array $frame): void
    {
        try {
            $frameId = (int)$frame['id'];
            (new SystemMessageModel())->createPersonal($userId, '获得新头像框', '你获得了头像框「' . (string)$frame['name'] . '」。', 1, 'system', '/index.php?path=avatar-frames', 'avatar_frame', $frameId);
            $this->db->prepare('UPDATE plugin_user_avatar_frames SET notice_sent=1 WHERE user_id=:uid AND frame_id=:fid')->execute([':uid'=>$userId, ':fid'=>$frameId]);
        } catch (\Throwable $e) {}
    }

    private function log(int $userId, int $frameId, string $action, string $source, int $operatorId, string $note): void
    {
        try {
            $this->db->prepare('INSERT INTO plugin_avatar_frame_logs (user_id,frame_id,action,source,operator_id,note,ip,created_at) VALUES (:uid,:fid,:action,:source,:operator,:note,:ip,NOW())')
                ->execute([':uid'=>$userId, ':fid'=>$frameId, ':action'=>$action, ':source'=>$source, ':operator'=>$operatorId ?: null, ':note'=>mb_substr($note,0,255), ':ip'=>$_SERVER['REMOTE_ADDR'] ?? null]);
        } catch (\Throwable $e) {}
    }
}

AvatarFrameService::bootHooks();
