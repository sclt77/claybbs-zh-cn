<?php

namespace App\Models;

use App\Core\Database;
use PDO;

require_once dirname(__DIR__) . '/helpers/upload.php';

class AdminBannerModel
{
    public function list(string $placement = 'home'): array
    {
        $placement = in_array($placement, ['home', 'section'], true) ? $placement : 'home';
        $statusWhere = $placement === 'section' ? " AND b.status = 'active'" : '';
        $stmt = Database::connection()->prepare(
            "SELECT b.id, b.title, b.description, b.image, b.url, b.thread_id, b.placement, b.sort_order, b.status, b.created_at, t.title AS thread_title, s.name AS section_name
             FROM banners b
             LEFT JOIN threads t ON t.id = b.thread_id
             LEFT JOIN sections s ON s.id = t.section_id
             WHERE b.placement = :placement{$statusWhere}
             ORDER BY b.sort_order ASC, b.id ASC"
        );
        $stmt->execute([':placement' => $placement]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): void
    {
        $placement = (string)($data['placement'] ?? 'home');
        $placement = in_array($placement, ['home', 'section'], true) ? $placement : 'home';

        $stmt = Database::connection()->prepare(
            "INSERT INTO banners (title, description, image, url, thread_id, placement, sort_order, status) VALUES (:title, :desc, :image, :url, :thread_id, :placement, :sort, 'active')"
        );
        $stmt->execute([
            ':title' => $data['title'],
            ':desc'  => $data['description'] ?? '',
            ':image' => $data['image'] ?? '',
            ':url'   => $data['url'] ?? '',
            ':thread_id' => !empty($data['thread_id']) ? (int)$data['thread_id'] : null,
            ':placement' => $placement,
            ':sort'  => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    public function update(int $id, array $data): void
    {
        $old = $this->find($id);
        $newImage = $data['image'] ?? '';

        $stmt = Database::connection()->prepare(
            "UPDATE banners
             SET title = :title,
                 description = :desc,
                 image = :image,
                 url = :url,
                 thread_id = :thread_id,
                 sort_order = :sort
             WHERE id = :id"
        );
        $stmt->execute([
            ':title' => $data['title'],
            ':desc'  => $data['description'] ?? '',
            ':image' => $newImage,
            ':url'   => $data['url'] ?? '',
            ':thread_id' => !empty($data['thread_id']) ? (int)$data['thread_id'] : null,
            ':sort'  => (int) ($data['sort_order'] ?? 0),
            ':id'    => $id,
        ]);

        if (!empty($old['image']) && $old['image'] !== $newImage) {
            delete_local_upload($old['image']);
        }
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare("UPDATE banners SET status = :s WHERE id = :id");
        $stmt->execute([':s' => $status, ':id' => $id]);
    }

    public function delete(int $id): void
    {
        $old = $this->find($id);
        $stmt = Database::connection()->prepare("DELETE FROM banners WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if (!empty($old['image'])) {
            delete_local_upload($old['image']);
        }
    }


    public function createFromThread(array $thread): void
    {
        $id = (int)($thread['id'] ?? 0);
        if ($id <= 0) return;
        $exists = Database::connection()->prepare("SELECT id FROM banners WHERE placement = 'section' AND thread_id = :id LIMIT 1");
        $exists->execute([':id' => $id]);
        if ($exists->fetchColumn()) {
            Database::connection()->prepare("UPDATE banners SET status = 'active', updated_at = NOW() WHERE placement = 'section' AND thread_id = :id")->execute([':id' => $id]);
            return;
        }
        $title = (string)($thread['title'] ?? '帖子转播');
        $summary = trim(strip_tags((string)($thread['summary'] ?? '')));
        if ($summary === '') $summary = trim(mb_substr(strip_tags((string)($thread['content'] ?? '')), 0, 80));
        $cover = $this->extractThreadCover($thread);
        $url = '/index.php?path=thread&id=' . $id;
        $stmt = Database::connection()->prepare("INSERT INTO banners (title, description, image, url, thread_id, placement, sort_order, status) VALUES (:title, :desc, :image, :url, :thread_id, 'section', 0, 'active')");
        $stmt->execute([':title' => $title, ':desc' => $summary, ':image' => $cover, ':url' => $url, ':thread_id' => $id]);
    }

    public function isThreadBroadcastActive(int $threadId): bool
    {
        if ($threadId <= 0) return false;
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM banners WHERE placement = 'section' AND thread_id = :id AND status = 'active'");
        $stmt->execute([':id' => $threadId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function cancelThreadBroadcast(int $threadId): void
    {
        if ($threadId <= 0) return;
        Database::connection()->prepare("UPDATE banners SET status = 'inactive', updated_at = NOW() WHERE placement = 'section' AND thread_id = :id")->execute([':id' => $threadId]);
    }

    private function extractThreadCover(array $thread): string
    {
        $cover = trim((string)($thread['cover'] ?? ''));
        if ($cover !== '') return safe_image_url($cover);
        $content = (string)($thread['content'] ?? '');
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m)) {
            return safe_image_url((string)$m[1]);
        }
        return '';
    }

    private function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM banners WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
