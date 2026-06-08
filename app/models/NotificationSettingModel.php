<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class NotificationSettingModel
{
    public function get(int $userId): array
    {
        $this->ensure($userId);
        $stmt = Database::connection()->prepare("SELECT * FROM user_notification_settings WHERE user_id = :id LIMIT 1");
        $stmt->execute([':id'=>$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['user_id'=>$userId,'follow_post'=>1,'reply'=>1,'mention'=>1,'fans'=>1,'like'=>1,'favorite'=>1,'private_chat'=>1,'review'=>1];
    }

    public function ensure(int $userId): void
    {
        if ($userId <= 0) return;
        Database::connection()->prepare("INSERT IGNORE INTO user_notification_settings (user_id, created_at, updated_at) VALUES (:id, NOW(), NOW())")->execute([':id'=>$userId]);
    }

    public function update(int $userId, array $data): void
    {
        $this->ensure($userId);
        Database::connection()->prepare("UPDATE user_notification_settings SET follow_post=:follow_post, reply=:reply, mention=:mention, fans=:fans, `like`=:like, favorite=:favorite, private_chat=:private_chat, review_notice=:review_notice, updated_at=NOW() WHERE user_id=:id")
            ->execute([
                ':follow_post'=>!empty($data['follow_post'])?1:0,
                ':reply'=>!empty($data['reply'])?1:0,
                ':mention'=>!empty($data['mention'])?1:0,
                ':fans'=>!empty($data['fans'])?1:0,
                ':like'=>!empty($data['like'])?1:0,
                ':favorite'=>!empty($data['favorite'])?1:0,
                ':private_chat'=>!empty($data['private_chat'])?1:0,
                ':review_notice'=>!empty($data['review'])?1:0,
                ':id'=>$userId,
            ]);
    }

    public function enabled(int $userId, string $key): bool
    {
        if (in_array($key, ['finance','system'], true)) return true;
        $aliases = ['private' => 'private_chat', 'private_chat' => 'private_chat', 'review' => 'review_notice'];
        $key = $aliases[$key] ?? $key;
        $settings = $this->get($userId);
        return !empty($settings[$key]);
    }
}
