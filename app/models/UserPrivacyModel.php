<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class UserPrivacyModel
{
    private array $defaults = [
        'disallow_follow' => 0,
        'hide_following' => 0,
        'hide_followers' => 0,
    ];

    public function ensureTable(): void
    {
        static $done = false;
        if ($done) return;
        $sql = "CREATE TABLE IF NOT EXISTS user_privacy_settings (
            user_id BIGINT UNSIGNED NOT NULL,
            disallow_follow TINYINT(1) NOT NULL DEFAULT 0,
            hide_following TINYINT(1) NOT NULL DEFAULT 0,
            hide_followers TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        Database::connection()->exec($sql);
        $done = true;
    }

    public function get(int $userId): array
    {
        if ($userId <= 0) return $this->defaults;
        $this->ensureTable();
        $stmt = Database::connection()->prepare('SELECT * FROM user_privacy_settings WHERE user_id=:uid LIMIT 1');
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return array_merge($this->defaults, array_map('intval', array_intersect_key($row, $this->defaults)));
    }

    public function update(int $userId, array $data): void
    {
        if ($userId <= 0) return;
        $this->ensureTable();
        $values = [
            ':uid' => $userId,
            ':disallow_follow' => !empty($data['disallow_follow']) ? 1 : 0,
            ':hide_following' => !empty($data['hide_following']) ? 1 : 0,
            ':hide_followers' => !empty($data['hide_followers']) ? 1 : 0,
        ];
        Database::connection()->prepare("INSERT INTO user_privacy_settings (user_id, disallow_follow, hide_following, hide_followers, created_at, updated_at)
            VALUES (:uid, :disallow_follow, :hide_following, :hide_followers, NOW(), NOW())
            ON DUPLICATE KEY UPDATE disallow_follow=VALUES(disallow_follow), hide_following=VALUES(hide_following), hide_followers=VALUES(hide_followers), updated_at=NOW()")
            ->execute($values);
    }

    public function canViewFollowing(int $profileUserId, int $viewerId = 0): bool
    {
        return $viewerId > 0 && $viewerId === $profileUserId ? true : empty($this->get($profileUserId)['hide_following']);
    }

    public function canViewFollowers(int $profileUserId, int $viewerId = 0): bool
    {
        return $viewerId > 0 && $viewerId === $profileUserId ? true : empty($this->get($profileUserId)['hide_followers']);
    }

    public function canFollow(int $profileUserId, int $viewerId = 0): bool
    {
        if ($viewerId > 0 && $viewerId === $profileUserId) return false;
        return empty($this->get($profileUserId)['disallow_follow']);
    }
}
