<?php

namespace App\Models;

use App\Core\Database;
use App\Models\PrivateChatModel;
use App\Models\SystemMessageModel;
use PDO;

class GroupChatModel
{
    private bool $schemaReady = false;

    private function ensureSchema(): void
    {
        if ($this->schemaReady) return;
        $db = Database::connection();
        $columns = [];
        try {
            $rs = $db->query('SHOW COLUMNS FROM `chat_group_members`');
            while ($row = $rs->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = $row['Field'];
            }
            if (!in_array('custom_title', $columns, true)) {
                $db->exec("ALTER TABLE `chat_group_members` ADD COLUMN `custom_title` VARCHAR(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `hidden`");
            }
            if (!in_array('custom_title_color', $columns, true)) {
                $db->exec("ALTER TABLE `chat_group_members` ADD COLUMN `custom_title_color` VARCHAR(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `custom_title`");
            }
        } catch (\Throwable $e) {
            // 安装/升级中若表尚未存在，由 install.sql 负责创建完整结构。
        }
        $this->schemaReady = true;
    }

    public function create(int $ownerId, string $name, string $notice, array $memberIds, string $avatar = ''): array
    {
        $name = trim($name);
        $notice = trim($notice);
        $avatar = trim($avatar);
        if ($ownerId <= 0) throw new \RuntimeException('请先登录');
        if ($name === '') throw new \RuntimeException('请输入群名称');
        if (mb_strlen($name) > 40) throw new \RuntimeException('群名称不能超过 40 字');
        if (mb_strlen($notice) > 500) throw new \RuntimeException('群公告不能超过 500 字');
        if ($avatar !== '' && !str_starts_with($avatar, '/uploads/')) throw new \RuntimeException('群头像地址无效');
        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
        $memberIds = array_values(array_filter($memberIds, fn($id) => $id > 0 && $id !== $ownerId));
        if (count($memberIds) < 1) throw new \RuntimeException('请至少选择 1 位好友建群');
        if (count($memberIds) > 199) throw new \RuntimeException('单次最多选择 199 位好友');
        $private = new PrivateChatModel();
        foreach ($memberIds as $mid) {
            if (!$private->areFriends($ownerId, $mid)) throw new \RuntimeException('只能邀请自己的互关好友建群');
        }
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $publicId = $this->generatePublicId($db);
            $db->prepare("INSERT INTO chat_groups (public_id,name,avatar,notice,owner_user_id,status,join_mode,visibility,created_at,updated_at) VALUES (:pid,:name,:avatar,:notice,:owner,'normal','direct','public',NOW(),NOW())")
                ->execute([':pid'=>$publicId, ':name'=>$name, ':avatar'=>$avatar ?: null, ':notice'=>$notice, ':owner'=>$ownerId]);
            $gid = (int)$db->lastInsertId();
            $ins = $db->prepare("INSERT INTO chat_group_members (group_id,user_id,role,join_source,created_at,updated_at) VALUES (:gid,:uid,:role,:src,NOW(),NOW())");
            $ins->execute([':gid'=>$gid, ':uid'=>$ownerId, ':role'=>'owner', ':src'=>'create']);
            foreach ($memberIds as $mid) $ins->execute([':gid'=>$gid, ':uid'=>$mid, ':role'=>'member', ':src'=>'invite']);
            $db->commit();
            return $this->groupForUser($gid, $ownerId) ?: ['id'=>$gid, 'public_id'=>$publicId, 'name'=>$name, 'avatar'=>$avatar, 'notice'=>$notice, 'role'=>'owner'];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function groups(int $userId): array
    {
        if ($userId <= 0) return [];
        $sql = "SELECT g.*, gm.role, gm.is_pinned, gm.muted_until, gm.last_read_message_id, COALESCE(gm.cleared_message_id,0) AS cleared_message_id,
                       u.username AS owner_username, u.nickname AS owner_nickname,
                       m.content AS last_content, m.message_type AS last_message_type, m.status AS last_status, m.created_at AS last_created_at,
                       (SELECT COUNT(*) FROM chat_group_members cm WHERE cm.group_id=g.id) AS member_count,
                       (SELECT COUNT(*) FROM chat_group_messages um WHERE um.group_id=g.id AND um.status='sent' AND um.sender_user_id<>:uid AND um.id>GREATEST(COALESCE(gm.last_read_message_id,0),COALESCE(gm.cleared_message_id,0)) AND (gm.muted_until IS NULL OR gm.muted_until<NOW())) AS unread_count
                FROM chat_group_members gm
                JOIN chat_groups g ON g.id=gm.group_id AND g.status='normal'
                LEFT JOIN users u ON u.id=g.owner_user_id
                LEFT JOIN chat_group_messages m ON m.id=g.last_message_id
                WHERE gm.user_id=:uid AND COALESCE(gm.hidden,0)=0
                ORDER BY gm.is_pinned DESC, COALESCE(g.last_message_at,g.updated_at,g.created_at) DESC, g.id DESC
                LIMIT 80";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([':uid'=>$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function groupForUser(int $groupId, int $userId): ?array
    {
        $stmt = Database::connection()->prepare("SELECT g.*, gm.role, gm.is_pinned, gm.muted_until, gm.last_read_message_id, COALESCE(gm.cleared_message_id,0) AS cleared_message_id, (SELECT COUNT(*) FROM chat_group_members WHERE group_id=g.id) AS member_count FROM chat_groups g JOIN chat_group_members gm ON gm.group_id=g.id AND gm.user_id=:uid WHERE g.id=:gid AND g.status='normal' LIMIT 1");
        $stmt->execute([':gid'=>$groupId, ':uid'=>$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function members(int $groupId, int $userId): array
    {
        $this->ensureSchema();
        if (!$this->groupForUser($groupId, $userId)) throw new \RuntimeException('你不在该群聊中');
        $stmt = Database::connection()->prepare("SELECT gm.user_id, gm.role, gm.custom_title, gm.custom_title_color, gm.banned_until, gm.banned_by, gm.ban_reason, u.public_id, u.username, u.nickname, u.avatar FROM chat_group_members gm JOIN users u ON u.id=gm.user_id WHERE gm.group_id=:gid ORDER BY FIELD(gm.role,'owner','admin','member'), gm.id ASC LIMIT 300");
        $stmt->execute([':gid'=>$groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cleanupExpiredRevoked(int $groupId = 0): void
    {
        $db = Database::connection();
        $where = $groupId > 0 ? 'AND group_id=:gid' : '';
        $stmt = $db->prepare("SELECT id, group_id FROM chat_group_messages WHERE revoked_at IS NOT NULL AND revoked_at < DATE_SUB(NOW(), INTERVAL 30 SECOND) $where");
        $groupId > 0 ? $stmt->execute([':gid'=>$groupId]) : $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return;
        foreach ($rows as $r) {
            $prev = $db->prepare("SELECT id, created_at FROM chat_group_messages WHERE group_id=:gid AND id<:mid AND (revoked_at IS NULL OR revoked_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)) ORDER BY id DESC LIMIT 1");
            $prev->execute([':gid'=>(int)$r['group_id'], ':mid'=>(int)$r['id']]);
            $p = $prev->fetch(PDO::FETCH_ASSOC);
            if ($p) {
                $db->prepare("UPDATE chat_groups SET last_message_id=:mid, last_message_at=:mat WHERE id=:gid AND last_message_id=:old")->execute([':mid'=>(int)$p['id'], ':mat'=>$p['created_at'], ':gid'=>(int)$r['group_id'], ':old'=>(int)$r['id']]);
            } else {
                $db->prepare("UPDATE chat_groups SET last_message_id=NULL, last_message_at=NULL WHERE id=:gid AND last_message_id=:old")->execute([':gid'=>(int)$r['group_id'], ':old'=>(int)$r['id']]);
            }
            $db->prepare("DELETE FROM chat_group_messages WHERE id=:id")->execute([':id'=>(int)$r['id']]);
        }
    }

    public function messages(int $userId, int $groupId, int $afterId = 0, int $limit = 80): array
    {
        $this->ensureSchema();
        if (!$this->groupForUser($groupId, $userId)) return [];
        $limit = max(1, min(160, $limit));
        $this->cleanupExpiredRevoked($groupId);
        $sql = "SELECT m.*, u.public_id, u.username, u.nickname, u.avatar, sm.role AS sender_role, sm.custom_title, sm.custom_title_color, b.effect_type AS bubble_effect_type, b.effect_params AS bubble_effect_params
                FROM chat_group_messages m
                JOIN users u ON u.id=m.sender_user_id
                LEFT JOIN chat_group_members sm ON sm.group_id=m.group_id AND sm.user_id=m.sender_user_id
                LEFT JOIN plugin_user_chat_bubbles ub ON ub.user_id=m.sender_user_id AND ub.is_equipped=1
                LEFT JOIN plugin_chat_bubbles b ON b.id=ub.bubble_id AND b.status='active'
                WHERE m.group_id=:gid AND m.id>:after AND (m.revoked_at IS NULL OR m.revoked_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)) AND m.id>(SELECT COALESCE(cleared_message_id,0) FROM chat_group_members WHERE group_id=:gid AND user_id=:uid LIMIT 1) AND (m.status='sent' OR (m.sender_user_id=:uid AND m.status IN ('pending_review','rejected')))
                ORDER BY m.id ASC LIMIT :limit";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':gid', $groupId, PDO::PARAM_INT);
        $stmt->bindValue(':after', $afterId, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function send(int $senderId, int $groupId, string $content, bool $reviewEnabled, int $maxLength): array
    {
        $content = trim($content);
        if ($content === '') throw new \RuntimeException('消息不能为空');
        if (mb_strlen($content) > $maxLength) throw new \RuntimeException('消息长度不能超过 ' . $maxLength . ' 字');
        $membership = $this->membership($groupId, $senderId);
        if (!$membership) throw new \RuntimeException('你不在该群聊中');
        if (!empty($membership['banned_until']) && strtotime((string)$membership['banned_until']) > time()) throw new \RuntimeException('你已被禁言，禁言结束时间：' . $membership['banned_until']);
        $status = $reviewEnabled ? 'pending_review' : 'sent';
        $db = Database::connection();
        $stmt = $db->prepare("INSERT INTO chat_group_messages (group_id,sender_user_id,content,message_type,media_url,status,created_at,updated_at) VALUES (:gid,:sid,:content,'text',NULL,:status,NOW(),NOW())");
        $stmt->execute([':gid'=>$groupId, ':sid'=>$senderId, ':content'=>$content, ':status'=>$status]);
        $id = (int)$db->lastInsertId();
        $db->prepare("UPDATE chat_groups SET last_message_id=:mid,last_message_at=NOW(),updated_at=NOW() WHERE id=:gid")->execute([':mid'=>$id, ':gid'=>$groupId]);
        return $this->messageById($id) ?: ['id'=>$id, 'group_id'=>$groupId, 'sender_user_id'=>$senderId, 'content'=>$content, 'status'=>$status, 'message_type'=>'text'];
    }

    public function sendImage(int $senderId, int $groupId, string $url): array
    {
        $url = trim($url);
        if ($url === '' || !str_starts_with($url, '/uploads/')) throw new \RuntimeException('图片地址无效');
        $membership = $this->membership($groupId, $senderId);
        if (!$membership) throw new \RuntimeException('你不在该群聊中');
        if (!empty($membership['banned_until']) && strtotime((string)$membership['banned_until']) > time()) throw new \RuntimeException('你已被禁言，禁言结束时间：' . $membership['banned_until']);
        $db = Database::connection();
        $stmt = $db->prepare("INSERT INTO chat_group_messages (group_id,sender_user_id,content,message_type,media_url,status,created_at,updated_at) VALUES (:gid,:sid,'[图片]','image',:url,'sent',NOW(),NOW())");
        $stmt->execute([':gid'=>$groupId, ':sid'=>$senderId, ':url'=>$url]);
        $id = (int)$db->lastInsertId();
        $db->prepare("UPDATE chat_groups SET last_message_id=:mid,last_message_at=NOW(),updated_at=NOW() WHERE id=:gid")->execute([':mid'=>$id, ':gid'=>$groupId]);
        return $this->messageById($id) ?: ['id'=>$id, 'group_id'=>$groupId, 'sender_user_id'=>$senderId, 'content'=>'[图片]', 'message_type'=>'image', 'media_url'=>$url, 'status'=>'sent'];
    }

    public function updateGroup(int $userId, int $groupId, string $name, string $notice, string $avatar = '', string $noticeTitle = ''): array
    {
        $group = $this->groupForUser($groupId, $userId);
        if (!$group) throw new \RuntimeException('群聊不存在或无权访问');
        if (!in_array((string)$group['role'], ['owner','admin'], true)) throw new \RuntimeException('只有群主或管理员可以修改群资料');
        $name = trim($name);
        $notice = trim($notice);
        $noticeTitle = trim($noticeTitle);
        $avatar = trim($avatar);
        if ($name === '') throw new \RuntimeException('请输入群名称');
        if (mb_strlen($name) > 40) throw new \RuntimeException('群名称不能超过 40 字');
        if (mb_strlen($noticeTitle) > 60) throw new \RuntimeException('群公告标题不能超过 60 字');
        if (mb_strlen($notice) > 500) throw new \RuntimeException('群公告不能超过 500 字');
        if ($notice !== '' && $noticeTitle === '') throw new \RuntimeException('请输入群公告标题');
        if ($avatar !== '' && !str_starts_with($avatar, '/uploads/')) throw new \RuntimeException('群头像地址无效');
        $ownerOnly = ($avatar !== '' && $avatar !== ($group['avatar'] ?? ''));
        if ($ownerOnly && (string)$group['role'] !== 'owner') throw new \RuntimeException('只有群主可以修改群头像');
        Database::connection()->prepare("UPDATE chat_groups SET name=:name, notice=:notice, notice_title=:notice_title, avatar=:avatar, updated_at=NOW() WHERE id=:gid")
            ->execute([':name'=>$name, ':notice'=>$notice, ':notice_title'=>$noticeTitle ?: null, ':avatar'=>$avatar ?: null, ':gid'=>$groupId]);
        return $this->groupForUser($groupId, $userId) ?: [];
    }

    public function updateMemberSettings(int $userId, int $groupId, ?bool $pinned = null, ?bool $muted = null): array
    {
        $group = $this->groupForUser($groupId, $userId);
        if (!$group) throw new \RuntimeException('群聊不存在或无权访问');
        $sets = ['updated_at=NOW()'];
        $params = [':gid'=>$groupId, ':uid'=>$userId];
        if ($pinned !== null) { $sets[] = 'is_pinned=:pin'; $params[':pin'] = $pinned ? 1 : 0; }
        if ($muted !== null) { $sets[] = 'muted_until=:muted'; $params[':muted'] = $muted ? '2099-12-31 23:59:59' : null; }
        Database::connection()->prepare('UPDATE chat_group_members SET ' . implode(',', $sets) . ' WHERE group_id=:gid AND user_id=:uid')->execute($params);
        return $this->groupForUser($groupId, $userId) ?: [];
    }

    public function clearHistory(int $userId, int $groupId): array
    {
        if (!$this->groupForUser($groupId, $userId)) throw new \RuntimeException('群聊不存在或无权访问');
        $db = Database::connection();
        $last = (int)($db->query('SELECT COALESCE(MAX(id),0) FROM chat_group_messages WHERE group_id=' . (int)$groupId)->fetchColumn() ?: 0);
        $db->prepare('UPDATE chat_group_members SET cleared_message_id=:mid,last_read_message_id=GREATEST(last_read_message_id,:mid),updated_at=NOW() WHERE group_id=:gid AND user_id=:uid')
            ->execute([':mid'=>$last, ':gid'=>$groupId, ':uid'=>$userId]);
        return $this->groupForUser($groupId, $userId) ?: [];
    }

    public function leaveOrDisband(int $userId, int $groupId): string
    {
        $group = $this->groupForUser($groupId, $userId);
        if (!$group) throw new \RuntimeException('群聊不存在或无权访问');
        $db = Database::connection();
        if ((string)$group['role'] === 'owner') {
            $db->prepare("UPDATE chat_groups SET status='disbanded',updated_at=NOW() WHERE id=:gid AND owner_user_id=:uid")->execute([':gid'=>$groupId, ':uid'=>$userId]);
            return 'disbanded';
        }
        $db->prepare('DELETE FROM chat_group_members WHERE group_id=:gid AND user_id=:uid')->execute([':gid'=>$groupId, ':uid'=>$userId]);
        return 'left';
    }

    public function searchMessages(int $userId, int $groupId, string $keyword): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') throw new \RuntimeException('请输入搜索关键词');
        if (!$this->groupForUser($groupId, $userId)) throw new \RuntimeException('群聊不存在或无权访问');
        $stmt = Database::connection()->prepare("SELECT m.id,m.content,m.message_type,m.created_at,u.username,u.nickname FROM chat_group_messages m JOIN users u ON u.id=m.sender_user_id JOIN chat_group_members gm ON gm.group_id=m.group_id AND gm.user_id=:uid WHERE m.group_id=:gid AND (m.revoked_at IS NULL OR m.revoked_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)) AND m.status='sent' AND m.message_type='text' AND m.id>COALESCE(gm.cleared_message_id,0) AND m.content LIKE :kw ORDER BY m.id DESC LIMIT 30");
        $stmt->execute([':uid'=>$userId, ':gid'=>$groupId, ':kw'=>'%' . $keyword . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markRead(int $userId, int $groupId): void
    {
        if (!$this->groupForUser($groupId, $userId)) return;
        $last = (int)(Database::connection()->query("SELECT COALESCE(MAX(id),0) FROM chat_group_messages WHERE group_id=" . (int)$groupId . " AND status='sent'")->fetchColumn() ?: 0);
        Database::connection()->prepare("UPDATE chat_group_members SET last_read_message_id=:mid, updated_at=NOW() WHERE group_id=:gid AND user_id=:uid")
            ->execute([':mid'=>$last, ':gid'=>$groupId, ':uid'=>$userId]);
    }

    public function unreadCount(int $userId): int
    {
        if ($userId <= 0) return 0;
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM chat_group_messages m JOIN chat_group_members gm ON gm.group_id=m.group_id AND gm.user_id=:uid WHERE m.status='sent' AND m.sender_user_id<>:uid AND m.id>GREATEST(COALESCE(gm.last_read_message_id,0),COALESCE(gm.cleared_message_id,0)) AND (gm.muted_until IS NULL OR gm.muted_until<NOW())");
        $stmt->execute([':uid'=>$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function approveMessage(int $id, array $review): void
    {
        Database::connection()->prepare("UPDATE chat_group_messages SET status='sent', review_reason=:reason, review_suggestion=:suggestion, ai_result_json=:json, updated_at=NOW() WHERE id=:id")
            ->execute([':reason'=>(string)($review['reason'] ?? ''), ':suggestion'=>(string)($review['suggestion'] ?? ''), ':json'=>json_encode($review, JSON_UNESCAPED_UNICODE), ':id'=>$id]);
    }

    public function rejectMessage(int $id, array $review): void
    {
        Database::connection()->prepare("UPDATE chat_group_messages SET status='rejected', review_reason=:reason, review_suggestion=:suggestion, ai_result_json=:json, updated_at=NOW() WHERE id=:id")
            ->execute([':reason'=>(string)($review['reason'] ?? '内容未通过审核'), ':suggestion'=>(string)($review['suggestion'] ?? ''), ':json'=>json_encode($review, JSON_UNESCAPED_UNICODE), ':id'=>$id]);
    }

    public function messageById(int $id): ?array
    {
        $stmt = Database::connection()->prepare("SELECT m.*, u.public_id, u.username, u.nickname, u.avatar, sm.role AS sender_role, sm.custom_title, sm.custom_title_color, b.effect_type AS bubble_effect_type, b.effect_params AS bubble_effect_params
            FROM chat_group_messages m
            JOIN users u ON u.id=m.sender_user_id
            LEFT JOIN chat_group_members sm ON sm.group_id=m.group_id AND sm.user_id=m.sender_user_id
            LEFT JOIN plugin_user_chat_bubbles ub ON ub.user_id=m.sender_user_id AND ub.is_equipped=1
            LEFT JOIN plugin_chat_bubbles b ON b.id=ub.bubble_id AND b.status='active'
            WHERE m.id=:id LIMIT 1");
        $stmt->execute([':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function clearRevokedContent(int $userId, int $messageId): void
    {
        Database::connection()->prepare("UPDATE chat_group_messages SET revoked_content=NULL WHERE id=:id AND sender_user_id=:uid AND revoked_content IS NOT NULL")->execute([':id'=>$messageId, ':uid'=>$userId]);
    }

    private function generatePublicId(PDO $db): string
    {
        for ($i = 0; $i < 20; $i++) {
            $code = 'G' . (string)random_int(10000000, 99999999);
            $stmt = $db->prepare('SELECT COUNT(*) FROM chat_groups WHERE public_id=:code');
            $stmt->execute([':code'=>$code]);
            if ((int)$stmt->fetchColumn() === 0) return $code;
        }
        throw new \RuntimeException('群号生成失败，请重试');
    }

    private function membership(int $groupId, int $userId): ?array
    {
        $stmt = Database::connection()->prepare("SELECT gm.*, g.name AS group_name, g.owner_user_id, g.public_id, g.join_mode, g.visibility FROM chat_group_members gm JOIN chat_groups g ON g.id=gm.group_id AND g.status='normal' WHERE gm.group_id=:gid AND gm.user_id=:uid LIMIT 1");
        $stmt->execute([':gid'=>$groupId, ':uid'=>$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function userBrief(int $userId): string
    {
        $stmt = Database::connection()->prepare('SELECT COALESCE(NULLIF(nickname,\'\'), username) FROM users WHERE id=:id LIMIT 1');
        $stmt->execute([':id'=>$userId]);
        return (string)($stmt->fetchColumn() ?: ('用户' . $userId));
    }

    private function canOperateRole(string $actorRole, string $targetRole): bool
    {
        if ($actorRole === 'owner') return $targetRole !== 'owner';
        if ($actorRole === 'admin') return $targetRole === 'member';
        return false;
    }

    private function assertCanManageMember(int $actorId, int $groupId, int $targetUserId): array
    {
        $actor = $this->membership($groupId, $actorId);
        $target = $this->membership($groupId, $targetUserId);
        if (!$actor || !$target) throw new \RuntimeException('成员不存在或不在群聊中');
        if ($actorId === $targetUserId) throw new \RuntimeException('不能操作自己');
        if (!$this->canOperateRole((string)$actor['role'], (string)$target['role'])) throw new \RuntimeException('无权操作该成员');
        return [$actor, $target];
    }

    private function notifyUser(int $userId, string $title, string $content, string $category = 'system', string $refType = 'group', int $refId = 0): void
    {
        try { (new SystemMessageModel())->createPersonal($userId, $title, $content, 1, $category, '', $refType, $refId); } catch (\Throwable $e) {}
    }

    public function setMemberRole(int $actorId, int $groupId, int $targetUserId, string $role): array
    {
        $actor = $this->membership($groupId, $actorId);
        $target = $this->membership($groupId, $targetUserId);
        if (!$actor || !$target) throw new \RuntimeException('成员不存在或不在群聊中');
        if ((string)$actor['role'] !== 'owner') throw new \RuntimeException('只有群主可以设置管理员');
        if ($actorId === $targetUserId || (string)$target['role'] === 'owner') throw new \RuntimeException('不能操作群主');
        if (!in_array($role, ['admin','member'], true)) throw new \RuntimeException('角色无效');
        Database::connection()->prepare('UPDATE chat_group_members SET role=:role, updated_at=NOW() WHERE group_id=:gid AND user_id=:uid')->execute([':role'=>$role, ':gid'=>$groupId, ':uid'=>$targetUserId]);
        $this->notifyUser($targetUserId, $role === 'admin' ? '你已成为群管理员' : '你的群管理员权限已取消', '群聊「' . (string)$actor['group_name'] . '」的成员权限已更新。', 'system', 'group', $groupId);
        return $this->groupForUser($groupId, $actorId) ?: [];
    }

    public function banMember(int $actorId, int $groupId, int $targetUserId, int $minutes, string $reason = ''): array
    {
        [$actor, $target] = $this->assertCanManageMember($actorId, $groupId, $targetUserId);
        $minutes = max(1, min(525600, $minutes));
        $until = date('Y-m-d H:i:s', time() + $minutes * 60);
        Database::connection()->prepare('UPDATE chat_group_members SET banned_until=:until, banned_by=:by, ban_reason=:reason, updated_at=NOW() WHERE group_id=:gid AND user_id=:uid')->execute([':until'=>$until, ':by'=>$actorId, ':reason'=>mb_substr(trim($reason),0,120), ':gid'=>$groupId, ':uid'=>$targetUserId]);
        $this->notifyUser($targetUserId, '你已被群聊禁言', '你在群聊「' . (string)$actor['group_name'] . '」中被禁言至 ' . $until . '。', 'system', 'group', $groupId);
        return $this->groupForUser($groupId, $actorId) ?: [];
    }

    public function unbanMember(int $actorId, int $groupId, int $targetUserId): array
    {
        $this->assertCanManageMember($actorId, $groupId, $targetUserId);
        Database::connection()->prepare('UPDATE chat_group_members SET banned_until=NULL, banned_by=NULL, ban_reason=NULL, updated_at=NOW() WHERE group_id=:gid AND user_id=:uid')->execute([':gid'=>$groupId, ':uid'=>$targetUserId]);
        $this->notifyUser($targetUserId, '群聊禁言已解除', '你在群聊中的禁言已解除。', 'system', 'group', $groupId);
        return $this->groupForUser($groupId, $actorId) ?: [];
    }

    public function kickMember(int $actorId, int $groupId, int $targetUserId): array
    {
        [$actor, $target] = $this->assertCanManageMember($actorId, $groupId, $targetUserId);
        Database::connection()->prepare('DELETE FROM chat_group_members WHERE group_id=:gid AND user_id=:uid')->execute([':gid'=>$groupId, ':uid'=>$targetUserId]);
        $this->notifyUser($targetUserId, '你已被移出群聊', '你已被移出群聊「' . (string)$actor['group_name'] . '」。', 'system', 'group', $groupId);
        return $this->groupForUser($groupId, $actorId) ?: [];
    }


    public function setMemberTitle(int $actorId, int $groupId, int $targetUserId, string $title, string $color): array
    {
        [$actor, $target] = $this->assertCanManageMember($actorId, $groupId, $targetUserId);
        $title = trim($title);
        if ($title === '') throw new \RuntimeException('请输入头衔');
        if (mb_strlen($title) > 12) throw new \RuntimeException('头衔不能超过 12 个字');
        $color = trim($color);
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#22c55e';
        Database::connection()->prepare('UPDATE chat_group_members SET custom_title=:title, custom_title_color=:color, updated_at=NOW() WHERE group_id=:gid AND user_id=:uid')
            ->execute([':title'=>$title, ':color'=>$color, ':gid'=>$groupId, ':uid'=>$targetUserId]);
        $this->notifyUser($targetUserId, '你的群聊头衔已更新', '你在群聊「' . (string)$actor['group_name'] . '」中的自定义头衔已更新为「' . $title . '」。', 'system', 'group', $groupId);
        return $this->groupForUser($groupId, $actorId) ?: [];
    }

    public function clearMemberTitle(int $actorId, int $groupId, int $targetUserId): array
    {
        [$actor, $target] = $this->assertCanManageMember($actorId, $groupId, $targetUserId);
        Database::connection()->prepare('UPDATE chat_group_members SET custom_title=NULL, custom_title_color=NULL, updated_at=NOW() WHERE group_id=:gid AND user_id=:uid')
            ->execute([':gid'=>$groupId, ':uid'=>$targetUserId]);
        $this->notifyUser($targetUserId, '你的群聊头衔已取消', '你在群聊「' . (string)$actor['group_name'] . '」中的自定义头衔已取消。', 'system', 'group', $groupId);
        return $this->groupForUser($groupId, $actorId) ?: [];
    }

    public function revokeMessage(int $actorId, int $messageId): array
    {
        $msg = $this->messageById($messageId);
        if (!$msg) throw new \RuntimeException('消息不存在');
        $groupId = (int)$msg['group_id'];
        $actor = $this->membership($groupId, $actorId);
        if (!$actor) throw new \RuntimeException('你不在该群聊中');
        if (!empty($msg['revoked_at'])) return $msg;
        $senderId = (int)$msg['sender_user_id'];
        if ($senderId === $actorId && !in_array((string)$actor['role'], ['owner','admin'], true) && strtotime((string)$msg['created_at']) < time() - 120) throw new \RuntimeException('只能撤回 2 分钟内发送的消息');
        if ($senderId !== $actorId) {
            $target = $this->membership($groupId, $senderId);
            if (!$target || !$this->canOperateRole((string)$actor['role'], (string)$target['role'])) throw new \RuntimeException('无权撤回该成员消息');
        }
        $origContent = (string)($msg['content'] ?? '');
        Database::connection()->prepare("UPDATE chat_group_messages SET revoked_at=NOW(), revoked_by=:by, revoked_content=:orig, content='[已撤回]', updated_at=NOW() WHERE id=:id")->execute([':by'=>$actorId, ':orig'=>$origContent, ':id'=>$messageId]);
        return $this->messageById($messageId) ?: $msg;
    }

    public function inviteMember(int $actorId, int $groupId, int $inviteeId): array
    {
        $group = $this->groupForUser($groupId, $actorId);
        if (!$group) throw new \RuntimeException('群聊不存在或无权访问');
        if ((int)$actorId === (int)$inviteeId) throw new \RuntimeException('不能邀请自己');
        if (!(new PrivateChatModel())->areFriends($actorId, $inviteeId)) throw new \RuntimeException('只能邀请互关好友');
        if ($this->membership($groupId, $inviteeId)) throw new \RuntimeException('对方已在群聊中');
        $db = Database::connection();
        $token = bin2hex(random_bytes(16));
        $db->prepare("UPDATE chat_group_invites SET status='expired', updated_at=NOW() WHERE group_id=:gid AND invitee_user_id=:uid AND status='pending'")->execute([':gid'=>$groupId, ':uid'=>$inviteeId]);
        $db->prepare("INSERT INTO chat_group_invites (group_id,inviter_user_id,invitee_user_id,status,token,expires_at,created_at,updated_at) VALUES (:gid,:inviter,:invitee,'pending',:token,DATE_ADD(NOW(), INTERVAL 7 DAY),NOW(),NOW())")->execute([':gid'=>$groupId, ':inviter'=>$actorId, ':invitee'=>$inviteeId, ':token'=>$token]);
        $id = (int)$db->lastInsertId();
        $this->notifyUser($inviteeId, '群聊邀请', $this->userBrief($actorId) . ' 邀请你加入群聊「' . (string)$group['name'] . '」。', 'private', 'group_invite', $id);
        return ['invite_id'=>$id, 'status'=>'pending'];
    }

    public function handleInvite(int $userId, int $inviteId, string $decision): array
    {
        $db = Database::connection();
        $stmt = $db->prepare("SELECT i.*, g.name, g.status AS group_status FROM chat_group_invites i JOIN chat_groups g ON g.id=i.group_id WHERE i.id=:id AND i.invitee_user_id=:uid LIMIT 1");
        $stmt->execute([':id'=>$inviteId, ':uid'=>$userId]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$inv || (string)$inv['status'] !== 'pending') throw new \RuntimeException('邀请已失效');
        if (!empty($inv['expires_at']) && strtotime((string)$inv['expires_at']) < time()) throw new \RuntimeException('邀请已过期');
        if ($decision === 'accept') {
            if (!$this->membership((int)$inv['group_id'], $userId)) $db->prepare("INSERT INTO chat_group_members (group_id,user_id,role,join_source,created_at,updated_at) VALUES (:gid,:uid,'member','invite',NOW(),NOW())")->execute([':gid'=>(int)$inv['group_id'], ':uid'=>$userId]);
            $db->prepare("UPDATE chat_group_invites SET status='accepted', decided_at=NOW(), updated_at=NOW() WHERE id=:id")->execute([':id'=>$inviteId]);
        } else {
            $db->prepare("UPDATE chat_group_invites SET status='rejected', decided_at=NOW(), updated_at=NOW() WHERE id=:id")->execute([':id'=>$inviteId]);
        }
        return ['status'=>$decision === 'accept' ? 'accepted' : 'rejected', 'group_id'=>(int)$inv['group_id']];
    }

    public function searchPublicGroups(int $userId, string $code): array
    {
        $code = trim($code);
        if ($code === '') return [];
        $stmt = Database::connection()->prepare("SELECT g.id,g.public_id,g.name,g.avatar,g.join_mode,g.visibility,(SELECT COUNT(*) FROM chat_group_members WHERE group_id=g.id) AS member_count, EXISTS(SELECT 1 FROM chat_group_members gm WHERE gm.group_id=g.id AND gm.user_id=:uid) AS is_member FROM chat_groups g WHERE g.status='normal' AND g.visibility='public' AND g.public_id=:code LIMIT 10");
        $stmt->execute([':uid'=>$userId, ':code'=>$code]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function joinOrRequest(int $userId, int $groupId, string $message = ''): array
    {
        if ($this->membership($groupId, $userId)) return ['status'=>'joined', 'group'=>$this->groupForUser($groupId, $userId)];
        $stmt = Database::connection()->prepare("SELECT * FROM chat_groups WHERE id=:gid AND status='normal' AND visibility='public' LIMIT 1");
        $stmt->execute([':gid'=>$groupId]);
        $g = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$g) throw new \RuntimeException('群聊不存在或不可加入');
        $db = Database::connection();
        if ((string)$g['join_mode'] === 'direct') {
            $db->prepare("INSERT INTO chat_group_members (group_id,user_id,role,join_source,created_at,updated_at) VALUES (:gid,:uid,'member','search',NOW(),NOW())")->execute([':gid'=>$groupId, ':uid'=>$userId]);
            return ['status'=>'joined', 'group'=>$this->groupForUser($groupId, $userId)];
        }
        $db->prepare("INSERT INTO chat_group_join_requests (group_id,user_id,status,message,created_at,updated_at) VALUES (:gid,:uid,'pending',:msg,NOW(),NOW()) ON DUPLICATE KEY UPDATE message=VALUES(message), updated_at=NOW()")->execute([':gid'=>$groupId, ':uid'=>$userId, ':msg'=>mb_substr(trim($message),0,120)]);
        $this->notifyUser((int)$g['owner_user_id'], '新的加群申请', $this->userBrief($userId) . ' 申请加入群聊「' . (string)$g['name'] . '」。', 'private', 'group_join_request', $groupId);
        return ['status'=>'pending'];
    }

    public function setJoinMode(int $actorId, int $groupId, string $mode): array
    {
        $actor = $this->membership($groupId, $actorId);
        if (!$actor || !in_array((string)$actor['role'], ['owner','admin'], true)) throw new \RuntimeException('只有群主或管理员可以设置加群方式');
        if (!in_array($mode, ['direct','approval'], true)) throw new \RuntimeException('加群方式无效');
        Database::connection()->prepare('UPDATE chat_groups SET join_mode=:mode, updated_at=NOW() WHERE id=:gid')->execute([':mode'=>$mode, ':gid'=>$groupId]);
        return $this->groupForUser($groupId, $actorId) ?: [];
    }

    public function pendingRequests(int $userId, int $groupId): array
    {
        $actor = $this->membership($groupId, $userId);
        if (!$actor || !in_array((string)$actor['role'], ['owner','admin'], true)) throw new \RuntimeException('无权查看加群申请');
        $stmt = Database::connection()->prepare("SELECT r.*, u.username, u.nickname, u.avatar, u.public_id FROM chat_group_join_requests r JOIN users u ON u.id=r.user_id WHERE r.group_id=:gid AND r.status='pending' ORDER BY r.created_at ASC");
        $stmt->execute([':gid'=>$groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function reviewJoinRequest(int $actorId, int $requestId, string $decision): array
    {
        $db = Database::connection();
        $stmt = $db->prepare("SELECT r.*, g.name AS group_name, g.owner_user_id FROM chat_group_join_requests r JOIN chat_groups g ON g.id=r.group_id WHERE r.id=:rid LIMIT 1");
        $stmt->execute([':rid'=>$requestId]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$req || (string)$req['status'] !== 'pending') throw new \RuntimeException('申请已处理');
        $actor = $this->membership((int)$req['group_id'], $actorId);
        if (!$actor || !in_array((string)$actor['role'], ['owner','admin'], true)) throw new \RuntimeException('无权审批');
        $db->prepare("UPDATE chat_group_join_requests SET status=:st, handled_by=:by, handled_at=NOW(), updated_at=NOW() WHERE id=:rid")->execute([':st'=>$decision === 'approve' ? 'approved' : 'rejected', ':by'=>$actorId, ':rid'=>$requestId]);
        if ($decision === 'approve') {
            if (!$this->membership((int)$req['group_id'], (int)$req['user_id'])) {
                $db->prepare("INSERT INTO chat_group_members (group_id,user_id,role,join_source,created_at,updated_at) VALUES (:gid,:uid,'member','approval',NOW(),NOW())")->execute([':gid'=>(int)$req['group_id'], ':uid'=>(int)$req['user_id']]);
            }
            $this->notifyUser((int)$req['user_id'], '加群申请已通过', '你申请加入群聊「' . (string)$req['group_name'] . '」已通过。', 'private', 'group_join_approved', (int)$req['group_id']);
        } else {
            $this->notifyUser((int)$req['user_id'], '加群申请未通过', '你申请加入群聊「' . (string)$req['group_name'] . '」未通过。', 'private', 'group_join_rejected', (int)$req['group_id']);
        }
        return ['status'=>$decision === 'approve' ? 'approved' : 'rejected'];
    }

    public function invitesForUser(int $userId): array
    {
        $stmt = Database::connection()->prepare("SELECT i.*, g.name AS group_name, g.public_id AS group_public_id, g.avatar AS group_avatar, u.username AS inviter_name, u.nickname AS inviter_nickname, u.avatar AS inviter_avatar FROM chat_group_invites i JOIN chat_groups g ON g.id=i.group_id JOIN users u ON u.id=i.inviter_user_id WHERE i.invitee_user_id=:uid AND i.status='pending' AND (i.expires_at IS NULL OR i.expires_at > NOW()) ORDER BY i.created_at DESC");
        $stmt->execute([':uid'=>$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
