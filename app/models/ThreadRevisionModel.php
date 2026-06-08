<?php

namespace App\Models;

use App\Core\Database;
use App\Middleware\Permission;
use PDO;

class ThreadRevisionModel
{
    public function ensureTable(): void
    {
        Database::connection()->exec("CREATE TABLE IF NOT EXISTS thread_revisions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            thread_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(200) NOT NULL,
            summary VARCHAR(500) DEFAULT NULL,
            content LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            review_reason VARCHAR(500) DEFAULT NULL,
            review_suggestion VARCHAR(500) DEFAULT NULL,
            ai_result_json LONGTEXT DEFAULT NULL,
            reviewer_id BIGINT UNSIGNED DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_thread_revisions_status (status, created_at),
            KEY idx_thread_revisions_thread (thread_id, status),
            KEY idx_thread_revisions_user (user_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function create(int $threadId, int $userId, string $title, string $content, string $summary, string $status = 'pending', array $review = []): int
    {
        $this->ensureTable();
        $status = in_array($status, ['pending','rejected','approved'], true) ? $status : 'pending';
        $stmt = Database::connection()->prepare("INSERT INTO thread_revisions (thread_id,user_id,title,summary,content,status,review_reason,review_suggestion,ai_result_json,reviewed_at,created_at,updated_at)
            VALUES (:thread_id,:user_id,:title,:summary,:content,:status,:reason,:suggestion,:ai_result_json,:reviewed_at,NOW(),NOW())");
        $stmt->execute([
            ':thread_id' => $threadId,
            ':user_id' => $userId,
            ':title' => $title,
            ':summary' => $summary,
            ':content' => $content,
            ':status' => $status,
            ':reason' => (string)($review['reason'] ?? ''),
            ':suggestion' => (string)($review['suggestion'] ?? ''),
            ':ai_result_json' => $review ? json_encode($review, JSON_UNESCAPED_UNICODE) : null,
            ':reviewed_at' => in_array($status, ['approved','rejected'], true) ? date('Y-m-d H:i:s') : null,
        ]);
        return (int)Database::connection()->lastInsertId();
    }

    public function pending(?int $userId = null, bool $ownedOnly = false): array
    {
        $this->ensureTable();
        $sql = "SELECT r.*, t.section_id, t.title AS old_title, s.name AS section_name, s.category_id, u.nickname AS author_name
                FROM thread_revisions r
                LEFT JOIN threads t ON t.id = r.thread_id
                LEFT JOIN sections s ON s.id = t.section_id
                LEFT JOIN users u ON u.id = r.user_id
                WHERE r.status = 'pending'";
        $params = [];
        if ($userId && $ownedOnly) {
            $sql .= " AND r.user_id = :uid";
            $params[':uid'] = $userId;
        } elseif ($userId) {
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
        $sql .= " ORDER BY r.created_at ASC, r.id ASC";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $id): ?array
    {
        $this->ensureTable();
        $stmt = Database::connection()->prepare("SELECT r.*, t.section_id, t.title AS old_title, t.content AS old_content, t.status AS thread_status, s.name AS section_name, s.category_id, u.nickname AS author_name
            FROM thread_revisions r
            LEFT JOIN threads t ON t.id = r.thread_id
            LEFT JOIN sections s ON s.id = t.section_id
            LEFT JOIN users u ON u.id = r.user_id
            WHERE r.id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function byUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        $this->ensureTable();
        $stmt = Database::connection()->prepare("SELECT r.*, t.section_id, s.name AS section_name, u.nickname AS author_name, u.avatar AS author_avatar
            FROM thread_revisions r
            LEFT JOIN threads t ON t.id = r.thread_id
            LEFT JOIN sections s ON s.id = t.section_id
            LEFT JOIN users u ON u.id = r.user_id
            WHERE r.user_id = :uid AND r.status = 'pending'
            ORDER BY r.updated_at DESC, r.id DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countByUser(int $userId): int
    {
        $this->ensureTable();
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM thread_revisions WHERE user_id=:uid AND status='pending'");
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    public function approve(int $id, int $reviewerId): void
    {
        $revision = $this->find($id);
        if (!$revision || ($revision['status'] ?? '') !== 'pending') return;
        $thread = (new ThreadModel())->find((int)$revision['thread_id']);
        if (!$thread) return;
        (new ThreadModel())->updateByAdmin((int)$revision['thread_id'], [
            'section_id' => (int)($thread['section_id'] ?? $revision['section_id'] ?? 0),
            'title' => (string)$revision['title'],
            'content' => (string)$revision['content'],
            'summary' => (string)($revision['summary'] ?? mb_substr(strip_tags((string)$revision['content']), 0, 120)),
            'status' => 'published',
        ]);
        (new ThreadEditLogModel())->create((int)$revision['thread_id'], $reviewerId, 'revision', $thread, [
            'title' => (string)$revision['title'],
            'content' => (string)$revision['content'],
            'section_id' => $thread['section_id'] ?? null,
            'status' => 'published',
        ]);
        $this->mark($id, 'approved', $reviewerId, '', '');
    }

    public function reject(int $id, int $reviewerId, string $reason = '', string $suggestion = ''): void
    {
        $this->mark($id, 'rejected', $reviewerId, $reason, $suggestion);
    }

    private function mark(int $id, string $status, int $reviewerId, string $reason, string $suggestion): void
    {
        $this->ensureTable();
        Database::connection()->prepare("UPDATE thread_revisions SET status=:status, reviewer_id=:reviewer_id, review_reason=:reason, review_suggestion=:suggestion, reviewed_at=NOW(), updated_at=NOW() WHERE id=:id")
            ->execute([':status'=>$status, ':reviewer_id'=>$reviewerId ?: null, ':reason'=>$reason, ':suggestion'=>$suggestion, ':id'=>$id]);
    }
}
