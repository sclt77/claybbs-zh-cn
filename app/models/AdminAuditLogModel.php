<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class AdminAuditLogModel
{
    public function ensureTable(): void
    {
        Database::connection()->exec("CREATE TABLE IF NOT EXISTS admin_audit_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_id BIGINT UNSIGNED DEFAULT NULL,
            admin_name VARCHAR(120) DEFAULT NULL,
            action VARCHAR(80) NOT NULL,
            target_type VARCHAR(50) DEFAULT NULL,
            target_id BIGINT UNSIGNED DEFAULT NULL,
            detail TEXT DEFAULT NULL,
            ip VARCHAR(80) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            KEY idx_admin_audit_action (action, created_at),
            KEY idx_admin_audit_target (target_type, target_id),
            KEY idx_admin_audit_admin (admin_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function record(string $action, string $targetType = '', int $targetId = 0, array|string $detail = ''): void
    {
        try {
            $this->ensureTable();
            $admin = $_SESSION['auth_user'] ?? [];
            $name = (string)($admin['nickname'] ?? $admin['username'] ?? $admin['email'] ?? '');
            $detailText = is_array($detail) ? json_encode($detail, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : (string)$detail;
            Database::connection()->prepare('INSERT INTO admin_audit_logs (admin_id,admin_name,action,target_type,target_id,detail,ip,created_at) VALUES (:admin_id,:admin_name,:action,:target_type,:target_id,:detail,:ip,NOW())')->execute([
                ':admin_id' => (int)($admin['id'] ?? 0) ?: null,
                ':admin_name' => $name !== '' ? $name : null,
                ':action' => mb_substr($action, 0, 80),
                ':target_type' => $targetType !== '' ? mb_substr($targetType, 0, 50) : null,
                ':target_id' => $targetId > 0 ? $targetId : null,
                ':detail' => $detailText !== '' ? mb_substr($detailText, 0, 20000) : null,
                ':ip' => mb_substr((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ''), 0, 80),
            ]);
        } catch (\Throwable $e) {}
    }


    public function latestForTarget(string $targetType, int $targetId, array $actions = [], int $limit = 8): array
    {
        $this->ensureTable();
        $where = 'WHERE target_type = :target_type AND target_id = :target_id';
        $params = [':target_type' => $targetType, ':target_id' => $targetId];
        if ($actions) {
            $placeholders = [];
            foreach (array_values($actions) as $idx => $action) {
                $key = ':action_' . $idx;
                $placeholders[] = $key;
                $params[$key] = $action;
            }
            $where .= ' AND action IN (' . implode(',', $placeholders) . ')';
        }
        $stmt = Database::connection()->prepare('SELECT * FROM admin_audit_logs ' . $where . ' ORDER BY id DESC LIMIT ' . max(1, min(50, $limit)));
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function list(string $q = '', int $limit = 100): array
    {
        $this->ensureTable();
        $where = '';
        $args = [];
        if ($q !== '') {
            $where = ' WHERE action LIKE :q OR admin_name LIKE :q OR target_type LIKE :q OR detail LIKE :q';
            $args[':q'] = '%' . $q . '%';
        }
        $stmt = Database::connection()->prepare('SELECT * FROM admin_audit_logs' . $where . ' ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)));
        $stmt->execute($args);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
