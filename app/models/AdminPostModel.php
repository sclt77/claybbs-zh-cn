<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class AdminPostModel
{
    public function list(string $keyword = '', array $filters = [], int $page = 1, int $pageSize = 20): array
    {
        [$where, $params] = $this->buildWhere($keyword, $filters);
        $offset = max(0, ($page - 1) * $pageSize);
        $sql = "SELECT p.*, u.nickname AS author_name, t.title AS thread_title, t.section_id, s.name AS section_name
                FROM posts p
                LEFT JOIN users u ON u.id = p.user_id
                LEFT JOIN threads t ON t.id = p.thread_id
                LEFT JOIN sections s ON s.id = t.section_id
                {$where}
                ORDER BY p.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(string $keyword = '', array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($keyword, $filters);
        $sql = "SELECT COUNT(*) FROM posts p LEFT JOIN threads t ON t.id = p.thread_id {$where}";
        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT p.*, t.title AS thread_title, t.section_id FROM posts p LEFT JOIN threads t ON t.id = p.thread_id WHERE p.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateStatus(int $id, string $status): void
    {
        $threadId = $this->threadIdForPost($id);
        $stmt = Database::connection()->prepare("UPDATE posts SET status = :status, updated_at = NOW() WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);
        if ($threadId > 0) {
            $this->refreshThreadStats($threadId);
        }
    }

    public function delete(int $id): void
    {
        $db = Database::connection();
        $threadId = $this->threadIdForPost($id);
        $ownTransaction = !$db->inTransaction();
        if ($ownTransaction) $db->beginTransaction();
        try {
            $db->prepare("UPDATE posts SET parent_id=NULL WHERE parent_id=:id")->execute([':id' => $id]);
            $db->prepare("UPDATE threads SET accepted_post_id=NULL, accepted_user_id=NULL, accepted_at=NULL, question_status=CASE WHEN accepted_post_id=:id THEN 'open' ELSE question_status END WHERE accepted_post_id=:id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM question_answer_scores WHERE post_id=:id")->execute([':id' => $id]);
            $db->prepare("UPDATE question_bounty_reviews SET high_score_post_id=NULL WHERE high_score_post_id=:id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM content_likes WHERE target_type='post' AND target_id=:id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM content_reports WHERE target_type='post' AND target_id=:id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM mentions WHERE post_id=:id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM attachments WHERE post_id=:id")->execute([':id' => $id]);
            $db->prepare("UPDATE thread_read_progress SET last_post_id=NULL WHERE last_post_id=:id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM posts WHERE id = :id")->execute([':id' => $id]);
            if ($ownTransaction) $db->commit();
        } catch (\Throwable $e) {
            if ($ownTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
        if ($threadId > 0) {
            $this->refreshThreadStats($threadId);
        }
    }

    public function updateContent(int $id, string $content): void
    {
        Database::connection()->prepare("UPDATE posts SET content = :content, updated_at = NOW() WHERE id = :id")->execute([':content' => $content, ':id' => $id]);
    }

    private function buildWhere(string $keyword, array $filters): array
    {
        $where = "WHERE 1=1";
        $params = [];
        if ($keyword !== '') {
            $where .= " AND (p.content LIKE :kw OR t.title LIKE :kw)";
            $params[':kw'] = '%' . $keyword . '%';
        }
        $status = (string)($filters['status'] ?? '');
        if ($status !== '' && in_array($status, ['published', 'pending', 'hidden', 'deleted'], true)) {
            $where .= " AND p.status = :status";
            $params[':status'] = $status;
        }
        $threadId = (int)($filters['thread_id'] ?? 0);
        if ($threadId > 0) {
            $where .= " AND p.thread_id = :thread_id";
            $params[':thread_id'] = $threadId;
        }
        return [$where, $params];
    }

    private function threadIdForPost(int $postId): int
    {
        $stmt = Database::connection()->prepare("SELECT thread_id FROM posts WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $postId]);
        return (int)$stmt->fetchColumn();
    }

    private function refreshThreadStats(int $threadId): void
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
}
