<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Hook;
use App\Models\NameplateModel;
use App\Models\SystemMessageModel;
use App\Models\WalletModel;
use PDO;



class NameplateService
{
    private PDO $db;
    private NameplateModel $model;
    private static bool $hooksBooted = false;
    
    private static bool $assetsInjected = false;

    public function __construct()
    {
        $this->db = Database::connection();
        $this->model = new NameplateModel();
    }

    public static function bootHooks(): void
    {
        if (self::$hooksBooted) return;
        self::$hooksBooted = true;

        Hook::listen('app.booted', function (array $payload): array {
            try {
                $svc = new self();
                $svc->ensureSchema();
                $svc->seedDefaults();
            } catch (\Throwable $e) {}
            return $payload;
        }, 2);

        
        Hook::listen('user.nameplate', function ($payload) {
            
            if (!is_array($payload)) return $payload;
            $ctx = $payload['context'] ?? [];
            $user = $ctx['user'] ?? [];
            if (!is_array($user)) return $payload;
            $uid = (int)($user['author_id'] ?? $user['user_id'] ?? $user['follower_id'] ?? $user['following_id'] ?? $user['id'] ?? 0);
            if ($uid <= 0) return $payload;
            try {
                $plate = (new self())->equipped($uid);
            } catch (\Throwable $e) {
                $plate = null;
            }
            if (!$plate) return $payload;
            $payload['value'] = self::wrap((string)$payload['value'], $plate);
            return $payload;
        }, 10);
    }

    
    public static function wrap(string $safeName, array $plate): string
    {
        $style = preg_replace('/[^a-z0-9_\-]/i', '', (string)($plate['style_key'] ?? 'aurora')) ?: 'aurora';
        $frame = self::color($plate['frame_color'] ?? '#38bdf8', '#38bdf8');
        $accent = self::color($plate['accent_color'] ?? '#a78bfa', '#a78bfa');
        $text = self::color($plate['text_color'] ?? '#0f172a', '#0f172a');
        $vars = '--np-frame:' . $frame . ';--np-accent:' . $accent . ';--np-text:' . $text . ';';
        $custom = trim((string)($plate['custom_css'] ?? ''));
        $styleTag = '';
        if ($custom !== '') {
            $decoded = @json_decode($custom, true);
            if (is_array($decoded) && !empty($decoded['css'])) $custom = (string)$decoded['css'];
            if (strpos($custom, '{') !== false && strpos($custom, '}') !== false) {
                
                $safeCss = str_replace(['</style', '<script', '</script'], ['<\/style', '<scr_ipt', '<\/scr_ipt'], $custom);
                $styleTag = '<style data-nameplate-style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '">' . $safeCss . '</style>';
            } else {
                
                $custom = str_replace(['}', '{', '<', '>'], '', $custom);
                $vars .= $custom;
                if (substr($vars, -1) !== ';') $vars .= ';';
            }
        }
        return $styleTag . '<span class="np-fx np-fx--' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-np-style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '"'
            . ' style="' . htmlspecialchars($vars, ENT_QUOTES, 'UTF-8') . '">'
            . '<span class="np-fx-text">' . $safeName . '</span></span>';
    }

    public function ensureSchema(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS `plugin_nameplates` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(80) NOT NULL,
            `description` VARCHAR(255) DEFAULT NULL,
            `style_key` VARCHAR(40) NOT NULL DEFAULT 'aurora',
            `frame_color` VARCHAR(20) NOT NULL DEFAULT '#38bdf8',
            `accent_color` VARCHAR(20) NOT NULL DEFAULT '#a78bfa',
            `text_color` VARCHAR(20) NOT NULL DEFAULT '#0f172a',
            `custom_css` TEXT DEFAULT NULL,
            `price_currency` VARCHAR(20) DEFAULT NULL,
            `price_amount` DECIMAL(18,6) NOT NULL DEFAULT 0,
            `obtain_method` VARCHAR(30) NOT NULL DEFAULT 'shop',
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `sort_order` INT NOT NULL DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_plugin_nameplates_obtain` (`obtain_method`),
            KEY `idx_plugin_nameplates_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->exec("CREATE TABLE IF NOT EXISTS `plugin_user_nameplates` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `nameplate_id` BIGINT UNSIGNED NOT NULL,
            `source` VARCHAR(30) NOT NULL DEFAULT 'grant',
            `source_note` VARCHAR(255) DEFAULT NULL,
            `is_equipped` TINYINT(1) NOT NULL DEFAULT 0,
            `granted_by` BIGINT UNSIGNED DEFAULT NULL,
            `obtained_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_plugin_user_nameplate` (`user_id`, `nameplate_id`),
            KEY `idx_plugin_user_nameplates_user` (`user_id`),
            KEY `idx_plugin_user_nameplates_plate` (`nameplate_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->exec("CREATE TABLE IF NOT EXISTS `plugin_nameplate_logs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `nameplate_id` BIGINT UNSIGNED NOT NULL,
            `action` VARCHAR(30) NOT NULL,
            `currency_code` VARCHAR(20) DEFAULT NULL,
            `amount` DECIMAL(18,6) DEFAULT NULL,
            `operator_id` BIGINT UNSIGNED DEFAULT NULL,
            `remark` VARCHAR(255) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_plugin_nameplate_logs_user` (`user_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function seedDefaults(): void
    {
        $defaults = [
            [
                'name' => '翰墨书法',
                'description' => '论坛专属字体名牌：楷体书法字形，墨色流光，适合版主、作者与荣誉用户。',
                'style_key' => 'calligraphy',
                'frame_color' => '#111827',
                'accent_color' => '#64748b',
                'text_color' => '#0f172a',
                'obtain_method' => 'grant',
                'sort_order' => 10,
            ],
            [
                'name' => '灵动手写',
                'description' => '论坛专属字体名牌：手写/行楷字体，轻微浮动与柔色描边，适合活跃创作者。',
                'style_key' => 'handwrite',
                'frame_color' => '#ec4899',
                'accent_color' => '#f9a8d4',
                'text_color' => '#be123c',
                'obtain_method' => 'grant',
                'sort_order' => 20,
            ],
            [
                'name' => '像素霓虹',
                'description' => '论坛专属字体名牌：等宽像素感字体，赛博错位霓虹闪烁，适合技术/极客身份。',
                'style_key' => 'pixel',
                'frame_color' => '#22d3ee',
                'accent_color' => '#a855f7',
                'text_color' => '#06b6d4',
                'obtain_method' => 'grant',
                'sort_order' => 30,
            ],
            [
                'name' => '典雅衬线',
                'description' => '论坛专属字体名牌：衬线字体、宽字距与金线掠光，适合认证用户与精品作者。',
                'style_key' => 'elegant-serif',
                'frame_color' => '#f59e0b',
                'accent_color' => '#fde68a',
                'text_color' => '#78350f',
                'obtain_method' => 'grant',
                'sort_order' => 40,
            ],
        ];
        foreach ($defaults as $row) {
            $stmt = $this->db->prepare('SELECT id FROM plugin_nameplates WHERE style_key=:style_key AND name=:name LIMIT 1');
            $stmt->execute([':style_key' => $row['style_key'], ':name' => $row['name']]);
            $id = (int)$stmt->fetchColumn();
            $payload = [
                ':name' => $row['name'],
                ':description' => $row['description'],
                ':style_key' => $row['style_key'],
                ':frame_color' => $row['frame_color'],
                ':accent_color' => $row['accent_color'],
                ':text_color' => $row['text_color'],
                ':custom_css' => null,
                ':price_currency' => null,
                ':price_amount' => '0.000000',
                ':obtain_method' => $row['obtain_method'],
                ':status' => 'active',
                ':sort_order' => $row['sort_order'],
            ];
            if ($id > 0) {
                $payload[':id'] = $id;
                $this->db->prepare('UPDATE plugin_nameplates SET name=:name,description=:description,style_key=:style_key,frame_color=:frame_color,accent_color=:accent_color,text_color=:text_color,custom_css=:custom_css,price_currency=:price_currency,price_amount=:price_amount,obtain_method=:obtain_method,status=:status,sort_order=:sort_order,updated_at=NOW() WHERE id=:id')
                    ->execute($payload);
            } else {
                $this->db->prepare('INSERT INTO plugin_nameplates (name,description,style_key,frame_color,accent_color,text_color,custom_css,price_currency,price_amount,obtain_method,status,sort_order,created_at,updated_at) VALUES (:name,:description,:style_key,:frame_color,:accent_color,:text_color,:custom_css,:price_currency,:price_amount,:obtain_method,:status,:sort_order,NOW(),NOW())')
                    ->execute($payload);
            }
        }
    }

    public function equipped(int $userId): ?array
    {
        return $this->model->equippedForUser($userId);
    }

    
    public function grant(int $userId, int $nameplateId, string $source = 'grant', string $note = '', int $operatorId = 0, bool $notify = true): bool
    {
        if ($userId <= 0 || $nameplateId <= 0) throw new \RuntimeException('请选择用户和名字特效');
        $plate = $this->model->find($nameplateId);
        if (!$plate) throw new \RuntimeException('名字特效不存在');
        if ($this->model->owns($userId, $nameplateId)) {
            $this->log($userId, $nameplateId, 'refresh', null, null, $operatorId, $note);
            return false;
        }
        $this->db->prepare('INSERT INTO plugin_user_nameplates (user_id,nameplate_id,source,source_note,is_equipped,granted_by,obtained_at) VALUES (:uid,:nid,:source,:note,0,:operator,NOW())')
            ->execute([':uid' => $userId, ':nid' => $nameplateId, ':source' => $source, ':note' => mb_substr($note, 0, 255) ?: null, ':operator' => $operatorId ?: null]);
        $this->log($userId, $nameplateId, 'grant', null, null, $operatorId, $note);
        if ($notify) $this->notifyGrant($userId, $plate);
        return true;
    }

    public function revokeGrant(int $grantId, int $operatorId = 0): void
    {
        $stmt = $this->db->prepare('SELECT * FROM plugin_user_nameplates WHERE id=:id LIMIT 1');
        $stmt->execute([':id' => $grantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return;
        $this->db->prepare('DELETE FROM plugin_user_nameplates WHERE id=:id')->execute([':id' => $grantId]);
        $this->log((int)$row['user_id'], (int)$row['nameplate_id'], 'revoke', null, null, $operatorId, (string)($row['source_note'] ?? ''));
    }

    
    public function claimFree(int $userId, int $nameplateId): void
    {
        if ($userId <= 0) throw new \RuntimeException('请先登录');
        $plate = $this->model->find($nameplateId);
        if (!$plate || (string)$plate['status'] !== 'active') throw new \RuntimeException('名字特效不存在或已下架');
        if ((string)$plate['obtain_method'] !== 'free') throw new \RuntimeException('该名字特效不是免费领取');
        if ($this->model->owns($userId, $nameplateId)) throw new \RuntimeException('你已拥有该名字特效');
        $this->grant($userId, $nameplateId, 'free', '免费领取', 0, false);
    }

    
    public function purchase(int $userId, int $nameplateId): void
    {
        if ($userId <= 0) throw new \RuntimeException('请先登录');
        $plate = $this->model->find($nameplateId);
        if (!$plate || (string)$plate['status'] !== 'active') throw new \RuntimeException('名字特效不存在或已下架');
        if ((string)$plate['obtain_method'] !== 'shop') throw new \RuntimeException('该名字特效不支持购买');
        if ($this->model->owns($userId, $nameplateId)) throw new \RuntimeException('你已拥有该名字特效');
        $currency = (string)($plate['price_currency'] ?? '');
        $amount = (float)($plate['price_amount'] ?? 0);
        if ($currency === '' || $amount <= 0) throw new \RuntimeException('该名字特效未正确配置价格');

        $this->db->beginTransaction();
        try {
            
            (new WalletModel())->addTransaction(
                $userId,
                $currency,
                '-' . number_format($amount, 6, '.', ''),
                'nameplate_purchase',
                '购买名字特效「' . (string)$plate['name'] . '」',
                '',
                null,
                null,
                'nameplate',
                $nameplateId
            );
            
            $this->db->prepare('INSERT INTO plugin_user_nameplates (user_id,nameplate_id,source,source_note,is_equipped,granted_by,obtained_at) VALUES (:uid,:nid,:source,:note,0,NULL,NOW())')
                ->execute([':uid' => $userId, ':nid' => $nameplateId, ':source' => 'shop', ':note' => '商城购买']);
            $this->log($userId, $nameplateId, 'purchase', $currency, $amount, 0, '商城购买');
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    
    public function equip(int $userId, int $nameplateId): void
    {
        if ($userId <= 0) throw new \RuntimeException('请先登录');
        if ($nameplateId <= 0) {
            $this->db->prepare('UPDATE plugin_user_nameplates SET is_equipped=0 WHERE user_id=:uid')->execute([':uid' => $userId]);
            return;
        }
        if (!$this->model->owns($userId, $nameplateId)) throw new \RuntimeException('你尚未获得该名字特效');
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE plugin_user_nameplates SET is_equipped=0 WHERE user_id=:uid')->execute([':uid' => $userId]);
            $this->db->prepare('UPDATE plugin_user_nameplates SET is_equipped=1 WHERE user_id=:uid AND nameplate_id=:nid')->execute([':uid' => $userId, ':nid' => $nameplateId]);
            $this->db->commit();
            $this->log($userId, $nameplateId, 'equip', null, null, $userId, '');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    
    public function checkAuto(int $userId, string $reason = ''): int
    {
        if ($userId <= 0) return 0;
        $count = 0;
        foreach ($this->model->all(['status' => 'active']) as $plate) {
            $obtain = (string)($plate['obtain_method'] ?? 'grant');
            if (!in_array($obtain, ['task', 'level'], true)) continue;
            if ($this->model->owns($userId, (int)$plate['id'])) continue;
            $progress = $this->model->progressFor($userId, $plate);
            if (!empty($progress['done'])) {
                if ($this->grant($userId, (int)$plate['id'], $obtain, $reason ?: ('自动解锁：' . (string)$progress['label']), 0, true)) $count++;
            }
        }
        return $count;
    }

    private function notifyGrant(int $userId, array $plate): void
    {
        try {
            $id = (int)$plate['id'];
            (new SystemMessageModel())->createPersonal($userId, '获得新名字特效', '你获得了名字特效「' . (string)$plate['name'] . '」，前往装饰中心装备试试吧。', 1, 'system', '/index.php?path=decoration&tab=nameplates', 'nameplate', $id);
        } catch (\Throwable $e) {}
    }

    private function log(int $userId, int $nameplateId, string $action, ?string $currency, ?float $amount, int $operatorId, string $remark): void
    {
        try {
            $this->db->prepare('INSERT INTO plugin_nameplate_logs (user_id,nameplate_id,action,currency_code,amount,operator_id,remark,created_at) VALUES (:uid,:nid,:action,:cur,:amt,:operator,:remark,NOW())')
                ->execute([':uid' => $userId, ':nid' => $nameplateId, ':action' => $action, ':cur' => $currency, ':amt' => $amount !== null ? number_format($amount, 6, '.', '') : null, ':operator' => $operatorId ?: null, ':remark' => mb_substr($remark, 0, 255) ?: null]);
        } catch (\Throwable $e) {}
    }

    private static function color($value, string $fallback): string
    {
        $c = trim((string)$value);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : $fallback;
    }
}

NameplateService::bootHooks();
