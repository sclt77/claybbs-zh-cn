<?php

namespace App\Services;

use App\Core\Database;
use App\Models\SystemMessageModel;
use PDO;

class ReviewNotificationService
{
    public function notifyThreadPending(int $sectionId, int $threadId, string $title): void
    {
        $url = '/admin.php?path=moderator-workbench&tab=threads';
        $this->notifyReviewers($sectionId, '有新帖子待审核', '板块内有新帖子《' . $this->short($title) . '》等待人工审核。\n处理入口：' . $url, $url);
    }

    public function notifyPostPending(int $sectionId, int $threadId, int $postId, string $threadTitle): void
    {
        $url = '/admin.php?path=moderator-workbench&tab=posts';
        $this->notifyReviewers($sectionId, '有新回复待审核', '帖子《' . $this->short($threadTitle) . '》有新回复等待人工审核。\n处理入口：' . $url, $url);
    }

    public function notifyRevisionPending(int $sectionId, int $threadId, int $revisionId, string $title): void
    {
        $url = '/admin.php?path=moderator-workbench&tab=revisions';
        $this->notifyReviewers($sectionId, '有帖子修改待审核', '帖子《' . $this->short($title) . '》提交了修改，等待人工审核。\n处理入口：' . $url, $url);
    }

    private function notifyReviewers(int $sectionId, string $title, string $content, string $url): void
    {
        if ($sectionId <= 0) return;
        $userIds = $this->reviewerUserIds($sectionId);
        if (!$userIds) return;
        $messages = new SystemMessageModel();
        foreach ($userIds as $userId) {
            try {
                $messages->createPersonal($userId, $title, $content, 1);
            } catch (\Throwable $e) {}
        }
    }

    private function reviewerUserIds(int $sectionId): array
    {
        $db = Database::connection();
        $stmt = $db->prepare("SELECT category_id FROM sections WHERE id=:id LIMIT 1");
        $stmt->execute([':id' => $sectionId]);
        $categoryId = (int)$stmt->fetchColumn();
        $params = [':section_id' => $sectionId];
        $categoryClause = '';
        if ($categoryId > 0) {
            $categoryClause = " OR (ur.scope='category' AND ur.scope_id=:category_id)";
            $params[':category_id'] = $categoryId;
        }
        $sql = "SELECT DISTINCT u.id
                FROM users u
                JOIN user_roles ur ON ur.user_id = u.id
                JOIN roles r ON r.id = ur.role_id
                JOIN role_permissions rp ON rp.role_id = r.id
                JOIN permissions p ON p.id = rp.permission_id
                WHERE u.status = 'active'
                  AND p.slug IN ('review.thread','review.post')
                  AND r.slug IN ('moderator','reviewer','admin','superadmin')
                  AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
                  AND (ur.scope='global' OR (ur.scope='section' AND ur.scope_id=:section_id){$categoryClause})";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        
        $stmt = $db->query("SELECT id FROM users WHERE status='active' AND role IN ('moderator','reviewer','admin')");
        $ids = array_merge($ids, array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
        return array_values(array_unique(array_filter($ids)));
    }

    private function short(string $text): string
    {
        $text = trim(strip_tags($text));
        return mb_strlen($text) > 80 ? mb_substr($text, 0, 80) . '…' : $text;
    }
}
