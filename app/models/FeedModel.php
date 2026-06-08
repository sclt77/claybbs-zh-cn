<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class FeedModel
{
    public function followingFeed(int $userId, int $limit = 15, int $offset = 0): array
    {
        $sql = "(
            SELECT 'thread' AS item_type, t.id AS item_id, t.user_id, t.title, t.summary, t.content, t.created_at, NULL AS thread_id, NULL AS thread_title, u.username, u.nickname, u.avatar, s.name AS section_name
            FROM threads t
            INNER JOIN user_follows f ON f.following_id=t.user_id AND f.follower_id=:uid1
            LEFT JOIN user_blocks b ON b.blocker_id=:uidb1 AND b.blocked_id=t.user_id
            LEFT JOIN users u ON u.id=t.user_id
            LEFT JOIN sections s ON s.id=t.section_id
            WHERE t.status='published' AND b.id IS NULL
        ) UNION ALL (
            SELECT 'post' AS item_type, p.id AS item_id, p.user_id, NULL AS title, NULL AS summary, p.content, p.created_at, p.thread_id, t.title AS thread_title, u.username, u.nickname, u.avatar, s.name AS section_name
            FROM posts p
            INNER JOIN user_follows f ON f.following_id=p.user_id AND f.follower_id=:uid2
            LEFT JOIN user_blocks b ON b.blocker_id=:uidb2 AND b.blocked_id=p.user_id
            INNER JOIN threads t ON t.id=p.thread_id AND t.status='published'
            LEFT JOIN users u ON u.id=p.user_id
            LEFT JOIN sections s ON s.id=t.section_id
            WHERE p.status='published' AND b.id IS NULL
        ) ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':uid1', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uidb1', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uidb2', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countFollowingFeed(int $userId): int
    {
        $db = Database::connection();
        $a = $db->prepare("SELECT COUNT(*) FROM threads t INNER JOIN user_follows f ON f.following_id=t.user_id AND f.follower_id=:uid LEFT JOIN user_blocks b ON b.blocker_id=:uidb AND b.blocked_id=t.user_id WHERE t.status='published' AND b.id IS NULL");
        $a->execute([':uid'=>$userId, ':uidb'=>$userId]);
        $b = $db->prepare("SELECT COUNT(*) FROM posts p INNER JOIN user_follows f ON f.following_id=p.user_id AND f.follower_id=:uid LEFT JOIN user_blocks b ON b.blocker_id=:uidb AND b.blocked_id=p.user_id INNER JOIN threads t ON t.id=p.thread_id AND t.status='published' WHERE p.status='published' AND b.id IS NULL");
        $b->execute([':uid'=>$userId, ':uidb'=>$userId]);
        return (int)$a->fetchColumn() + (int)$b->fetchColumn();
    }
}
