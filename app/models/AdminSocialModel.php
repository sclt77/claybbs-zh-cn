<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class AdminSocialModel
{
    public function follows(string $keyword = '', int $page = 1, int $pageSize = 30): array
    {
        [$where, $params] = $this->where($keyword);
        $offset = ($page - 1) * $pageSize;
        $sql = "SELECT f.*, a.username AS follower_username, a.nickname AS follower_nickname, b.username AS following_username, b.nickname AS following_nickname
                FROM user_follows f
                LEFT JOIN users a ON a.id = f.follower_id
                LEFT JOIN users b ON b.id = f.following_id
                {$where}
                ORDER BY f.created_at DESC, f.id DESC LIMIT :limit OFFSET :offset";
        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $k=>$v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countFollows(string $keyword = ''): int
    {
        [$where, $params] = $this->where($keyword);
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM user_follows f LEFT JOIN users a ON a.id=f.follower_id LEFT JOIN users b ON b.id=f.following_id {$where}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function deleteFollow(int $id): void
    {
        Database::connection()->prepare("DELETE FROM user_follows WHERE id = :id")->execute([':id'=>$id]);
    }

    private function where(string $keyword): array
    {
        if ($keyword === '') return ['WHERE 1=1', []];
        return ["WHERE a.username LIKE :kw OR a.nickname LIKE :kw OR b.username LIKE :kw OR b.nickname LIKE :kw", [':kw'=>"%{$keyword}%"]];
    }
}
