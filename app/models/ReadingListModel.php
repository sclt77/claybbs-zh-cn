<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ReadingListModel
{
    public function ensureSchema(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        Database::connection()->exec("CREATE TABLE IF NOT EXISTS user_reading_list (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            thread_id BIGINT UNSIGNED NOT NULL,
            list_type VARCHAR(20) NOT NULL DEFAULT 'later',
            note VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_reading_user_thread_type (user_id, thread_id, list_type),
            KEY idx_reading_user_type (user_id, list_type, updated_at),
            KEY idx_reading_thread (thread_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function isSaved(int $userId, int $threadId, string $type = 'later'): bool
    {
        if ($userId <= 0 || $threadId <= 0) return false;
        $this->ensureSchema();
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM user_reading_list WHERE user_id=:uid AND thread_id=:tid AND list_type=:type");
        $stmt->execute([':uid'=>$userId, ':tid'=>$threadId, ':type'=>$this->normalizeType($type)]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function toggle(int $userId, int $threadId, string $type = 'later'): bool
    {
        if ($userId <= 0 || $threadId <= 0) return false;
        $this->ensureSchema();
        $type = $this->normalizeType($type);
        if ($this->isSaved($userId, $threadId, $type)) {
            Database::connection()->prepare("DELETE FROM user_reading_list WHERE user_id=:uid AND thread_id=:tid AND list_type=:type")
                ->execute([':uid'=>$userId, ':tid'=>$threadId, ':type'=>$type]);
            return false;
        }
        Database::connection()->prepare("INSERT IGNORE INTO user_reading_list (user_id,thread_id,list_type,created_at,updated_at) VALUES (:uid,:tid,:type,NOW(),NOW())")
            ->execute([':uid'=>$userId, ':tid'=>$threadId, ':type'=>$type]);
        return true;
    }

    public function listByUser(int $userId, string $type, int $limit, int $offset): array
    {
        $this->ensureSchema();
        $type = $this->normalizeType($type);
        $stmt = Database::connection()->prepare("SELECT t.*, t.section_id AS section_id, u.nickname AS author_name, u.avatar AS author_avatar, COALESCE(NULLIF(uv.display_name,''), vt.name) AS verification_name, vt.color AS verification_color, vt.description AS verification_description, s.name AS section_name, rl.created_at AS saved_at, rl.note AS reading_note, (SELECT r.name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=t.user_id AND (ur.expires_at IS NULL OR ur.expires_at > NOW()) ORDER BY r.level DESC LIMIT 1) AS author_role_label FROM user_reading_list rl JOIN threads t ON t.id=rl.thread_id LEFT JOIN users u ON u.id=t.user_id LEFT JOIN user_verifications uv ON uv.user_id=t.user_id AND uv.status='active' LEFT JOIN verification_types vt ON vt.id=uv.type_id AND vt.status='active' LEFT JOIN sections s ON s.id=t.section_id WHERE rl.user_id=:uid AND rl.list_type=:type AND t.status='published' ORDER BY rl.updated_at DESC, rl.id DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByUser(int $userId, string $type): int
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM user_reading_list rl JOIN threads t ON t.id=rl.thread_id WHERE rl.user_id=:uid AND rl.list_type=:type AND t.status='published'");
        $stmt->execute([':uid'=>$userId, ':type'=>$this->normalizeType($type)]);
        return (int)$stmt->fetchColumn();
    }

    private function normalizeType(string $type): string
    {
        return in_array($type, ['later'], true) ? $type : 'later';
    }
}
