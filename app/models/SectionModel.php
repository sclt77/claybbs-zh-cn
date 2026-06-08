<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class SectionModel
{
    public function list(): array
    {
        $stmt = Database::connection()->query(
            "SELECT s.id, s.name, s.slug, s.icon, s.post_permission, s.is_question, s.description, s.sort_order,
                    c.name AS category_name,
                    c.sort_order AS category_sort_order,
                    COUNT(t.id) AS thread_count
             FROM sections s
             LEFT JOIN categories c ON c.id = s.category_id
             LEFT JOIN threads t ON t.section_id = s.id AND t.status = 'published'
             WHERE s.status = 'active' AND c.status = 'active'
             GROUP BY s.id
             ORDER BY c.sort_order ASC, c.id ASC, s.sort_order ASC, s.id ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function hot(int $limit = 4): array
    {
        $sql = "SELECT s.id, s.name, s.slug, s.icon, s.post_permission, s.is_question, s.description, s.sort_order,
                       c.name AS category_name,
                       COUNT(t.id) AS thread_count,
                       MAX(t.created_at) AS last_thread_at,
                       (
                         COUNT(t.id) * 10
                         + SUM(CASE WHEN t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 20 ELSE 0 END)
                         + SUM(CASE WHEN t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 5 ELSE 0 END)
                       ) AS hot_score
                FROM sections s
                LEFT JOIN categories c ON c.id = s.category_id
                LEFT JOIN threads t ON t.section_id = s.id AND t.status = 'published'
                WHERE s.status = 'active' AND c.status = 'active'
                GROUP BY s.id
                ORDER BY hot_score DESC, last_thread_at DESC, s.thread_count DESC, s.sort_order ASC, s.id ASC
                LIMIT :limit";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function moderators(int $sectionId): array
    {
        $sql = "SELECT u.id, u.nickname, u.username, u.avatar, r.slug AS role_slug, r.name AS role_name, r.level
                FROM user_roles ur
                JOIN roles r ON r.id = ur.role_id
                JOIN users u ON u.id = ur.user_id
                WHERE (ur.expires_at IS NULL OR ur.expires_at > NOW())
                  AND ur.scope = 'section'
                  AND ur.scope_id = :section_id
                  AND r.slug IN ('moderator','reviewer','admin','superadmin')
                ORDER BY r.level DESC, u.id ASC";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([':section_id' => $sectionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT s.*, c.name AS category_name FROM sections s
             LEFT JOIN categories c ON c.id = s.category_id
             WHERE s.id = :id AND s.status = 'active'"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function incrementThreadCount(int $sectionId): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE sections SET thread_count = COALESCE(thread_count, 0) + 1 WHERE id = :id"
        );
        $stmt->execute([':id' => $sectionId]);
    }
}
