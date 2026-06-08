<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class PrivateChatModel
{
    public function pair(int $a, int $b): array
    {
        if ($a <= 0 || $b <= 0 || $a === $b) {
            throw new \RuntimeException('无效的会话用户');
        }
        return [min($a, $b), max($a, $b)];
    }

    public function areFriends(int $a, int $b): bool
    {
        if ($a <= 0 || $b <= 0 || $a === $b) return false;
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM user_follows f1 INNER JOIN user_follows f2 ON f2.follower_id=f1.following_id AND f2.following_id=f1.follower_id WHERE f1.follower_id=:a AND f1.following_id=:b");
        $stmt->execute([':a'=>$a, ':b'=>$b]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function conversationFor(int $userId, int $peerId, bool $create = true): ?array
    {
        [$low, $high] = $this->pair($userId, $peerId);
        $db = Database::connection();
        $stmt = $db->prepare("SELECT * FROM private_conversations WHERE user_low_id=:low AND user_high_id=:high LIMIT 1");
        $stmt->execute([':low'=>$low, ':high'=>$high]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row || !$create) return $row ?: null;
        $db->prepare("INSERT INTO private_conversations (user_low_id,user_high_id,created_at,updated_at) VALUES (:low,:high,NOW(),NOW())")
            ->execute([':low'=>$low, ':high'=>$high]);
        $id = (int)$db->lastInsertId();
        $stmt = $db->prepare("SELECT * FROM private_conversations WHERE id=:id");
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function conversations(int $userId): array
    {
        $this->ensureConversationColumns();
        $sql = "SELECT c.*, p.id AS peer_id, p.public_id, p.username, p.nickname, p.avatar,
                       IF(c.user_low_id=:uid, c.pinned_for_low, c.pinned_for_high) AS is_pinned,
                       IF(c.user_low_id=:uid, c.muted_for_low, c.muted_for_high) AS is_muted,
                       CASE WHEN m.status='rejected' AND COALESCE(m.review_reason,'')='举报处理' THEN '[消息已隐藏]' ELSE m.content END AS last_content, m.status AS last_status, m.sender_id AS last_sender_id, m.message_type AS last_message_type, m.media_url AS last_media_url, m.revoked_at AS last_revoked_at,
                       (SELECT COUNT(*) FROM private_messages um WHERE um.conversation_id=c.id AND um.receiver_id=:uid AND um.status='sent' AND um.read_at IS NULL AND IF(c.user_low_id=:uid, c.muted_for_low, c.muted_for_high)=0) AS unread_count
                FROM private_conversations c
                JOIN users p ON p.id = IF(c.user_low_id=:uid, c.user_high_id, c.user_low_id)
                LEFT JOIN private_messages m ON m.id = c.last_message_id
                WHERE (c.user_low_id=:uid AND COALESCE(c.hidden_for_low,0)=0) OR (c.user_high_id=:uid AND COALESCE(c.hidden_for_high,0)=0)
                ORDER BY is_pinned DESC, COALESCE(c.last_message_at,c.updated_at,c.created_at) DESC, c.id DESC
                LIMIT 80";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([':uid'=>$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function friends(int $userId, string $kw = ''): array
    {
        $where = "WHERE f1.follower_id=:uid";
        $params = [':uid'=>$userId];
        $kw = trim($kw);
        if ($kw !== '') {
            $where .= " AND (u.public_id LIKE :kw OR u.username LIKE :kw OR u.nickname LIKE :kw)";
            $params[':kw'] = '%' . $kw . '%';
        }
        $stmt = Database::connection()->prepare("SELECT u.id AS user_id, u.public_id, u.username, u.nickname, u.avatar, u.bio
            FROM user_follows f1
            INNER JOIN user_follows f2 ON f2.follower_id=f1.following_id AND f2.following_id=f1.follower_id
            INNER JOIN users u ON u.id=f1.following_id
            {$where}
            ORDER BY f1.created_at DESC LIMIT 100");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function followingWithRelation(int $userId): array
    {
        $stmt = Database::connection()->prepare("SELECT u.id AS user_id, u.public_id, u.username, u.nickname, u.avatar, u.bio,
            EXISTS(SELECT 1 FROM user_follows b WHERE b.follower_id=u.id AND b.following_id=:uid) AS follows_me
            FROM user_follows f
            INNER JOIN users u ON u.id=f.following_id
            WHERE f.follower_id=:uid
            ORDER BY f.created_at DESC LIMIT 100");
        $stmt->execute([':uid'=>$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function followersWithRelation(int $userId): array
    {
        $stmt = Database::connection()->prepare("SELECT u.id AS user_id, u.public_id, u.username, u.nickname, u.avatar, u.bio,
            EXISTS(SELECT 1 FROM user_follows b WHERE b.follower_id=:uid AND b.following_id=u.id) AS is_following
            FROM user_follows f
            INNER JOIN users u ON u.id=f.follower_id
            WHERE f.following_id=:uid
            ORDER BY f.created_at DESC LIMIT 100");
        $stmt->execute([':uid'=>$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchUsers(int $userId, string $kw, bool $nicknameEnabled = true): array
    {
        $kw = trim($kw);
        if ($kw === '') return [];
        $where = "u.id<>:uid AND u.status='active' AND (u.public_id = :exact OR u.username LIKE :kw" . ($nicknameEnabled ? " OR u.nickname LIKE :kw" : "") . ")";
        $stmt = Database::connection()->prepare("SELECT u.id AS user_id, u.public_id, u.username, u.nickname, u.avatar, u.bio,
            EXISTS(SELECT 1 FROM user_follows f WHERE f.follower_id=:uid AND f.following_id=u.id) AS is_following,
            EXISTS(SELECT 1 FROM user_follows f WHERE f.follower_id=u.id AND f.following_id=:uid) AS follows_me
            FROM users u WHERE {$where} ORDER BY (u.public_id=:exact) DESC, u.id DESC LIMIT 30");
        $stmt->execute([':uid'=>$userId, ':exact'=>$kw, ':kw'=>'%' . $kw . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchMessages(int $userId, string $kw, int $limit = 30): array
    {
        $kw = trim($kw);
        if ($userId <= 0 || $kw === '') return [];
        $limit = max(1, min(80, $limit));
        $sql = "SELECT m.*, p.id AS peer_id, p.public_id, p.username, p.nickname, p.avatar,
                       (SELECT COUNT(*) FROM private_messages cm WHERE cm.conversation_id=m.conversation_id AND cm.status='sent' AND cm.revoked_at IS NULL AND cm.message_type='text' AND cm.content LIKE :kw_count) AS match_count
                FROM private_messages m
                JOIN private_conversations c ON c.id=m.conversation_id
                JOIN users p ON p.id = IF(m.sender_id=:uid, m.receiver_id, m.sender_id)
                WHERE (m.sender_id=:uid OR m.receiver_id=:uid)
                  AND m.status='sent'
                  AND m.revoked_at IS NULL
                  AND m.message_type='text'
                  AND m.content LIKE :kw
                  AND ((c.user_low_id=:uid AND COALESCE(c.hidden_for_low,0)=0) OR (c.user_high_id=:uid AND COALESCE(c.hidden_for_high,0)=0))
                ORDER BY m.id DESC
                LIMIT :limit";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':kw', '%' . $kw . '%');
        $stmt->bindValue(':kw_count', '%' . $kw . '%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cleanupExpiredRevoked(int $conversationId = 0): void
    {
        $db = Database::connection();
        $where = $conversationId > 0 ? 'AND conversation_id=:cid' : '';
        $stmt = $db->prepare("SELECT id, conversation_id FROM private_messages WHERE revoked_at IS NOT NULL AND revoked_at < DATE_SUB(NOW(), INTERVAL 30 SECOND) $where");
        $conversationId > 0 ? $stmt->execute([':cid'=>$conversationId]) : $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return;
        foreach ($rows as $r) {
            $prev = $db->prepare("SELECT id, created_at FROM private_messages WHERE conversation_id=:cid AND id<:mid AND (revoked_at IS NULL OR revoked_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)) ORDER BY id DESC LIMIT 1");
            $prev->execute([':cid'=>(int)$r['conversation_id'], ':mid'=>(int)$r['id']]);
            $p = $prev->fetch(PDO::FETCH_ASSOC);
            if ($p) {
                $db->prepare("UPDATE private_conversations SET last_message_id=:mid, last_message_at=:mat WHERE id=:cid AND last_message_id=:old")->execute([':mid'=>(int)$p['id'], ':mat'=>$p['created_at'], ':cid'=>(int)$r['conversation_id'], ':old'=>(int)$r['id']]);
            } else {
                $db->prepare("UPDATE private_conversations SET last_message_id=NULL, last_message_at=NULL WHERE id=:cid AND last_message_id=:old")->execute([':cid'=>(int)$r['conversation_id'], ':old'=>(int)$r['id']]);
            }
            $db->prepare("DELETE FROM private_messages WHERE id=:id")->execute([':id'=>(int)$r['id']]);
        }
    }

    public function messages(int $userId, int $peerId, int $afterId = 0, int $limit = 60): array
    {
        if ($userId <= 0 || $peerId <= 0 || $userId === $peerId) return [];
        $conv = $this->conversationFor($userId, $peerId, false);
        if (!$conv) return [];
        $this->cleanupExpiredRevoked((int)$conv['id']);
        $sql = "SELECT m.*, u.username AS sender_username, u.nickname AS sender_nickname, u.avatar AS sender_avatar,
                       b.effect_type AS bubble_effect_type, b.effect_params AS bubble_effect_params
                FROM private_messages m
                LEFT JOIN users u ON u.id=m.sender_id
                LEFT JOIN plugin_user_chat_bubbles ub ON ub.user_id=m.sender_id AND ub.is_equipped=1
                LEFT JOIN plugin_chat_bubbles b ON b.id=ub.bubble_id AND b.status='active'
                WHERE m.conversation_id=:cid AND m.id>:after AND (m.revoked_at IS NULL OR m.revoked_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)) AND ((m.receiver_id=:uid AND m.status='sent') OR (m.sender_id=:uid AND (m.status='sent' OR (m.status IN ('pending_review','rejected') AND COALESCE(m.review_reason,'')<>'举报处理')))) ORDER BY m.id ASC LIMIT :limit";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':cid', (int)$conv['id'], PDO::PARAM_INT);
        $stmt->bindValue(':after', $afterId, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function messagesAround(int $userId, int $peerId, int $messageId, int $before = 60, int $after = 60): array
    {
        if ($userId <= 0 || $peerId <= 0 || $messageId <= 0 || $userId === $peerId) return [];
        $conv = $this->conversationFor($userId, $peerId, false);
        if (!$conv) return [];
        $stmt = Database::connection()->prepare("SELECT m.*, u.username AS sender_username, u.nickname AS sender_nickname, u.avatar AS sender_avatar,
                   b.effect_type AS bubble_effect_type, b.effect_params AS bubble_effect_params
            FROM private_messages m
            LEFT JOIN users u ON u.id=m.sender_id
            LEFT JOIN plugin_user_chat_bubbles ub ON ub.user_id=m.sender_id AND ub.is_equipped=1
            LEFT JOIN plugin_chat_bubbles b ON b.id=ub.bubble_id AND b.status='active'
            WHERE m.id=:id AND m.conversation_id=:cid AND (m.revoked_at IS NULL OR m.revoked_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)) AND (((m.receiver_id=:uid AND m.status='sent') OR (m.sender_id=:uid AND (m.status='sent' OR (m.status IN ('pending_review','rejected') AND COALESCE(m.review_reason,'')<>'举报处理')))) LIMIT 1");
        $stmt->execute([':id'=>$messageId, ':cid'=>(int)$conv['id'], ':uid'=>$userId]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) return $this->messages($userId, $peerId, 0, 120);

        $before = max(1, min(120, $before));
        $after = max(1, min(120, $after));
        $db = Database::connection();
        $prev = $db->prepare("SELECT m.*, u.username AS sender_username, u.nickname AS sender_nickname, u.avatar AS sender_avatar,
                   b.effect_type AS bubble_effect_type, b.effect_params AS bubble_effect_params
            FROM private_messages m
            LEFT JOIN users u ON u.id=m.sender_id
            LEFT JOIN plugin_user_chat_bubbles ub ON ub.user_id=m.sender_id AND ub.is_equipped=1
            LEFT JOIN plugin_chat_bubbles b ON b.id=ub.bubble_id AND b.status='active'
            WHERE m.conversation_id=:cid AND m.id<:mid AND (m.revoked_at IS NULL OR m.revoked_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)) AND ((m.receiver_id=:uid AND m.status='sent') OR (m.sender_id=:uid AND (m.status='sent' OR (m.status IN ('pending_review','rejected') AND COALESCE(m.review_reason,'')<>'举报处理')))) ORDER BY m.id DESC LIMIT :limit");
        $prev->bindValue(':cid', (int)$conv['id'], PDO::PARAM_INT);
        $prev->bindValue(':mid', $messageId, PDO::PARAM_INT);
        $prev->bindValue(':uid', $userId, PDO::PARAM_INT);
        $prev->bindValue(':limit', $before, PDO::PARAM_INT);
        $prev->execute();
        $prevRows = array_reverse($prev->fetchAll(PDO::FETCH_ASSOC));

        $next = $db->prepare("SELECT m.*, u.username AS sender_username, u.nickname AS sender_nickname, u.avatar AS sender_avatar,
                   b.effect_type AS bubble_effect_type, b.effect_params AS bubble_effect_params
            FROM private_messages m
            LEFT JOIN users u ON u.id=m.sender_id
            LEFT JOIN plugin_user_chat_bubbles ub ON ub.user_id=m.sender_id AND ub.is_equipped=1
            LEFT JOIN plugin_chat_bubbles b ON b.id=ub.bubble_id AND b.status='active'
            WHERE m.conversation_id=:cid AND m.id>:mid AND (m.revoked_at IS NULL OR m.revoked_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)) AND ((m.receiver_id=:uid AND m.status='sent') OR (m.sender_id=:uid AND (m.status='sent' OR (m.status IN ('pending_review','rejected') AND COALESCE(m.review_reason,'')<>'举报处理')))) ORDER BY m.id ASC LIMIT :limit");
        $next->bindValue(':cid', (int)$conv['id'], PDO::PARAM_INT);
        $next->bindValue(':mid', $messageId, PDO::PARAM_INT);
        $next->bindValue(':uid', $userId, PDO::PARAM_INT);
        $next->bindValue(':limit', $after, PDO::PARAM_INT);
        $next->execute();
        return array_merge($prevRows, [$target], $next->fetchAll(PDO::FETCH_ASSOC));
    }

    public function send(int $senderId, int $receiverId, string $content, bool $reviewEnabled, int $maxLength): array
    {
        $content = trim($content);
        if ($content === '') throw new \RuntimeException('消息不能为空');
        if (mb_strlen($content) > $maxLength) throw new \RuntimeException('消息长度不能超过 ' . $maxLength . ' 字');
        if (!$this->areFriends($senderId, $receiverId)) throw new \RuntimeException('只有互关好友可以私聊');
        if ((new BlockModel())->isBlocked($receiverId, $senderId)) throw new \RuntimeException('对方已屏蔽你，暂不能发送私聊');
        if ((new BlockModel())->isBlocked($senderId, $receiverId)) throw new \RuntimeException('你已屏蔽对方，请先取消屏蔽');
        $conv = $this->conversationFor($senderId, $receiverId, true);
        if (!$conv) throw new \RuntimeException('会话创建失败');
        $status = $reviewEnabled ? 'pending_review' : 'sent';
        $db = Database::connection();
        $stmt = $db->prepare("INSERT INTO private_messages (conversation_id,sender_id,receiver_id,content,message_type,media_url,status,created_at,updated_at) VALUES (:cid,:sid,:rid,:content,'text',NULL,:status,NOW(),NOW())");
        $stmt->execute([':cid'=>(int)$conv['id'], ':sid'=>$senderId, ':rid'=>$receiverId, ':content'=>$content, ':status'=>$status]);
        $id = (int)$db->lastInsertId();
        $db->prepare("UPDATE private_conversations SET last_message_id=:mid,last_message_at=NOW(),hidden_for_low=0,hidden_for_high=0,updated_at=NOW() WHERE id=:cid")->execute([':mid'=>$id, ':cid'=>(int)$conv['id']]);
        $msg = $this->messageById($id);
        return $msg ?: ['id'=>$id, 'status'=>$status, 'content'=>$content];
    }

    public function sendImage(int $senderId, int $receiverId, string $url): array
    {
        $url = trim($url);
        if ($url === '' || !str_starts_with($url, '/uploads/')) throw new \RuntimeException('图片地址无效');
        if (!$this->areFriends($senderId, $receiverId)) throw new \RuntimeException('只有互关好友可以私聊');
        if ((new BlockModel())->isBlocked($receiverId, $senderId)) throw new \RuntimeException('对方已屏蔽你，暂不能发送私聊');
        if ((new BlockModel())->isBlocked($senderId, $receiverId)) throw new \RuntimeException('你已屏蔽对方，请先取消屏蔽');
        $conv = $this->conversationFor($senderId, $receiverId, true);
        if (!$conv) throw new \RuntimeException('会话创建失败');
        $db = Database::connection();
        $stmt = $db->prepare("INSERT INTO private_messages (conversation_id,sender_id,receiver_id,content,message_type,media_url,status,created_at,updated_at) VALUES (:cid,:sid,:rid,'[图片]','image',:url,'sent',NOW(),NOW())");
        $stmt->execute([':cid'=>(int)$conv['id'], ':sid'=>$senderId, ':rid'=>$receiverId, ':url'=>$url]);
        $id = (int)$db->lastInsertId();
        $db->prepare("UPDATE private_conversations SET last_message_id=:mid,last_message_at=NOW(),hidden_for_low=0,hidden_for_high=0,updated_at=NOW() WHERE id=:cid")->execute([':mid'=>$id, ':cid'=>(int)$conv['id']]);
        return $this->messageById($id) ?: ['id'=>$id,'message_type'=>'image','media_url'=>$url,'content'=>'[图片]','status'=>'sent'];
    }

    public function revokeMessage(int $userId, int $messageId): array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM private_messages WHERE id=:id AND sender_id=:uid LIMIT 1");
        $stmt->execute([':id'=>$messageId, ':uid'=>$userId]);
        $msg = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$msg) throw new \RuntimeException('消息不存在或无权撤回');
        if ((string)($msg['message_type'] ?? 'text') === 'image') throw new \RuntimeException('图片消息不能撤回，请联系管理员处理');
        if (!empty($msg['revoked_at'])) return $msg;
        if (strtotime((string)($msg['created_at'] ?? '')) < time() - 120) throw new \RuntimeException('只能撤回 2 分钟内发送的消息');
        $origContent = (string)($msg['content'] ?? '');
        Database::connection()->prepare("UPDATE private_messages SET revoked_at=NOW(), revoked_content=:orig, content='[已撤回]', updated_at=NOW() WHERE id=:id")->execute([':orig'=>$origContent, ':id'=>$messageId]);
        return $this->messageById($messageId) ?: $msg;
    }

    public function approveMessage(int $id, array $review): void
    {
        Database::connection()->prepare("UPDATE private_messages SET status='sent', review_reason=:reason, review_suggestion=:suggestion, ai_result_json=:json, updated_at=NOW() WHERE id=:id")
            ->execute([':reason'=>(string)($review['reason'] ?? ''), ':suggestion'=>(string)($review['suggestion'] ?? ''), ':json'=>json_encode($review, JSON_UNESCAPED_UNICODE), ':id'=>$id]);
    }

    public function rejectMessage(int $id, array $review): void
    {
        Database::connection()->prepare("UPDATE private_messages SET status='rejected', review_reason=:reason, review_suggestion=:suggestion, ai_result_json=:json, updated_at=NOW() WHERE id=:id")
            ->execute([':reason'=>(string)($review['reason'] ?? '内容未通过审核'), ':suggestion'=>(string)($review['suggestion'] ?? ''), ':json'=>json_encode($review, JSON_UNESCAPED_UNICODE), ':id'=>$id]);
    }

    public function messageById(int $id): ?array
    {
        $stmt = Database::connection()->prepare("SELECT m.*, u.username AS sender_username, u.nickname AS sender_nickname, u.avatar AS sender_avatar,
                   b.effect_type AS bubble_effect_type, b.effect_params AS bubble_effect_params
            FROM private_messages m
            LEFT JOIN users u ON u.id=m.sender_id
            LEFT JOIN plugin_user_chat_bubbles ub ON ub.user_id=m.sender_id AND ub.is_equipped=1
            LEFT JOIN plugin_chat_bubbles b ON b.id=ub.bubble_id AND b.status='active'
            WHERE m.id=:id LIMIT 1");
        $stmt->execute([':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function clearRevokedContent(int $userId, int $messageId): void
    {
        Database::connection()->prepare("UPDATE private_messages SET revoked_content=NULL WHERE id=:id AND sender_id=:uid AND revoked_content IS NOT NULL")->execute([':id'=>$messageId, ':uid'=>$userId]);
    }

    public function unreadCount(int $userId): int
    {
        if ($userId <= 0) return 0;
        $this->ensureConversationColumns();
        $sql = "SELECT COUNT(*)
                FROM private_messages m
                JOIN private_conversations c ON c.id=m.conversation_id
                WHERE m.receiver_id=:uid
                  AND m.status='sent'
                  AND m.read_at IS NULL
                  AND IF(c.user_low_id=:uid, c.muted_for_low, c.muted_for_high)=0";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([':uid'=>$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function markRead(int $userId, int $peerId): void
    {
        $conv = $this->conversationFor($userId, $peerId, false);
        if (!$conv) return;
        Database::connection()->prepare("UPDATE private_messages SET read_at=NOW() WHERE conversation_id=:cid AND receiver_id=:uid AND status='sent' AND read_at IS NULL")
            ->execute([':cid'=>(int)$conv['id'], ':uid'=>$userId]);
    }

    public function hideConversation(int $userId, int $peerId): void
    {
        if ($userId <= 0 || $peerId <= 0 || $userId === $peerId) return;
        $conv = $this->conversationFor($userId, $peerId, false);
        if (!$conv) return;
        [$low] = $this->pair($userId, $peerId);
        $field = $userId === $low ? 'hidden_for_low' : 'hidden_for_high';
        Database::connection()->prepare("UPDATE private_conversations SET {$field}=1, updated_at=NOW() WHERE id=:id")
            ->execute([':id'=>(int)$conv['id']]);
    }

    public function setPinned(int $userId, int $peerId, bool $pinned): void
    {
        if ($userId <= 0 || $peerId <= 0 || $userId === $peerId) return;
        $this->ensureConversationColumns();
        $conv = $this->conversationFor($userId, $peerId, false);
        if (!$conv) return;
        [$low] = $this->pair($userId, $peerId);
        $pinField = $userId === $low ? 'pinned_for_low' : 'pinned_for_high';
        Database::connection()->prepare("UPDATE private_conversations SET {$pinField}=:v, updated_at=NOW() WHERE id=:id")->execute([':v'=>$pinned ? 1 : 0, ':id'=>(int)$conv['id']]);
    }

    public function setMuted(int $userId, int $peerId, bool $muted): void
    {
        if ($userId <= 0 || $peerId <= 0 || $userId === $peerId) return;
        $this->ensureConversationColumns();
        $conv = $this->conversationFor($userId, $peerId, false);
        if (!$conv) return;
        [$low] = $this->pair($userId, $peerId);
        $muteField = $userId === $low ? 'muted_for_low' : 'muted_for_high';
        Database::connection()->prepare("UPDATE private_conversations SET {$muteField}=:v, updated_at=NOW() WHERE id=:id")->execute([':v'=>$muted ? 1 : 0, ':id'=>(int)$conv['id']]);
    }

    private function ensureConversationColumns(): void
    {
        
    }

    public function reportMessage(int $userId, int $messageId, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') throw new \RuntimeException('请填写举报原因');
        if ($messageId <= 0) throw new \RuntimeException('参数错误');

        $db = Database::connection();
        $stmt = $db->prepare("SELECT * FROM private_messages WHERE id=:id AND receiver_id=:uid AND status='sent' AND revoked_at IS NULL LIMIT 1");
        $stmt->execute([':id'=>$messageId, ':uid'=>$userId]);
        $msg = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$msg) throw new \RuntimeException('只能举报对方发送且仍可见的私聊消息');

        $stmt = $db->prepare("SELECT id, status FROM content_reports WHERE user_id=:uid AND target_type='private_message' AND target_id=:mid LIMIT 1");
        $stmt->execute([':uid'=>$userId, ':mid'=>$messageId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $status = (string)($existing['status'] ?? '');
            if (in_array($status, ['pending', 'processing'], true)) {
                throw new \RuntimeException('这条消息已举报，正在处理中');
            }
            throw new \RuntimeException('这条消息已举报过，请勿重复提交');
        }

        $db->prepare("INSERT INTO content_reports (user_id,target_type,target_id,reason,category,status,created_at) VALUES (:uid,'private_message',:mid,:reason,'chat','pending',NOW())")
            ->execute([':uid'=>$userId, ':mid'=>$messageId, ':reason'=>mb_substr($reason, 0, 255)]);
    }
}
