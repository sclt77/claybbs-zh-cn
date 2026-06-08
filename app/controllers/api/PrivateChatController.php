<?php

namespace App\Controllers\Api;

use App\Models\FollowModel;
use App\Models\PrivateChatModel;
use App\Models\MomentModel;
use App\Models\SettingModel;
use App\Services\AiReviewService;
use App\Models\UserCreditModel;

class PrivateChatController
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
            'friend_system_enabled' => ($s['friend_system_enabled'] ?? '1') === '1',
            'private_chat_enabled' => ($s['private_chat_enabled'] ?? '1') === '1',
            'private_chat_review_enabled' => ($s['private_chat_review_enabled'] ?? '0') === '1',
            'ai_review_images' => ($s['ai_review_images'] ?? '0') === '1',
            'private_chat_message_max_length' => max(50, (int)($s['private_chat_message_max_length'] ?? 1000)),
            'private_chat_poll_interval' => max(1200, (int)($s['private_chat_poll_interval'] ?? 3000)),
            'friend_search_nickname_enabled' => ($s['friend_search_nickname_enabled'] ?? '1') === '1',
            'credit_restrict_enabled' => ($s['credit_restrict_enabled'] ?? '1') === '1',
        ];
    }

    public function bootstrap(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        $settings = $this->settings();
        $model = new PrivateChatModel();
        try {
            $this->json([
                'ok'=>true,
                'settings'=>$settings,
                'unread'=>$model->unreadCount($uid),
                'conversations'=>$model->conversations($uid),
                'friends'=>$model->friends($uid),
                'following'=>$model->followingWithRelation($uid),
                'followers'=>$model->followersWithRelation($uid),
            ]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function poll(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        $peerId = (int)($_GET['peer_id'] ?? 0);
        $afterId = (int)($_GET['after_id'] ?? 0);
        $model = new PrivateChatModel();
        try {
            $messages = $peerId > 0 ? $model->messages($uid, $peerId, $afterId, 80) : [];
            $this->json(['ok'=>true, 'unread'=>$model->unreadCount($uid), 'conversations'=>$model->conversations($uid), 'messages'=>$messages]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function messages(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        $peerId = (int)($_GET['peer_id'] ?? 0);
        $model = new PrivateChatModel();
        try {
            if ($peerId > 0) $model->markRead($uid, $peerId);
            $focusId = (int)($_GET['focus_id'] ?? 0);
            $messages = [];
            if ($peerId > 0) {
                $messages = $focusId > 0 ? $model->messagesAround($uid, $peerId, $focusId, 70, 70) : $model->messages($uid, $peerId, 0, 120);
            }
            $this->json(['ok'=>true, 'messages'=>$messages, 'unread'=>$model->unreadCount($uid), 'can_chat'=>$peerId > 0 && $model->areFriends($uid, $peerId)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function send(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        $settings = $this->settings();
        if (!$settings['private_chat_enabled']) { $this->json(['ok'=>false,'error'=>'私聊功能暂未开启']); return; }
        $peerId = (int)($_POST['peer_id'] ?? 0);
        $content = (string)($_POST['content'] ?? '');
        $model = new PrivateChatModel();
        try {
            $creditLimit = (new UserCreditModel())->checkRestriction($uid, 'private_message');
            if (empty($creditLimit['allowed'])) { $this->json(['ok'=>false,'error'=>(string)$creditLimit['message'],'credit_limited'=>true]); return; }
            $msg = $model->send($uid, $peerId, $content, $settings['private_chat_review_enabled'], $settings['private_chat_message_max_length']);
            if ($settings['private_chat_review_enabled'] && !empty($msg['id'])) {
                $review = (new AiReviewService())->review('private_message', $uid, '私聊消息', $content);
                if (!empty($review['passed'])) {
                    $model->approveMessage((int)$msg['id'], $review);
                } else {
                    $model->rejectMessage((int)$msg['id'], $review);
                }
                $msg = $model->messageById((int)$msg['id']) ?: $msg;
            }
            if (($msg['status'] ?? 'sent') === 'sent') {
                $this->notifyPrivateMessage($uid, $peerId, $content);
            }
            $this->json(['ok'=>true,'message'=>$msg,'unread'=>$model->unreadCount($uid),'conversations'=>$model->conversations($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function sendImage(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        $settings = $this->settings();
        if (!$settings['private_chat_enabled']) { $this->json(['ok'=>false,'error'=>'私聊功能暂未开启']); return; }
        $peerId = (int)($_POST['peer_id'] ?? 0);
        try {
            $creditLimit = (new UserCreditModel())->checkPrivateImageAllowed($uid);
            if (empty($creditLimit['allowed'])) { $this->json(['ok'=>false,'error'=>(string)$creditLimit['message'],'credit_limited'=>true]); return; }
            if (empty($_FILES['image'])) throw new \RuntimeException('请选择图片');
            $url = upload_image($_FILES['image'], 'private-chat/' . date('Ymd'), 5 * 1024 * 1024);
            if ($settings['ai_review_images']) {
                $review = (new AiReviewService())->reviewImage('private_message_image', $uid, '私聊图片', $url);
                if (empty($review['passed'])) {
                    delete_local_upload($url);
                    $this->json(['ok'=>false,'error'=>($review['reason'] ?? '图片未通过 AI 审核')]);
                    return;
                }
            }
            $model = new PrivateChatModel();
            try {
                $msg = $model->sendImage($uid, $peerId, $url);
            } catch (\Throwable $e) {
                delete_local_upload($url);
                throw $e;
            }
            $this->notifyPrivateMessage($uid, $peerId, '[图片]');
            $this->json(['ok'=>true,'message'=>$msg,'unread'=>$model->unreadCount($uid),'conversations'=>$model->conversations($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }


    private function notifyPrivateMessage(int $senderId, int $receiverId, string $preview): void
    {
        try {
            if ($receiverId <= 0 || !(new \App\Models\NotificationSettingModel())->enabled($receiverId, 'private_chat')) return;
            $sender = $_SESSION['auth_user'] ?? [];
            $name = user_display_name($sender, '用户');
            $preview = mb_substr(trim(strip_tags($preview)), 0, 80);
            (new \App\Models\SystemMessageModel())->createPersonal($receiverId, '你收到一条新的私聊', $name . ' 给你发来私聊：' . ($preview !== '' ? $preview : '[消息]'), 0, 'private');
        } catch (\Throwable $e) {}
    }

    public function moments(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        try {
            $model = new MomentModel();
            $this->json(['ok'=>true, 'profile'=>$model->profile($uid), 'moments'=>$model->feed($uid, 40, (int)($_GET['before_id'] ?? 0))]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function publishMoment(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        try {
            $creditLimit = (new UserCreditModel())->checkRestriction($uid, 'moment');
            if (empty($creditLimit['allowed'])) { $this->json(['ok'=>false,'error'=>(string)$creditLimit['message'],'credit_limited'=>true]); return; }
            $images = [];
            if (!empty($_FILES['images']) && is_array($_FILES['images']['name'] ?? null)) {
                $count = min(9, count($_FILES['images']['name']));
                for ($i=0; $i<$count; $i++) {
                    if (empty($_FILES['images']['tmp_name'][$i]) || (int)($_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                    $file = [
                        'name'=>$_FILES['images']['name'][$i],
                        'type'=>$_FILES['images']['type'][$i] ?? '',
                        'tmp_name'=>$_FILES['images']['tmp_name'][$i],
                        'error'=>$_FILES['images']['error'][$i],
                        'size'=>$_FILES['images']['size'][$i] ?? 0,
                    ];
                    $images[] = upload_image($file, 'moments/' . date('Ymd'), 5 * 1024 * 1024);
                }
            }
            $content = (string)($_POST['content'] ?? '');
            if ((new AiReviewService())->enabledFor('moment')) {
                $reviewText = trim($content . ($images ? "\n[图片数量：" . count($images) . "]" : ''));
                $review = (new AiReviewService())->review('moment', $uid, '朋友圈动态', $reviewText);
                if (empty($review['passed'])) {
                    foreach ($images as $url) { delete_local_upload($url); }
                    $this->json(['ok'=>false,'error'=>($review['reason'] ?? '朋友圈内容未通过 AI 审核')]);
                    return;
                }
            }
            $moment = (new MomentModel())->create($uid, $content, $images);
            $this->json(['ok'=>true, 'moment'=>$moment]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function momentCover(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        try {
            if (empty($_FILES['cover'])) throw new \RuntimeException('请选择背景图');
            $url = upload_image($_FILES['cover'], 'moments/covers', 5 * 1024 * 1024);
            $model = new MomentModel();
            $model->setCover($uid, $url);
            $this->json(['ok'=>true, 'profile'=>$model->profile($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function revoke(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        try {
            $model = new PrivateChatModel();
            $msg = $model->revokeMessage($uid, (int)($_POST['message_id'] ?? 0));
            $this->json(['ok'=>true,'message'=>$msg,'conversations'=>$model->conversations($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function clearRevokedContent(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false]); return; }
        (new PrivateChatModel())->clearRevokedContent($uid, (int)($_POST['message_id'] ?? 0));
        $this->json(['ok'=>true]);
    }

    public function search(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        $settings = $this->settings();
        $kw = (string)($_GET['q'] ?? '');
        try { $this->json(['ok'=>true,'users'=>(new PrivateChatModel())->searchUsers($uid, $kw, $settings['friend_search_nickname_enabled'])]); }
        catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function searchAll(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        $settings = $this->settings();
        $kw = (string)($_GET['q'] ?? '');
        try {
            $model = new PrivateChatModel();
            $this->json([
                'ok'=>true,
                'users'=>$model->searchUsers($uid, $kw, $settings['friend_search_nickname_enabled']),
                'messages'=>$model->searchMessages($uid, $kw, 40),
            ]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function follow(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        $targetId = (int)($_POST['user_id'] ?? 0);
        try {
            (new FollowModel())->follow($uid, $targetId);
            $model = new PrivateChatModel();
            $this->json(['ok'=>true,'is_friend'=>$model->areFriends($uid, $targetId),'friends'=>$model->friends($uid),'following'=>$model->followingWithRelation($uid),'followers'=>$model->followersWithRelation($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function hideConversation(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        $peerId = (int)($_POST['peer_id'] ?? 0);
        try {
            $model = new PrivateChatModel();
            $model->hideConversation($uid, $peerId);
            $this->json(['ok'=>true,'conversations'=>$model->conversations($uid),'unread'=>$model->unreadCount($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function pinConversation(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        try {
            $model = new PrivateChatModel();
            $model->setPinned($uid, (int)($_POST['peer_id'] ?? 0), !empty($_POST['pinned']));
            $this->json(['ok'=>true,'conversations'=>$model->conversations($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function muteConversation(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        try {
            $model = new PrivateChatModel();
            $model->setMuted($uid, (int)($_POST['peer_id'] ?? 0), !empty($_POST['muted']));
            $this->json(['ok'=>true,'conversations'=>$model->conversations($uid),'unread'=>$model->unreadCount($uid)]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }

    public function reportMessage(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok'=>false, 'login'=>false]); return; }
        csrf_verify();
        try {
            (new PrivateChatModel())->reportMessage($uid, (int)($_POST['message_id'] ?? 0), (string)($_POST['reason'] ?? ''));
            $this->json(['ok'=>true]);
        } catch (\Throwable $e) { $this->json(['ok'=>false,'error'=>$e->getMessage()]); }
    }
}
