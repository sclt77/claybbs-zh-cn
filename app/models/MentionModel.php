<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class MentionModel
{
    public function extractNames(string $content): array
    {
        $plain = html_entity_decode(strip_tags($content), ENT_QUOTES, 'UTF-8');
        preg_match_all('/@([\p{L}\p{N}_\x{4e00}-\x{9fa5}]{2,30})/u', $plain, $matches);
        return array_values(array_unique($matches[1] ?? []));
    }

    public function findUsersByNames(array $names): array
    {
        $names = array_values(array_unique(array_filter($names)));
        if (!$names) return [];
        $placeholders = [];
        $params = [];
        foreach ($names as $i => $name) {
            $ph = ':name_' . $i;
            $placeholders[] = $ph;
            $params[$ph] = $name;
        }
        $sql = "SELECT id, username, nickname FROM users WHERE username IN (" . implode(',', $placeholders) . ") OR nickname IN (" . implode(',', $placeholders) . ")";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(int $userId, int $actorId, int $threadId, ?int $postId, string $type): void
    {
        if ($userId <= 0 || $actorId <= 0 || $userId === $actorId) return;
        Database::connection()->prepare(
            "INSERT INTO mentions (user_id, actor_id, thread_id, post_id, type, created_at) VALUES (:user_id, :actor_id, :thread_id, :post_id, :type, NOW())"
        )->execute([':user_id'=>$userId, ':actor_id'=>$actorId, ':thread_id'=>$threadId, ':post_id'=>$postId, ':type'=>$type]);
    }

    public function notifyMentioned(string $content, int $actorId, int $threadId, ?int $postId, string $threadTitle): void
    {
        $users = $this->findUsersByNames($this->extractNames($content));
        foreach ($users as $user) {
            $uid = (int)$user['id'];
            if ($uid === $actorId) continue;
            $this->create($uid, $actorId, $threadId, $postId, 'mention');
            if ((new NotificationSettingModel())->enabled($uid, 'mention')) {
                (new SystemMessageModel())->createPersonal($uid, '有人提到了你', '你在帖子《' . $threadTitle . '》中被 @ 提到，快去看看吧。' . ($postId ? ' #post-' . $postId : ''), 1);
            }
        }
    }

    public function notifyReply(int $targetUserId, int $actorId, int $threadId, ?int $postId, string $threadTitle): void
    {
        if ($targetUserId <= 0 || $targetUserId === $actorId) return;
        $this->create($targetUserId, $actorId, $threadId, $postId, 'reply');
        if ((new NotificationSettingModel())->enabled($targetUserId, 'reply')) {
            (new SystemMessageModel())->createPersonal($targetUserId, '有人回复了你', '你在帖子《' . $threadTitle . '》中的回复收到了新回复。' . ($postId ? ' #post-' . $postId : ''), 1);
        }
    }
}
