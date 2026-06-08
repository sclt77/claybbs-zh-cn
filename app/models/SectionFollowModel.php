<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class SectionFollowModel
{
    public function ensureTable(): void
    {
        Database::connection()->exec("CREATE TABLE IF NOT EXISTS section_follows (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            section_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            UNIQUE KEY uniq_section_follow (user_id, section_id),
            KEY idx_section_follow_section (section_id),
            KEY idx_section_follow_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function toggle(int $userId, int $sectionId): bool
    {
        if ($userId <= 0 || $sectionId <= 0) return false;
        $this->ensureTable();
        if ($this->isFollowing($userId, $sectionId)) {
            Database::connection()->prepare('DELETE FROM section_follows WHERE user_id=:uid AND section_id=:sid')->execute([':uid'=>$userId, ':sid'=>$sectionId]);
            return false;
        }
        Database::connection()->prepare('INSERT IGNORE INTO section_follows (user_id, section_id, created_at) VALUES (:uid,:sid,NOW())')->execute([':uid'=>$userId, ':sid'=>$sectionId]);
        return true;
    }

    public function isFollowing(int $userId, int $sectionId): bool
    {
        if ($userId <= 0 || $sectionId <= 0) return false;
        $this->ensureTable();
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM section_follows WHERE user_id=:uid AND section_id=:sid');
        $stmt->execute([':uid'=>$userId, ':sid'=>$sectionId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function countBySection(int $sectionId): int
    {
        if ($sectionId <= 0) return 0;
        $this->ensureTable();
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM section_follows WHERE section_id=:sid');
        $stmt->execute([':sid'=>$sectionId]);
        return (int)$stmt->fetchColumn();
    }
}
