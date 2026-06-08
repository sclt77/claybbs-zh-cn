<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class AdminUserModel
{
    public function list(string $keyword = '', array $filters = [], int $page = 1, int $pageSize = 20): array
    {
        [$where, $params] = $this->buildWhere($keyword, $filters);
        $offset = max(0, ($page - 1) * $pageSize);
        $sql = "SELECT u.id, u.public_id, u.public_id_style, u.username, u.nickname, u.email, u.avatar, u.role, u.status, IFNULL(u.email_verified, 0) AS email_verified, u.created_at, COALESCE(ucs.score, 100) AS credit_score
                FROM users u LEFT JOIN user_credit_stats ucs ON ucs.user_id=u.id {$where} ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(string $keyword = '', array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($keyword, $filters);
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM users u {$where}");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    private function buildWhere(string $keyword, array $filters = []): array
    {
        $where = "WHERE 1=1";
        $params = [];
        if ($keyword !== '') {
            $where .= " AND (u.public_id LIKE :kw OR u.username LIKE :kw OR u.nickname LIKE :kw OR u.email LIKE :kw)";
            $params[':kw'] = '%' . $keyword . '%';
        }
        $status = (string)($filters['status'] ?? '');
        if ($status !== '' && in_array($status, ['active', 'banned'], true)) {
            $where .= " AND u.status = :status";
            $params[':status'] = $status;
        }
        $role = (string)($filters['role'] ?? '');
        if ($role !== '' && in_array($role, ['user', 'reviewer', 'moderator', 'admin', 'superadmin'], true)) {
            $where .= " AND u.role = :role";
            $params[':role'] = $role;
        }
        return [$where, $params];
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT u.id, u.public_id, u.public_id_style, u.username, u.nickname, u.email, u.avatar, u.role, u.status, IFNULL(u.email_verified, 0) AS email_verified, u.created_at, COALESCE(ucs.score, 100) AS credit_score FROM users u LEFT JOIN user_credit_stats ucs ON ucs.user_id=u.id WHERE u.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByIdOrUsername(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return $this->find((int)$value);
        }
        $stmt = Database::connection()->prepare(
            "SELECT u.id, u.public_id, u.public_id_style, u.username, u.nickname, u.email, u.avatar, u.role, u.status, IFNULL(u.email_verified, 0) AS email_verified, u.created_at, COALESCE(ucs.score, 100) AS credit_score FROM users u LEFT JOIN user_credit_stats ucs ON ucs.user_id=u.id WHERE u.username = :username OR u.public_id = :username LIMIT 1"
        );
        $stmt->execute([':username' => $value]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare("UPDATE users SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['public_id', 'public_id_style', 'username', 'nickname', 'email', 'role', 'status', 'email_verified', 'password'];
        $sets    = [];
        $params  = [':id' => $id];
        foreach ($data as $k => $v) {
            if (!in_array($k, $allowed, true)) continue;
            $sets[]       = "`{$k}` = :{$k}";
            $params[":{$k}"] = $v;
        }
        if (empty($sets)) return;
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id';
            $db->prepare($sql)->execute($params);
            if (array_key_exists('role', $data)) {
                $this->syncGlobalRole($id, (string)$data['role']);
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $stmt = Database::connection()->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function updateBan(int $id, ?string $bannedUntil): void
    {
        $status = $bannedUntil === null ? 'active' : 'banned';
        $stmt = Database::connection()->prepare(
            "UPDATE users SET status = :status, banned_until = :banned_until WHERE id = :id"
        );
        $stmt->execute([':status' => $status, ':banned_until' => $bannedUntil, ':id' => $id]);
    }

    public function existsByUsername(int $excludeId, string $username): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM users WHERE username = :username AND id != :id"
        );
        $stmt->execute([':username' => $username, ':id' => $excludeId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function existsByEmail(int $excludeId, string $email): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM users WHERE email = :email AND id != :id"
        );
        $stmt->execute([':email' => $email, ':id' => $excludeId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function existsByPublicId(int $excludeId, string $publicId): bool
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM users WHERE public_id = :public_id AND id != :id");
        $stmt->execute([':public_id' => $publicId, ':id' => $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function syncGlobalRole(int $userId, string $roleSlug): void
    {
        $db = Database::connection();
        $stmt = $db->prepare("SELECT id FROM roles WHERE slug=:slug LIMIT 1");
        $stmt->execute([':slug' => $roleSlug]);
        $roleId = (int)$stmt->fetchColumn();
        if ($userId <= 0 || $roleId <= 0) return;
        $db->prepare("DELETE FROM user_roles WHERE user_id=:uid AND scope='global'")->execute([':uid' => $userId]);
        $db->prepare("INSERT INTO user_roles (user_id,role_id,scope,scope_id,granted_by,expires_at,created_at) VALUES (:uid,:rid,'global',0,:granted_by,NULL,NOW())")
            ->execute([
                ':uid' => $userId,
                ':rid' => $roleId,
                ':granted_by' => (int)($_SESSION['auth_user']['id'] ?? 0) ?: null,
            ]);
    }

}
