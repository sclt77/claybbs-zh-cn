<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class AvatarFrameModel
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
            $where[] = 'f.status = :status';
            $params[':status'] = (string)$filters['status'];
        }
        if (!empty($filters['grant_type'])) {
            $where[] = 'f.grant_type = :grant_type';
            $params[':grant_type'] = (string)$filters['grant_type'];
        }
        $sql = "SELECT f.*,
                (SELECT COUNT(*) FROM plugin_user_avatar_frames uf WHERE uf.frame_id=f.id) AS owner_count,
                (SELECT COUNT(*) FROM plugin_user_avatar_frames uf WHERE uf.frame_id=f.id AND uf.is_equipped=1) AS equipped_count
                FROM plugin_avatar_frames f";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY f.sort_order ASC, f.id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM plugin_avatar_frames WHERE id=:id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM plugin_avatar_frames WHERE code=:code LIMIT 1');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        $id = (int)($data['id'] ?? 0);
        $code = strtolower(trim((string)($data['code'] ?? '')));
        $code = preg_replace('/[^a-z0-9_\-]/', '', $code) ?: '';
        if ($code === '') throw new \RuntimeException('请填写头像框标识');
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') throw new \RuntimeException('请填写头像框名称');
        $quality = preg_replace('/[^a-z0-9_\-]/i', '', (string)($data['quality'] ?? 'standard')) ?: 'standard';
        $qualityName = trim((string)($data['quality_name'] ?? ''));
        $color = trim((string)($data['quality_color'] ?? ''));
        if ($qualityName === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $qStmt = $this->db->prepare('SELECT name,color FROM plugin_avatar_frame_qualities WHERE code=:code LIMIT 1');
            $qStmt->execute([':code' => $quality]);
            $qRow = $qStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            if ($qualityName === '') $qualityName = trim((string)($qRow['name'] ?? '标准')) ?: '标准';
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = trim((string)($qRow['color'] ?? '#64748b'));
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#64748b';
        $payload = [
            ':code' => $code,
            ':name' => $name,
            ':description' => trim((string)($data['description'] ?? '')),
            ':image' => trim((string)($data['image'] ?? '')),
            ':quality' => $quality,
            ':quality_name' => $qualityName,
            ':quality_color' => $color,
            ':grant_type' => in_array((string)($data['grant_type'] ?? 'manual'), ['manual','auto','purchase'], true) ? (string)$data['grant_type'] : 'manual',
            ':rule_type' => preg_replace('/[^a-z0-9_\-]/i', '', (string)($data['rule_type'] ?? 'manual')) ?: 'manual',
            ':rule_value' => max(0, (int)($data['rule_value'] ?? 0)),
            ':obtain_method' => in_array((string)($data['obtain_method'] ?? 'grant'), ['free','shop','task','level','grant'], true) ? (string)$data['obtain_method'] : 'grant',
            ':price_currency' => trim((string)($data['price_currency'] ?? '')) ?: null,
            ':price_amount' => number_format(max(0, (float)($data['price_amount'] ?? 0)), 6, '.', ''),
            ':sort_order' => (int)($data['sort_order'] ?? 0),
            ':status' => in_array((string)($data['status'] ?? 'active'), ['active','disabled'], true) ? (string)$data['status'] : 'active',
        ];
        if ($id > 0) {
            $payload[':id'] = $id;
            $stmt = $this->db->prepare('UPDATE plugin_avatar_frames SET code=:code,name=:name,description=:description,image=:image,quality=:quality,quality_name=:quality_name,quality_color=:quality_color,grant_type=:grant_type,rule_type=:rule_type,rule_value=:rule_value,obtain_method=:obtain_method,price_currency=:price_currency,price_amount=:price_amount,sort_order=:sort_order,status=:status,updated_at=NOW() WHERE id=:id');
            $stmt->execute($payload);
            return $id;
        }
        $stmt = $this->db->prepare('INSERT INTO plugin_avatar_frames (code,name,description,image,quality,quality_name,quality_color,grant_type,rule_type,rule_value,obtain_method,price_currency,price_amount,sort_order,status,created_at,updated_at) VALUES (:code,:name,:description,:image,:quality,:quality_name,:quality_color,:grant_type,:rule_type,:rule_value,:obtain_method,:price_currency,:price_amount,:sort_order,:status,NOW(),NOW())');
        $stmt->execute($payload);
        return (int)$this->db->lastInsertId();
    }

    public function delete(int $id): void
    {
        if ($id <= 0) return;
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM plugin_user_avatar_frames WHERE frame_id=:id');
        $stmt->execute([':id' => $id]);
        if ((int)$stmt->fetchColumn() > 0) throw new \RuntimeException('已有用户获得该头像框，不能删除，可改为禁用');
        $this->db->prepare('DELETE FROM plugin_avatar_frames WHERE id=:id')->execute([':id' => $id]);
    }

    public function qualityOptions(bool $activeOnly = false): array
    {
        $rows = $this->qualities($activeOnly);
        if (!$rows) {
            return [
                ['code'=>'legend','name'=>'传奇','color'=>'#b91c1c','sort_order'=>10,'status'=>'active','frame_count'=>0],
                ['code'=>'epic','name'=>'史诗','color'=>'#7c3aed','sort_order'=>20,'status'=>'active','frame_count'=>0],
                ['code'=>'rare','name'=>'稀有','color'=>'#2563eb','sort_order'=>30,'status'=>'active','frame_count'=>0],
                ['code'=>'standard','name'=>'标准','color'=>'#64748b','sort_order'=>40,'status'=>'active','frame_count'=>0],
            ];
        }
        return $rows;
    }

    public function qualities(bool $activeOnly = false): array
    {
        $sql = "SELECT q.*, (SELECT COUNT(*) FROM plugin_avatar_frames f WHERE f.quality=q.code AND f.status='active') AS frame_count FROM plugin_avatar_frame_qualities q";
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
        $stmt = $this->db->prepare('INSERT INTO plugin_avatar_frame_qualities (code,name,color,sort_order,status,created_at,updated_at) VALUES (:code,:name,:color,:sort_order,:status,NOW(),NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),color=VALUES(color),sort_order=VALUES(sort_order),status=VALUES(status),updated_at=NOW()');
        $stmt->execute([':code'=>$code, ':name'=>$name, ':color'=>$color, ':sort_order'=>$sort, ':status'=>$status]);
    }

    public function deleteQuality(string $code): void
    {
        $code = strtolower(trim($code));
        if ($code === '') return;
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM plugin_avatar_frames WHERE quality=:code');
        $stmt->execute([':code'=>$code]);
        if ((int)$stmt->fetchColumn() > 0) throw new \RuntimeException('该品质下已有头像框，不能删除，可先停用');
        $this->db->prepare('DELETE FROM plugin_avatar_frame_qualities WHERE code=:code')->execute([':code'=>$code]);
    }

    public function userFrames(int $userId, bool $ownedOnly = false): array
    {
        if ($ownedOnly) {
            $stmt = $this->db->prepare("SELECT uf.*, f.* FROM plugin_user_avatar_frames uf JOIN plugin_avatar_frames f ON f.id=uf.frame_id WHERE uf.user_id=:uid ORDER BY uf.is_equipped DESC, f.sort_order ASC, f.id DESC");
            $stmt->execute([':uid' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        $stmt = $this->db->prepare("SELECT f.*, uf.id AS grant_id, uf.is_equipped, uf.granted_at, uf.expires_at, uf.grant_source, uf.note, CASE WHEN uf.id IS NULL THEN 0 ELSE 1 END AS owned FROM plugin_avatar_frames f LEFT JOIN plugin_user_avatar_frames uf ON uf.frame_id=f.id AND uf.user_id=:uid WHERE f.status='active' ORDER BY f.sort_order ASC, f.id DESC");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function equippedForUser(int $userId): ?array
    {
        if ($userId <= 0) return null;
        $stmt = $this->db->prepare("SELECT f.*, uf.id AS grant_id FROM plugin_user_avatar_frames uf JOIN plugin_avatar_frames f ON f.id=uf.frame_id WHERE uf.user_id=:uid AND uf.is_equipped=1 AND f.status='active' AND (uf.expires_at IS NULL OR uf.expires_at > NOW()) ORDER BY uf.updated_at DESC LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function grantRows(int $limit = 200): array
    {
        $stmt = $this->db->prepare("SELECT uf.*, f.name AS frame_name, f.image AS frame_image, f.quality_color, u.username, u.nickname, u.public_id FROM plugin_user_avatar_frames uf JOIN plugin_avatar_frames f ON f.id=uf.frame_id JOIN users u ON u.id=uf.user_id ORDER BY uf.id DESC LIMIT :limit");
        $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function progressFor(int $userId, array $frame): array
    {
        $type = (string)($frame['rule_type'] ?? 'manual');
        $target = max(0, (int)($frame['rule_value'] ?? 0));
        $current = 0;
        $label = '手动授予';
        if ($type === 'register_days') {
            $stmt = $this->db->prepare('SELECT DATEDIFF(NOW(), created_at) FROM users WHERE id=:uid');
            $stmt->execute([':uid' => $userId]);
            $current = max(0, (int)$stmt->fetchColumn());
            $label = '注册天数达到 ' . $target . ' 天';
        } elseif ($type === 'thread_count') {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM threads WHERE user_id=:uid AND status='published'");
            $stmt->execute([':uid' => $userId]);
            $current = (int)$stmt->fetchColumn();
            $label = '发布主题达到 ' . $target . ' 篇';
        } elseif ($type === 'post_count') {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM posts WHERE user_id=:uid AND status='published'");
            $stmt->execute([':uid' => $userId]);
            $current = (int)$stmt->fetchColumn();
            $label = '回复数量达到 ' . $target . ' 条';
        } elseif ($type === 'like_count') {
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(like_count),0) FROM threads WHERE user_id=:uid AND status='published'");
            $stmt->execute([':uid' => $userId]);
            $current = (int)$stmt->fetchColumn();
            $label = '主题获赞达到 ' . $target . ' 次';
        } elseif ($type === 'level') {
            try {
                $stmt = $this->db->prepare('SELECT level FROM user_growth WHERE user_id=:uid LIMIT 1');
                $stmt->execute([':uid' => $userId]);
                $current = (int)$stmt->fetchColumn();
            } catch (\Throwable $e) { $current = 0; }
            $label = '等级达到 Lv.' . $target;
        }
        return ['current' => $current, 'target' => $target, 'label' => $label, 'done' => $current >= $target];
    }
}
