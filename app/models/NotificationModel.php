<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class NotificationModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function create(int $userId, string $type, string $title, string $content): int
    {
        $stmt = $this->db->prepare('INSERT INTO notifications (user_id, type, title, content) VALUES (:uid, :type, :title, :content)');
        $stmt->execute([':uid' => $userId, ':type' => $type, ':title' => $title, ':content' => $content]);
        return (int)$this->db->lastInsertId();
    }

    public function findByUser(int $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare('SELECT * FROM notifications WHERE user_id=:uid ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markRead(int $id): void
    {
        $this->db->prepare('UPDATE notifications SET is_read=1 WHERE id=:id')->execute([':id' => $id]);
    }

    public function unreadCount(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=:uid AND is_read=0');
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn();
    }
}
