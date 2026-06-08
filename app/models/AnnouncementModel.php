<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class AnnouncementModel
{
    public function ensurePopupColumns(): void
    {
        
    }

    public function active(int $limit = 5): array
    {
        $this->ensurePopupColumns();
        $stmt = Database::connection()->prepare(
            "SELECT id, title, content, image, url, sort_order, is_pinned, created_at
             FROM announcements
             WHERE status = 'active'
             ORDER BY is_pinned DESC, sort_order ASC, id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function popupForUser(int $userId = 0, string $visitorKey = ''): ?array
    {
        $this->ensurePopupColumns();
        $sql = "SELECT a.* FROM announcements a WHERE a.status='active' AND a.popup_enabled=1 AND (
            a.popup_once=0 OR NOT EXISTS(SELECT 1 FROM announcement_reads r WHERE r.announcement_id=a.id AND ((:uid>0 AND r.user_id=:uid) OR (:vkey<>'' AND r.visitor_key=:vkey)))
        ) ORDER BY a.is_pinned DESC, a.sort_order ASC, a.id DESC LIMIT 1";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([':uid'=>$userId, ':vkey'=>$visitorKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function markRead(int $id, int $userId = 0, string $visitorKey = ''): void
    {
        $this->ensurePopupColumns();
        if ($id <= 0) return;
        Database::connection()->prepare('INSERT IGNORE INTO announcement_reads (announcement_id,user_id,visitor_key,read_at) VALUES (:id,:uid,:vkey,NOW())')
            ->execute([':id'=>$id, ':uid'=>$userId > 0 ? $userId : null, ':vkey'=>$visitorKey !== '' ? $visitorKey : null]);
    }

    public function find(int $id): ?array
    {
        $this->ensurePopupColumns();
        $stmt = Database::connection()->prepare(
            "SELECT id, title, content, image, url, sort_order, is_pinned, created_at
             FROM announcements
             WHERE id = :id AND status = 'active'
             LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
