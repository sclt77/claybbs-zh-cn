<?php

namespace App\Models;

use App\Core\Database;
use PDO;

require_once dirname(__DIR__) . '/helpers/upload.php';

class AdminAnnouncementModel
{
    public function list(): array
    {
        (new AnnouncementModel())->ensurePopupColumns();
        $stmt = Database::connection()->query(
            "SELECT id, title, content, image, url, sort_order, is_pinned, popup_enabled, popup_once, status, created_at
             FROM announcements
             ORDER BY is_pinned DESC, sort_order ASC, id DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): void
    {
        (new AnnouncementModel())->ensurePopupColumns();
        $stmt = Database::connection()->prepare(
            "INSERT INTO announcements (title, content, image, url, sort_order, is_pinned, popup_enabled, popup_once, status)
             VALUES (:title, :content, :image, :url, :sort_order, :is_pinned, :popup_enabled, :popup_once, 'active')"
        );
        $stmt->execute([
            ':title'      => $data['title'],
            ':content'    => $data['content'] ?? '',
            ':image'      => $data['image'] ?? '',
            ':url'        => $data['url'] ?? '',
            ':sort_order' => (int) ($data['sort_order'] ?? 0),
            ':is_pinned'  => !empty($data['is_pinned']) ? 1 : 0,
            ':popup_enabled' => !empty($data['popup_enabled']) ? 1 : 0,
            ':popup_once' => !empty($data['popup_once']) ? 1 : 0,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $old = $this->find($id);
        $newImage = $data['image'] ?? '';

        $stmt = Database::connection()->prepare(
            "UPDATE announcements
             SET title = :title,
                 content = :content,
                 image = :image,
                 url = :url,
                 sort_order = :sort_order,
                 is_pinned = :is_pinned,
                 popup_enabled = :popup_enabled,
                 popup_once = :popup_once
             WHERE id = :id"
        );
        $stmt->execute([
            ':title'      => $data['title'],
            ':content'    => $data['content'] ?? '',
            ':image'      => $newImage,
            ':url'        => $data['url'] ?? '',
            ':sort_order' => (int) ($data['sort_order'] ?? 0),
            ':is_pinned'  => !empty($data['is_pinned']) ? 1 : 0,
            ':popup_enabled' => !empty($data['popup_enabled']) ? 1 : 0,
            ':popup_once' => !empty($data['popup_once']) ? 1 : 0,
            ':id'         => $id,
        ]);

        if (!empty($old['image']) && $old['image'] !== $newImage) {
            delete_local_upload($old['image']);
        }
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare("UPDATE announcements SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);
    }

    public function delete(int $id): void
    {
        $old = $this->find($id);
        $stmt = Database::connection()->prepare("DELETE FROM announcements WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if (!empty($old['image'])) {
            delete_local_upload($old['image']);
        }
    }

    private function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM announcements WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
