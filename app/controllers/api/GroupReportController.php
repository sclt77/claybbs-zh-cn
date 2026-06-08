<?php

namespace App\Controllers\Api;

use App\Core\Database;
use App\Models\GroupChatModel;
use App\Models\SystemMessageModel;

class GroupReportController
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

    

    public function messages(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok' => false, 'login' => false]); return; }

        $groupId = (int)($_GET['group_id'] ?? 0);
        if ($groupId <= 0) { $this->json(['ok' => false, 'error' => '参数错误']); return; }

        try {
            $model = new GroupChatModel();
            $group = $model->groupForUser($groupId, $uid);
            if (!$group) { $this->json(['ok' => false, 'error' => '你不在该群聊中']); return; }

            $db = Database::connection();
            $stmt = $db->prepare("SELECT m.id, m.content, m.message_type, m.media_url, m.created_at,
                                         m.sender_user_id, u.username, u.nickname, u.avatar
                                  FROM chat_group_messages m
                                  JOIN users u ON u.id = m.sender_user_id
                                  WHERE m.group_id = :gid AND m.status = 'sent' AND m.sender_user_id <> :uid
                                  ORDER BY m.id DESC LIMIT 200");
            $stmt->execute([':gid' => $groupId, ':uid' => $uid]);
            $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $this->json(['ok' => true, 'messages' => array_reverse($messages), 'group' => $group]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    

    public function submit(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok' => false, 'login' => false]); return; }
        csrf_verify();

        $groupId = (int)($_POST['group_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));
        $messageIds = $_POST['message_ids'] ?? [];

        if ($groupId <= 0) { $this->json(['ok' => false, 'error' => '参数错误']); return; }
        if ($reason === '') { $this->json(['ok' => false, 'error' => '请填写投诉原因']); return; }
        if (mb_strlen($reason) > 1000) { $this->json(['ok' => false, 'error' => '投诉原因不能超过1000字']); return; }

        if (!is_array($messageIds)) $messageIds = explode(',', (string)$messageIds);
        $messageIds = array_values(array_unique(array_map('intval', $messageIds)));
        $messageIds = array_filter($messageIds, fn($id) => $id > 0);
        if (empty($messageIds)) { $this->json(['ok' => false, 'error' => '请至少选择一条消息']); return; }
        if (count($messageIds) > 50) { $this->json(['ok' => false, 'error' => '单次最多选择50条消息']); return; }

        try {
            $model = new GroupChatModel();
            $group = $model->groupForUser($groupId, $uid);
            if (!$group) { $this->json(['ok' => false, 'error' => '你不在该群聊中']); return; }

            
            $db = Database::connection();
            $placeholders = implode(',', array_map(fn($i) => ':mid_' . $i, array_keys($messageIds)));
            $stmt = $db->prepare("SELECT COUNT(*) FROM group_reports gr
                                  JOIN group_report_messages grm ON grm.report_id = gr.id
                                  WHERE gr.reporter_id = :uid AND gr.group_id = :gid
                                  AND grm.message_id IN ($placeholders)
                                  AND gr.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $params = [':uid' => $uid, ':gid' => $groupId];
            foreach ($messageIds as $i => $mid) {
                $params[':mid_' . $i] = $mid;
            }
            $stmt->execute($params);
            if ((int)$stmt->fetchColumn() > 0) {
                $this->json(['ok' => false, 'error' => '这些消息已在24小时内被投诉过，请勿重复投诉']);
                return;
            }

            
            $validStmt = $db->prepare("SELECT id, content, message_type, sender_user_id, created_at
                                       FROM chat_group_messages
                                       WHERE id IN ($placeholders)
                                         AND group_id = :gid
                                         AND sender_user_id <> :uid
                                         AND status = 'sent'
                                         AND revoked_at IS NULL");
            $validStmt->execute($params);
            $validMessages = $validStmt->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($validMessages)) {
                $this->json(['ok' => false, 'error' => '没有可投诉的有效消息']);
                return;
            }

            $db->beginTransaction();

            
            $db->prepare("INSERT INTO group_reports (group_id, reporter_id, reason, status, created_at) VALUES (:gid, :uid, :reason, 'pending', NOW())")
                ->execute([':gid' => $groupId, ':uid' => $uid, ':reason' => $reason]);
            $reportId = (int)$db->lastInsertId();

            
            $insStmt = $db->prepare("INSERT INTO group_report_messages (report_id, message_id, user_id, message_text, message_type, created_at) VALUES (:rid, :mid, :uid, :text, :type, :created)");

            foreach ($validMessages as $msg) {
                $insStmt->execute([
                    ':rid' => $reportId,
                    ':mid' => (int)$msg['id'],
                    ':uid' => (int)$msg['sender_user_id'],
                    ':text' => $msg['content'] ?? '',
                    ':type' => $msg['message_type'] ?? 'text',
                    ':created' => $msg['created_at'] ?? null,
                ]);
            }

            $db->commit();
            $this->json(['ok' => true, 'msg' => '投诉已提交，管理员将尽快处理', 'report_id' => $reportId]);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    

    public function myReports(): void
    {
        $uid = $this->userId();
        if ($uid <= 0) { $this->json(['ok' => false, 'login' => false]); return; }

        try {
            $db = Database::connection();
            $stmt = $db->prepare("SELECT r.*, g.name AS group_name,
                                         (SELECT COUNT(*) FROM group_report_messages WHERE report_id = r.id) AS message_count
                                  FROM group_reports r
                                  LEFT JOIN chat_groups g ON g.id = r.group_id
                                  WHERE r.reporter_id = :uid
                                  ORDER BY r.created_at DESC LIMIT 50");
            $stmt->execute([':uid' => $uid]);
            $this->json(['ok' => true, 'reports' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}
