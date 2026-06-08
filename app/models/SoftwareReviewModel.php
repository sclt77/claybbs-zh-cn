<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class SoftwareReviewModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findBySoftware(int $softwareId, int $page = 1, int $perPage = 20): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $this->db->prepare('SELECT r.*, u.nickname, u.avatar FROM software_reviews r LEFT JOIN users u ON u.id=r.user_id WHERE r.software_id=:id AND r.status="active" AND r.parent_id IS NULL ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':id', $softwareId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findReplies(int $parentId): array
    {
        $stmt = $this->db->prepare('SELECT r.*, u.nickname, u.avatar FROM software_reviews r LEFT JOIN users u ON u.id=r.user_id WHERE r.parent_id=:pid AND r.status="active" ORDER BY r.created_at ASC');
        $stmt->execute([':pid' => $parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(int $softwareId, int $userId, string $content, ?int $parentId = null): int
    {
        $stmt = $this->db->prepare('INSERT INTO software_reviews (software_id, user_id, content, parent_id) VALUES (:sid, :uid, :content, :parent)');
        $stmt->execute([':sid' => $softwareId, ':uid' => $userId, ':content' => $content, ':parent' => $parentId]);
        return (int)$this->db->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->db->prepare('UPDATE software_reviews SET status="deleted" WHERE id=:id')->execute([':id' => $id]);
    }
}
