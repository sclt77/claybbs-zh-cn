<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class FollowModel
{
    public function follow(int $followerId, int $followingId): void
    {
        if ($followerId <= 0 || $followingId <= 0 || $followerId === $followingId) return;
        Database::connection()->prepare("INSERT IGNORE INTO user_follows (follower_id, following_id, created_at) VALUES (:follower, :following, NOW())")
            ->execute([':follower'=>$followerId, ':following'=>$followingId]);
    }

    public function unfollow(int $followerId, int $followingId): void
    {
        Database::connection()->prepare("DELETE FROM user_follows WHERE follower_id = :follower AND following_id = :following")
            ->execute([':follower'=>$followerId, ':following'=>$followingId]);
    }

    public function isFollowing(int $followerId, int $followingId): bool
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM user_follows WHERE follower_id = :follower AND following_id = :following");
        $stmt->execute([':follower'=>$followerId, ':following'=>$followingId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function followerCount(int $userId): int
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM user_follows WHERE following_id = :id");
        $stmt->execute([':id'=>$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function followingCount(int $userId): int
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM user_follows WHERE follower_id = :id");
        $stmt->execute([':id'=>$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function followers(int $userId, int $limit = 50, string $kw = ''): array
    {
        $where = 'WHERE f.following_id = :id';
        $params = [':id' => $userId];
        $kw = trim($kw);
        if ($kw !== '') {
            $where .= ' AND (u.username LIKE :kw OR u.nickname LIKE :kw)';
            $params[':kw'] = "%{$kw}%";
        }
        $stmt = Database::connection()->prepare("SELECT f.*, u.id AS user_id, u.username, u.nickname, u.avatar, u.bio, COALESCE(NULLIF(uv.display_name,''), vt.name) AS verification_name, vt.color AS verification_color, vt.description AS verification_description FROM user_follows f JOIN users u ON u.id = f.follower_id LEFT JOIN user_verifications uv ON uv.user_id=u.id AND uv.status='active' LEFT JOIN verification_types vt ON vt.id=uv.type_id AND vt.status='active' {$where} ORDER BY f.created_at DESC LIMIT :limit");
        foreach ($params as $key => $value) $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function following(int $userId, int $limit = 50, string $kw = ''): array
    {
        $where = 'WHERE f.follower_id = :id';
        $params = [':id' => $userId];
        $kw = trim($kw);
        if ($kw !== '') {
            $where .= ' AND (u.username LIKE :kw OR u.nickname LIKE :kw)';
            $params[':kw'] = "%{$kw}%";
        }
        $stmt = Database::connection()->prepare("SELECT f.*, u.id AS user_id, u.username, u.nickname, u.avatar, u.bio, COALESCE(NULLIF(uv.display_name,''), vt.name) AS verification_name, vt.color AS verification_color, vt.description AS verification_description FROM user_follows f JOIN users u ON u.id = f.following_id LEFT JOIN user_verifications uv ON uv.user_id=u.id AND uv.status='active' LEFT JOIN verification_types vt ON vt.id=uv.type_id AND vt.status='active' {$where} ORDER BY f.created_at DESC LIMIT :limit");
        foreach ($params as $key => $value) $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function followingMap(int $followerId, array $targetIds): array
    {
        $targetIds = array_values(array_unique(array_filter(array_map('intval', $targetIds))));
        if ($followerId <= 0 || !$targetIds) return [];
        $placeholders=[]; $params=[':follower'=>$followerId];
        foreach ($targetIds as $i=>$id) { $ph=':id'.$i; $placeholders[]=$ph; $params[$ph]=$id; }
        $stmt = Database::connection()->prepare("SELECT following_id FROM user_follows WHERE follower_id = :follower AND following_id IN (" . implode(',', $placeholders) . ")");
        $stmt->execute($params);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        return array_fill_keys($ids, true);
    }

    public function followingIds(int $userId): array
    {
        $stmt = Database::connection()->prepare("SELECT follower_id FROM user_follows WHERE following_id = :id ORDER BY created_at DESC");
        $stmt->execute([':id' => $userId]);
        return array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    }

    public function recentFollowerMessages(int $userId, int $limit = 20): array
    {
        return $this->followers($userId, $limit);
    }
}
