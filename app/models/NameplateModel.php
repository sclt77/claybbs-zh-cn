<?php

namespace App\Models;

use App\Core\Database;
use PDO;



class NameplateModel
{
    private PDO $db;

    
    public const STYLE_KEYS = [
        'aurora', 'neon', 'rainbow', 'gold', 'fire', 'ice',
        'glitch', 'starlight', 'gradient-flow', 'shadow-pulse',
        'calligraphy', 'handwrite', 'pixel', 'elegant-serif',
    ];

    public const OBTAIN_METHODS = ['free', 'shop', 'task', 'level', 'grant'];

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function all(array $filters = []): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'n.status = :status';
            $params[':status'] = (string)$filters['status'];
        }
        if (!empty($filters['obtain_method'])) {
            $where[] = 'n.obtain_method = :obtain_method';
            $params[':obtain_method'] = (string)$filters['obtain_method'];
        }
        $sql = "SELECT n.*,
                (SELECT COUNT(*) FROM plugin_user_nameplates un WHERE un.nameplate_id=n.id) AS owner_count,
                (SELECT COUNT(*) FROM plugin_user_nameplates un WHERE un.nameplate_id=n.id AND un.is_equipped=1) AS equipped_count
                FROM plugin_nameplates n";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY n.sort_order ASC, n.id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM plugin_nameplates WHERE id=:id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByStyleKey(string $styleKey): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM plugin_nameplates WHERE style_key=:k ORDER BY id ASC LIMIT 1');
        $stmt->execute([':k' => $styleKey]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function save(array $data): int
    {
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') throw new \RuntimeException('请填写名字特效名称');

        $customCss = trim((string)($data['custom_css'] ?? ''));
        $styleKey = preg_replace('/[^a-z0-9_\-]/i', '', strtolower((string)($data['style_key'] ?? 'aurora'))) ?: 'aurora';
        
        if ($customCss !== '') {
            $decoded = @json_decode($customCss, true);
            if (is_array($decoded)) {
                if (!empty($decoded['type'])) $styleKey = preg_replace('/[^a-z0-9_\-]/i', '', strtolower((string)$decoded['type'])) ?: $styleKey;
                if (!empty($decoded['css'])) $customCss = (string)$decoded['css'];
            } elseif (preg_match('/\.np-fx\.([a-zA-Z][a-zA-Z0-9_-]*)/', $customCss, $m)) {
                $styleKey = strtolower($m[1]);
            } elseif (preg_match('/\.np-fx--([a-zA-Z][a-zA-Z0-9_-]*)/', $customCss, $m)) {
                $styleKey = strtolower($m[1]);
            } elseif (preg_match('/\.([a-zA-Z][a-zA-Z0-9_-]*)\s*\{/', $customCss, $m)) {
                $styleKey = strtolower($m[1]);
            }
        }

        $obtain = (string)($data['obtain_method'] ?? 'grant');
        if (!in_array($obtain, self::OBTAIN_METHODS, true)) $obtain = 'grant';

        $frameColor = $this->normColor($data['frame_color'] ?? '#38bdf8', '#38bdf8');
        $accentColor = $this->normColor($data['accent_color'] ?? '#a78bfa', '#a78bfa');
        $textColor = $this->normColor($data['text_color'] ?? '#0f172a', '#0f172a');

        $priceCurrency = trim((string)($data['price_currency'] ?? ''));
        $priceCurrency = $priceCurrency !== '' ? strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', $priceCurrency)) : null;
        $priceAmount = max(0, (float)($data['price_amount'] ?? 0));

        
        
        
        
        if (in_array($obtain, ['task', 'level'], true)) {
            $ruleValue = (int)($data['rule_value'] ?? $priceAmount);
            $priceAmount = (float)$ruleValue; 
            $priceCurrency = null;
        }

        $payload = [
            ':name' => mb_substr($name, 0, 80),
            ':description' => mb_substr(trim((string)($data['description'] ?? '')), 0, 255),
            ':style_key' => $styleKey,
            ':frame_color' => $frameColor,
            ':accent_color' => $accentColor,
            ':text_color' => $textColor,
            ':custom_css' => $customCss !== '' ? $customCss : null,
            ':price_currency' => $priceCurrency,
            ':price_amount' => number_format($priceAmount, 6, '.', ''),
            ':obtain_method' => $obtain,
            ':status' => in_array((string)($data['status'] ?? 'active'), ['active', 'disabled'], true) ? (string)$data['status'] : 'active',
            ':sort_order' => (int)($data['sort_order'] ?? 0),
        ];

        if ($id > 0) {
            $payload[':id'] = $id;
            $stmt = $this->db->prepare('UPDATE plugin_nameplates SET name=:name,description=:description,style_key=:style_key,frame_color=:frame_color,accent_color=:accent_color,text_color=:text_color,custom_css=:custom_css,price_currency=:price_currency,price_amount=:price_amount,obtain_method=:obtain_method,status=:status,sort_order=:sort_order,updated_at=NOW() WHERE id=:id');
            $stmt->execute($payload);
            return $id;
        }
        $stmt = $this->db->prepare('INSERT INTO plugin_nameplates (name,description,style_key,frame_color,accent_color,text_color,custom_css,price_currency,price_amount,obtain_method,status,sort_order,created_at,updated_at) VALUES (:name,:description,:style_key,:frame_color,:accent_color,:text_color,:custom_css,:price_currency,:price_amount,:obtain_method,:status,:sort_order,NOW(),NOW())');
        $stmt->execute($payload);
        return (int)$this->db->lastInsertId();
    }

    public function delete(int $id): void
    {
        if ($id <= 0) return;
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM plugin_user_nameplates WHERE nameplate_id=:id');
        $stmt->execute([':id' => $id]);
        if ((int)$stmt->fetchColumn() > 0) throw new \RuntimeException('已有用户获得该名字特效，不能删除，可改为禁用');
        $this->db->prepare('DELETE FROM plugin_nameplates WHERE id=:id')->execute([':id' => $id]);
    }

    
    public function userNameplates(int $userId, bool $ownedOnly = false): array
    {
        if ($ownedOnly) {
            $stmt = $this->db->prepare("SELECT un.*, n.* FROM plugin_user_nameplates un JOIN plugin_nameplates n ON n.id=un.nameplate_id WHERE un.user_id=:uid ORDER BY un.is_equipped DESC, n.sort_order ASC, n.id DESC");
            $stmt->execute([':uid' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        $stmt = $this->db->prepare("SELECT n.*, un.id AS grant_id, un.is_equipped, un.obtained_at, un.source, CASE WHEN un.id IS NULL THEN 0 ELSE 1 END AS owned FROM plugin_nameplates n LEFT JOIN plugin_user_nameplates un ON un.nameplate_id=n.id AND un.user_id=:uid WHERE n.status='active' ORDER BY n.sort_order ASC, n.id DESC");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function owns(int $userId, int $nameplateId): bool
    {
        if ($userId <= 0 || $nameplateId <= 0) return false;
        $stmt = $this->db->prepare('SELECT 1 FROM plugin_user_nameplates WHERE user_id=:uid AND nameplate_id=:nid LIMIT 1');
        $stmt->execute([':uid' => $userId, ':nid' => $nameplateId]);
        return (bool)$stmt->fetchColumn();
    }

    public function equippedForUser(int $userId): ?array
    {
        if ($userId <= 0) return null;
        $stmt = $this->db->prepare("SELECT n.*, un.id AS grant_id FROM plugin_user_nameplates un JOIN plugin_nameplates n ON n.id=un.nameplate_id WHERE un.user_id=:uid AND un.is_equipped=1 AND n.status='active' LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function grantRows(int $limit = 200): array
    {
        $stmt = $this->db->prepare("SELECT un.*, n.name AS plate_name, n.style_key, n.frame_color, n.accent_color, u.username, u.nickname, u.public_id FROM plugin_user_nameplates un JOIN plugin_nameplates n ON n.id=un.nameplate_id JOIN users u ON u.id=un.user_id ORDER BY un.id DESC LIMIT :limit");
        $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    

    public function progressFor(int $userId, array $plate): array
    {
        $obtain = (string)($plate['obtain_method'] ?? 'grant');
        $target = (int)round((float)($plate['price_amount'] ?? 0));
        $current = 0;
        $label = '由管理员手动发放';
        $done = false;

        if ($obtain === 'free') {
            return ['current' => 1, 'target' => 1, 'label' => '免费领取', 'done' => true, 'percent' => 100];
        }
        if ($obtain === 'shop') {
            $cur = (string)($plate['price_currency'] ?? '');
            $amt = rtrim(rtrim(number_format((float)($plate['price_amount'] ?? 0), 6, '.', ''), '0'), '.');
            return ['current' => 0, 'target' => $target, 'label' => '商城购买：' . $amt . ' ' . $cur, 'done' => false, 'percent' => 0];
        }
        if ($obtain === 'level') {
            try {
                $stmt = $this->db->prepare('SELECT current_level FROM user_growth_stats WHERE user_id=:uid LIMIT 1');
                $stmt->execute([':uid' => $userId]);
                $current = (int)$stmt->fetchColumn();
            } catch (\Throwable $e) {
                $current = 0;
            }
            $label = '等级达到 Lv.' . $target;
            $done = $target > 0 && $current >= $target;
        } elseif ($obtain === 'task') {
            try {
                $stmt = $this->db->prepare("SELECT 1 FROM user_task_progress WHERE user_id=:uid AND task_id=:tid AND status IN ('completed','claimed') LIMIT 1");
                $stmt->execute([':uid' => $userId, ':tid' => $target]);
                $done = (bool)$stmt->fetchColumn();
                $current = $done ? 1 : 0;
            } catch (\Throwable $e) {
                $done = false;
            }
            $taskTitle = '';
            try {
                $ts = $this->db->prepare('SELECT title FROM tasks WHERE id=:tid LIMIT 1');
                $ts->execute([':tid' => $target]);
                $taskTitle = (string)$ts->fetchColumn();
            } catch (\Throwable $e) {}
            $label = $taskTitle !== '' ? ('完成任务：' . $taskTitle) : ('完成指定任务 #' . $target);
        }

        $percent = $target > 0 ? min(100, (int)floor($current / $target * 100)) : ($done ? 100 : 0);
        return ['current' => $current, 'target' => $target, 'label' => $label, 'done' => $done, 'percent' => $percent];
    }

    private function normColor($value, string $fallback): string
    {
        $c = trim((string)$value);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : $fallback;
    }
}
