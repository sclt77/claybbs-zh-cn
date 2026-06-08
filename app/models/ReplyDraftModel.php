<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ReplyDraftModel
{
    public function listByUser(int $userId): array
    {
        $stmt = Database::connection()->prepare("SELECT d.*, t.title AS thread_title FROM reply_drafts d LEFT JOIN threads t ON t.id=d.thread_id WHERE d.user_id=:uid AND COALESCE(d.is_autosave,0)=0 ORDER BY d.updated_at DESC, d.id DESC");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function searchByUser(int $userId, string $keyword = ''): array
    {
        $sql = "SELECT d.*, t.title AS thread_title FROM reply_drafts d LEFT JOIN threads t ON t.id=d.thread_id WHERE d.user_id=:uid AND COALESCE(d.is_autosave,0)=0";
        $params = [':uid' => $userId];
        if ($keyword !== '') {
            $sql .= " AND (d.content LIKE :kw OR t.title LIKE :kw)";
            $params[':kw'] = '%' . $keyword . '%';
        }
        $sql .= " ORDER BY d.updated_at DESC, d.id DESC";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function deleteManyForUser(array $ids, int $userId): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
        if (!$ids) return 0;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare("DELETE FROM reply_drafts WHERE user_id=? AND COALESCE(is_autosave,0)=0 AND id IN ($placeholders)");
        $stmt->execute(array_merge([$userId], $ids));
        return $stmt->rowCount();
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = Database::connection()->prepare("SELECT d.*, t.title AS thread_title FROM reply_drafts d LEFT JOIN threads t ON t.id=d.thread_id WHERE d.id=:id AND d.user_id=:uid LIMIT 1");
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function saveRejected(int $userId, int $threadId, ?int $parentId, string $content, array $review): int
    {
        Database::connection()->prepare("INSERT INTO reply_drafts (user_id,thread_id,parent_id,content,is_autosave,review_status,review_reason,review_suggestion,review_categories,reviewed_at,created_at,updated_at) VALUES (:uid,:tid,:pid,:content,0,'ai_rejected',:reason,:suggestion,:categories,NOW(),NOW(),NOW())")
            ->execute([
                ':uid' => $userId,
                ':tid' => $threadId,
                ':pid' => $parentId ?: null,
                ':content' => $content,
                ':reason' => (string)($review['reason'] ?? ''),
                ':suggestion' => (string)($review['suggestion'] ?? ''),
                ':categories' => is_array($review['categories'] ?? null) ? implode(',', $review['categories']) : (string)($review['categories'] ?? ''),
            ]);
        return (int)Database::connection()->lastInsertId();
    }

    public function deleteForUser(int $id, int $userId): void
    {
        Database::connection()->prepare("DELETE FROM reply_drafts WHERE id=:id AND user_id=:uid")->execute([':id'=>$id, ':uid'=>$userId]);
    }
}
