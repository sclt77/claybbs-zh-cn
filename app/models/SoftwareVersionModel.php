<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class SoftwareVersionModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT v.*, s.name AS software_name, s.slug AS software_slug, s.version AS current_version, u.nickname AS uploader_nickname FROM software_versions v LEFT JOIN softwares s ON s.id=v.software_id LEFT JOIN users u ON u.id=v.uploader_id WHERE v.id=:id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function createSubmission(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO software_versions (software_id,uploader_id,version,size,download_url,file_path,changelog,icon,status) VALUES (:software_id,:uploader_id,:version,:size,:download_url,:file_path,:changelog,:icon,:status)');
        $stmt->execute([
            ':software_id' => (int)$data['software_id'],
            ':uploader_id' => (int)$data['uploader_id'],
            ':version' => (string)$data['version'],
            ':size' => $data['size'] ?? null,
            ':download_url' => (string)$data['download_url'],
            ':file_path' => $data['file_path'] ?? null,
            ':changelog' => $data['changelog'] ?? null,
            ':icon' => $data['icon'] ?? null,
            ':status' => $data['status'] ?? 'pending',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function pending(int $page = 1, int $perPage = 50): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $this->db->prepare('SELECT v.*, s.name AS software_name, s.slug AS software_slug, s.version AS current_version, u.nickname AS uploader_nickname FROM software_versions v LEFT JOIN softwares s ON s.id=v.software_id LEFT JOIN users u ON u.id=v.uploader_id WHERE v.status=:status ORDER BY v.created_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':status', 'pending');
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countPending(): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM software_versions WHERE status=:status');
        $stmt->execute([':status' => 'pending']);
        return (int)$stmt->fetchColumn();
    }

    public function history(int $softwareId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM software_versions WHERE software_id=:software_id AND status=:status ORDER BY COALESCE(published_at, created_at) DESC, id DESC');
        $stmt->execute([':software_id' => $softwareId, ':status' => 'published']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function byUploader(int $uploaderId, int $limit = 100): array
    {
        $stmt = $this->db->prepare('SELECT v.*, s.name AS software_name, s.slug AS software_slug, s.version AS current_version FROM software_versions v LEFT JOIN softwares s ON s.id=v.software_id WHERE v.uploader_id=:uploader_id ORDER BY v.created_at DESC LIMIT :limit');
        $stmt->bindValue(':uploader_id', $uploaderId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function approve(int $id, int $reviewerId, string $note = ''): void
    {
        $stmt = $this->db->prepare('UPDATE software_versions SET status=:status, admin_note=:note, reviewed_by=:reviewed_by, reviewed_at=NOW(), published_at=NOW(), updated_at=NOW() WHERE id=:id');
        $stmt->execute([':status' => 'published', ':note' => $note, ':reviewed_by' => $reviewerId, ':id' => $id]);
    }

    public function reject(int $id, int $reviewerId, string $note = ''): void
    {
        $stmt = $this->db->prepare('UPDATE software_versions SET status=:status, admin_note=:note, reviewed_by=:reviewed_by, reviewed_at=NOW(), updated_at=NOW() WHERE id=:id');
        $stmt->execute([':status' => 'rejected', ':note' => $note, ':reviewed_by' => $reviewerId, ':id' => $id]);
    }
}
