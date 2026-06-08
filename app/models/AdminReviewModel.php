<?php

namespace App\Models;

use App\Core\Database;
use App\Middleware\Permission;
use App\Models\ThreadModel;
use PDO;

class AdminReviewModel
{
    public function pendingThreads(?int $userId = null): array
    {
        $sql = "SELECT t.id, t.user_id, t.title, t.summary, t.content, t.created_at, t.section_id, s.name AS section_name, s.category_id, u.nickname AS author_name
                FROM threads t
                LEFT JOIN sections s ON s.id = t.section_id
                LEFT JOIN users u ON u.id = t.user_id
                WHERE t.status = 'pending'";
        $params = [];
        if ($userId) {
            $sectionIds = Permission::accessibleSectionIds($userId, 'review.thread');
            if (is_array($sectionIds)) {
                if (empty($sectionIds)) return [];
                $placeholders = [];
                foreach ($sectionIds as $index => $sid) {
                    $ph = ':sid_' . $index;
                    $placeholders[] = $ph;
                    $params[$ph] = $sid;
                }
                $sql .= " AND t.section_id IN (" . implode(',', $placeholders) . ")";
            }
        }
        $sql .= " ORDER BY t.created_at ASC";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function pendingPosts(?int $userId = null): array
    {
        $sql = "SELECT p.id, p.user_id, p.thread_id, p.parent_id, p.content, p.created_at, t.title AS thread_title, t.section_id, s.category_id, u.nickname AS author_name
                FROM posts p
                LEFT JOIN threads t ON t.id = p.thread_id
                LEFT JOIN sections s ON s.id = t.section_id
                LEFT JOIN users u ON u.id = p.user_id
                WHERE p.status = 'pending'";
        $params = [];
        if ($userId) {
            $sectionIds = Permission::accessibleSectionIds($userId, 'review.post');
            if (is_array($sectionIds)) {
                if (empty($sectionIds)) return [];
                $placeholders = [];
                foreach ($sectionIds as $index => $sid) {
                    $ph = ':sid_' . $index;
                    $placeholders[] = $ph;
                    $params[$ph] = $sid;
                }
                $sql .= " AND t.section_id IN (" . implode(',', $placeholders) . ")";
            }
        }
        $sql .= " ORDER BY p.created_at ASC";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function reviewThread(int $id, string $action): void
    {
        $status = $action === 'approve' ? 'published' : 'hidden';
        (new ThreadModel())->updateStatus($id, $status);
    }

    public function reviewPost(int $id, string $action): void
    {
        $status = $action === 'approve' ? 'published' : 'hidden';
        $threadId = $this->threadIdForPost($id);
        $stmt = Database::connection()->prepare("UPDATE posts SET status = :s WHERE id = :id");
        $stmt->execute([':s' => $status, ':id' => $id]);
        if ($threadId > 0) {
            $this->refreshThreadReplyStats($threadId);
        }
    }

    private function threadIdForPost(int $postId): int
    {
        $stmt = Database::connection()->prepare("SELECT thread_id FROM posts WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $postId]);
        return (int) $stmt->fetchColumn();
    }

    private function refreshThreadReplyStats(int $threadId): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE threads SET reply_count = (
                SELECT COUNT(*) FROM posts WHERE thread_id = :thread_id AND status = 'published'
            ), last_reply_at = (
                SELECT MAX(created_at) FROM posts WHERE thread_id = :thread_id2 AND status = 'published'
            ) WHERE id = :id"
        );
        $stmt->execute([':thread_id' => $threadId, ':thread_id2' => $threadId, ':id' => $threadId]);
    }

    public function pendingCounts(int $userId): array
    {
        return [
            'threads' => count($this->pendingThreads($userId)),
            'posts' => count($this->pendingPosts($userId)),
        ];
    }
}
