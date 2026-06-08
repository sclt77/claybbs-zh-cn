<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class SoftwareTypeModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function all(bool $activeOnly = true, ?string $selectableScope = null): array
    {
        $where = [];
        $params = [];
        if ($activeOnly) {
            $where[] = "status='active'";
        }
        if ($selectableScope !== null) {
            $where[] = 'selectable_scope=:scope';
            $params[':scope'] = $selectableScope;
        }
        $sql = 'SELECT * FROM software_types' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY sort_order ASC, id ASC';
        $stmt = $params ? $this->db->prepare($sql) : $this->db->query($sql);
        if ($params) { $stmt->execute($params); }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function userSelectable(bool $activeOnly = true): array
    {
        return $this->all($activeOnly, 'user');
    }

    public function adminSelectable(bool $activeOnly = true): array
    {
        return $this->all($activeOnly, null);
    }

    public function map(bool $activeOnly = true, ?string $selectableScope = null): array
    {
        $map = [];
        foreach ($this->all($activeOnly, $selectableScope) as $row) {
            $map[(string)$row['slug']] = $row;
        }
        return $map;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM software_types WHERE id=:id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO software_types (name, slug, color, sort_order, status, selectable_scope) VALUES (:name, :slug, :color, :sort_order, :status, :selectable_scope)');
        $stmt->execute([
            ':name' => $data['name'],
            ':slug' => $data['slug'],
            ':color' => $data['color'] ?? '#3cc9a4',
            ':sort_order' => $data['sort_order'] ?? 0,
            ':status' => $data['status'] ?? 'active',
            ':selectable_scope' => in_array(($data['selectable_scope'] ?? 'user'), ['user','admin'], true) ? $data['selectable_scope'] : 'user',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = [':id' => $id];
        foreach (['name','slug','color','sort_order','status','selectable_scope'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f=:$f";
                $params[":$f"] = $data[$f];
            }
        }
        if (!$fields) return;
        $sql = 'UPDATE software_types SET ' . implode(', ', $fields) . ', updated_at=NOW() WHERE id=:id';
        $this->db->prepare($sql)->execute($params);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM software_types WHERE id=:id')->execute([':id' => $id]);
    }
}
