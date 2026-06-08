<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class MedalModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function all(array $filters = []): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'm.status = :status';
            $params[':status'] = (string)$filters['status'];
        }
        if (!empty($filters['category'])) {
            $where[] = 'm.category = :category';
            $params[':category'] = (string)$filters['category'];
        }
        if (!empty($filters['grant_type'])) {
            $where[] = 'm.grant_type = :grant_type';
            $params[':grant_type'] = (string)$filters['grant_type'];
        }
        $sql = 'SELECT m.*, q.name AS level_name, q.color AS level_color, q.sort_order AS level_sort, q.status AS level_status, (SELECT COUNT(*) FROM plugin_user_badges ub WHERE ub.badge_id=m.id) AS grant_count FROM plugin_badges m LEFT JOIN plugin_badge_qualities q ON q.code=m.level';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY COALESCE(q.sort_order, 999) ASC, m.sort_order ASC, m.id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function qualities(bool $activeOnly = false): array
    {
        $sql = 'SELECT q.*, (SELECT COUNT(*) FROM plugin_badges b WHERE b.level=q.code AND b.status=\'active\') AS badge_count FROM plugin_badge_qualities q';
        if ($activeOnly) $sql .= " WHERE q.status='active'";
        $sql .= ' ORDER BY q.sort_order ASC, q.id ASC';
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function qualityOptions(bool $activeOnly = false): array
    {
        $rows = $this->qualities($activeOnly);
        if (!$rows) {
            return [
                ['code'=>'legend','name'=>'传奇','color'=>'#b91c1c','sort_order'=>10,'status'=>'active','badge_count'=>0],
                ['code'=>'epic','name'=>'史诗','color'=>'#7c3aed','sort_order'=>20,'status'=>'active','badge_count'=>0],
                ['code'=>'rare','name'=>'稀有','color'=>'#2563eb','sort_order'=>30,'status'=>'active','badge_count'=>0],
                ['code'=>'standard','name'=>'标准','color'=>'#64748b','sort_order'=>40,'status'=>'active','badge_count'=>0],
            ];
        }
        return $rows;
    }

    public function saveQuality(array $data): void
    {
        $code = strtolower(trim((string)($data['code'] ?? '')));
        $code = preg_replace('/[^a-z0-9_\-]/', '', $code) ?: '';
        if ($code === '') throw new \RuntimeException('请填写品质标识');
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') throw new \RuntimeException('请填写品质名称');
        $color = trim((string)($data['color'] ?? '#64748b'));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#64748b';
        $sort = (int)($data['sort_order'] ?? 0);
        $status = ((string)($data['status'] ?? 'active') === 'inactive') ? 'inactive' : 'active';
        $stmt = $this->db->prepare('INSERT INTO plugin_badge_qualities (code,name,color,sort_order,status,created_at,updated_at) VALUES (:code,:name,:color,:sort_order,:status,NOW(),NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),color=VALUES(color),sort_order=VALUES(sort_order),status=VALUES(status),updated_at=NOW()');
        $stmt->execute([':code'=>$code, ':name'=>$name, ':color'=>$color, ':sort_order'=>$sort, ':status'=>$status]);
    }

    public function deleteQuality(string $code): void
    {
        $code = strtolower(trim($code));
        if ($code === '') return;
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM plugin_badges WHERE level=:code');
        $stmt->execute([':code'=>$code]);
        if ((int)$stmt->fetchColumn() > 0) throw new \RuntimeException('该品质下已有勋章，不能删除，可先停用');
        $this->db->prepare('DELETE FROM plugin_badge_qualities WHERE code=:code')->execute([':code'=>$code]);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM plugin_badges WHERE id=:id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') throw new \RuntimeException('请填写勋章名称');
        $code = trim((string)($data['code'] ?? ''));
        if ($code === '') $code = $this->slugify($name);
        $category = trim((string)($data['category'] ?? 'manual')) ?: 'manual';
        $description = trim((string)($data['description'] ?? ''));
        $icon = trim((string)($data['icon'] ?? ''));
        $color = trim((string)($data['color'] ?? '#f59e0b'));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#f59e0b';
        $level = trim((string)($data['level'] ?? 'standard')) ?: 'standard';
        $level = preg_replace('/[^a-z0-9_\-]/i', '', $level) ?: 'standard';
        $grantType = in_array((string)($data['grant_type'] ?? 'manual'), ['manual','auto'], true) ? (string)$data['grant_type'] : 'manual';
        $ruleType = trim((string)($data['rule_type'] ?? 'manual')) ?: 'manual';
        $ruleValue = max(0, (int)($data['rule_value'] ?? 0));
        $maxEquip = max(0, (int)($data['max_equipped'] ?? 1));
        $obtainMethod = in_array((string)($data['obtain_method'] ?? 'grant'), ['free','shop','task','level','grant'], true) ? (string)$data['obtain_method'] : 'grant';
        $priceCurrency = trim((string)($data['price_currency'] ?? '')) ?: null;
        $priceAmount = number_format(max(0, (float)($data['price_amount'] ?? 0)), 6, '.', '');
        $sort = (int)($data['sort_order'] ?? 0);
        $status = ((string)($data['status'] ?? 'active') === 'inactive') ? 'inactive' : 'active';
        if ($id > 0) {
            $stmt = $this->db->prepare('UPDATE plugin_badges SET code=:code,name=:name,description=:description,icon=:icon,color=:color,category=:category,level=:level,grant_type=:grant_type,rule_type=:rule_type,rule_value=:rule_value,max_equipped=:max_equipped,obtain_method=:obtain_method,price_currency=:price_currency,price_amount=:price_amount,sort_order=:sort_order,status=:status,updated_at=NOW() WHERE id=:id');
            $stmt->execute([':code'=>$code,':name'=>$name,':description'=>$description,':icon'=>$icon,':color'=>$color,':category'=>$category,':level'=>$level,':grant_type'=>$grantType,':rule_type'=>$ruleType,':rule_value'=>$ruleValue,':max_equipped'=>$maxEquip,':obtain_method'=>$obtainMethod,':price_currency'=>$priceCurrency,':price_amount'=>$priceAmount,':sort_order'=>$sort,':status'=>$status,':id'=>$id]);
            return $id;
        }
        $stmt = $this->db->prepare('INSERT INTO plugin_badges (code,name,description,icon,color,category,level,grant_type,rule_type,rule_value,max_equipped,obtain_method,price_currency,price_amount,sort_order,status,created_at,updated_at) VALUES (:code,:name,:description,:icon,:color,:category,:level,:grant_type,:rule_type,:rule_value,:max_equipped,:obtain_method,:price_currency,:price_amount,:sort_order,:status,NOW(),NOW())');
        $stmt->execute([':code'=>$code,':name'=>$name,':description'=>$description,':icon'=>$icon,':color'=>$color,':category'=>$category,':level'=>$level,':grant_type'=>$grantType,':rule_type'=>$ruleType,':rule_value'=>$ruleValue,':max_equipped'=>$maxEquip,':obtain_method'=>$obtainMethod,':price_currency'=>$priceCurrency,':price_amount'=>$priceAmount,':sort_order'=>$sort,':status'=>$status]);
        return (int)$this->db->lastInsertId();
    }

    public function delete(int $id): void
    {
        if ($id <= 0) return;
        $this->db->prepare('DELETE FROM plugin_user_badges WHERE badge_id=:id')->execute([':id'=>$id]);
        $this->db->prepare('DELETE FROM plugin_badge_logs WHERE badge_id=:id')->execute([':id'=>$id]);
        $this->db->prepare('DELETE FROM plugin_badges WHERE id=:id')->execute([':id'=>$id]);
    }

    public function userMedals(int $userId, bool $equippedOnly = false, int $limit = 100): array
    {
        $sql = "SELECT ub.*, b.name, b.code, b.description, b.icon, b.color, b.category, b.level, b.grant_type
                FROM plugin_user_badges ub
                JOIN plugin_badges b ON b.id=ub.badge_id
                WHERE ub.user_id=:uid AND b.status='active'";
        if ($equippedOnly) $sql .= ' AND ub.is_equipped=1';
        $sql .= ' ORDER BY ub.is_equipped DESC, COALESCE(ub.equip_slot, 99) ASC, ub.granted_at DESC, ub.id DESC LIMIT ' . max(1, min(200, $limit));
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':uid'=>$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function grantRows(int $limit = 200): array
    {
        $stmt = $this->db->query("SELECT ub.*, b.name AS badge_name, b.icon, b.color, b.level, u.username, u.nickname, u.public_id
            FROM plugin_user_badges ub
            LEFT JOIN plugin_badges b ON b.id=ub.badge_id
            LEFT JOIN users u ON u.id=ub.user_id
            ORDER BY ub.granted_at DESC, ub.id DESC LIMIT " . max(1, min(500, $limit)));
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function statsForUser(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT
            (SELECT COUNT(*) FROM threads WHERE user_id=:uid AND status='published') AS threads,
            (SELECT COUNT(*) FROM posts WHERE user_id=:uid AND status='published') AS posts,
            (SELECT COALESCE(SUM(like_count),0) FROM threads WHERE user_id=:uid AND status='published') + (SELECT COALESCE(SUM(like_count),0) FROM posts WHERE user_id=:uid AND status='published') AS likes,
            (SELECT COUNT(*) FROM threads WHERE user_id=:uid AND status='published' AND is_featured=1) AS featured,
            (SELECT COUNT(*) FROM task_submissions WHERE user_id=:uid AND status='approved') + (SELECT COUNT(*) FROM user_task_progress WHERE user_id=:uid AND status IN ('completed','claimed')) AS tasks,
            DATEDIFF(NOW(), (SELECT created_at FROM users WHERE id=:uid LIMIT 1)) AS days,
            (SELECT COALESCE(level,0) FROM user_growth_stats WHERE user_id=:uid LIMIT 1) AS level
        ");
        $stmt->execute([':uid'=>$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return array_map('intval', $row);
    }

    public function progressFor(int $userId, array $medal): array
    {
        $rule = (string)($medal['rule_type'] ?? 'manual');
        $target = max(0, (int)($medal['rule_value'] ?? 0));
        if ($rule === 'manual' || $target <= 0) {
            return ['current'=>0,'target'=>$target,'percent'=>0,'label'=>'由管理员手动发放'];
        }
        $stats = $this->statsForUser($userId);
        $map = ['threads'=>'threads','posts'=>'posts','likes'=>'likes','featured'=>'featured','tasks'=>'tasks','days'=>'days','level'=>'level'];
        $current = (int)($stats[$map[$rule] ?? ''] ?? 0);
        return ['current'=>$current,'target'=>$target,'percent'=>$target > 0 ? min(100, (int)floor($current * 100 / $target)) : 0,'label'=>$this->ruleLabel($rule, $target)];
    }

    public function ruleLabel(string $rule, int $target): string
    {
        return match ($rule) {
            'threads' => '发布主题达到 ' . $target . ' 篇',
            'posts' => '发布回复达到 ' . $target . ' 条',
            'likes' => '获赞达到 ' . $target . ' 次',
            'featured' => '精华主题达到 ' . $target . ' 篇',
            'tasks' => '完成任务达到 ' . $target . ' 次',
            'days' => '注册天数达到 ' . $target . ' 天',
            'level' => '等级达到 Lv.' . $target,
            default => '由管理员手动发放',
        };
    }

    public function slugify(string $text): string
    {
        $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $text), '-'));
        if ($base === '') $base = 'medal-' . substr(bin2hex(random_bytes(4)), 0, 8);
        return substr($base, 0, 80);
    }
}
