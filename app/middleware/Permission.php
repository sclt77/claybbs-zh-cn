<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use PDO;



class Permission
{
    
    private static array $cache = [];

    

    

    public static function can(string $perm, string $scope = 'global', ?int $scopeId = null): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = $_SESSION['auth_user'] ?? null;
        if (empty($user)) {
            return false;
        }

        $userId = (int) $user['id'];

        
        if (self::isSuperAdmin($userId, $user)) {
            return true;
        }

        return self::userHasPerm($userId, $perm, $scope, $scopeId);
    }

    

    public static function require(string $perm, string $scope = 'global', ?int $scopeId = null): void
    {
        if (!self::can($perm, $scope, $scopeId)) {
            self::abort403();
        }
    }

    

    public static function requireLogin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['auth_user'])) {
            header('Location: /index.php?path=login&redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
            exit;
        }
    }

    

    public static function getUserRoles(int $userId): array
    {
        $sql = "SELECT r.slug, r.name, r.level, ur.scope, ur.scope_id
                FROM user_roles ur
                JOIN roles r ON r.id = ur.role_id
                WHERE ur.user_id = :uid
                  AND (ur.expires_at IS NULL OR ur.expires_at > NOW())";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    

    public static function getMaxLevel(int $userId): int
    {
        $sql = "SELECT MAX(r.level)
                FROM user_roles ur
                JOIN roles r ON r.id = ur.role_id
                WHERE ur.user_id = :uid
                  AND (ur.expires_at IS NULL OR ur.expires_at > NOW())";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    

    public static function canAnyScope(string $perm): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userId = (int)($_SESSION['auth_user']['id'] ?? 0);
        if ($userId <= 0) return false;
        if (self::isSuperAdmin($userId, $_SESSION['auth_user'] ?? null)) return true;
        $sql = "SELECT COUNT(*) FROM user_roles ur
                JOIN roles r ON r.id = ur.role_id
                JOIN role_permissions rp ON rp.role_id = r.id
                JOIN permissions p ON p.id = rp.permission_id
                WHERE ur.user_id = :uid
                  AND p.slug = :perm
                  AND (ur.expires_at IS NULL OR ur.expires_at > NOW())";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([':uid' => $userId, ':perm' => $perm]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public static function accessibleSectionIds(int $userId, string $perm): ?array
    {
        if ($userId <= 0) return [];
        if (self::isSuperAdmin($userId, $_SESSION['auth_user'] ?? null)) return null;

        $roles = self::getUserRoles($userId);
        $hasGlobal = false;
        $sectionIds = [];
        $categoryIds = [];
        foreach ($roles as $role) {
            if (!self::roleHasPerm((string)$role['slug'], $perm)) continue;
            $scope = (string)($role['scope'] ?? 'global');
            if ($scope === 'global') {
                $hasGlobal = true;
                break;
            }
            if ($scope === 'section' && !empty($role['scope_id'])) {
                $sectionIds[] = (int)$role['scope_id'];
            } elseif ($scope === 'category' && !empty($role['scope_id'])) {
                $categoryIds[] = (int)$role['scope_id'];
            }
        }
        if ($hasGlobal) return null;
        if ($categoryIds) {
            $placeholders = [];
            $params = [];
            foreach (array_values(array_unique($categoryIds)) as $index => $cid) {
                $ph = ':cid_' . $index;
                $placeholders[] = $ph;
                $params[$ph] = $cid;
            }
            $stmt = Database::connection()->prepare("SELECT id FROM sections WHERE category_id IN (" . implode(',', $placeholders) . ")");
            $stmt->execute($params);
            $sectionIds = array_merge($sectionIds, array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
        }
        return array_values(array_unique($sectionIds));
    }

    public static function roleHasPerm(string $roleSlug, string $perm): bool
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM roles r JOIN role_permissions rp ON rp.role_id=r.id JOIN permissions p ON p.id=rp.permission_id WHERE r.slug=:role AND p.slug=:perm");
        $stmt->execute([':role' => $roleSlug, ':perm' => $perm]);
        return (int)$stmt->fetchColumn() > 0;
    }

    

    public static function assignRole(
        int $userId,
        string $roleSlug,
        string $scope = 'global',
        ?int $scopeId = null,
        ?int $grantedBy = null,
        ?string $expiresAt = null
    ): void {
        $roleId = self::getRoleIdBySlug($roleSlug);
        if (!$roleId) {
            throw new \InvalidArgumentException("角色不存在：{$roleSlug}");
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

        
        unset(self::$cache[$userId]);
    }

    

    public static function revokeRole(int $userId, string $roleSlug, string $scope = 'global', ?int $scopeId = null): void
    {
        $roleId = self::getRoleIdBySlug($roleSlug);
        if (!$roleId) return;

        $dbScopeId = $scopeId ?? 0;
        $sql = "DELETE FROM user_roles WHERE user_id=:uid AND role_id=:rid AND scope=:scope AND scope_id=:scope_id";
        Database::connection()->prepare($sql)->execute([
            ':uid'      => $userId,
            ':rid'      => $roleId,
            ':scope'    => $scope,
            ':scope_id' => $dbScopeId,
        ]);

        unset(self::$cache[$userId]);
    }

    

    private static function isSuperAdmin(int $userId, ?array $sessionUser = null): bool
    {
        if (($sessionUser['role'] ?? '') === 'superadmin') {
            return true;
        }
        $stmtUser = Database::connection()->prepare("SELECT role FROM users WHERE id = :uid LIMIT 1");
        $stmtUser->execute([':uid' => $userId]);
        if ((string)$stmtUser->fetchColumn() === 'superadmin') {
            return true;
        }
        $sql = "SELECT 1 FROM user_roles ur
                JOIN roles r ON r.id = ur.role_id
                WHERE ur.user_id = :uid AND r.slug = 'superadmin'
                  AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
                LIMIT 1";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        return (bool) $stmt->fetchColumn();
    }

    private static function userHasPerm(int $userId, string $perm, string $scope, ?int $scopeId): bool
    {
        $cacheKey = "{$perm}:{$scope}:" . ($scopeId ?? 0);
        if (isset(self::$cache[$userId][$cacheKey])) {
            return self::$cache[$userId][$cacheKey];
        }

        $dbScopeId = $scopeId ?? 0;

        
        $sql = "SELECT COUNT(*) FROM user_roles ur
                JOIN roles r ON r.id = ur.role_id
                JOIN role_permissions rp ON rp.role_id = r.id
                JOIN permissions p ON p.id = rp.permission_id
                WHERE ur.user_id = :uid
                  AND p.slug = :perm
                  AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
                  AND (
                    (:scope = 'global' AND ur.scope = 'global')
                    OR (:scope <> 'global' AND (
                        ur.scope = 'global'
                        OR (ur.scope = :scope AND ur.scope_id = :scope_id)
                    ))
                  )";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            ':uid'      => $userId,
            ':perm'     => $perm,
            ':scope'    => $scope,
            ':scope_id' => $dbScopeId,
        ]);

        $result = (int) $stmt->fetchColumn() > 0;
        self::$cache[$userId][$cacheKey] = $result;
        return $result;
    }

    private static function getRoleIdBySlug(string $slug): ?int
    {
        $stmt = Database::connection()->prepare("SELECT id FROM roles WHERE slug = :slug LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    private static function abort403(): void
    {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="zh"><head><meta charset="UTF-8"><title>403</title>'
            . '<style>body{font-family:sans-serif;text-align:center;padding:80px;background:#f5f5f5;}'
            . '.box{display:inline-block;background:#fff;padding:48px 64px;border-radius:12px;box-shadow:0 2px 16px rgba(0,0,0,.08);}'
            . 'h1{color:#e53935;font-size:48px;margin:0 0 8px}p{color:#555}</style>'
            . '</head><body><div class="box">'
            . '<h1>403</h1><p>您没有执行此操作的权限</p>'
            . '<p><a href="javascript:history.back()">返回</a></p>'
            . '</div></body></html>';
        exit;
    }
}
