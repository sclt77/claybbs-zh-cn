<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class DraftModel
{
    public function listByUser(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT d.*, s.name AS section_name FROM thread_drafts d LEFT JOIN sections s ON s.id = d.section_id WHERE d.user_id = :user_id AND COALESCE(d.is_autosave,0)=0 ORDER BY d.updated_at DESC, d.id DESC"
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchByUser(int $userId, string $keyword = '', int $sectionId = 0): array
    {
        $sql = "SELECT d.*, s.name AS section_name FROM thread_drafts d LEFT JOIN sections s ON s.id = d.section_id WHERE d.user_id = :user_id AND COALESCE(d.is_autosave,0)=0";
        $params = [':user_id' => $userId];
        if ($keyword !== '') {
            $sql .= " AND (d.title LIKE :kw OR d.content LIKE :kw OR s.name LIKE :kw)";
            $params[':kw'] = '%' . $keyword . '%';
        }
        if ($sectionId > 0) {
            $sql .= " AND d.section_id = :section_id";
            $params[':section_id'] = $sectionId;
        }
        $sql .= " ORDER BY d.updated_at DESC, d.id DESC";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sectionsForUser(int $userId): array
    {
        $stmt = Database::connection()->prepare("SELECT DISTINCT s.id, s.name FROM thread_drafts d INNER JOIN sections s ON s.id=d.section_id WHERE d.user_id=:user_id AND COALESCE(d.is_autosave,0)=0 ORDER BY s.sort_order ASC, s.id ASC");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteManyForUser(array $ids, int $userId): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
        if (!$ids) return 0;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare("DELETE FROM thread_drafts WHERE user_id=? AND COALESCE(is_autosave,0)=0 AND id IN ($placeholders)");
        $stmt->execute(array_merge([$userId], $ids));
        return $stmt->rowCount();
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM thread_drafts WHERE id = :id AND user_id = :user_id LIMIT 1");
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $userId, ?int $id, int $sectionId, string $title, string $content): int
    {
        if ($id && $this->findForUser($id, $userId)) {
            Database::connection()->prepare("UPDATE thread_drafts SET section_id=:section_id, title=:title, content=:content, review_status=NULL, review_reason=NULL, review_suggestion=NULL, review_categories=NULL, reviewed_at=NULL, updated_at=NOW() WHERE id=:id AND user_id=:user_id")
                ->execute([':section_id'=>$sectionId ?: null, ':title'=>$title, ':content'=>$content, ':id'=>$id, ':user_id'=>$userId]);
            return $id;
        }
        Database::connection()->prepare("INSERT INTO thread_drafts (user_id, section_id, title, content, is_autosave, created_at, updated_at) VALUES (:user_id, :section_id, :title, :content, 0, NOW(), NOW())")
            ->execute([':user_id'=>$userId, ':section_id'=>$sectionId ?: null, ':title'=>$title, ':content'=>$content]);
        return (int)Database::connection()->lastInsertId();
    }


    public function findAutosaveForUser(int $userId): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM thread_drafts WHERE user_id = :user_id AND COALESCE(is_autosave,0)=1 ORDER BY updated_at DESC, id DESC LIMIT 1");
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function saveAutosave(int $userId, int $sectionId, string $title, string $content): int
    {
        $existing = $this->findAutosaveForUser($userId);
        if ($existing) {
            $id = (int)$existing['id'];
            Database::connection()->prepare("UPDATE thread_drafts SET section_id=:section_id, title=:title, content=:content, is_autosave=1, review_status=NULL, review_reason=NULL, review_suggestion=NULL, review_categories=NULL, reviewed_at=NULL, updated_at=NOW() WHERE id=:id AND user_id=:user_id")
                ->execute([':section_id'=>$sectionId ?: null, ':title'=>$title, ':content'=>$content, ':id'=>$id, ':user_id'=>$userId]);
            return $id;
        }
        Database::connection()->prepare("INSERT INTO thread_drafts (user_id, section_id, title, content, is_autosave, created_at, updated_at) VALUES (:user_id, :section_id, :title, :content, 1, NOW(), NOW())")
            ->execute([':user_id'=>$userId, ':section_id'=>$sectionId ?: null, ':title'=>$title, ':content'=>$content]);
        return (int)Database::connection()->lastInsertId();
    }

    public function clearAutosaveForUser(int $userId): void
    {
        Database::connection()->prepare("DELETE FROM thread_drafts WHERE user_id=:user_id AND COALESCE(is_autosave,0)=1")->execute([':user_id'=>$userId]);
    }

    public function promoteAutosaveToDraft(int $userId, int $autosaveId, int $sectionId, string $title, string $content): int
    {
        if ($autosaveId > 0 && $this->findForUser($autosaveId, $userId)) {
            Database::connection()->prepare("UPDATE thread_drafts SET section_id=:section_id, title=:title, content=:content, is_autosave=0, review_status=NULL, review_reason=NULL, review_suggestion=NULL, review_categories=NULL, reviewed_at=NULL, updated_at=NOW() WHERE id=:id AND user_id=:user_id")
                ->execute([':section_id'=>$sectionId ?: null, ':title'=>$title, ':content'=>$content, ':id'=>$autosaveId, ':user_id'=>$userId]);
            return $autosaveId;
        }
        return $this->save($userId, null, $sectionId, $title, $content);
    }

    public function saveRejected(int $userId, ?int $id, int $sectionId, string $title, string $content, array $review): int
    {
        $params = [
            ':section_id' => $sectionId ?: null,
            ':title' => $title,
            ':content' => $content,
            ':reason' => (string)($review['reason'] ?? ''),
            ':suggestion' => (string)($review['suggestion'] ?? ''),
            ':categories' => is_array($review['categories'] ?? null) ? implode(',', $review['categories']) : (string)($review['categories'] ?? ''),
            ':user_id' => $userId,
        ];
        if ($id && $this->findForUser($id, $userId)) {
            $params[':id'] = $id;
            Database::connection()->prepare("UPDATE thread_drafts SET section_id=:section_id,title=:title,content=:content,review_status='ai_rejected',review_reason=:reason,review_suggestion=:suggestion,review_categories=:categories,reviewed_at=NOW(),updated_at=NOW() WHERE id=:id AND user_id=:user_id")->execute($params);
            return $id;
        }
        Database::connection()->prepare("INSERT INTO thread_drafts (user_id,section_id,title,content,review_status,review_reason,review_suggestion,review_categories,reviewed_at,created_at,updated_at) VALUES (:user_id,:section_id,:title,:content,'ai_rejected',:reason,:suggestion,:categories,NOW(),NOW(),NOW())")->execute($params);
        return (int)Database::connection()->lastInsertId();
    }

    public function deleteForUser(int $id, int $userId): void
    {
        Database::connection()->prepare("DELETE FROM thread_drafts WHERE id = :id AND user_id = :user_id")->execute([':id'=>$id, ':user_id'=>$userId]);
    }
}
