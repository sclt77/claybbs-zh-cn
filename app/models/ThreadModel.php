<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ThreadModel
{
    public function latest(int $limit = 10, int $offset = 0, string $feed = 'latest', int $viewerId = 0): array
    {
        $feedWhere = $this->feedWhere($feed, true, $viewerId);
        $orderBy = $this->feedOrderBy($feed);
        $sql = "SELECT t.*, u.nickname AS author_name, u.avatar AS author_avatar, COALESCE(NULLIF(uv.display_name,''), vt.name) AS verification_name, vt.color AS verification_color, vt.description AS verification_description, s.name AS section_name, s.is_question AS section_is_question,
                       (SELECT r.name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=t.user_id AND (ur.expires_at IS NULL OR ur.expires_at > NOW()) ORDER BY r.level DESC LIMIT 1) AS author_role_label
                FROM threads t
                LEFT JOIN users u ON u.id = t.user_id
                LEFT JOIN user_verifications uv ON uv.user_id=t.user_id AND uv.status='active'
                LEFT JOIN verification_types vt ON vt.id=uv.type_id AND vt.status='active'
                LEFT JOIN sections s ON s.id = t.section_id
                WHERE t.status = 'published' AND NOT (t.is_top = 1 AND t.top_scope = 'global') {$feedWhere}
                ORDER BY {$orderBy}
                LIMIT :limit OFFSET :offset";

        $stmt = Database::connection()->prepare($sql);
        if ($feed === 'following' && $viewerId > 0) $stmt->bindValue(':viewer_id', $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countPublished(string $feed = 'latest', int $viewerId = 0): int
    {
        $feedWhere = $this->feedWhere($feed, false, $viewerId);
        $sql = "SELECT COUNT(*) FROM threads WHERE status = 'published' AND NOT (is_top = 1 AND top_scope = 'global') {$feedWhere}";
        $stmt = Database::connection()->prepare($sql);
        if ($feed === 'following' && $viewerId > 0) $stmt->bindValue(':viewer_id', $viewerId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    private function feedWhere(string $feed, bool $withAlias = true, int $viewerId = 0): string
    {
        $prefix = $withAlias ? 't.' : '';
        return match ($feed) {
            'hot' => "AND ({$prefix}view_count > 0 OR {$prefix}reply_count > 0 OR {$prefix}like_count > 0)",
            'featured' => "AND {$prefix}is_featured = 1",
            'bounty' => "AND {$prefix}question_status IN ('open','resolved','reviewing_close') AND {$prefix}bounty_currency IS NOT NULL AND {$prefix}bounty_amount > 0",
            'following' => $viewerId > 0 ? "AND ({$prefix}user_id IN (SELECT following_id FROM user_follows WHERE follower_id = :viewer_id) OR {$prefix}section_id IN (SELECT section_id FROM section_follows WHERE user_id = :viewer_id))" : "AND 1=0",
            default => '',
        };
    }

    private function feedOrderBy(string $feed): string
    {
        return match ($feed) {
            'hot' => '((t.view_count * 0.15) + (t.reply_count * 3.5) + (t.like_count * 2.4) + (t.favorite_count * 2.8) + (COALESCE(t.read_complete_count,0) * 1.2) - (COALESCE(t.report_count,0) * 5) + IF(t.is_featured=1, 12, 0) + IF(t.is_recommended=1, 6, 0)) DESC, COALESCE(t.last_reply_at,t.created_at) DESC',
            'featured' => 't.updated_at DESC, t.created_at DESC',
            'bounty' => "CASE WHEN t.question_status='open' THEN 0 WHEN t.question_status='reviewing_close' THEN 1 ELSE 2 END ASC, t.bounty_amount DESC, t.created_at DESC",
            'following' => 't.created_at DESC',
            default => 't.is_featured DESC, t.is_recommended DESC, t.created_at DESC',
        };
    }

    public function activeAuthors(int $limit = 8): array
    {
        $sql = "SELECT u.id, u.nickname, u.username, u.avatar, COUNT(t.id) AS thread_count, MAX(t.created_at) AS last_thread_at,
                       (SELECT r.name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=u.id AND (ur.expires_at IS NULL OR ur.expires_at > NOW()) ORDER BY r.level DESC LIMIT 1) AS author_role_label
                FROM users u
                JOIN threads t ON t.user_id = u.id AND t.status = 'published'
                WHERE u.status = 'active'
                GROUP BY u.id
                ORDER BY thread_count DESC, last_thread_at DESC
                LIMIT :limit";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':limit', max(1, min(30, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function byUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT t.*, u.nickname AS author_name, u.avatar AS author_avatar, s.name AS section_name, s.is_question AS section_is_question
                FROM threads t
                LEFT JOIN users u ON u.id = t.user_id
                LEFT JOIN sections s ON s.id = t.section_id
                WHERE t.status = 'published' AND t.user_id = :user_id
                ORDER BY t.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function bySectionId(int $sectionId, int $limit = 20, int $offset = 0, string $filter = 'all'): array
    {
        $filterWhere = $this->sectionFilterWhere($filter);
        $sql = "SELECT t.*, u.nickname AS author_name, u.avatar AS author_avatar, COALESCE(NULLIF(uv.display_name,''), vt.name) AS verification_name, vt.color AS verification_color, vt.description AS verification_description, s.name AS section_name, s.is_question AS section_is_question,
                       (SELECT r.name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=t.user_id AND (ur.expires_at IS NULL OR ur.expires_at > NOW()) ORDER BY r.level DESC LIMIT 1) AS author_role_label
                FROM threads t
                LEFT JOIN users u ON u.id = t.user_id
                LEFT JOIN user_verifications uv ON uv.user_id=t.user_id AND uv.status='active'
                LEFT JOIN verification_types vt ON vt.id=uv.type_id AND vt.status='active'
                LEFT JOIN sections s ON s.id = t.section_id
                WHERE t.status = 'published' AND t.section_id = :section_id AND NOT (t.is_top = 1 AND t.top_scope IN ('global','section')) {$filterWhere}
                ORDER BY " . ($filter === 'hot' ? "((t.view_count * 0.15) + (t.reply_count * 3.5) + (t.like_count * 2.4) + (t.favorite_count * 2.8) + (COALESCE(t.read_complete_count,0) * 1.2) - (COALESCE(t.report_count,0) * 5) + IF(t.is_featured=1, 12, 0) + IF(t.is_recommended=1, 6, 0)) DESC, COALESCE(t.last_reply_at,t.created_at) DESC" : "t.is_featured DESC, t.is_recommended DESC, t.created_at DESC") . "
                LIMIT :limit OFFSET :offset";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':section_id', $sectionId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countBySectionId(int $sectionId, string $filter = 'all'): int
    {
        $filterWhere = $this->sectionFilterWhere($filter, false);
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM threads WHERE status = 'published' AND section_id = :id AND NOT (is_top = 1 AND top_scope IN ('global','section')) {$filterWhere}");
        $stmt->execute([':id' => $sectionId]);
        return (int) $stmt->fetchColumn();
    }

    private function sectionFilterWhere(string $filter, bool $withAlias = true): string
    {
        $prefix = $withAlias ? 't.' : '';
        return match ($filter) {
            'recommended' => "AND {$prefix}is_recommended = 1",
            'featured' => "AND {$prefix}is_featured = 1",
            'hot' => "AND ({$prefix}view_count > 0 OR {$prefix}reply_count > 0 OR {$prefix}like_count > 0)",
            default => '',
        };
    }

    public function ensureModerationColumns(): void
    {
        
    }

    public function find(int $id): ?array
    {
        $this->ensureModerationColumns();
        $sql = "SELECT t.*, u.nickname AS author_name, u.avatar AS author_avatar, u.bio AS author_bio, COALESCE(NULLIF(uv.display_name,''), vt.name) AS verification_name, vt.color AS verification_color, vt.description AS verification_description, s.name AS section_name, s.is_question AS section_is_question,
                       (SELECT r.name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=t.user_id AND (ur.expires_at IS NULL OR ur.expires_at > NOW()) ORDER BY r.level DESC LIMIT 1) AS author_role_label
                FROM threads t
                LEFT JOIN users u ON u.id = t.user_id
                LEFT JOIN user_verifications uv ON uv.user_id=t.user_id AND uv.status='active'
                LEFT JOIN verification_types vt ON vt.id=uv.type_id AND vt.status='active'
                LEFT JOIN sections s ON s.id = t.section_id
                WHERE t.id = :id LIMIT 1";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $status = in_array(($data['status'] ?? 'published'), ['published', 'pending'], true) ? (string)$data['status'] : 'published';
        $sql = "INSERT INTO threads (user_id, section_id, title, summary, content, status, paid_visible_enabled, paid_visible_price, paid_visible_currency, question_status, bounty_currency, bounty_amount, created_at, updated_at)
                VALUES (:user_id, :section_id, :title, :summary, :content, :status, :paid_visible_enabled, :paid_visible_price, :paid_visible_currency, :question_status, :bounty_currency, :bounty_amount, NOW(), NOW())";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            ':user_id'    => $data['user_id'],
            ':section_id' => $data['section_id'],
            ':title'      => $data['title'],
            ':summary'    => $data['summary'] ?? null,
            ':content'    => $data['content'],
            ':status'     => $status,
            ':paid_visible_enabled' => !empty($data['paid_visible_enabled']) ? 1 : 0,
            ':paid_visible_price' => !empty($data['paid_visible_enabled']) ? (float)($data['paid_visible_price'] ?? 0) : null,
            ':paid_visible_currency' => !empty($data['paid_visible_enabled']) ? strtoupper(trim((string)($data['paid_visible_currency'] ?? ''))) : null,
            ':question_status' => (string)($data['question_status'] ?? 'none'),
            ':bounty_currency' => !empty($data['bounty_currency']) ? strtoupper(trim((string)$data['bounty_currency'])) : null,
            ':bounty_amount' => !empty($data['bounty_amount']) ? (float)$data['bounty_amount'] : null,
        ]);

        $threadId = (int) Database::connection()->lastInsertId();

        
        try {
            $this->updateThreadCount($data['section_id']);
        } catch (\Throwable $e) {
            
        }

        return $threadId;
    }

    public function updateOwned(int $id, int $userId, array $data): void
    {
        $status = in_array((string)($data['status'] ?? ''), ['published', 'pending', 'hidden', 'deleted'], true) ? (string)$data['status'] : null;
        $sql = "UPDATE threads SET title=:title, content=:content, summary=:summary, paid_visible_enabled=:paid_visible_enabled, paid_visible_price=:paid_visible_price, paid_visible_currency=:paid_visible_currency" . ($status !== null ? ", status=:status" : "") . ", updated_at=NOW() WHERE id=:id AND user_id=:user_id";
        $stmt = Database::connection()->prepare($sql);
        $params = [
            ':title' => $data['title'],
            ':content' => $data['content'],
            ':summary' => $data['summary'] ?? null,
            ':id' => $id,
            ':user_id' => $userId,
            ':paid_visible_enabled' => !empty($data['paid_visible_enabled']) ? 1 : 0,
            ':paid_visible_price' => !empty($data['paid_visible_enabled']) ? (float)($data['paid_visible_price'] ?? 0) : null,
            ':paid_visible_currency' => !empty($data['paid_visible_enabled']) ? strtoupper(trim((string)($data['paid_visible_currency'] ?? ''))) : null,
        ];
        if ($status !== null) {
            $params[':status'] = $status;
        }
        $stmt->execute($params);
        if ($status !== null) {
            $sectionId = $this->sectionIdForThread($id);
            if ($sectionId > 0) $this->updateThreadCount($sectionId);
        }
    }

    public function updateByAdmin(int $id, array $data): void
    {
        $this->ensureModerationColumns();
        $oldSectionId = $this->sectionIdForThread($id);
        $newSectionId = (int)($data['section_id'] ?? $oldSectionId);
        $sql = "UPDATE threads SET section_id=:section_id, title=:title, content=:content, summary=:summary, status=:status";
        $params = [
            ':section_id' => $newSectionId,
            ':title' => $data['title'],
            ':content' => $data['content'],
            ':summary' => $data['summary'] ?? null,
            ':status' => $data['status'],
            ':id' => $id,
        ];
        if (array_key_exists('paid_visible_enabled', $data)) {
            $sql .= ", paid_visible_enabled=:paid_visible_enabled, paid_visible_price=:paid_visible_price, paid_visible_currency=:paid_visible_currency";
            $params[':paid_visible_enabled'] = !empty($data['paid_visible_enabled']) ? 1 : 0;
            $params[':paid_visible_price'] = !empty($data['paid_visible_enabled']) ? (float)($data['paid_visible_price'] ?? 0) : null;
            $params[':paid_visible_currency'] = !empty($data['paid_visible_enabled']) ? strtoupper(trim((string)($data['paid_visible_currency'] ?? ''))) : null;
        }
        $sql .= ", updated_at=NOW() WHERE id=:id";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        if ($oldSectionId > 0) {
            $this->updateThreadCount($oldSectionId);
        }
        if ($newSectionId > 0 && $newSectionId !== $oldSectionId) {
            $this->updateThreadCount($newSectionId);
        }
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->ensureModerationColumns();
        $sectionId = $this->sectionIdForThread($id);
        $stmt = Database::connection()->prepare("UPDATE threads SET status=:status, updated_at=NOW() WHERE id=:id");
        $stmt->execute([':status' => $status, ':id' => $id]);
        if ($sectionId > 0) {
            $this->updateThreadCount($sectionId);
        }
    }

    public function deleteHard(int $id): void
    {
        $this->ensureModerationColumns();
        $sectionId = $this->sectionIdForThread($id);
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM content_likes WHERE (target_type='thread' AND target_id=:id) OR (target_type='post' AND target_id IN (SELECT id FROM posts WHERE thread_id=:id2))")->execute([':id'=>$id, ':id2'=>$id]);
            $db->prepare("DELETE FROM content_reports WHERE (target_type='thread' AND target_id=:id) OR (target_type='post' AND target_id IN (SELECT id FROM posts WHERE thread_id=:id2))")->execute([':id'=>$id, ':id2'=>$id]);
            $db->prepare("DELETE FROM mentions WHERE thread_id=:id")->execute([':id'=>$id]);
            $db->prepare("DELETE FROM attachments WHERE thread_id=:id OR post_id IN (SELECT id FROM posts WHERE thread_id=:id2)")->execute([':id'=>$id, ':id2'=>$id]);
            $db->prepare("DELETE FROM thread_favorites WHERE thread_id=:id")->execute([':id'=>$id]);
            $db->prepare("DELETE FROM thread_edit_logs WHERE thread_id=:id")->execute([':id'=>$id]);
            $db->prepare("DELETE FROM posts WHERE thread_id=:id")->execute([':id'=>$id]);
            $db->prepare("DELETE FROM threads WHERE id=:id")->execute([':id'=>$id]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        if ($sectionId > 0) {
            $this->updateThreadCount($sectionId);
        }
    }

    public function updateModeration(int $id, array $flags): void
    {
        $this->ensureModerationColumns();
        $allowed = ['is_top', 'is_featured', 'is_recommended', 'is_locked'];
        $sets = [];
        $params = [':id' => $id];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $flags)) {
                $sets[] = "`{$key}` = :{$key}";
                $params[':' . $key] = !empty($flags[$key]) ? 1 : 0;
            }
        }
        if (array_key_exists('featured_reason', $flags)) {
            $sets[] = '`featured_reason` = :featured_reason';
            $params[':featured_reason'] = trim((string)$flags['featured_reason']) !== '' ? mb_substr(trim((string)$flags['featured_reason']), 0, 255) : null;
        }
        if (array_key_exists('recommended_reason', $flags)) {
            $sets[] = '`recommended_reason` = :recommended_reason';
            $params[':recommended_reason'] = trim((string)$flags['recommended_reason']) !== '' ? mb_substr(trim((string)$flags['recommended_reason']), 0, 255) : null;
        }
        if (array_key_exists('top_scope', $flags)) {
            $scope = in_array((string)$flags['top_scope'], ['none','section','global'], true) ? (string)$flags['top_scope'] : 'none';
            $sets[] = "`top_scope` = :top_scope";
            $params[':top_scope'] = $scope;
            if ($scope !== 'none' && !array_key_exists('is_top', $flags)) {
                $sets[] = "`is_top` = 1";
            }
            if ($scope === 'none' && !array_key_exists('is_top', $flags)) {
                $sets[] = "`is_top` = 0";
            }
        }
        if (array_key_exists('is_top', $flags) && empty($flags['is_top']) && !array_key_exists('top_scope', $flags)) {
            $sets[] = "`top_scope` = 'none'";
        }
        if (!$sets) return;
        $sql = 'UPDATE threads SET ' . implode(', ', $sets) . ', updated_at=NOW() WHERE id=:id';
        Database::connection()->prepare($sql)->execute($params);
    }

    public function incrementReportCount(int $threadId): void
    {
        if ($threadId <= 0) return;
        try {
            Database::connection()->prepare("UPDATE threads SET report_count = COALESCE(report_count,0) + 1 WHERE id=:id")->execute([':id'=>$threadId]);
        } catch (\Throwable $e) {}
    }

    public function markReadComplete(int $threadId): void
    {
        if ($threadId <= 0) return;
        try {
            Database::connection()->prepare("UPDATE threads SET read_complete_count = COALESCE(read_complete_count,0) + 1 WHERE id=:id")->execute([':id'=>$threadId]);
        } catch (\Throwable $e) {}
    }

    public function topGlobal(int $limit = 8): array
    {
        return $this->topList("t.top_scope = 'global'", [], $limit);
    }

    public function topForSection(int $sectionId, int $limit = 8): array
    {
        return $this->topList("t.section_id = :section_id AND t.top_scope = 'section'", [':section_id' => $sectionId], $limit);
    }

    private function topList(string $whereExtra, array $params, int $limit): array
    {
        $sql = "SELECT t.id, t.title, t.section_id, t.top_scope, t.created_at, t.view_count, t.reply_count, s.name AS section_name, s.is_question AS section_is_question
                FROM threads t
                LEFT JOIN sections s ON s.id = t.section_id
                WHERE t.status = 'published' AND t.is_top = 1 AND {$whereExtra}
                ORDER BY t.updated_at DESC, t.created_at DESC
                LIMIT :limit";
        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function incrementReplyCount(int $threadId): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE threads SET reply_count = reply_count + 1, last_reply_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':id' => $threadId]);
    }

    public function incrementViewCount(int $threadId): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE threads SET view_count = view_count + 1 WHERE id = :id"
        );
        $stmt->execute([':id' => $threadId]);
    }

    public function refreshSectionThreadCount(int $sectionId): void
    {
        if ($sectionId > 0) {
            $this->updateThreadCount($sectionId);
        }
    }

    private function sectionIdForThread(int $threadId): int
    {
        $stmt = Database::connection()->prepare("SELECT section_id FROM threads WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $threadId]);
        return (int) $stmt->fetchColumn();
    }

    private function updateThreadCount(int $sectionId): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE sections SET thread_count = (
                SELECT COUNT(*) FROM threads WHERE section_id = :sid AND status = 'published'
            ) WHERE id = :sid2"
        );
        $stmt->execute([':sid' => $sectionId, ':sid2' => $sectionId]);
    }
}
