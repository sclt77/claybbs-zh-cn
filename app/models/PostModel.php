<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class PostModel
{
    public function byThreadId(int $threadId, int $limit = 20, int $offset = 0, int $onlyUserId = 0): array
    {
        $threshold = 90.0;
        try {
            $threshold = max(0, min(100, (float)((new SettingModel())->get('question_bounty_ai_threshold', '90') ?? 90)));
        } catch (\Throwable $e) {
            $threshold = 90.0;
        }

        $sql = "SELECT p.*, p.user_id AS author_id, u.nickname AS author_name, u.avatar AS author_avatar, COALESCE(NULLIF(uv.display_name,''), vt.name) AS verification_name, vt.color AS verification_color, vt.description AS verification_description,
                       (SELECT r.name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=p.user_id AND (ur.expires_at IS NULL OR ur.expires_at > NOW()) ORDER BY r.level DESC LIMIT 1) AS author_role_label,
                       parent.user_id AS parent_author_id, parent.content AS parent_content, pu.nickname AS parent_author_name,
                       qas.score AS answer_match_score, qas.reason AS answer_match_reason, qas.status AS answer_match_status
                FROM posts p
                LEFT JOIN users u ON u.id = p.user_id
                LEFT JOIN user_verifications uv ON uv.user_id=p.user_id AND uv.status='active'
                LEFT JOIN verification_types vt ON vt.id=uv.type_id AND vt.status='active'
                LEFT JOIN posts parent ON parent.id = p.parent_id
                LEFT JOIN users pu ON pu.id = parent.user_id
                LEFT JOIN question_answer_scores qas ON qas.post_id=p.id
                WHERE p.thread_id = :thread_id AND p.status = 'published'" . ($onlyUserId > 0 ? " AND p.user_id = :only_user_id" : "") . "
                ORDER BY CASE WHEN COALESCE(qas.score,0) >= :score_threshold THEN 0 ELSE 1 END ASC, COALESCE(qas.score,0) DESC, COALESCE(p.parent_id, p.id) ASC, CASE WHEN p.parent_id IS NULL THEN 0 ELSE 1 END ASC, p.created_at ASC, p.id ASC
                LIMIT :limit OFFSET :offset";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':thread_id', $threadId, PDO::PARAM_INT);
        $stmt->bindValue(':score_threshold', $threshold);
        if ($onlyUserId > 0) $stmt->bindValue(':only_user_id', $onlyUserId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByThreadId(int $threadId, int $onlyUserId = 0): int
    {
        $sql = "SELECT COUNT(*) FROM posts WHERE thread_id=:thread_id AND status='published'" . ($onlyUserId > 0 ? " AND user_id=:only_user_id" : "");
        $stmt = Database::connection()->prepare($sql);
        $params = [':thread_id'=>$threadId];
        if ($onlyUserId > 0) $params[':only_user_id'] = $onlyUserId;
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM posts WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function acceptedForThread(int $threadId): ?array
    {
        $sql = "SELECT p.*, p.user_id AS author_id, u.nickname AS author_name, u.avatar AS author_avatar, COALESCE(NULLIF(uv.display_name,''), vt.name) AS verification_name, vt.color AS verification_color, vt.description AS verification_description,
                       (SELECT r.name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=p.user_id AND (ur.expires_at IS NULL OR ur.expires_at > NOW()) ORDER BY r.level DESC LIMIT 1) AS author_role_label
                FROM posts p
                LEFT JOIN users u ON u.id = p.user_id
                LEFT JOIN user_verifications uv ON uv.user_id=p.user_id AND uv.status='active'
                LEFT JOIN verification_types vt ON vt.id=uv.type_id AND vt.status='active'
                WHERE p.thread_id=:thread_id AND p.status='published' AND p.is_accepted=1
                ORDER BY p.accepted_at DESC, p.id ASC LIMIT 1";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([':thread_id' => $threadId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function canDelete(array $post, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        return (int)($post['user_id'] ?? 0) === $userId || \App\Middleware\Permission::can('post.delete_any') || \App\Middleware\Permission::can('admin.access');
    }

    public function delete(int $id): void
    {
        $threadId = $this->threadIdForPost($id);
        Database::connection()->prepare("UPDATE posts SET status = 'deleted', updated_at = NOW() WHERE id = :id")->execute([':id' => $id]);
        if ($threadId > 0) {
            $this->refreshThreadStats($threadId);
        }
    }

    public function create(array $data): int
    {
        $status = in_array(($data['status'] ?? 'published'), ['published', 'pending'], true) ? (string)$data['status'] : 'published';
        $sql = "INSERT INTO posts (thread_id, user_id, parent_id, reply_user_id, content, status, created_at, updated_at)
                VALUES (:thread_id, :user_id, :parent_id, :reply_user_id, :content, :status, NOW(), NOW())";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            ':thread_id' => $data['thread_id'],
            ':user_id' => $data['user_id'],
            ':parent_id' => $data['parent_id'] ?? null,
            ':reply_user_id' => $data['reply_user_id'] ?? null,
            ':content' => $data['content'],
            ':status' => $status,
        ]);

        return (int) Database::connection()->lastInsertId();
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
