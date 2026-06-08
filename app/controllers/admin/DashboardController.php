<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Core\Database;

class DashboardController
{
    public function __construct()
    {
        AdminAuth::check();
    }

    public function index(): void
    {
        $role = (string)($_SESSION['auth_user']['role'] ?? '');
        $roleRows = [];
        try { $roleRows = Permission::getUserRoles((int)($_SESSION['auth_user']['id'] ?? 0)); } catch (\Throwable $e) { $roleRows = []; }
        $roleSlugs = array_values(array_unique(array_merge([$role], array_map(static fn($r)=>(string)($r['slug'] ?? ''), $roleRows))));
        $hasGlobalAdminRole = $role === 'superadmin' || in_array('superadmin', $roleSlugs, true) || (bool)array_filter($roleRows, static fn($r)=>(string)($r['scope'] ?? 'global') === 'global' && in_array((string)($r['slug'] ?? ''), ['admin','superadmin'], true));
        $isFullAdmin = $hasGlobalAdminRole || Permission::can('admin.settings') || Permission::can('user.ban') || Permission::can('section.manage') || Permission::can('admin.full');
        $isScopedModerator = !$isFullAdmin && (in_array('moderator', $roleSlugs, true) || Permission::canAnyScope('moderator.dashboard') || Permission::canAnyScope('moderator.report.handle'));
        $isScopedReviewer = !$isFullAdmin && !$isScopedModerator && (in_array('reviewer', $roleSlugs, true) || Permission::canAnyScope('review.thread') || Permission::canAnyScope('review.post'));
        if ($isScopedModerator) { header('Location: /admin.php?path=moderator-workbench'); exit; }
        if ($isScopedReviewer) { header('Location: /admin.php?path=reviewer-workbench'); exit; }
        $limitedWorkbench = false;
        $workspaceTitle = null;
        $workspaceSubtitle = null;
        $reportLink = '/admin.php?path=reports';
        $stats = ['users' => null, 'threads' => null, 'posts' => null, 'pending' => null, 'today_threads' => null, 'today_posts' => null, 'today_users' => null, 'reports' => null, 'ai_rejected' => null, 'image_rejected' => null, 'revoked_private' => null, 'blocks_today' => null];
        $ops = ['hot_threads' => [], 'recent_reports' => [], 'risk_users' => [], 'top_reported' => [], 'sensitive_hits' => [], 'recent_ai_blocks' => []];
        try {
            $db = Database::connection();
            if (Permission::can('user.ban')) {
                $stats['users'] = (int) $db->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
            }
            if (Permission::can('thread.edit_any') || Permission::can('thread.delete_any') || Permission::can('thread.hide') || Permission::can('thread.pin') || Permission::can('thread.feature') || Permission::can('thread.recommend') || Permission::can('thread.lock')) {
                $stats['threads'] = (int) $db->query("SELECT COUNT(*) FROM threads WHERE status='published'")->fetchColumn();
            }
            if (Permission::canAnyScope('review.post') || Permission::can('post.delete_any')) {
                $stats['posts'] = (int) $db->query("SELECT COUNT(*) FROM posts WHERE status='published'")->fetchColumn();
            }
            if (Permission::canAnyScope('review.thread') || Permission::canAnyScope('review.post')) {
                $stats['pending'] = (int) $db->query("SELECT COUNT(*) FROM threads WHERE status='pending'")->fetchColumn();
                $stats['pending'] += (int) $db->query("SELECT COUNT(*) FROM posts WHERE status='pending'")->fetchColumn();
            }
            if (Permission::can('thread.edit_any') || Permission::canAnyScope('review.thread')) {
                $stats['today_threads'] = (int)$db->query("SELECT COUNT(*) FROM threads WHERE created_at >= CURDATE()")->fetchColumn();
                $stats['today_posts'] = (int)$db->query("SELECT COUNT(*) FROM posts WHERE created_at >= CURDATE()")->fetchColumn();
                $ops['hot_threads'] = $db->query("SELECT id,title,view_count,reply_count,like_count,favorite_count FROM threads WHERE status='published' ORDER BY (view_count*0.2 + reply_count*3 + like_count*2 + favorite_count*2) DESC, id DESC LIMIT 6")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
            if (Permission::can('user.ban')) {
                $stats['today_users'] = (int)$db->query("SELECT COUNT(*) FROM users WHERE created_at >= CURDATE()")->fetchColumn();
            }
            if (Permission::can('admin.report') || Permission::can('thread.delete_any') || Permission::can('post.delete_any')) {
                $stats['reports'] = (int)$db->query("SELECT COUNT(*) FROM content_reports WHERE status='pending'")->fetchColumn();
                $ops['recent_reports'] = $db->query("SELECT target_type,target_id,reason,created_at FROM content_reports WHERE status='pending' ORDER BY created_at DESC, id DESC LIMIT 6")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $ops['top_reported'] = $db->query("SELECT target_type,target_id,COUNT(*) AS total,MAX(created_at) AS last_at FROM content_reports GROUP BY target_type,target_id ORDER BY total DESC,last_at DESC LIMIT 6")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
            if (Permission::canAnyScope('review.thread') || Permission::canAnyScope('review.post')) {
                $stats['ai_rejected'] = (int)$db->query("SELECT COUNT(*) FROM ai_review_logs WHERE status='rejected' AND created_at >= CURDATE()")->fetchColumn();
                $stats['image_rejected'] = (int)$db->query("SELECT COUNT(*) FROM ai_review_logs WHERE status='rejected' AND target_type='private_message_image' AND created_at >= CURDATE()")->fetchColumn();
                $ops['sensitive_hits'] = $db->query("SELECT COALESCE(NULLIF(categories,''),'未分类') AS category, COUNT(*) AS total FROM ai_review_logs WHERE status='rejected' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY COALESCE(NULLIF(categories,''),'未分类') ORDER BY total DESC LIMIT 6")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $ops['recent_ai_blocks'] = $db->query("SELECT target_type,user_id,reason,created_at FROM ai_review_logs WHERE status='rejected' ORDER BY created_at DESC LIMIT 6")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
            if (Permission::can('admin.report') || Permission::can('user.ban')) {
                $stats['revoked_private'] = (int)$db->query("SELECT COUNT(*) FROM private_messages WHERE revoked_at IS NOT NULL AND revoked_at >= CURDATE()")->fetchColumn();
                $stats['blocks_today'] = (int)$db->query("SELECT COUNT(*) FROM user_blocks WHERE created_at >= CURDATE()")->fetchColumn();
                $ops['risk_users'] = $db->query("SELECT u.id,u.nickname,u.username,COUNT(*) AS reports FROM content_reports r LEFT JOIN users u ON u.id=r.user_id WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY r.user_id,u.id,u.nickname,u.username ORDER BY reports DESC LIMIT 6")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Throwable $e) {
            
        }

        $quickLinks = [];
        if (!$limitedWorkbench && (Permission::can('thread.edit_any') || Permission::can('thread.delete_any') || Permission::can('thread.hide') || Permission::can('thread.pin') || Permission::can('thread.feature') || Permission::can('thread.recommend') || Permission::can('thread.lock'))) {
            $quickLinks[] = ['href' => '/admin.php?path=threads', 'label' => '帖子管理'];
        }
        if (Permission::canAnyScope('review.thread') || Permission::canAnyScope('review.post')) {
            $quickLinks[] = ['href' => $role === 'moderator' ? '/admin.php?path=moderator-workbench' : ($role === 'reviewer' ? '/admin.php?path=reviewer-workbench' : '/admin.php?path=review'), 'label' => $role === 'moderator' ? '版主工作台' : ($role === 'reviewer' ? '审核员工作台' : '审核中心')];
        }
        if (!$limitedWorkbench && Permission::can('user.ban')) {
            $quickLinks[] = ['href' => '/admin.php?path=users', 'label' => '用户管理'];
        }
        if (!$limitedWorkbench && Permission::can('section.manage')) {
            $quickLinks[] = ['href' => '/admin.php?path=sections', 'label' => '板块管理'];
        }
        if (!$limitedWorkbench && Permission::can('admin.banner')) {
            $quickLinks[] = ['href' => '/admin.php?path=banners', 'label' => '轮播管理'];
        }
        if (!$limitedWorkbench && Permission::can('admin.announcement')) {
            $quickLinks[] = ['href' => '/admin.php?path=announcements', 'label' => '公告管理'];
        }
        if (!$limitedWorkbench && Permission::can('user.assign_role')) {
            $quickLinks[] = ['href' => '/admin.php?path=roles', 'label' => '角色权限'];
        }
        if (!$limitedWorkbench && Permission::can('admin.update_center')) {
            $quickLinks[] = ['href' => '/admin.php?path=update-center', 'label' => '官方更新中心'];
        }
        if (!$limitedWorkbench && Permission::can('admin.settings')) {
            $quickLinks[] = ['href' => '/admin.php?path=settings', 'label' => '站点设置'];
        }

        require dirname(__DIR__, 2) . '/views/admin/dashboard/index.php';
    }
}
