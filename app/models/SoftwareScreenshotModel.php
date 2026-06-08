<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class SoftwareScreenshotModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findBySoftware(int $softwareId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM software_screenshots WHERE software_id=:id ORDER BY sort_order ASC, id ASC');
        $stmt->execute([':id' => $softwareId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(int $softwareId, string $imagePath, int $sortOrder = 0): int
    {
        $stmt = $this->db->prepare('INSERT INTO software_screenshots (software_id, image_path, sort_order) VALUES (:sid, :path, :sort)');
        $stmt->execute([':sid' => $softwareId, ':path' => $imagePath, ':sort' => $sortOrder]);
        return (int)$this->db->lastInsertId();
    }

    public function deleteBySoftware(int $softwareId): void
    {
        $this->db->prepare('DELETE FROM software_screenshots WHERE software_id=:id')->execute([':id' => $softwareId]);
    }
}
