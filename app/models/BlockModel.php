<?php

namespace App\Models;

use App\Core\Database;

class BlockModel
{
    public function block(int $blockerId, int $blockedId): void
    {
        if ($blockerId <= 0 || $blockedId <= 0 || $blockerId === $blockedId) return;
        Database::connection()->prepare("INSERT IGNORE INTO user_blocks (blocker_id, blocked_id, created_at) VALUES (:a,:b,NOW())")->execute([':a'=>$blockerId, ':b'=>$blockedId]);
    }

    public function unblock(int $blockerId, int $blockedId): void
    {
        Database::connection()->prepare("DELETE FROM user_blocks WHERE blocker_id=:a AND blocked_id=:b")->execute([':a'=>$blockerId, ':b'=>$blockedId]);
    }

    public function isBlocked(int $blockerId, int $blockedId): bool
    {
        if ($blockerId <= 0 || $blockedId <= 0) return false;
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM user_blocks WHERE blocker_id=:a AND blocked_id=:b");
        $stmt->execute([':a'=>$blockerId, ':b'=>$blockedId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function blockedMap(int $blockerId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($blockerId <= 0 || !$ids) return [];
        $params=[':a'=>$blockerId]; $ph=[];
        foreach($ids as $i=>$id){$k=':id'.$i;$ph[]=$k;$params[$k]=$id;}
        $stmt=Database::connection()->prepare("SELECT blocked_id FROM user_blocks WHERE blocker_id=:a AND blocked_id IN (".implode(',',$ph).")");
        $stmt->execute($params);
        return array_fill_keys(array_map('intval',$stmt->fetchAll(\PDO::FETCH_COLUMN)), true);
    }

    public function blocksByUser(int $userId, int $limit = 100): array
    {
        $stmt = Database::connection()->prepare("SELECT b.*, u.id AS user_id, u.username, u.nickname, u.avatar, u.bio FROM user_blocks b JOIN users u ON u.id=b.blocked_id WHERE b.blocker_id=:uid ORDER BY b.created_at DESC LIMIT :limit");
        $stmt->bindValue(':uid',$userId,\PDO::PARAM_INT); $stmt->bindValue(':limit',$limit,\PDO::PARAM_INT); $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
