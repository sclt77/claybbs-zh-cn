<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class RecycleBinModel
{
    public function ensureTable(): void
    {
        Database::connection()->exec("CREATE TABLE IF NOT EXISTS recycle_bin (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            target_type VARCHAR(30) NOT NULL,
            target_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) DEFAULT NULL,
            snapshot MEDIUMTEXT DEFAULT NULL,
            deleted_by BIGINT UNSIGNED DEFAULT NULL,
            deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            restored_at DATETIME DEFAULT NULL,
            purged_at DATETIME DEFAULT NULL,
            PRIMARY KEY(id),
            KEY idx_recycle_target (target_type, target_id),
            KEY idx_recycle_deleted (deleted_at),
            KEY idx_recycle_state (restored_at, purged_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function add(string $type, int $id, string $title, array $snapshot): void
    {
        $this->ensureTable();
        Database::connection()->prepare('INSERT INTO recycle_bin (target_type,target_id,title,snapshot,deleted_by,deleted_at) VALUES (:type,:id,:title,:snapshot,:deleted_by,NOW())')->execute([
            ':type'=>$type, ':id'=>$id, ':title'=>mb_substr($title,0,255), ':snapshot'=>json_encode($snapshot, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), ':deleted_by'=>(int)($_SESSION['auth_user']['id'] ?? 0) ?: null,
        ]);
    }

    public function list(string $type = ''): array
    {
        $this->ensureTable();
        $where = ' WHERE restored_at IS NULL AND purged_at IS NULL';
        $args = [];
        if ($type !== '') { $where .= ' AND target_type=:type'; $args[':type']=$type; }
        $stmt = Database::connection()->prepare('SELECT r.*, u.nickname, u.username FROM recycle_bin r LEFT JOIN users u ON u.id=r.deleted_by' . $where . ' ORDER BY r.id DESC LIMIT 300');
        $stmt->execute($args);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $id): ?array
    {
        $this->ensureTable();
        $stmt = Database::connection()->prepare('SELECT * FROM recycle_bin WHERE id=:id LIMIT 1');
        $stmt->execute([':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function markRestored(int $id): void
    {
        Database::connection()->prepare('UPDATE recycle_bin SET restored_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
    }

    public function markPurged(int $id): void
    {
        Database::connection()->prepare('UPDATE recycle_bin SET purged_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
    }
}
