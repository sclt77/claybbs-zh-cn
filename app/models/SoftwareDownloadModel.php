<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class SoftwareDownloadModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function record(int $softwareId, ?int $userId, string $ip, string $userAgent): bool
    {
        try {
            $stmt = $this->db->prepare('INSERT INTO software_downloads (software_id, user_id, ip, user_agent) VALUES (:sid, :uid, :ip, :ua)');
            $stmt->execute([':sid' => $softwareId, ':uid' => $userId, ':ip' => $ip, ':ua' => $userAgent]);
            return true;
        } catch (\PDOException $e) {
            
            return false;
        }
    }

    public function countBySoftware(int $softwareId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM software_downloads WHERE software_id=:id');
        $stmt->execute([':id' => $softwareId]);
        return (int)$stmt->fetchColumn();
    }

    public function hasDownloaded(int $softwareId, string $ip): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM software_downloads WHERE software_id=:id AND ip=:ip');
        $stmt->execute([':id' => $softwareId, ':ip' => $ip]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
