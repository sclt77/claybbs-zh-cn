<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ThreadReadProgressModel
{
    public function ensureTable(): void
    {
        Database::connection()->exec("CREATE TABLE IF NOT EXISTS thread_read_progress (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            thread_id BIGINT UNSIGNED NOT NULL,
            progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
            last_post_id BIGINT UNSIGNED DEFAULT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            UNIQUE KEY uk_thread_read_user (user_id, thread_id),
            KEY idx_thread_read_user_updated (user_id, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function mark(int $userId, int $threadId, int $progress, ?int $lastPostId = null): void
    {
        if ($userId <= 0 || $threadId <= 0) return;
        $this->ensureTable();
        $progress = max(0, min(100, $progress));
        Database::connection()->prepare('INSERT INTO thread_read_progress (user_id,thread_id,progress,last_post_id,updated_at) VALUES (:uid,:tid,:progress,:pid,NOW()) ON DUPLICATE KEY UPDATE progress=GREATEST(progress,VALUES(progress)), last_post_id=VALUES(last_post_id), updated_at=NOW()')
            ->execute([':uid'=>$userId, ':tid'=>$threadId, ':progress'=>$progress, ':pid'=>$lastPostId]);
    }

    public function get(int $userId, int $threadId): ?array
    {
        if ($userId <= 0 || $threadId <= 0) return null;
        $this->ensureTable();
        $stmt = Database::connection()->prepare('SELECT * FROM thread_read_progress WHERE user_id=:uid AND thread_id=:tid LIMIT 1');
        $stmt->execute([':uid'=>$userId, ':tid'=>$threadId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
