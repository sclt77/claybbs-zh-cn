<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class VerificationModel
{
    public function types(bool $onlyActive = false, bool $onlyOpen = false): array
    {
        $where = 'WHERE 1=1';
        if ($onlyActive) $where .= " AND status='active'";
        if ($onlyOpen) $where .= " AND allow_apply=1";
        return Database::connection()->query("SELECT * FROM verification_types {$where} ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findType(int $id): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM verification_types WHERE id=:id LIMIT 1");
        $stmt->execute([':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function saveType(array $data): void
    {
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['name'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        if ($name === '' || $description === '') return;
        $payload = [
            ':name' => $name,
            ':color' => $this->normalizeColor((string)($data['color'] ?? '#2563eb')),
            ':description' => $description,
            ':apply_note' => trim((string)($data['apply_note'] ?? '')),
            ':allow_apply' => !empty($data['allow_apply']) ? 1 : 0,
            ':status' => in_array(($data['status'] ?? 'active'), ['active','inactive'], true) ? (string)$data['status'] : 'active',
            ':sort_order' => (int)($data['sort_order'] ?? 0),
        ];
        if ($id > 0) {
            $payload[':id'] = $id;
            Database::connection()->prepare("UPDATE verification_types SET name=:name,color=:color,description=:description,apply_note=:apply_note,allow_apply=:allow_apply,status=:status,sort_order=:sort_order,updated_at=NOW() WHERE id=:id")->execute($payload);
        } else {
            Database::connection()->prepare("INSERT INTO verification_types (name,color,description,apply_note,allow_apply,status,sort_order,created_at,updated_at) VALUES (:name,:color,:description,:apply_note,:allow_apply,:status,:sort_order,NOW(),NOW())")->execute($payload);
        }
    }

    public function deleteType(int $id): bool
    {
        if ($id <= 0) return false;
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM user_verifications WHERE type_id=:id");
        $stmt->execute([':id'=>$id]);
        if ((int)$stmt->fetchColumn() > 0) return false;
        Database::connection()->prepare("DELETE FROM verification_requests WHERE type_id=:id AND status IN ('rejected','cancelled')")->execute([':id'=>$id]);
        $delete = Database::connection()->prepare("DELETE FROM verification_types WHERE id=:id");
        $delete->execute([':id'=>$id]);
        return $delete->rowCount() > 0;
    }

    public function activeForUser(int $userId): ?array
    {
        if ($userId <= 0) return null;
        $stmt = Database::connection()->prepare("SELECT uv.*, COALESCE(NULLIF(uv.display_name,''), vt.name) AS verification_name, vt.color AS verification_color, vt.description AS verification_description FROM user_verifications uv JOIN verification_types vt ON vt.id=uv.type_id WHERE uv.user_id=:uid AND uv.status='active' AND vt.status='active' ORDER BY uv.verified_at DESC, uv.id DESC LIMIT 1");
        $stmt->execute([':uid'=>$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function activeMap(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds) return [];
        $ph = []; $params = [];
        foreach ($userIds as $i=>$id) { $key=':u'.$i; $ph[]=$key; $params[$key]=$id; }
        $stmt = Database::connection()->prepare("SELECT uv.user_id, COALESCE(NULLIF(uv.display_name,''), vt.name) AS verification_name, vt.color AS verification_color, vt.description AS verification_description FROM user_verifications uv JOIN verification_types vt ON vt.id=uv.type_id WHERE uv.status='active' AND vt.status='active' AND uv.user_id IN (".implode(',', $ph).") ORDER BY uv.verified_at DESC, uv.id DESC");
        $stmt->execute($params);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $uid = (int)$row['user_id'];
            if (!isset($map[$uid])) $map[$uid] = $row;
        }
        return $map;
    }

    public function latestRequest(int $userId): ?array
    {
        $stmt = Database::connection()->prepare("SELECT vr.*, COALESCE(NULLIF(vr.display_name,''), vt.name) AS verification_name, vt.name AS type_name, vt.color AS verification_color, vt.description, vt.apply_note FROM verification_requests vr JOIN verification_types vt ON vt.id=vr.type_id WHERE vr.user_id=:uid ORDER BY vr.id DESC LIMIT 1");
        $stmt->execute([':uid'=>$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function pendingRequest(int $userId): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM verification_requests WHERE user_id=:uid AND status='pending' ORDER BY id DESC LIMIT 1");
        $stmt->execute([':uid'=>$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function latestRevocation(int $userId): ?array
    {
        if ($userId <= 0) return null;
        $stmt = Database::connection()->prepare("SELECT uv.*, COALESCE(NULLIF(uv.display_name,''), vt.name) AS verification_name, vt.color AS verification_color FROM user_verifications uv JOIN verification_types vt ON vt.id=uv.type_id WHERE uv.user_id=:uid AND uv.status='revoked' AND uv.revoked_at IS NOT NULL ORDER BY uv.revoked_at DESC, uv.id DESC LIMIT 1");
        $stmt->execute([':uid'=>$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function cooldownInfo(int $userId, int $cooldownHours): array
    {
        $cooldownHours = max(0, min(8760, $cooldownHours));
        $revoked = $this->latestRevocation($userId);
        if (!$revoked || $cooldownHours <= 0) {
            return ['active' => false, 'remaining_seconds' => 0, 'until' => null, 'revoked' => $revoked];
        }
        $revokedAt = strtotime((string)($revoked['revoked_at'] ?? ''));
        if (!$revokedAt) {
            return ['active' => false, 'remaining_seconds' => 0, 'until' => null, 'revoked' => $revoked];
        }
        $untilTs = $revokedAt + ($cooldownHours * 3600);
        $remaining = max(0, $untilTs - time());
        return [
            'active' => $remaining > 0,
            'remaining_seconds' => $remaining,
            'until' => date('Y-m-d H:i:s', $untilTs),
            'revoked' => $revoked,
        ];
    }

    public function createRequest(int $userId, int $typeId, string $displayName, string $realName, string $material, int $cooldownHours = 0): bool
    {
        if ($userId <= 0 || $typeId <= 0 || trim($material) === '') return false;
        $displayName = trim($displayName);
        if ($displayName === '' || mb_strlen($displayName) > 8) return false;
        if ($this->activeForUser($userId)) return false;
        if ($this->pendingRequest($userId)) return false;
        if (($this->cooldownInfo($userId, $cooldownHours)['active'] ?? false) === true) return false;
        $type = $this->findType($typeId);
        if (!$type || ($type['status'] ?? '') !== 'active' || empty($type['allow_apply'])) return false;
        Database::connection()->prepare("INSERT INTO verification_requests (user_id,type_id,display_name,real_name,material,status,created_at,updated_at) VALUES (:uid,:tid,:display_name,:real_name,:material,'pending',NOW(),NOW())")
            ->execute([':uid'=>$userId, ':tid'=>$typeId, ':display_name'=>$displayName, ':real_name'=>trim($realName), ':material'=>trim($material)]);
        return true;
    }

    public function requests(string $status = '', int $limit = 200): array
    {
        $where = 'WHERE 1=1'; $params = [];
        if ($status !== '') { $where .= ' AND vr.status=:status'; $params[':status']=$status; }
        $stmt = Database::connection()->prepare("SELECT vr.*, u.username,u.nickname,u.avatar, COALESCE(NULLIF(vr.display_name,''), vt.name) AS verification_name, vt.name AS type_name, vt.color AS verification_color, admin.username AS reviewer_name FROM verification_requests vr JOIN users u ON u.id=vr.user_id JOIN verification_types vt ON vt.id=vr.type_id LEFT JOIN users admin ON admin.id=vr.reviewed_by {$where} ORDER BY FIELD(vr.status,'pending','approved','rejected','cancelled'), vr.id DESC LIMIT :limit");
        foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function reviewRequest(int $id, string $action, int $adminId, string $note = ''): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare("SELECT vr.*, COALESCE(NULLIF(vr.display_name,''), vt.name) AS verification_name, vt.name AS type_name, vt.color AS verification_color FROM verification_requests vr JOIN verification_types vt ON vt.id=vr.type_id WHERE vr.id=:id LIMIT 1");
        $stmt->execute([':id'=>$id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$req || ($req['status'] ?? '') !== 'pending') return null;
        if ($action === 'approve' && $this->activeForUser((int)$req['user_id'])) return null;
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE verification_requests SET status=:status,review_note=:note,reviewed_by=:admin,reviewed_at=NOW(),updated_at=NOW() WHERE id=:id")
                ->execute([':status'=>$status, ':note'=>$note, ':admin'=>$adminId, ':id'=>$id]);
            if ($status === 'approved') {
                $db->prepare("UPDATE user_verifications SET status='revoked', revoked_at=NOW(), revoked_by=:admin, revoke_reason='新认证通过后自动替换' WHERE user_id=:uid AND status='active'")
                    ->execute([':admin'=>$adminId, ':uid'=>(int)$req['user_id']]);
                $db->prepare("INSERT INTO user_verifications (user_id,type_id,request_id,display_name,status,verified_at,verified_by,created_at,updated_at) VALUES (:uid,:tid,:rid,:display_name,'active',NOW(),:admin,NOW(),NOW())")
                    ->execute([':uid'=>(int)$req['user_id'], ':tid'=>(int)$req['type_id'], ':rid'=>$id, ':display_name'=>(string)($req['display_name'] ?? ''), ':admin'=>$adminId]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        $req['status'] = $status;
        $req['review_note'] = $note;
        return $req;
    }

    public function activeUsers(int $limit = 200): array
    {
        $stmt = Database::connection()->prepare("SELECT uv.*, u.username,u.nickname,u.avatar, COALESCE(NULLIF(uv.display_name,''), vt.name) AS verification_name, vt.color AS verification_color, vt.description AS verification_description, admin.username AS verified_by_name FROM user_verifications uv JOIN users u ON u.id=uv.user_id JOIN verification_types vt ON vt.id=uv.type_id LEFT JOIN users admin ON admin.id=uv.verified_by WHERE uv.status='active' ORDER BY uv.verified_at DESC LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cooldownUsers(int $cooldownHours, int $limit = 200): array
    {
        $cooldownHours = max(0, min(8760, $cooldownHours));
        if ($cooldownHours <= 0) return [];
        $sql = "SELECT uv.*, u.username, u.nickname, u.avatar, COALESCE(NULLIF(uv.display_name,''), vt.name) AS verification_name, vt.color AS verification_color,
                       DATE_ADD(uv.revoked_at, INTERVAL {$cooldownHours} HOUR) AS cooldown_until,
                       TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(uv.revoked_at, INTERVAL {$cooldownHours} HOUR)) AS remaining_seconds
                FROM user_verifications uv
                JOIN users u ON u.id=uv.user_id
                JOIN verification_types vt ON vt.id=uv.type_id
                WHERE uv.status='revoked' AND uv.revoked_at IS NOT NULL
                  AND DATE_ADD(uv.revoked_at, INTERVAL {$cooldownHours} HOUR) > NOW()
                  AND NOT EXISTS (SELECT 1 FROM user_verifications active WHERE active.user_id=uv.user_id AND active.status='active')
                ORDER BY uv.revoked_at DESC
                LIMIT :limit";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function revoke(int $verificationId, int $adminId, string $reason = ''): ?array
    {
        if ($verificationId <= 0) return null;
        $stmt = Database::connection()->prepare("SELECT uv.*, COALESCE(NULLIF(uv.display_name,''), vt.name) AS verification_name, vt.color AS verification_color FROM user_verifications uv JOIN verification_types vt ON vt.id=uv.type_id WHERE uv.id=:id AND uv.status='active' LIMIT 1");
        $stmt->execute([':id'=>$verificationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        Database::connection()->prepare("UPDATE user_verifications SET status='revoked', revoked_at=NOW(), revoked_by=:admin, revoke_reason=:reason, updated_at=NOW() WHERE id=:id AND status='active'")
            ->execute([':admin'=>$adminId > 0 ? $adminId : null, ':reason'=>$reason, ':id'=>$verificationId]);
        $row['status'] = 'revoked';
        $row['revoked_at'] = date('Y-m-d H:i:s');
        $row['revoke_reason'] = $reason;
        return $row;
    }

    public function revokeByUser(int $userId, string $reason = '用户主动撤销认证'): ?array
    {
        $active = $this->activeForUser($userId);
        if (!$active) return null;
        return $this->revoke((int)$active['id'], $userId, $reason);
    }

    private function normalizeColor(string $color): string
    {
        $color = trim($color);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) return strtolower($color);
        return '#2563eb';
    }
}
