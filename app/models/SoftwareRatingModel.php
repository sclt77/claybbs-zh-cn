<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class SoftwareRatingModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function find(int $softwareId, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM software_ratings WHERE software_id=:sid AND user_id=:uid LIMIT 1');
        $stmt->execute([':sid' => $softwareId, ':uid' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function rate(int $softwareId, int $userId, int $rating): void
    {
        $existing = $this->find($softwareId, $userId);
        if ($existing) {
            $this->db->prepare('UPDATE software_ratings SET rating=:rating, updated_at=NOW() WHERE id=:id')->execute([':rating' => $rating, ':id' => $existing['id']]);
        } else {
            $this->db->prepare('INSERT INTO software_ratings (software_id, user_id, rating) VALUES (:sid, :uid, :rating)')->execute([':sid' => $softwareId, ':uid' => $userId, ':rating' => $rating]);
        }
    }

    public function avgBySoftware(int $softwareId): float
    {
        $stmt = $this->db->prepare('SELECT AVG(rating) FROM software_ratings WHERE software_id=:id');
        $stmt->execute([':id' => $softwareId]);
        return (float)($stmt->fetchColumn() ?: 0);
    }

    public function countBySoftware(int $softwareId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM software_ratings WHERE software_id=:id');
        $stmt->execute([':id' => $softwareId]);
        return (int)$stmt->fetchColumn();
    }
}
