<?php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\SystemMessageModel;

class AdminGroupController
{
    public function __construct()
    {
        AdminAuth::check();
        if (!Permission::can('admin.social') && !Permission::can('admin.report') && !Permission::canAnyScope('moderator.report.handle')) {
            Permission::require('admin.social');
        }
    }

    private function json(array $data): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    
    public function index(): void
    {
        $db = Database::connection();
        $tab = trim((string)($_GET['tab'] ?? 'groups'));
        if (!in_array($tab, ['groups', 'reports'], true)) $tab = 'groups';

        if ($tab === 'reports') {
            $this->reportsIndex($db);
        } else {
            $this->groupsIndex($db);
        }
    }

    
    private function groupsIndex($db): void
    {
        $q = trim((string)($_GET['q'] ?? ''));

        $where = "WHERE g.status = 'normal'";
        $params = [];
        if ($q !== '') {
            $where .= " AND (g.name LIKE :q OR g.public_id LIKE :q2)";
            $params[':q'] = '%' . $q . '%';
            $params[':q2'] = '%' . $q . '%';
        }

        $sql = "SELECT g.*, u.username AS owner_username, u.nickname AS owner_nickname,
                       (SELECT COUNT(*) FROM chat_group_members WHERE group_id = g.id) AS member_count,
                       (SELECT COUNT(*) FROM chat_group_messages WHERE group_id = g.id) AS message_count
                FROM chat_groups g
                LEFT JOIN users u ON u.id = g.owner_user_id
                $where
                ORDER BY g.created_at DESC
                LIMIT 100";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $groups = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stats = [
            'total' => (int)$db->query("SELECT COUNT(*) FROM chat_groups WHERE status='normal'")->fetchColumn(),
            'members' => (int)$db->query("SELECT COUNT(*) FROM chat_group_members gm JOIN chat_groups g ON g.id=gm.group_id WHERE g.status='normal'")->fetchColumn(),
            'messages' => (int)$db->query("SELECT COUNT(*) FROM chat_group_messages gm JOIN chat_groups g ON g.id=gm.group_id WHERE g.status='normal'")->fetchColumn(),
        ];

        $reportStats = $this->reportStats($db);

        $pageTitle = '群聊管理';
        $tab = 'groups';
        require dirname(__DIR__, 2) . '/views/admin/group/index.php';
    }

    
    private function reportsIndex($db): void
    {
        $status = trim((string)($_GET['status'] ?? ''));

        $where = '';
        $params = [];
        if ($status !== '' && in_array($status, ['pending', 'processed', 'rejected'], true)) {
            $where = 'WHERE r.status = :status';
            $params[':status'] = $status;
        }

        $sql = "SELECT r.*,
                       g.name AS group_name, g.public_id AS group_public_id,
                       reporter.username AS reporter_username, reporter.nickname AS reporter_nickname,
                       admin.username AS admin_username, admin.nickname AS admin_nickname,
                       (SELECT COUNT(*) FROM group_report_messages WHERE report_id = r.id) AS message_count,
                       (SELECT COUNT(DISTINCT user_id) FROM group_report_messages WHERE report_id = r.id) AS reported_user_count
                FROM group_reports r
                LEFT JOIN chat_groups g ON g.id = r.group_id
                LEFT JOIN users reporter ON reporter.id = r.reporter_id
                LEFT JOIN users admin ON admin.id = r.admin_id
                $where
                ORDER BY FIELD(r.status, 'pending', 'processed', 'rejected'), r.created_at DESC
                LIMIT 100";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $reports = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $reportStats = $this->reportStats($db);
        $groupStats = [
            'total' => (int)$db->query("SELECT COUNT(*) FROM chat_groups WHERE status='normal'")->fetchColumn(),
        ];

        $pageTitle = '群聊管理';
        $tab = 'reports';
        $statusLabels = ['pending' => '待处理', 'processed' => '已处理', 'rejected' => '已驳回'];
        require dirname(__DIR__, 2) . '/views/admin/group/index.php';
    }

    private function reportStats($db): array
    {
        return [
            'all' => (int)$db->query("SELECT COUNT(*) FROM group_reports")->fetchColumn(),
            'pending' => (int)$db->query("SELECT COUNT(*) FROM group_reports WHERE status='pending'")->fetchColumn(),
            'processed' => (int)$db->query("SELECT COUNT(*) FROM group_reports WHERE status='processed'")->fetchColumn(),
            'rejected' => (int)$db->query("SELECT COUNT(*) FROM group_reports WHERE status='rejected'")->fetchColumn(),
        ];
    }

    
    public function view(): void
    {
        $db = Database::connection();
        $groupId = (int)($_GET['id'] ?? 0);
        if ($groupId <= 0) { header('Location: /admin.php?path=group-manage'); exit; }

        $stmt = $db->prepare("SELECT g.*, u.username AS owner_username, u.nickname AS owner_nickname
                              FROM chat_groups g LEFT JOIN users u ON u.id = g.owner_user_id WHERE g.id = :gid");
        $stmt->execute([':gid' => $groupId]);
        $group = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$group) { header('Location: /admin.php?path=group-manage'); exit; }

        $stmt = $db->prepare("SELECT gm.*, u.username, u.nickname, u.avatar AS user_avatar
                              FROM chat_group_members gm JOIN users u ON u.id = gm.user_id
                              WHERE gm.group_id = :gid ORDER BY FIELD(gm.role,'owner','admin','member'), gm.id ASC");
        $stmt->execute([':gid' => $groupId]);
        $members = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stmt = $db->prepare("SELECT m.*, u.username, u.nickname
                              FROM chat_group_messages m JOIN users u ON u.id = m.sender_user_id
                              WHERE m.group_id = :gid ORDER BY m.id DESC LIMIT 200");
        $stmt->execute([':gid' => $groupId]);
        $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $messages = array_reverse($messages);

        $pageTitle = '群聊详情 - ' . ($group['name'] ?? '');
        require dirname(__DIR__, 2) . '/views/admin/group/view.php';
    }

    
    public function reportView(): void
    {
        $db = Database::connection();
        $reportId = (int)($_GET['id'] ?? 0);
        if ($reportId <= 0) { header('Location: /admin.php?path=group-manage&tab=reports'); exit; }

        $stmt = $db->prepare("SELECT r.*,
                                     g.name AS group_name, g.public_id AS group_public_id,
                                     reporter.username AS reporter_username, reporter.nickname AS reporter_nickname,
                                     admin.username AS admin_username, admin.nickname AS admin_nickname
                              FROM group_reports r
                              LEFT JOIN chat_groups g ON g.id = r.group_id
                              LEFT JOIN users reporter ON reporter.id = r.reporter_id
                              LEFT JOIN users admin ON admin.id = r.admin_id
                              WHERE r.id = :rid");
        $stmt->execute([':rid' => $reportId]);
        $report = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$report) { header('Location: /admin.php?path=group-manage&tab=reports'); exit; }

        $stmt = $db->prepare("SELECT rm.*, u.username, u.nickname, u.avatar AS user_avatar
                              FROM group_report_messages rm
                              JOIN users u ON u.id = rm.user_id
                              WHERE rm.report_id = :rid ORDER BY rm.id ASC");
        $stmt->execute([':rid' => $reportId]);
        $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $userMap = [];
        foreach ($messages as $m) {
            $uid = (int)$m['user_id'];
            if (!isset($userMap[$uid])) {
                $userMap[$uid] = [
                    'user_id' => $uid,
                    'username' => $m['username'],
                    'nickname' => $m['nickname'],
                    'avatar' => $m['user_avatar'],
                    'message_count' => 0,
                ];
            }
            $userMap[$uid]['message_count']++;
        }
        $reportedUsers = array_values($userMap);

        $stmt = $db->prepare("SELECT ra.*, u.username, u.nickname
                              FROM group_report_actions ra
                              JOIN users u ON u.id = ra.target_user_id
                              WHERE ra.report_id = :rid ORDER BY ra.id ASC");
        $stmt->execute([':rid' => $reportId]);
        $actions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $pageTitle = '投诉详情 #' . $reportId;
        require dirname(__DIR__, 2) . '/views/admin/group/report_view.php';
    }

    
    public function action(): void
    {
        csrf_verify();
        $db = Database::connection();
        $groupId = (int)($_POST['group_id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        $adminId = (int)($_SESSION['auth_user']['id'] ?? 0);

        if ($groupId <= 0) { $this->json(['ok' => false, 'error' => '参数错误']); }

        try {
            if ($action === 'disband') {
                $db->prepare("UPDATE chat_groups SET status='disbanded', updated_at=NOW() WHERE id=:gid")->execute([':gid' => $groupId]);
                $this->json(['ok' => true, 'msg' => '群聊已解散']);
            } elseif ($action === 'kick') {
                $userId = (int)($_POST['user_id'] ?? 0);
                if ($userId <= 0) throw new \RuntimeException('参数错误');
                $db->prepare("DELETE FROM chat_group_members WHERE group_id=:gid AND user_id=:uid AND role<>'owner'")->execute([':gid' => $groupId, ':uid' => $userId]);
                $this->json(['ok' => true, 'msg' => '已踢出']);
            } elseif ($action === 'ban') {
                $userId = (int)($_POST['user_id'] ?? 0);
                $days = (int)($_POST['days'] ?? 0);
                $reason = trim((string)($_POST['reason'] ?? ''));
                if ($userId <= 0) throw new \RuntimeException('参数错误');
                $until = $days > 0 ? date('Y-m-d H:i:s', time() + $days * 86400) : null;
                $db->prepare("UPDATE chat_group_members SET banned_until=:until, banned_by=:admin, ban_reason=:reason, updated_at=NOW() WHERE group_id=:gid AND user_id=:uid")
                    ->execute([':until' => $until, ':admin' => $adminId, ':reason' => $reason, ':gid' => $groupId, ':uid' => $userId]);
                $this->json(['ok' => true, 'msg' => '已封禁']);
            } elseif ($action === 'unban') {
                $userId = (int)($_POST['user_id'] ?? 0);
                $db->prepare("UPDATE chat_group_members SET banned_until=NULL, banned_by=NULL, ban_reason=NULL, updated_at=NOW() WHERE group_id=:gid AND user_id=:uid")
                    ->execute([':gid' => $groupId, ':uid' => $userId]);
                $this->json(['ok' => true, 'msg' => '已解封']);
            } else {
                $this->json(['ok' => false, 'error' => '未知操作']);
            }
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    
    public function processReport(): void
    {
        csrf_verify();
        $db = Database::connection();
        $reportId = (int)($_POST['report_id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        $adminId = (int)($_SESSION['auth_user']['id'] ?? 0);
        $adminNote = trim((string)($_POST['admin_note'] ?? ''));

        if ($reportId <= 0) { $this->json(['ok' => false, 'error' => '参数错误']); }
        if (!in_array($action, ['ban', 'warn', 'reject'], true)) { $this->json(['ok' => false, 'error' => '操作无效']); }

        $stmt = $db->prepare("SELECT * FROM group_reports WHERE id = :rid");
        $stmt->execute([':rid' => $reportId]);
        $report = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$report) { $this->json(['ok' => false, 'error' => '投诉不存在']); }
        if ($report['status'] !== 'pending') { $this->json(['ok' => false, 'error' => '该投诉已处理']); }

        $db->beginTransaction();
        try {
            $status = $action === 'reject' ? 'rejected' : 'processed';
            $db->prepare("UPDATE group_reports SET status=:status, admin_id=:admin, admin_note=:note, processed_at=NOW() WHERE id=:rid")
                ->execute([':status' => $status, ':admin' => $adminId, ':note' => $adminNote, ':rid' => $reportId]);

            $stmt = $db->prepare("SELECT DISTINCT user_id FROM group_report_messages WHERE report_id = :rid");
            $stmt->execute([':rid' => $reportId]);
            $affectedUserIds = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'user_id');

            $groupId = (int)$report['group_id'];
            $reporterId = (int)$report['reporter_id'];
            $banDays = (int)($_POST['ban_days'] ?? 0);
            if ($banDays < 0 || $banDays > 3650) {
                $db->rollBack();
                $this->json(['ok' => false, 'error' => '封禁天数范围：0-3650']);
            }
            $banReason = $adminNote !== '' ? $adminNote : '群聊投诉处理';

            foreach ($affectedUserIds as $targetUserId) {
                $targetUserId = (int)$targetUserId;
                if ($targetUserId <= 0) continue;

                $db->prepare("INSERT INTO group_report_actions (report_id, action_type, target_user_id, ban_duration, ban_reason) VALUES (:rid, :action, :uid, :days, :reason)")
                    ->execute([
                        ':rid' => $reportId,
                        ':action' => $action,
                        ':uid' => $targetUserId,
                        ':days' => $action === 'ban' ? $banDays : null,
                        ':reason' => $action !== 'reject' ? $banReason : null,
                    ]);

                if ($action === 'ban') {
                    $until = $banDays > 0 ? date('Y-m-d H:i:s', time() + $banDays * 86400) : null;
                    $db->prepare("UPDATE chat_group_members SET banned_until=:until, banned_by=:admin, ban_reason=:reason, updated_at=NOW() WHERE group_id=:gid AND user_id=:uid")
                        ->execute([':until' => $until, ':admin' => $adminId, ':reason' => $banReason, ':gid' => $groupId, ':uid' => $targetUserId]);

                    $this->notifyUser($targetUserId, '群聊投诉处理通知',
                        '您在群聊中的发言被投诉，已被管理员封禁' . ($banDays > 0 ? $banDays . '天' : '（永久）') . '。原因：' . $banReason);
                } elseif ($action === 'warn') {
                    $this->notifyUser($targetUserId, '群聊投诉警告',
                        '您在群聊中的发言被投诉，管理员已对您发出警告。原因：' . $banReason);
                }

                if ($targetUserId === $reporterId) continue;
            }

            $reporterMsg = $action === 'reject'
                ? '您提交的群聊投诉经审核未发现问题，已驳回。'
                : '您提交的群聊投诉已处理，管理员已对相关用户进行' . ($action === 'ban' ? '封禁' : '警告') . '处理。';
            $this->notifyUser($reporterId, '群聊投诉处理结果', $reporterMsg);

            $db->commit();
            $redirect = '/admin.php?path=group-manage&tab=reports';
            $this->json(['ok' => true, 'msg' => '处理成功', 'redirect' => $redirect]);
        } catch (\Throwable $e) {
            $db->rollBack();
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    private function notifyUser(int $userId, string $title, string $content): void
    {
        if ($userId <= 0) return;
        $maxRetries = 3;
        $delay = 1; 
        $lastError = null;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                (new SystemMessageModel())->createPersonal($userId, $title, $content, 0, 'system');
                return;
            } catch (\Throwable $e) {
                $lastError = $e;
                if ($attempt < $maxRetries) {
                    usleep($delay * 1000000);
                    $delay *= 2; 
                }
            }
        }
        error_log('[ClayBBS] group report notify failed after ' . $maxRetries . ' retries for user ' . $userId . ': ' . ($lastError ? $lastError->getMessage() : 'unknown'));
    }
}
