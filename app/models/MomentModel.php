<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class MomentModel
{
    public function ensureTables(): void
    {
        
    }

    public function profile(int $userId): array
    {
        if ($userId <= 0) return [];
        $stmt = Database::connection()->prepare("SELECT u.id AS user_id, u.public_id, u.username, u.nickname, u.avatar, u.bio, p.cover_url
            FROM users u
            LEFT JOIN moment_profiles p ON p.user_id=u.id
            WHERE u.id=:uid LIMIT 1");
        $stmt->execute([':uid'=>$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if ($row && empty($row['cover_url'])) $row['cover_url'] = '';
        return $row;
    }

    public function setCover(int $userId, string $url): void
    {
        if ($userId <= 0 || $url === '' || !str_starts_with($url, '/uploads/')) throw new \RuntimeException('背景图无效');
        Database::connection()->prepare("INSERT INTO moment_profiles (user_id,cover_url,created_at,updated_at) VALUES (:uid,:url,NOW(),NOW())
            ON DUPLICATE KEY UPDATE cover_url=VALUES(cover_url), updated_at=NOW()")
            ->execute([':uid'=>$userId, ':url'=>$url]);
    }

    public function create(int $userId, string $content, array $images = []): array
    {
        $content = trim($content);
        $images = array_values(array_filter(array_map('strval', $images), fn($v) => $v !== '' && str_starts_with($v, '/uploads/')));
        if ($userId <= 0) throw new \RuntimeException('请先登录');
        if ($content === '' && !$images) throw new \RuntimeException('请输入内容或上传图片');
        if (mb_strlen($content) > 1000) throw new \RuntimeException('朋友圈内容不能超过 1000 字');
        $db = Database::connection();
        $stmt = $db->prepare("INSERT INTO moments (user_id,content,images_json,visibility,status,created_at,updated_at) VALUES (:uid,:content,:images,'friends','published',NOW(),NOW())");
        $stmt->execute([':uid'=>$userId, ':content'=>$content, ':images'=>json_encode($images, JSON_UNESCAPED_UNICODE)]);
        return $this->one((int)$db->lastInsertId(), $userId) ?: [];
    }

    public function feed(int $userId, int $limit = 30, int $beforeId = 0): array
    {
        if ($userId <= 0) return [];
        $limit = max(1, min(60, $limit));
        $where = "m.status='published' AND (m.user_id=:uid OR EXISTS(SELECT 1 FROM user_follows f1 INNER JOIN user_follows f2 ON f2.follower_id=f1.following_id AND f2.following_id=f1.follower_id WHERE f1.follower_id=:uid AND f1.following_id=m.user_id))";
        if ($beforeId > 0) $where .= " AND m.id<:before";
        $sql = "SELECT m.*, u.public_id, u.username, u.nickname, u.avatar
                FROM moments m JOIN users u ON u.id=m.user_id
                WHERE {$where}
                ORDER BY m.id DESC LIMIT :limit";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        if ($beforeId > 0) $stmt->bindValue(':before', $beforeId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalize'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function one(int $id, int $viewerId): ?array
    {
        if ($id <= 0 || $viewerId <= 0) return null;
        $stmt = Database::connection()->prepare("SELECT m.*, u.public_id, u.username, u.nickname, u.avatar
            FROM moments m JOIN users u ON u.id=m.user_id
            WHERE m.id=:id AND m.status='published' AND (m.user_id=:viewer OR EXISTS(SELECT 1 FROM user_follows f1 INNER JOIN user_follows f2 ON f2.follower_id=f1.following_id AND f2.following_id=f1.follower_id WHERE f1.follower_id=:viewer AND f1.following_id=m.user_id)) LIMIT 1");
        $stmt->execute([':id'=>$id, ':viewer'=>$viewerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->normalize($row) : null;
    }

    private function normalize(array $row): array
    {
        $row['images'] = [];
        if (!empty($row['images_json'])) {
            $decoded = json_decode((string)$row['images_json'], true);
            if (is_array($decoded)) $row['images'] = array_values(array_filter(array_map('strval', $decoded)));
        }
        $row['display_name'] = $row['nickname'] ?: $row['username'];
        return $row;
    }
}
