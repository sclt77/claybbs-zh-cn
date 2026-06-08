<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class AdminSectionModel
{
    public function sections(): array
    {
        $sql = "SELECT s.id, s.category_id, s.name, s.slug, s.icon, s.post_permission, s.is_question, s.description, s.sort_order, s.status,
                       c.name AS category_name,
                       COUNT(t.id) AS thread_count,
                       SUM(t.created_at >= CURDATE()) AS today_thread_count,
                       COALESCE((SELECT COUNT(*) FROM posts p JOIN threads pt ON pt.id=p.thread_id WHERE pt.section_id=s.id AND p.status='published'),0) AS post_count,
                       COALESCE((SELECT COUNT(*) FROM posts p JOIN threads pt ON pt.id=p.thread_id WHERE pt.section_id=s.id AND p.status='published' AND p.created_at>=CURDATE()),0) AS today_post_count,
                       COALESCE((SELECT COUNT(*) FROM threads rt WHERE rt.section_id=s.id AND rt.status='pending'),0) AS pending_thread_count,
                       COALESCE((SELECT COUNT(*) FROM posts rp JOIN threads rpt ON rpt.id=rp.thread_id WHERE rpt.section_id=s.id AND rp.status='pending'),0) AS pending_post_count,
                       COALESCE((SELECT COUNT(*) FROM content_reports cr LEFT JOIN threads crt ON cr.target_type='thread' AND crt.id=cr.target_id LEFT JOIN posts crp ON cr.target_type='post' AND crp.id=cr.target_id LEFT JOIN threads crpt ON crpt.id=crp.thread_id WHERE cr.status IN ('pending','processing') AND (crt.section_id=s.id OR crpt.section_id=s.id)),0) AS open_report_count,
                       COALESCE((SELECT COUNT(*) FROM section_follows sf WHERE sf.section_id=s.id),0) AS follower_count
                FROM sections s
                LEFT JOIN categories c ON c.id = s.category_id
                LEFT JOIN threads t ON t.section_id = s.id AND t.status != 'deleted'
                GROUP BY s.id
                ORDER BY c.sort_order ASC, c.id ASC, s.sort_order ASC, s.id ASC";
        $stmt = Database::connection()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function categories(): array
    {
        $stmt = Database::connection()->query(
            "SELECT id, name, slug, description, sort_order, status
             FROM categories
             ORDER BY sort_order ASC, id ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createCategory(array $data): void
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO categories (name, slug, description, sort_order, status, created_at, updated_at)
             VALUES (:name, :slug, :description, :sort_order, 'active', NOW(), NOW())"
        );
        $stmt->execute([
            ':name'        => $data['name'],
            ':slug'        => $data['slug'],
            ':description' => $data['description'] ?? '',
            ':sort_order'  => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    public function updateCategory(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE categories
             SET name = :name,
                 slug = :slug,
                 description = :description,
                 sort_order = :sort_order,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            ':name'        => $data['name'],
            ':slug'        => $data['slug'],
            ':description' => $data['description'] ?? '',
            ':sort_order'  => (int) ($data['sort_order'] ?? 0),
            ':status'      => $data['status'] ?? 'active',
            ':id'          => $id,
        ]);
    }

    public function deleteCategory(int $id): void
    {
        $stmt = Database::connection()->prepare("DELETE FROM categories WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function createSection(array $data): void
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO sections (category_id, name, slug, icon, post_permission, is_question, description, sort_order, status, created_at, updated_at)
             VALUES (:category_id, :name, :slug, :icon, :post_permission, :is_question, :description, :sort_order, 'active', NOW(), NOW())"
        );
        $stmt->execute([
            ':category_id' => $data['category_id'],
            ':name'        => $data['name'],
            ':slug'        => $data['slug'],
            ':icon'        => $data['icon'] ?? '',
            ':post_permission' => $data['post_permission'] ?? 'login',
            ':is_question' => !empty($data['is_question']) ? 1 : 0,
            ':description' => $data['description'] ?? '',
            ':sort_order'  => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    public function updateSection(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE sections
             SET category_id = :category_id,
                 name = :name,
                 slug = :slug,
                 icon = :icon,
                 post_permission = :post_permission,
                 is_question = :is_question,
                 description = :description,
                 sort_order = :sort_order,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            ':category_id' => $data['category_id'],
            ':name'        => $data['name'],
            ':slug'        => $data['slug'],
            ':icon'        => $data['icon'] ?? '',
            ':post_permission' => $data['post_permission'] ?? 'login',
            ':is_question' => !empty($data['is_question']) ? 1 : 0,
            ':description' => $data['description'] ?? '',
            ':sort_order'  => (int) ($data['sort_order'] ?? 0),
            ':status'      => $data['status'] ?? 'active',
            ':id'          => $id,
        ]);
    }

    public function deleteSection(int $id): void
    {
        $stmt = Database::connection()->prepare("DELETE FROM sections WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }
}
