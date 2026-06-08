<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;



class SoftwareModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT s.*, c.name AS category_name, c.slug AS category_slug, u.nickname AS uploader_nickname, u.avatar AS uploader_avatar, u.status AS uploader_status FROM softwares s LEFT JOIN software_categories c ON c.id=s.category_id LEFT JOIN users u ON u.id=s.uploader_id WHERE s.id=:id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT s.*, c.name AS category_name, c.slug AS category_slug, u.nickname AS uploader_nickname, u.avatar AS uploader_avatar, u.status AS uploader_status FROM softwares s LEFT JOIN software_categories c ON c.id=s.category_id LEFT JOIN users u ON u.id=s.uploader_id WHERE s.slug=:slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO softwares (name, slug, icon, description, detail, platform, category_id, uploader_id, developer, version, size, download_url, file_path, type, status) ' .
            'VALUES (:name, :slug, :icon, :description, :detail, :platform, :category_id, :uploader_id, :developer, :version, :size, :download_url, :file_path, :type, :status)'
        );
        $stmt->execute([
            ':name'         => $data['name'],
            ':slug'         => $data['slug'],
            ':icon'         => $data['icon'] ?? null,
            ':description'  => $data['description'] ?? null,
            ':detail'       => $data['detail'] ?? null,
            ':platform'     => $data['platform'],
            ':category_id'  => $data['category_id'] ?? null,
            ':uploader_id'  => $data['uploader_id'],
            ':developer'    => $data['developer'] ?? null,
            ':version'      => $data['version'] ?? '1.0.0',
            ':size'         => $data['size'] ?? null,
            ':download_url' => $data['download_url'],
            ':file_path'    => $data['file_path'] ?? null,
            ':type'         => $data['type'] ?? '',
            ':status'       => $data['status'] ?? 'draft',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = [':id' => $id];
        foreach ($data as $key => $val) {
            if (in_array($key, ['name','slug','icon','description','detail','platform','category_id','developer','version','size','download_url','file_path','status','admin_note','type','is_recommended','recommended_at'], true)) {
                $fields[] = "$key=:$key";
                $params[":$key"] = $val;
            }
        }
        if (empty($fields)) return;
        $sql = 'UPDATE softwares SET ' . implode(', ', $fields) . ', updated_at=NOW() WHERE id=:id';
        $this->db->prepare($sql)->execute($params);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM softwares WHERE id=:id')->execute([':id' => $id]);
    }

    

    public function all(array $filters = [], int $page = 1, int $perPage = 20, string $orderBy = 's.created_at DESC'): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 's.status=:status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['platform'])) {
            $where[] = 's.platform=:platform';
            $params[':platform'] = $filters['platform'];
        }
        if (!empty($filters['category_id'])) {
            $where[] = 's.category_id=:category_id';
            $params[':category_id'] = $filters['category_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(s.name LIKE :q OR s.description LIKE :q OR s.developer LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['uploader_id'])) {
            $where[] = 's.uploader_id=:uploader_id';
            $params[':uploader_id'] = $filters['uploader_id'];
        }
        if (array_key_exists('is_recommended', $filters)) {
            $where[] = 's.is_recommended=:is_recommended';
            $params[':is_recommended'] = (int)$filters['is_recommended'];
        }

        $offset = max(0, ($page - 1) * $perPage);
        $whereClause = empty($where) ? '1=1' : implode(' AND ', $where);
        $sql = 'SELECT s.*, c.name AS category_name, c.slug AS category_slug FROM softwares s LEFT JOIN software_categories c ON c.id=s.category_id WHERE ' . $whereClause . ' ORDER BY ' . $orderBy . ' LIMIT :limit OFFSET :offset';
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function count(array $filters = []): int
    {
        $where = [];
        $params = [];
        if (!empty($filters['status'])) { $where[] = 'status=:status'; $params[':status'] = $filters['status']; }
        if (!empty($filters['platform'])) { $where[] = 'platform=:platform'; $params[':platform'] = $filters['platform']; }
        if (!empty($filters['category_id'])) { $where[] = 'category_id=:category_id'; $params[':category_id'] = $filters['category_id']; }
        if (!empty($filters['q'])) { $where[] = '(name LIKE :q OR description LIKE :q OR developer LIKE :q)'; $params[':q'] = '%' . $filters['q'] . '%'; }
        if (!empty($filters['uploader_id'])) { $where[] = 'uploader_id=:uploader_id'; $params[':uploader_id'] = $filters['uploader_id']; }
        if (array_key_exists('is_recommended', $filters)) { $where[] = 'is_recommended=:is_recommended'; $params[':is_recommended'] = (int)$filters['is_recommended']; }
        $whereClause = empty($where) ? '1=1' : implode(' AND ', $where);
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM softwares WHERE ' . $whereClause);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function countRecommended(): int
    {
        return $this->count(['status' => 'published', 'is_recommended' => 1]);
    }

    public function setRecommended(int $id, bool $recommended): void
    {
        $this->update($id, [
            'is_recommended' => $recommended ? 1 : 0,
            'recommended_at' => $recommended ? date('Y-m-d H:i:s') : null,
        ]);
    }

    public function totalDownloads(): int
    {
        return (int)$this->db->query('SELECT COALESCE(SUM(download_count),0) FROM softwares')->fetchColumn();
    }

    

    public function incrementDownload(int $id): void
    {
        $this->db->prepare('UPDATE softwares SET download_count=download_count+1 WHERE id=:id')->execute([':id' => $id]);
    }

    

    public function recalcRating(int $id): void
    {
        $stmt = $this->db->prepare('SELECT AVG(rating) AS avg_rating, COUNT(*) AS cnt FROM software_ratings WHERE software_id=:id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->db->prepare('UPDATE softwares SET rating_avg=:avg, rating_count=:cnt WHERE id=:id')->execute([
            ':avg' => round((float)($row['avg_rating'] ?? 0), 1),
            ':cnt' => (int)($row['cnt'] ?? 0),
            ':id'  => $id,
        ]);
    }
}
