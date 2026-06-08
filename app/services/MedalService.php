<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Hook;
use App\Models\MedalModel;
use App\Models\SystemMessageModel;
use PDO;

class MedalService
{
    private PDO $db;
    private MedalModel $model;
    private static bool $hooksBooted = false;

    public function __construct()
    {
        $this->db = Database::connection();
        $this->model = new MedalModel();
    }

    public static function bootHooks(): void
    {
        if (self::$hooksBooted) return;
        self::$hooksBooted = true;
        Hook::listen('app.booted', function (array $payload): array {
            (new self())->ensureSchema();
            (new self())->seedDefaults();
            return $payload;
        }, 1);

        Hook::listen('user.badges', function (array $payload): array {
            $ctx = $payload['context'] ?? [];
            $uid = (int)($ctx['user_id'] ?? 0);
            if ($uid <= 0) return $payload;
            $class = preg_replace('/[^A-Za-z0-9_\-\s]/', '', (string)($ctx['class'] ?? 'clay-user-badges')) ?: 'clay-user-badges';
            $limit = max(1, min(12, (int)($ctx['limit'] ?? 6)));
            $payload['value'] = (new self())->renderUserBadges($uid, $class, $limit);
            return $payload;
        }, 10);
    }

    public function ensureSchema(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS `plugin_badge_qualities` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(40) NOT NULL,
            `name` VARCHAR(40) NOT NULL,
            `color` VARCHAR(20) NOT NULL DEFAULT '#64748b',
            `sort_order` INT NOT NULL DEFAULT 0,
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_plugin_badge_qualities_code` (`code`),
            KEY `idx_plugin_badge_qualities_status_sort` (`status`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->exec("CREATE TABLE IF NOT EXISTS `plugin_badges` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(80) DEFAULT NULL,
            `name` VARCHAR(60) NOT NULL,
            `description` VARCHAR(255) DEFAULT NULL,
            `icon` VARCHAR(255) DEFAULT NULL,
            `color` VARCHAR(20) NOT NULL DEFAULT '#f59e0b',
            `category` VARCHAR(40) NOT NULL DEFAULT 'manual',
            `level` VARCHAR(20) NOT NULL DEFAULT 'standard',
            `grant_type` VARCHAR(20) NOT NULL DEFAULT 'manual',
            `rule_type` VARCHAR(40) NOT NULL DEFAULT 'manual',
            `rule_value` INT NOT NULL DEFAULT 0,
            `max_equipped` TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `sort_order` INT NOT NULL DEFAULT 0,
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_plugin_badges_code` (`code`),
            KEY `idx_plugin_badges_status_sort` (`status`, `sort_order`),
            KEY `idx_plugin_badges_category` (`category`),
            KEY `idx_plugin_badges_grant` (`grant_type`, `rule_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->addColumn('plugin_badges', 'code', "ALTER TABLE plugin_badges ADD COLUMN code VARCHAR(80) DEFAULT NULL AFTER id");
        $this->addColumn('plugin_badges', 'category', "ALTER TABLE plugin_badges ADD COLUMN category VARCHAR(40) NOT NULL DEFAULT 'manual' AFTER color");
        $this->addColumn('plugin_badges', 'level', "ALTER TABLE plugin_badges ADD COLUMN level VARCHAR(20) NOT NULL DEFAULT 'standard' AFTER category");
        $this->addColumn('plugin_badges', 'grant_type', "ALTER TABLE plugin_badges ADD COLUMN grant_type VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER level");
        $this->addColumn('plugin_badges', 'rule_type', "ALTER TABLE plugin_badges ADD COLUMN rule_type VARCHAR(40) NOT NULL DEFAULT 'manual' AFTER grant_type");
        $this->addColumn('plugin_badges', 'rule_value', "ALTER TABLE plugin_badges ADD COLUMN rule_value INT NOT NULL DEFAULT 0 AFTER rule_type");
        $this->addColumn('plugin_badges', 'max_equipped', "ALTER TABLE plugin_badges ADD COLUMN max_equipped TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER rule_value");
        $this->addColumn('plugin_badges', 'obtain_method', "ALTER TABLE plugin_badges ADD COLUMN obtain_method VARCHAR(30) NOT NULL DEFAULT 'grant' AFTER max_equipped");
        $this->addColumn('plugin_badges', 'price_currency', "ALTER TABLE plugin_badges ADD COLUMN price_currency VARCHAR(20) DEFAULT NULL AFTER obtain_method");
        $this->addColumn('plugin_badges', 'price_amount', "ALTER TABLE plugin_badges ADD COLUMN price_amount DECIMAL(18,6) NOT NULL DEFAULT 0 AFTER price_currency");
        try { $this->db->exec("ALTER TABLE plugin_badges MODIFY COLUMN icon VARCHAR(255) DEFAULT NULL"); } catch (\Throwable $e) {}
        try { $this->db->exec("UPDATE plugin_badges SET code=CONCAT('legacy-',id) WHERE code IS NULL OR code=''"); } catch (\Throwable $e) {}
        $this->addIndex('plugin_badges', 'uniq_plugin_badges_code', "ALTER TABLE plugin_badges ADD UNIQUE KEY uniq_plugin_badges_code (code)");
        $this->addIndex('plugin_badges', 'idx_plugin_badges_category', "ALTER TABLE plugin_badges ADD KEY idx_plugin_badges_category (category)");
        $this->addIndex('plugin_badges', 'idx_plugin_badges_grant', "ALTER TABLE plugin_badges ADD KEY idx_plugin_badges_grant (grant_type, rule_type)");

        $this->db->exec("CREATE TABLE IF NOT EXISTS `plugin_user_badges` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `badge_id` BIGINT UNSIGNED NOT NULL,
            `note` VARCHAR(255) DEFAULT NULL,
            `grant_source` VARCHAR(30) NOT NULL DEFAULT 'manual',
            `is_equipped` TINYINT(1) NOT NULL DEFAULT 1,
            `equip_slot` TINYINT UNSIGNED DEFAULT NULL,
            `notice_sent` TINYINT(1) NOT NULL DEFAULT 0,
            `granted_by` BIGINT UNSIGNED DEFAULT NULL,
            `granted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_plugin_user_badge` (`user_id`, `badge_id`),
            KEY `idx_plugin_user_badges_user` (`user_id`),
            KEY `idx_plugin_user_badges_badge` (`badge_id`),
            KEY `idx_plugin_user_badges_equipped` (`user_id`, `is_equipped`, `equip_slot`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->addColumn('plugin_user_badges', 'grant_source', "ALTER TABLE plugin_user_badges ADD COLUMN grant_source VARCHAR(30) NOT NULL DEFAULT 'manual' AFTER note");
        $this->addColumn('plugin_user_badges', 'is_equipped', "ALTER TABLE plugin_user_badges ADD COLUMN is_equipped TINYINT(1) NOT NULL DEFAULT 1 AFTER grant_source");
        $this->addColumn('plugin_user_badges', 'equip_slot', "ALTER TABLE plugin_user_badges ADD COLUMN equip_slot TINYINT UNSIGNED DEFAULT NULL AFTER is_equipped");
        $this->addColumn('plugin_user_badges', 'notice_sent', "ALTER TABLE plugin_user_badges ADD COLUMN notice_sent TINYINT(1) NOT NULL DEFAULT 0 AFTER equip_slot");
        $this->addColumn('plugin_user_badges', 'updated_at', "ALTER TABLE plugin_user_badges ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER granted_at");
        $this->addIndex('plugin_user_badges', 'idx_plugin_user_badges_equipped', "ALTER TABLE plugin_user_badges ADD KEY idx_plugin_user_badges_equipped (user_id, is_equipped, equip_slot)");

        $this->db->exec("CREATE TABLE IF NOT EXISTS `plugin_badge_logs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `badge_id` BIGINT UNSIGNED NOT NULL,
            `action` VARCHAR(30) NOT NULL,
            `source` VARCHAR(30) DEFAULT NULL,
            `operator_id` BIGINT UNSIGNED DEFAULT NULL,
            `note` VARCHAR(255) DEFAULT NULL,
            `ip` VARCHAR(64) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_plugin_badge_logs_user` (`user_id`, `created_at`),
            KEY `idx_plugin_badge_logs_badge` (`badge_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function seedDefaults(): void
    {
        $items = [
            ['legend','传奇','#b91c1c',10],
            ['epic','史诗','#7c3aed',20],
            ['rare','稀有','#2563eb',30],
            ['standard','标准','#64748b',40],
        ];
        foreach ($items as $i) {
            [$code,$name,$color,$sort] = $i;
            $this->db->prepare("INSERT INTO plugin_badge_qualities (code,name,color,sort_order,status,created_at,updated_at) VALUES (:code,:name,:color,:sort,'active',NOW(),NOW()) ON DUPLICATE KEY UPDATE updated_at=updated_at")
                ->execute([':code'=>$code,':name'=>$name,':color'=>$color,':sort'=>$sort]);
        }

        
    }

    public function grant(int $userId, int $badgeId, string $note = '', int $operatorId = 0, string $source = 'manual', bool $notify = true): bool
    {
        if ($userId <= 0 || $badgeId <= 0) throw new \RuntimeException('请选择用户和勋章');
        $badge = $this->model->find($badgeId);
        if (!$badge) throw new \RuntimeException('勋章不存在');
        $stmt = $this->db->prepare('SELECT id FROM plugin_user_badges WHERE user_id=:uid AND badge_id=:bid LIMIT 1');
        $stmt->execute([':uid'=>$userId, ':bid'=>$badgeId]);
        $existingId = (int)$stmt->fetchColumn();
        if ($existingId > 0) {
            $this->db->prepare('UPDATE plugin_user_badges SET note=:note, grant_source=:source, granted_by=:operator, granted_at=NOW(), updated_at=NOW() WHERE id=:id')
                ->execute([':note'=>$note, ':source'=>$source, ':operator'=>$operatorId ?: null, ':id'=>$existingId]);
            $this->log($userId, $badgeId, 'refresh', $source, $operatorId, $note);
            return false;
        }
        $this->db->prepare('INSERT INTO plugin_user_badges (user_id,badge_id,note,grant_source,is_equipped,equip_slot,notice_sent,granted_by,granted_at,updated_at) VALUES (:uid,:bid,:note,:source,0,NULL,0,:operator,NOW(),NOW())')
            ->execute([':uid'=>$userId, ':bid'=>$badgeId, ':note'=>$note, ':source'=>$source, ':operator'=>$operatorId ?: null]);
        $this->log($userId, $badgeId, 'grant', $source, $operatorId, $note);
        if ($notify) $this->notifyGrant($userId, $badge);
        return true;
    }

    public function revokeGrant(int $grantId, int $operatorId = 0): void
    {
        $stmt = $this->db->prepare('SELECT * FROM plugin_user_badges WHERE id=:id LIMIT 1');
        $stmt->execute([':id'=>$grantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return;
        $this->db->prepare('DELETE FROM plugin_user_badges WHERE id=:id')->execute([':id'=>$grantId]);
        $this->log((int)$row['user_id'], (int)$row['badge_id'], 'revoke', 'manual', $operatorId, (string)($row['note'] ?? ''));
        $this->normalizeSlots((int)$row['user_id']);
    }

    public function equip(int $userId, int $badgeId, bool $on): void
    {
        $stmt = $this->db->prepare('SELECT * FROM plugin_user_badges WHERE user_id=:uid AND badge_id=:bid LIMIT 1');
        $stmt->execute([':uid'=>$userId, ':bid'=>$badgeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new \RuntimeException('你尚未获得该勋章');
        if ($on) {
            $slot = $this->nextSlot($userId, (int)$badgeId);
            if ($slot > 5) throw new \RuntimeException('最多佩戴 5 枚勋章');
            $this->db->prepare('UPDATE plugin_user_badges SET is_equipped=1,equip_slot=:slot,updated_at=NOW() WHERE id=:id')->execute([':slot'=>$slot, ':id'=>(int)$row['id']]);
            $this->log($userId, $badgeId, 'equip', 'user', $userId, '');
        } else {
            $this->db->prepare('UPDATE plugin_user_badges SET is_equipped=0,equip_slot=NULL,updated_at=NOW() WHERE id=:id')->execute([':id'=>(int)$row['id']]);
            $this->log($userId, $badgeId, 'unequip', 'user', $userId, '');
            $this->normalizeSlots($userId);
        }
    }

    public function saveLayout(int $userId, array $badgeIds): void
    {
        if ($userId <= 0) throw new \RuntimeException('请先登录');
        $layout = [];
        foreach ($badgeIds as $id) {
            $id = (int)$id;
            if ($id <= 0) continue;
            if (in_array($id, $layout, true)) continue;
            $layout[] = $id;
            if (count($layout) >= 5) break;
        }
        if ($layout) {
            $placeholders = implode(',', array_fill(0, count($layout), '?'));
            $stmt = $this->db->prepare("SELECT badge_id FROM plugin_user_badges WHERE user_id=? AND badge_id IN ($placeholders)");
            $stmt->execute(array_merge([$userId], $layout));
            $owned = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            foreach ($layout as $id) {
                if (!in_array($id, $owned, true)) throw new \RuntimeException('只能佩戴已获得的勋章');
            }
        }
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE plugin_user_badges SET is_equipped=0,equip_slot=NULL,updated_at=NOW() WHERE user_id=:uid')->execute([':uid'=>$userId]);
            $slot = 1;
            foreach ($layout as $badgeId) {
                $this->db->prepare('UPDATE plugin_user_badges SET is_equipped=1,equip_slot=:slot,updated_at=NOW() WHERE user_id=:uid AND badge_id=:bid')->execute([':slot'=>$slot, ':uid'=>$userId, ':bid'=>$badgeId]);
                $this->log($userId, $badgeId, 'layout', 'user', $userId, 'slot ' . $slot);
                $slot++;
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function checkAuto(int $userId, string $reason = ''): int
    {
        if ($userId <= 0) return 0;
        $count = 0;
        foreach ($this->model->all(['status'=>'active']) as $badge) {
            $obtain = (string)($badge['obtain_method'] ?? 'grant');
            if (!in_array($obtain, ['task','level'], true)) continue;
            $progress = $this->badgeProgress($userId, $badge);
            if (!empty($progress['done'])) {
                if ($this->grant($userId, (int)$badge['id'], $reason ?: ('自动解锁：' . (string)$progress['label']), 0, $obtain, true)) {
                    $count++;
                }
            }
        }
        return $count;
    }

    
    public function claimFree(int $userId, int $badgeId): void
    {
        if ($userId <= 0) throw new \RuntimeException('请先登录');
        $badge = $this->model->find($badgeId);
        if (!$badge || (string)$badge['status'] !== 'active') throw new \RuntimeException('勋章不存在或已下架');
        if ((string)($badge['obtain_method'] ?? '') !== 'free') throw new \RuntimeException('该勋章不是免费领取');
        $stmt = $this->db->prepare('SELECT id FROM plugin_user_badges WHERE user_id=:uid AND badge_id=:bid LIMIT 1');
        $stmt->execute([':uid'=>$userId, ':bid'=>$badgeId]);
        if ((int)$stmt->fetchColumn() > 0) throw new \RuntimeException('你已拥有该勋章');
        $this->grant($userId, $badgeId, '免费领取', 0, 'free', false);
    }

    
    public function purchase(int $userId, int $badgeId): void
    {
        if ($userId <= 0) throw new \RuntimeException('请先登录');
        $badge = $this->model->find($badgeId);
        if (!$badge || (string)$badge['status'] !== 'active') throw new \RuntimeException('勋章不存在或已下架');
        if ((string)($badge['obtain_method'] ?? '') !== 'shop') throw new \RuntimeException('该勋章不支持购买');
        $stmt = $this->db->prepare('SELECT id FROM plugin_user_badges WHERE user_id=:uid AND badge_id=:bid LIMIT 1');
        $stmt->execute([':uid'=>$userId, ':bid'=>$badgeId]);
        if ((int)$stmt->fetchColumn() > 0) throw new \RuntimeException('你已拥有该勋章');
        $currency = (string)($badge['price_currency'] ?? '');
        $amount = (float)($badge['price_amount'] ?? 0);
        if ($currency === '' || $amount <= 0) throw new \RuntimeException('该勋章未正确配置价格');
        $this->db->beginTransaction();
        try {
            (new \App\Models\WalletModel())->addTransaction($userId, $currency, '-' . number_format($amount, 6, '.', ''), 'badge_purchase', '购买勋章「' . (string)$badge['name'] . '」', '', null, null, 'badge', $badgeId);
            $this->db->prepare('INSERT INTO plugin_user_badges (user_id,badge_id,note,grant_source,is_equipped,equip_slot,notice_sent,granted_by,granted_at,updated_at) VALUES (:uid,:bid,:note,:source,0,NULL,0,NULL,NOW(),NOW())')
                ->execute([':uid'=>$userId, ':bid'=>$badgeId, ':note'=>'商城购买', ':source'=>'shop']);
            $this->log($userId, $badgeId, 'purchase', 'shop', 0, '商城购买');
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    
    public function badgeProgress(int $userId, array $badge): array
    {
        $obtain = (string)($badge['obtain_method'] ?? 'grant');
        $threshold = (int)($badge['price_amount'] ?? 0);
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

    public function renderUserBadges(int $userId, string $class = 'clay-user-badges', int $limit = 6, bool $linked = true): string
    {
        $badges = $this->model->userMedals($userId, true, $limit);
        if (!$badges) return '';
        $html = '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">';
        foreach ($badges as $b) {
            $name = htmlspecialchars((string)$b['name'], ENT_QUOTES, 'UTF-8');
            $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)$b['color']) ? (string)$b['color'] : '#f59e0b';
            $icon = trim((string)($b['icon'] ?? ''));
            $img = $icon !== '' ? '<img src="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy">' : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M8.5 12.5 7 21l5-3 5 3-1.5-8.5"></path></svg>';
            $level = htmlspecialchars((string)$b['level'], ENT_QUOTES, 'UTF-8');
            $style = '--medal-color:' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8');
            $content = $img . '<span class="clay-user-medal-name">' . $name . '</span>';
            if ($linked) {
                $html .= '<a class="clay-user-medal level-' . $level . '" href="/index.php?path=medal&id=' . (int)$b['badge_id'] . '" title="' . $name . '" aria-label="' . $name . '" style="' . $style . '">' . $content . '</a>';
            } else {
                $html .= '<button class="clay-user-medal is-icon-only level-' . $level . '" type="button" title="' . $name . '" aria-label="' . $name . '" data-medal-name="' . $name . '" style="' . $style . '">' . $content . '</button>';
            }
        }
        return $html . '</span>';
    }

    private function notifyGrant(int $userId, array $badge): void
    {
        try {
            $badgeId = (int)$badge['id'];
            (new SystemMessageModel())->createPersonal($userId, '获得新勋章', '你获得了「' . (string)$badge['name'] . '」勋章。', 1, 'system', '/index.php?path=medal&id=' . $badgeId, 'badge', $badgeId);
            $this->db->prepare('UPDATE plugin_user_badges SET notice_sent=1 WHERE user_id=:uid AND badge_id=:bid')->execute([':uid'=>$userId, ':bid'=>$badgeId]);
        } catch (\Throwable $e) {}
    }

    private function nextSlot(int $userId, int $excludeBadgeId = 0): int
    {
        $sql = 'SELECT equip_slot FROM plugin_user_badges WHERE user_id=:uid AND is_equipped=1';
        $params = [':uid'=>$userId];
        if ($excludeBadgeId > 0) { $sql .= ' AND badge_id<>:bid'; $params[':bid'] = $excludeBadgeId; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $used = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        for ($i=1; $i<=5; $i++) if (!in_array($i, $used, true)) return $i;
        return 6;
    }

    private function normalizeSlots(int $userId): void
    {
        $rows = $this->model->userMedals($userId, true, 100);
        $slot = 1;
        foreach ($rows as $row) {
            if ($slot > 5) {
                $this->db->prepare('UPDATE plugin_user_badges SET is_equipped=0,equip_slot=NULL WHERE id=:id')->execute([':id'=>(int)$row['id']]);
            } else {
                $this->db->prepare('UPDATE plugin_user_badges SET equip_slot=:slot WHERE id=:id')->execute([':slot'=>$slot, ':id'=>(int)$row['id']]);
            }
            $slot++;
        }
    }

    private function log(int $userId, int $badgeId, string $action, string $source, int $operatorId, string $note): void
    {
        try {
            $this->db->prepare('INSERT INTO plugin_badge_logs (user_id,badge_id,action,source,operator_id,note,ip,created_at) VALUES (:uid,:bid,:action,:source,:operator,:note,:ip,NOW())')
                ->execute([':uid'=>$userId, ':bid'=>$badgeId, ':action'=>$action, ':source'=>$source, ':operator'=>$operatorId ?: null, ':note'=>mb_substr($note,0,255), ':ip'=>$_SERVER['REMOTE_ADDR'] ?? null]);
        } catch (\Throwable $e) {}
    }

    private function addColumn(string $table, string $column, string $sql): void
    {
        try {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c');
            $stmt->execute([':t'=>$table, ':c'=>$column]);
            if (!(int)$stmt->fetchColumn()) $this->db->exec($sql);
        } catch (\Throwable $e) {}
    }

    private function addIndex(string $table, string $index, string $sql): void
    {
        try {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND INDEX_NAME=:i');
            $stmt->execute([':t'=>$table, ':i'=>$index]);
            if (!(int)$stmt->fetchColumn()) $this->db->exec($sql);
        } catch (\Throwable $e) {}
    }
}

MedalService::bootHooks();
