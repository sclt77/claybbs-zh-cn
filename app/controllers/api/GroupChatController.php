<?php

namespace App\Controllers\Api;

use App\Models\GroupChatModel;
use App\Models\PrivateChatModel;
use App\Models\SettingModel;
use App\Models\UserCreditModel;
use App\Services\AiReviewService;
use App\Core\Database;

class GroupChatController
{
    private function userId(): int
    {
        return (int)($_SESSION['auth_user']['id'] ?? 0);
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function settings(): array
    {
        $s = (new SettingModel())->all();
        return [
            'group_chat_enabled' => ($s['group_chat_enabled'] ?? '1') === '1',
            'group_chat_review_enabled' => ($s['group_chat_review_enabled'] ?? '0') === '1',
            'ai_review_images' => ($s['ai_review_images'] ?? '0') === '1',
            'private_chat_message_max_length' => max(50, (int)($s['private_chat_message_max_length'] ?? 1000)),
        ];
    }

    public function bootstrap(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        try {
            $model = new GroupChatModel();
            $private = new PrivateChatModel();
            $this->json([
                'ok'=>true,
                'settings'=>$this->settings(),
                'groups'=>$model->groups($uid),
                'friends'=>$private->friends($uid),
                'unread'=>$model->unreadCount($uid),
            ]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function create(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        $settings = $this->settings();
        if (!$settings['group_chat_enabled']) { $this->json(['ok'=>false,'error'=>'群聊功能暂未开启']); return; }
        try {
            $memberIds = $_POST['member_ids'] ?? [];
            if (!is_array($memberIds)) $memberIds = explode(',', (string)$memberIds);
            $group = (new GroupChatModel())->create($uid, (string)($_POST['name'] ?? ''), (string)($_POST['notice'] ?? ''), $memberIds, (string)($_POST['avatar'] ?? ''));
            $model = new GroupChatModel();
            $this->json(['ok'=>true, 'group'=>$group, 'groups'=>$model->groups($uid), 'unread'=>$model->unreadCount($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function messages(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        $groupId = (int)($_GET['group_id'] ?? 0);
        try {
            $model = new GroupChatModel();
            if ($groupId > 0) $model->markRead($uid, $groupId);
            $this->json([
                'ok'=>true,
                'group'=>$groupId > 0 ? $model->groupForUser($groupId, $uid) : null,
                'members'=>$groupId > 0 ? $model->members($groupId, $uid) : [],
                'messages'=>$groupId > 0 ? $model->messages($uid, $groupId, 0, 120) : [],
                'groups'=>$model->groups($uid),
                'unread'=>$model->unreadCount($uid),
            ]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function poll(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        $groupId = (int)($_GET['group_id'] ?? 0);
        $afterId = (int)($_GET['after_id'] ?? 0);
        try {
            $model = new GroupChatModel();
            $this->json(['ok'=>true, 'unread'=>$model->unreadCount($uid), 'groups'=>$model->groups($uid), 'messages'=>$groupId > 0 ? $model->messages($uid, $groupId, $afterId, 80) : []]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function send(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        $settings = $this->settings();
        if (!$settings['group_chat_enabled']) { $this->json(['ok'=>false,'error'=>'群聊功能暂未开启']); return; }
        $groupId = (int)($_POST['group_id'] ?? 0);
        $content = (string)($_POST['content'] ?? '');
        try {
            $creditLimit = (new UserCreditModel())->checkRestriction($uid, 'private_message');
            if (empty($creditLimit['allowed'])) { $this->json(['ok'=>false,'error'=>(string)$creditLimit['message'],'credit_limited'=>true]); return; }
            $model = new GroupChatModel();
            $msg = $model->send($uid, $groupId, $content, $settings['group_chat_review_enabled'], $settings['private_chat_message_max_length']);
            if ($settings['group_chat_review_enabled'] && !empty($msg['id'])) {
                $review = (new AiReviewService())->review('group_message', $uid, '群聊消息', $content);
                if (!empty($review['passed'])) $model->approveMessage((int)$msg['id'], $review);
                else $model->rejectMessage((int)$msg['id'], $review);
                $msg = $model->messageById((int)$msg['id']) ?: $msg;
            }
            $this->json(['ok'=>true, 'message'=>$msg, 'groups'=>$model->groups($uid), 'unread'=>$model->unreadCount($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function sendImage(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        $settings = $this->settings();
        if (!$settings['group_chat_enabled']) { $this->json(['ok'=>false,'error'=>'群聊功能暂未开启']); return; }
        $groupId = (int)($_POST['group_id'] ?? 0);
        try {
            $creditLimit = (new UserCreditModel())->checkPrivateImageAllowed($uid);
            if (empty($creditLimit['allowed'])) { $this->json(['ok'=>false,'error'=>(string)$creditLimit['message'],'credit_limited'=>true]); return; }
            if (empty($_FILES['image'])) throw new \RuntimeException('请选择图片');
            $url = upload_image($_FILES['image'], 'group-chat/' . date('Ymd'), 5 * 1024 * 1024);
            if ($settings['ai_review_images']) {
                $review = (new AiReviewService())->reviewImage('group_message_image', $uid, '群聊图片', $url);
                if (empty($review['passed'])) {
                    delete_local_upload($url);
                    $this->json(['ok'=>false,'error'=>($review['reason'] ?? '图片未通过 AI 审核')]);
                    return;
                }
            }
            $model = new GroupChatModel();
            try {
                $msg = $model->sendImage($uid, $groupId, $url);
            } catch (\Throwable $e) {
                delete_local_upload($url);
                throw $e;
            }
            $this->json(['ok'=>true, 'message'=>$msg, 'groups'=>$model->groups($uid), 'unread'=>$model->unreadCount($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function update(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        try {
            $model = new GroupChatModel();
            $group = $model->updateGroup($uid, (int)($_POST['group_id'] ?? 0), (string)($_POST['name'] ?? ''), (string)($_POST['notice'] ?? ''), (string)($_POST['avatar'] ?? ''), (string)($_POST['notice_title'] ?? ''));
            $this->json(['ok'=>true, 'group'=>$group, 'groups'=>$model->groups($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function uploadAvatar(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        try {
            $groupId = (int)($_POST['group_id'] ?? 0);
            if ($groupId <= 0) throw new \RuntimeException('参数错误');
            $model = new GroupChatModel();
            $group = $model->groupForUser($groupId, $uid);
            if (!$group) throw new \RuntimeException('群聊不存在或无权访问');
            if ((string)$group['role'] !== 'owner') throw new \RuntimeException('只有群主可以修改群头像');
            if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) throw new \RuntimeException('请选择图片');
            $file = $_FILES['avatar'];
            $allowed = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!isset($allowed[$ext])) throw new \RuntimeException('仅支持 JPG/PNG/GIF/WEBP');
            if ($file['size'] > 5 * 1024 * 1024) throw new \RuntimeException('图片不能超过 5MB');
            $dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/group-avatars/';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $name = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            move_uploaded_file($file['tmp_name'], $dir . $name);
            $url = '/uploads/group-avatars/' . $name;
            Database::connection()->prepare("UPDATE chat_groups SET avatar=:avatar, updated_at=NOW() WHERE id=:gid")
                ->execute([':avatar'=>$url, ':gid'=>$groupId]);
            $this->json(['ok'=>true, 'avatar'=>$url, 'group'=>$model->groupForUser($groupId, $uid), 'groups'=>$model->groups($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function memberSettings(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        try {
            $groupId = (int)($_POST['group_id'] ?? 0);
            $type = (string)($_POST['type'] ?? '');
            $enabled = ((string)($_POST['enabled'] ?? '0')) === '1';
            if (!in_array($type, ['pinned','muted'], true)) throw new \RuntimeException('设置项无效');
            $model = new GroupChatModel();
            $group = $model->updateMemberSettings($uid, $groupId, $type === 'pinned' ? $enabled : null, $type === 'muted' ? $enabled : null);
            $this->json(['ok'=>true, 'group'=>$group, 'groups'=>$model->groups($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function clearHistory(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        try {
            $model = new GroupChatModel();
            $group = $model->clearHistory($uid, (int)($_POST['group_id'] ?? 0));
            $this->json(['ok'=>true, 'group'=>$group, 'groups'=>$model->groups($uid), 'messages'=>[]]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function leave(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        try {
            $model = new GroupChatModel();
            $action = $model->leaveOrDisband($uid, (int)($_POST['group_id'] ?? 0));
            $this->json(['ok'=>true, 'action'=>$action, 'groups'=>$model->groups($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function search(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        try {
            $model = new GroupChatModel();
            $items = $model->searchMessages($uid, (int)($_GET['group_id'] ?? 0), (string)($_GET['q'] ?? ''));
            $this->json(['ok'=>true, 'items'=>$items]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function memberAction(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        try {
            $model = new GroupChatModel();
            $groupId = (int)($_POST['group_id'] ?? 0);
            $targetId = (int)($_POST['user_id'] ?? 0);
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'set_admin') $group = $model->setMemberRole($uid, $groupId, $targetId, 'admin');
            elseif ($action === 'unset_admin') $group = $model->setMemberRole($uid, $groupId, $targetId, 'member');
            elseif ($action === 'ban') $group = $model->banMember($uid, $groupId, $targetId, (int)($_POST['minutes'] ?? 60), (string)($_POST['reason'] ?? ''));
            elseif ($action === 'unban') $group = $model->unbanMember($uid, $groupId, $targetId);
            elseif ($action === 'kick') $group = $model->kickMember($uid, $groupId, $targetId);
            elseif ($action === 'set_title') $group = $model->setMemberTitle($uid, $groupId, $targetId, (string)($_POST['title'] ?? ''), (string)($_POST['color'] ?? '#22c55e'));
            elseif ($action === 'clear_title') $group = $model->clearMemberTitle($uid, $groupId, $targetId);
            else throw new \RuntimeException('操作无效');
            $this->json(['ok'=>true, 'group'=>$group, 'members'=>$model->members($groupId, $uid), 'groups'=>$model->groups($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function revoke(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        try {
            $model = new GroupChatModel();
            $msg = $model->revokeMessage($uid, (int)($_POST['message_id'] ?? 0));
            $this->json(['ok'=>true, 'message'=>$msg, 'groups'=>$model->groups($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function clearRevokedContent(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false]); return; }
        (new GroupChatModel())->clearRevokedContent($uid, (int)($_POST['message_id'] ?? 0));
        $this->json(['ok'=>true]);
    }

    public function invite(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        try {
            $model = new GroupChatModel();
            $result = $model->inviteMember($uid, (int)($_POST['group_id'] ?? 0), (int)($_POST['user_id'] ?? 0));
            $this->json(['ok'=>true] + $result);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function handleInvite(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        try {
            $model = new GroupChatModel();
            $result = $model->handleInvite($uid, (int)($_POST['invite_id'] ?? 0), (string)($_POST['decision'] ?? 'reject'));
            $this->json(['ok'=>true] + $result + ['groups'=>$model->groups($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function searchGroups(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        try {
            $this->json(['ok'=>true, 'groups'=>(new GroupChatModel())->searchPublicGroups($uid, (string)($_GET['q'] ?? ''))]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function join(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        try {
            $model = new GroupChatModel();
            $result = $model->joinOrRequest($uid, (int)($_POST['group_id'] ?? 0), (string)($_POST['message'] ?? ''));
            $this->json(['ok'=>true] + $result + ['groups'=>$model->groups($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function joinMode(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        try {
            $model = new GroupChatModel();
            $group = $model->setJoinMode($uid, (int)($_POST['group_id'] ?? 0), (string)($_POST['mode'] ?? 'direct'));
            $this->json(['ok'=>true, 'group'=>$group, 'groups'=>$model->groups($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function pendingRequests(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        try {
            $this->json(['ok'=>true, 'requests'=>(new GroupChatModel())->pendingRequests($uid, (int)($_GET['group_id'] ?? 0))]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function reviewJoinRequest(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        try {
            $result = (new GroupChatModel())->reviewJoinRequest($uid, (int)($_POST['request_id'] ?? 0), (string)($_POST['decision'] ?? 'reject'));
            $this->json(['ok'=>true] + $result);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function myInvites(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        try {
            $this->json(['ok'=>true, 'invites'=>(new GroupChatModel())->invitesForUser($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }
}
