<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class BubbleModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function all(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['status'])) { $where[] = 'b.status = :status'; $params[':status'] = $filters['status']; }
        if (!empty($filters['grant_type'])) { $where[] = 'b.grant_type = :gt'; $params[':gt'] = $filters['grant_type']; }
        $sql = 'SELECT b.*,
            (SELECT COUNT(*) FROM plugin_user_chat_bubbles WHERE bubble_id=b.id) AS owner_count,
            (SELECT COUNT(*) FROM plugin_user_chat_bubbles WHERE bubble_id=b.id AND is_equipped=1) AS equipped_count
            FROM plugin_chat_bubbles b WHERE ' . implode(' AND ', $where) . ' ORDER BY b.sort_order ASC, b.id ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM plugin_chat_bubbles WHERE id=:id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM plugin_chat_bubbles WHERE code=:code LIMIT 1');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        $id = (int)($data['id'] ?? 0);
        $code = trim((string)($data['code'] ?? ''));
        $name = trim((string)($data['name'] ?? ''));
        if ($code === '' || $name === '') throw new \RuntimeException('编码和名称不能为空');
        
        $data['obtain_method'] = in_array((string)($data['obtain_method'] ?? 'grant'), ['free','shop','task','level','grant'], true) ? (string)$data['obtain_method'] : 'grant';
        $data['price_currency'] = trim((string)($data['price_currency'] ?? '')) ?: null;
        $data['price_amount'] = number_format(max(0, (float)($data['price_amount'] ?? 0)), 6, '.', '');
        $data['quality'] = preg_replace('/[^a-z0-9_\-]/i', '', (string)($data['quality'] ?? 'standard')) ?: 'standard';
        $qualityName = trim((string)($data['quality_name'] ?? ''));
        $qualityColor = trim((string)($data['quality_color'] ?? ''));
        if ($qualityName === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $qualityColor)) {
            $qStmt = $this->db->prepare('SELECT name,color FROM plugin_bubble_qualities WHERE code=:code LIMIT 1');
            $qStmt->execute([':code' => $data['quality']]);
            $qRow = $qStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            if ($qualityName === '') $qualityName = trim((string)($qRow['name'] ?? '标准')) ?: '标准';
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $qualityColor)) $qualityColor = trim((string)($qRow['color'] ?? '#64748b'));
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $qualityColor)) $qualityColor = '#64748b';
        $data['quality_name'] = $qualityName;
        $data['quality_color'] = $qualityColor;
        $fields = ['code', 'name', 'description', 'image', 'quality', 'quality_name', 'quality_color', 'grant_type', 'rule_type', 'rule_value', 'obtain_method', 'price_currency', 'price_amount', 'sort_order', 'status', 'effect_type', 'effect_params'];
        if ($id > 0) {
            $sets = []; $params = [':id' => $id];
            foreach ($fields as $f) { if (array_key_exists($f, $data)) { $sets[] = "$f=:$f"; $params[":$f"] = $data[$f]; } }
            if ($sets) { $this->db->prepare('UPDATE plugin_chat_bubbles SET ' . implode(',', $sets) . ',updated_at=NOW() WHERE id=:id')->execute($params); }
            return $id;
        }
        $cols = []; $vals = []; $params = [];
        foreach ($fields as $f) { if (array_key_exists($f, $data)) { $cols[] = $f; $vals[] = ":$f"; $params[":$f"] = $data[$f]; } }
        $this->db->prepare('INSERT INTO plugin_chat_bubbles (' . implode(',', $cols) . ',created_at,updated_at) VALUES (' . implode(',', $vals) . ',NOW(),NOW())')->execute($params);
        return (int)$this->db->lastInsertId();
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM plugin_user_chat_bubbles WHERE bubble_id=:id');
        $stmt->execute([':id' => $id]);
        if ((int)$stmt->fetchColumn() > 0) throw new \RuntimeException('有用户拥有此气泡，不能删除');
        $this->db->prepare('DELETE FROM plugin_chat_bubbles WHERE id=:id')->execute([':id' => $id]);
    }

    public function userBubbles(int $userId, bool $ownedOnly = false): array
    {
        if ($ownedOnly) {
            $sql = "SELECT b.*, ub.is_equipped, ub.granted_at FROM plugin_chat_bubbles b
                    INNER JOIN plugin_user_chat_bubbles ub ON ub.bubble_id=b.id AND ub.user_id=:uid
                    WHERE b.status='active' ORDER BY b.sort_order ASC, b.id ASC";
        } else {
            $sql = "SELECT b.*, ub.is_equipped, ub.granted_at FROM plugin_chat_bubbles b
                    LEFT JOIN plugin_user_chat_bubbles ub ON ub.bubble_id=b.id AND ub.user_id=:uid
                    WHERE b.status='active' ORDER BY b.sort_order ASC, b.id ASC";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function equippedForUser(int $userId): ?array
    {
        $stmt = $this->db->prepare("SELECT b.* FROM plugin_chat_bubbles b
            INNER JOIN plugin_user_chat_bubbles ub ON ub.bubble_id=b.id AND ub.user_id=:uid AND ub.is_equipped=1
            WHERE b.status='active' LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function qualityOptions(bool $activeOnly = false): array
    {
        $rows = $this->qualities($activeOnly);
        if (!$rows) {
            return [
                ['code'=>'legend','name'=>'传奇','color'=>'#b91c1c','sort_order'=>10,'status'=>'active','bubble_count'=>0],
                ['code'=>'epic','name'=>'史诗','color'=>'#7c3aed','sort_order'=>20,'status'=>'active','bubble_count'=>0],
                ['code'=>'rare','name'=>'稀有','color'=>'#2563eb','sort_order'=>30,'status'=>'active','bubble_count'=>0],
                ['code'=>'standard','name'=>'标准','color'=>'#64748b','sort_order'=>40,'status'=>'active','bubble_count'=>0],
            ];
        }
        return $rows;
    }

    public function qualities(bool $activeOnly = false): array
    {
        $sql = "SELECT q.*, (SELECT COUNT(*) FROM plugin_chat_bubbles b WHERE b.quality=q.code AND b.status='active') AS bubble_count FROM plugin_bubble_qualities q";
        if ($activeOnly) $sql .= " WHERE q.status='active'";
        $sql .= ' ORDER BY q.sort_order ASC, q.id ASC';
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
        $stmt = $this->db->prepare('INSERT INTO plugin_bubble_qualities (code,name,color,sort_order,status,created_at,updated_at) VALUES (:code,:name,:color,:sort_order,:status,NOW(),NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),color=VALUES(color),sort_order=VALUES(sort_order),status=VALUES(status),updated_at=NOW()');
        $stmt->execute([':code'=>$code, ':name'=>$name, ':color'=>$color, ':sort_order'=>$sort, ':status'=>$status]);
    }

    public function deleteQuality(string $code): void
    {
        $code = strtolower(trim($code));
        if ($code === '') return;
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM plugin_chat_bubbles WHERE quality=:code');
        $stmt->execute([':code'=>$code]);
        if ((int)$stmt->fetchColumn() > 0) throw new \RuntimeException('该品质下已有气泡，不能删除，可先停用');
        $this->db->prepare('DELETE FROM plugin_bubble_qualities WHERE code=:code')->execute([':code'=>$code]);
    }

    public function grantRows(int $limit = 200): array
    {
        $stmt = $this->db->prepare('SELECT ub.*, b.name AS bubble_name, b.code AS bubble_code, u.username, u.nickname, u.public_id
            FROM plugin_user_chat_bubbles ub
            JOIN plugin_chat_bubbles b ON b.id=ub.bubble_id
            JOIN users u ON u.id=ub.user_id
            ORDER BY ub.granted_at DESC LIMIT ' . (int)$limit);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    
    public static function effectTypes(): array
    {
        return [
            'galaxy' => [
                'name' => '星河幻想',
                'desc' => '深空渐变、星轨光环与星尘粒子',
                'default_color' => '#22d3ee',
                'default_count' => 14,
                'default_speed' => 0.8,
                'default_size' => 3,
            ],
            'cat' => [
                'name' => '甜心喵咪',
                'desc' => '奶橘渐变、猫脸挂件与猫爪粒子',
                'default_color' => '#fb923c',
                'default_count' => 10,
                'default_speed' => 0.7,
                'default_size' => 12,
            ],
        ];
    }
}
