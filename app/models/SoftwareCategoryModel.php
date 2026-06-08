<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class SoftwareCategoryModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function all(): array
    {
        $stmt = $this->db->query("SELECT * FROM software_categories WHERE status='active' ORDER BY sort_order ASC, id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM software_categories WHERE id=:id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO software_categories (name, slug, icon, sort_order) VALUES (:name, :slug, :icon, :sort_order)');
        $stmt->execute([
            ':name' => $data['name'],
            ':slug' => $data['slug'],
            ':icon' => $data['icon'] ?? null,
            ':sort_order' => $data['sort_order'] ?? 0,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = [':id' => $id];
        foreach (['name','slug','icon','sort_order','status'] as $f) {
            if (isset($data[$f])) { $fields[] = "$f=:$f"; $params[":$f"] = $data[$f]; }
        }
        if (empty($fields)) return;
        $sql = 'UPDATE software_categories SET ' . implode(', ', $fields) . ', updated_at=NOW() WHERE id=:id';
        $this->db->prepare($sql)->execute($params);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM software_categories WHERE id=:id')->execute([':id' => $id]);
    }
}
