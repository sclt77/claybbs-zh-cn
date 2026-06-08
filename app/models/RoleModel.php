<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class RoleModel
{
    

    public function all(): array
    {
        return Database::connection()
            ->query("SELECT * FROM roles ORDER BY level DESC")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    

    public function byUserId(int $userId): array
    {
        $sql = "SELECT ur.id AS id, ur.id AS user_role_id,
                       r.id AS role_id, r.slug, r.name, r.level,
                       ur.scope, ur.scope_id, ur.expires_at, ur.created_at,
                       gb.nickname AS granted_by_name
                FROM user_roles ur
                JOIN roles r ON r.id = ur.role_id
                LEFT JOIN users gb ON gb.id = ur.granted_by
                WHERE ur.user_id = :uid
                ORDER BY r.level DESC";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    

    public function assign(int $userId, int $roleId, string $scope = 'global', ?int $scopeId = null, ?int $grantedBy = null, ?string $expiresAt = null): void
    {
        if ($scope === 'global') {
            Database::connection()->prepare("DELETE FROM user_roles WHERE user_id = :uid AND scope = 'global'")
                ->execute([':uid' => $userId]);
        }

        $dbScopeId = $scopeId ?? 0;
        $sql = "INSERT INTO user_roles (user_id, role_id, scope, scope_id, granted_by, expires_at)
                VALUES (:uid, :rid, :scope, :scope_id, :granted_by, :expires_at)
                ON DUPLICATE KEY UPDATE granted_by=VALUES(granted_by), expires_at=VALUES(expires_at)";
        Database::connection()->prepare($sql)->execute([
            ':uid'        => $userId,
            ':rid'        => $roleId,
            ':scope'      => $scope,
            ':scope_id'   => $dbScopeId,
            ':granted_by' => $grantedBy,
            ':expires_at' => $expiresAt,
        ]);

        if ($scope === 'global') {
            $roleStmt = Database::connection()->prepare("SELECT slug FROM roles WHERE id = :id LIMIT 1");
            $roleStmt->execute([':id' => $roleId]);
            $slug = $roleStmt->fetchColumn();
            $role = $slug !== false && $slug !== '' ? (string) $slug : 'user';
            Database::connection()->prepare("UPDATE users SET role = :role WHERE id = :id")
                ->execute([':role' => $role, ':id' => $userId]);
        }
    }

    

    public function revoke(int $userRoleId): void
    {
        $stmt = Database::connection()->prepare("SELECT user_id, scope FROM user_roles WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userRoleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $del = Database::connection()->prepare("DELETE FROM user_roles WHERE id = :id");
        $del->execute([':id' => $userRoleId]);

        if ($row && ($row['scope'] ?? '') === 'global') {
            $this->syncUserPrimaryRole((int) $row['user_id']);
        }
    }

    

    public function revokeScoped(int $userRoleId, string $scope, ?int $scopeId = null): void
    {
        if ($userRoleId <= 0 || !in_array($scope, ['global', 'category', 'section'], true)) {
            throw new \InvalidArgumentException('无效角色绑定');
        }

        $sql = "SELECT user_id, scope, scope_id FROM user_roles WHERE id = :id AND scope = :scope";
        $params = [':id' => $userRoleId, ':scope' => $scope];
        $dbScopeId = $scopeId ?? 0;
        $sql .= " AND scope_id = :scope_id";
        $params[':scope_id'] = $dbScopeId;
        $sql .= " LIMIT 1";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$row) {
            throw new \RuntimeException('未找到对应作用域的角色绑定，已阻止误删');
        }

        Database::connection()->prepare("DELETE FROM user_roles WHERE id = :id")
            ->execute([':id' => $userRoleId]);

        if (($row['scope'] ?? '') === 'global') {
            $this->syncUserPrimaryRole((int) $row['user_id']);
        }
    }

    

    public function allPermissions(): array
    {
        return Database::connection()
            ->query("SELECT * FROM permissions ORDER BY group_name, slug")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function allRolePermissions(): array
    {
        $rows = Database::connection()->query("SELECT role_id, permission_id FROM role_permissions")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['role_id']][] = (int) $row['permission_id'];
        }
        return $map;
    }

    public function saveRolePermissions(int $roleId, array $permissionIds): void
    {
        if ($roleId <= 0) {
            throw new \InvalidArgumentException('无效角色');
        }
        $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));
        $db = Database::connection();
        $db->prepare("DELETE FROM role_permissions WHERE role_id = :role_id")->execute([':role_id' => $roleId]);
        if (!$permissionIds) {
            return;
        }
        $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)");
        foreach ($permissionIds as $permissionId) {
            if ($permissionId > 0) {
                $stmt->execute([':role_id' => $roleId, ':permission_id' => $permissionId]);
            }
        }
    }

    private function syncUserPrimaryRole(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            "SELECT r.slug
             FROM user_roles ur
             JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :uid AND ur.scope = 'global'
             ORDER BY r.level DESC
             LIMIT 1"
        );
        $stmt->execute([':uid' => $userId]);
        $slug = $stmt->fetchColumn();
        $role = $slug !== false && $slug !== '' ? (string) $slug : 'user';

        $update = Database::connection()->prepare("UPDATE users SET role = :role WHERE id = :id");
        $update->execute([':role' => $role, ':id' => $userId]);
    }
}
