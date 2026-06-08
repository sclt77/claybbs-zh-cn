<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class EngagementModel
{
    public function isFavorited(int $userId, int $threadId): bool
    {
        if ($userId <= 0 || $threadId <= 0) return false;
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM thread_favorites WHERE user_id=:uid AND thread_id=:tid");
        $stmt->execute([':uid'=>$userId, ':tid'=>$threadId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function toggleFavorite(int $userId, int $threadId): bool
    {
        if ($userId <= 0 || $threadId <= 0) return false;
        $db = Database::connection();
        if ($this->isFavorited($userId, $threadId)) {
            $db->prepare("DELETE FROM thread_favorites WHERE user_id=:uid AND thread_id=:tid")->execute([':uid'=>$userId, ':tid'=>$threadId]);
            $this->refreshThreadFavoriteCount($threadId);
            return false;
        }
        $db->prepare("INSERT IGNORE INTO thread_favorites (user_id, thread_id, created_at) VALUES (:uid,:tid,NOW())")->execute([':uid'=>$userId, ':tid'=>$threadId]);
        $this->refreshThreadFavoriteCount($threadId);
        return true;
    }

    public function isLiked(int $userId, string $type, int $targetId): bool
    {
        if ($userId <= 0 || $targetId <= 0) return false;
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM content_likes WHERE user_id=:uid AND target_type=:type AND target_id=:tid");
        $stmt->execute([':uid'=>$userId, ':type'=>$type, ':tid'=>$targetId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function toggleLike(int $userId, string $type, int $targetId): bool
    {
        if ($userId <= 0 || $targetId <= 0 || !in_array($type, ['thread','post'], true)) return false;
        $db = Database::connection();
        if ($this->isLiked($userId, $type, $targetId)) {
            $db->prepare("DELETE FROM content_likes WHERE user_id=:uid AND target_type=:type AND target_id=:tid")->execute([':uid'=>$userId, ':type'=>$type, ':tid'=>$targetId]);
            $this->refreshLikeCount($type, $targetId);
            return false;
        }
        $db->prepare("INSERT IGNORE INTO content_likes (user_id, target_type, target_id, created_at) VALUES (:uid,:type,:tid,NOW())")->execute([':uid'=>$userId, ':type'=>$type, ':tid'=>$targetId]);
        $this->refreshLikeCount($type, $targetId);
        $this->notifyLike($userId, $type, $targetId);
        return true;
    }

    public function hasReported(int $userId, string $type, int $targetId): bool
    {
        if ($userId <= 0 || $targetId <= 0 || !in_array($type, ['thread','post'], true)) return false;
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM content_reports WHERE user_id=:uid AND target_type=:type AND target_id=:tid");
        $stmt->execute([':uid'=>$userId, ':type'=>$type, ':tid'=>$targetId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function report(int $userId, string $type, int $targetId, string $reason): bool
    {
        if ($userId <= 0 || $targetId <= 0 || !in_array($type, ['thread','post'], true)) return false;
        $reason = trim(mb_substr($reason, 0, 255));
        if ($reason === '') return false;
        if ($this->hasReported($userId, $type, $targetId)) return false;
        Database::connection()->prepare("INSERT INTO content_reports (user_id,target_type,target_id,reason,status,created_at) VALUES (:uid,:type,:tid,:reason,'pending',NOW())")
            ->execute([':uid'=>$userId, ':type'=>$type, ':tid'=>$targetId, ':reason'=>$reason]);
        try {
            $threadId = $type === 'thread' ? $targetId : (int)Database::connection()->query('SELECT thread_id FROM posts WHERE id=' . (int)$targetId . ' LIMIT 1')->fetchColumn();
            if ($threadId > 0) (new ThreadModel())->incrementReportCount($threadId);
        } catch (\Throwable $e) {}
        return true;
    }

    public function likedMap(int $userId, string $type, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($userId <= 0 || !$ids) return [];
        $params = [':uid'=>$userId, ':type'=>$type]; $ph=[];
        foreach ($ids as $i=>$id) { $k=':id'.$i; $ph[]=$k; $params[$k]=$id; }
        $stmt = Database::connection()->prepare("SELECT target_id FROM content_likes WHERE user_id=:uid AND target_type=:type AND target_id IN (" . implode(',', $ph) . ")");
        $stmt->execute($params);
        return array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
    }

    public function favoritesByUser(int $userId, int $limit, int $offset): array
    {
        $stmt = Database::connection()->prepare("SELECT t.*, t.section_id AS section_id, u.nickname AS author_name, u.avatar AS author_avatar, COALESCE(NULLIF(uv.display_name,''), vt.name) AS verification_name, vt.color AS verification_color, vt.description AS verification_description, s.name AS section_name, f.created_at AS favorited_at, (SELECT r.name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=t.user_id AND (ur.expires_at IS NULL OR ur.expires_at > NOW()) ORDER BY r.level DESC LIMIT 1) AS author_role_label FROM thread_favorites f JOIN threads t ON t.id=f.thread_id LEFT JOIN users u ON u.id=t.user_id LEFT JOIN user_verifications uv ON uv.user_id=t.user_id AND uv.status='active' LEFT JOIN verification_types vt ON vt.id=uv.type_id AND vt.status='active' LEFT JOIN sections s ON s.id=t.section_id WHERE f.user_id=:uid AND t.status='published' ORDER BY f.created_at DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':uid',$userId,PDO::PARAM_INT); $stmt->bindValue(':limit',$limit,PDO::PARAM_INT); $stmt->bindValue(':offset',$offset,PDO::PARAM_INT); $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countFavoritesByUser(int $userId): int
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM thread_favorites f JOIN threads t ON t.id=f.thread_id WHERE f.user_id=:uid AND t.status='published'");
        $stmt->execute([':uid'=>$userId]);
        return (int)$stmt->fetchColumn();
    }

    private function refreshThreadFavoriteCount(int $threadId): void
    {
        Database::connection()->prepare("UPDATE threads SET favorite_count=(SELECT COUNT(*) FROM thread_favorites WHERE thread_id=:id) WHERE id=:id2")->execute([':id'=>$threadId, ':id2'=>$threadId]);
    }

    private function refreshLikeCount(string $type, int $targetId): void
    {
        $table = $type === 'thread' ? 'threads' : 'posts';
        Database::connection()->prepare("UPDATE {$table} SET like_count=(SELECT COUNT(*) FROM content_likes WHERE target_type=:type AND target_id=:id) WHERE id=:id2")->execute([':type'=>$type, ':id'=>$targetId, ':id2'=>$targetId]);
    }

    private function notifyLike(int $actorId, string $type, int $targetId): void
    {
        try {
            if ($type === 'thread') {
                $stmt = Database::connection()->prepare("SELECT user_id,title FROM threads WHERE id=:id LIMIT 1");
                $stmt->execute([':id'=>$targetId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && (int)$row['user_id'] !== $actorId && (new NotificationSettingModel())->enabled((int)$row['user_id'], 'like')) (new SystemMessageModel())->createPersonal((int)$row['user_id'], '有人点赞了你的帖子', '你的帖子《' . $row['title'] . '》收到了新的点赞。', 0, 'like', '/index.php?path=thread&id=' . $targetId, 'thread', $targetId);
            } else {
                $stmt = Database::connection()->prepare("SELECT p.user_id,p.thread_id,t.title FROM posts p LEFT JOIN threads t ON t.id=p.thread_id WHERE p.id=:id LIMIT 1");
                $stmt->execute([':id'=>$targetId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && (int)$row['user_id'] !== $actorId && (new NotificationSettingModel())->enabled((int)$row['user_id'], 'like')) (new SystemMessageModel())->createPersonal((int)$row['user_id'], '有人点赞了你的回复', '你在帖子《' . ($row['title'] ?? '') . '》中的回复收到了新的点赞。', 0, 'like', '/index.php?path=thread&id=' . (int)($row['thread_id'] ?? 0) . '#post-' . $targetId, 'post', $targetId);
            }
        } catch (\Throwable $e) {}
    }
}
