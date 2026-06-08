<?php

use App\Middleware\Permission;

$_adminUser = $_SESSION['auth_user'] ?? [];
if (!empty($_adminUser['id'])) {
    try {
        $_freshAdminUser = (new \App\Models\UserModel())->find((int)$_adminUser['id']);
        if ($_freshAdminUser) {
            $_adminUser = $_freshAdminUser;
            $_SESSION['auth_user'] = $_freshAdminUser;
        }
    } catch (\Throwable $e) {}
}
$_adminRole = $_adminUser['role'] ?? 'user';
$_currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$_currentAdminPath = trim((string)($_GET['path'] ?? ''), '/');
function adminMenuActive(string $path, string $current): string {
    $path = trim($path, '/');
    $current = trim($current, '/');
    if ($path === 'admin') {
        return $current === '' ? ' active' : '';
    }
    if (str_starts_with($path, 'admin/')) {
        $path = substr($path, 6);
    }
    return ($current === $path || str_starts_with($current, $path . '/')) ? ' active' : '';
}
function adminCan(string $perm): bool {
    return Permission::can($perm);
}
function adminCanAnyScope(string $perm): bool {
    return Permission::canAnyScope($perm);
}
?>
<?php $siteCfg = (new \App\Models\SettingModel())->getSiteConfig(); ?>
<?php
$_adminPendingReviewTotal = 0;
try {
    $_adminReviewModelForMenu = new \App\Models\AdminReviewModel();
    $_adminReviewerIdForMenu = (int)($_adminUser['id'] ?? 0);
    $_adminPendingReviewTotal = count($_adminReviewModelForMenu->pendingThreads($_adminReviewerIdForMenu)) + count($_adminReviewModelForMenu->pendingPosts($_adminReviewerIdForMenu)) + count((new \App\Models\ThreadRevisionModel())->pending($_adminReviewerIdForMenu));
} catch (\Throwable $e) { $_adminPendingReviewTotal = 0; }
$_adminUpdateAvailable = false;
try {
    if (adminCan('admin.update_center')) {
        $_adminUpdateStatus = (new \App\Services\UpdateCheckService())->checkIfStale();
        $_adminUpdateAvailable = !empty($_adminUpdateStatus['update']);
    }
} catch (\Throwable $e) { $_adminUpdateAvailable = false; }
function adminMenuBadge(int $count): string { return $count > 0 ? '<span class="menu-badge">' . (int)$count . '</span>' : ''; }
function adminMenuDot(bool $show): string { return $show ? '<span class="menu-dot" title="发现新版本"></span>' : ''; }
$_adminRolesForShell = [];
try { $_adminRolesForShell = !empty($_adminUser['id']) ? Permission::getUserRoles((int)$_adminUser['id']) : []; } catch (\Throwable $e) { $_adminRolesForShell = []; }
$_adminRoleSlugsForShell = array_values(array_unique(array_merge(array_column($_adminRolesForShell, 'slug'), [(string)$_adminRole])));
$_adminScopedRolesForShell = array_filter($_adminRolesForShell, static fn($r)=>in_array((string)($r['scope'] ?? ''), ['section','category'], true));
$_adminHasGlobalRoleShell = (bool)array_filter($_adminRolesForShell, static fn($r)=>(string)($r['scope'] ?? 'global') === 'global' && in_array((string)($r['slug'] ?? ''), ['admin','superadmin'], true));
$_isSuperAdminShell = in_array('superadmin', $_adminRoleSlugsForShell, true) || $_adminRole === 'superadmin';
$_isFullAdminShell = $_isSuperAdminShell || $_adminHasGlobalRoleShell || adminCan('admin.settings') || adminCan('user.ban') || adminCan('section.manage') || adminCan('admin.full');
$_isModeratorShell = (in_array('moderator', $_adminRoleSlugsForShell, true) || (bool)array_filter($_adminScopedRolesForShell, static fn($r)=>(string)($r['slug'] ?? '') === 'moderator') || adminCanAnyScope('moderator.dashboard') || adminCanAnyScope('moderator.report.handle')) && !$_isFullAdminShell;
$_isReviewerShell = (in_array('reviewer', $_adminRoleSlugsForShell, true) || (bool)array_filter($_adminScopedRolesForShell, static fn($r)=>(string)($r['slug'] ?? '') === 'reviewer') || adminCanAnyScope('review.thread') || adminCanAnyScope('review.post')) && !$_isFullAdminShell && !$_isModeratorShell;
$_isLimitedWorkbenchShell = $_isModeratorShell || $_isReviewerShell;
$_adminPluginMenu = '';
try {
    $_adminPluginMenu = (string) \App\Core\Hook::filter('admin.menu.plugins', '');
} catch (\Throwable $e) {
    $_adminPluginMenu = '';
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<script>(function(){try{var m=localStorage.getItem('clay_theme')||'system';var d=m==='dark'||(m==='system'&&matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.setAttribute('data-theme',d?'dark':'light');document.documentElement.setAttribute('data-theme-mode',m);}catch(e){}})();</script>
<title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : ''; ?><?= htmlspecialchars($siteCfg['site_name'] ?? 'ClayBBS') ?> 后台</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/admin-redesign.css?v=2026052602">
<style>
/* Legacy admin layout CSS intentionally replaced by DESIGN.md-driven admin-redesign.css. */
</style>
</head>
<body>
<div class="admin-mask" id="adminMask" onclick="closeAdminSidebar()"></div>
<div class="admin-layout">
  
  <aside class="admin-side" id="adminSidebar">
    <a href="<?= $_isModeratorShell ? '/admin.php?path=moderator-workbench' : ($_isReviewerShell ? '/admin.php?path=reviewer-workbench' : '/admin.php') ?>" class="admin-logo"><?= htmlspecialchars($siteCfg['site_logo_text'] ?? 'ClayBBS') ?><span>Admin</span></a>
    <nav class="admin-nav">
      <?php if ($_isLimitedWorkbenchShell): ?>
      <div class="admin-nav-group">
        <div class="admin-nav-label">审核与安全</div>
        <?php if ($_isModeratorShell): ?>
        <a href="/admin.php?path=moderator-workbench" class="menu-link<?= adminMenuActive('moderator-workbench', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
          版主工作台
          <?= adminMenuBadge($_adminPendingReviewTotal) ?>
        </a>
        <?php endif; ?>
        <?php if ($_isReviewerShell): ?>
        <a href="/admin.php?path=reviewer-workbench" class="menu-link<?= adminMenuActive('reviewer-workbench', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          审核员工作台
          <?= adminMenuBadge($_adminPendingReviewTotal) ?>
        </a>
        <?php endif; ?>
        <?php if (adminCan('admin.report') || adminCanAnyScope('moderator.report.handle') || adminCanAnyScope('thread.hide') || adminCanAnyScope('post.delete_any') || adminCanAnyScope('review.post')): ?>
        <a href="/admin.php?path=reports" class="menu-link<?= adminMenuActive('reports', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          举报管理
        </a>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="admin-nav-group">
        <div class="admin-nav-label">概览</div>
        <a href="/admin.php" class="menu-link<?= adminMenuActive('admin', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
          工作台
        </a>
      </div>
      <div class="admin-nav-group">
        <div class="admin-nav-label">内容管理</div>
        <?php if (adminCan('thread.edit_any') || adminCan('thread.delete_any') || adminCan('thread.hide') || adminCan('thread.pin') || adminCan('thread.feature') || adminCan('thread.recommend') || adminCan('thread.lock')): ?>
        <a href="/admin.php?path=threads" class="menu-link<?= adminMenuActive('threads', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          帖子管理
        </a>
        <?php endif; ?>
        <?php if (adminCan('post.delete_any') || adminCan('review.post')): ?>
        <a href="/admin.php?path=posts" class="menu-link<?= adminMenuActive('posts', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          回复管理
        </a>
        <?php endif; ?>
        <?php if (adminCan('section.manage')): ?>
        <a href="/admin.php?path=sections" class="menu-link<?= adminMenuActive('sections', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
          板块管理
        </a>
        <?php endif; ?>
        <?php if (adminCan('admin.badge')): ?>
        <a href="/admin.php?path=badges" class="menu-link<?= adminMenuActive('badges', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M8.5 12.5 7 21l5-3 5 3-1.5-8.5"/></svg>
          勋章管理
        </a>
        <a href="/admin.php?path=avatar-frames" class="menu-link<?= adminMenuActive('avatar-frames', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="7"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9 7 7M17 17l2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1"/></svg>
          头像框管理
        </a>
        <a href="/admin.php?path=bubbles" class="menu-link<?= adminMenuActive('bubbles', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2z"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><circle cx="9" cy="9" r="1"/><circle cx="15" cy="9" r="1"/></svg>
          气泡管理
        </a>
        <a href="/admin.php?path=nameplates" class="menu-link<?= adminMenuActive('nameplates', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v2"/><path d="M9 20h6"/><path d="M12 4v16"/><path d="M5 11h2M17 11h2"/></svg>
          名字特效
        </a>
        <?php endif; ?>
      </div>

      <?php if (adminCanAnyScope('review.thread') || adminCanAnyScope('review.post') || adminCan('admin.message') || adminCan('admin.report') || adminCanAnyScope('moderator.report.handle') || adminCanAnyScope('thread.hide') || adminCanAnyScope('post.delete_any')): ?>
      <div class="admin-nav-group">
        <div class="admin-nav-label">审核与安全</div>
        <?php $_rolesForWorkbench = Permission::getUserRoles((int)($_adminUser['id'] ?? 0)); $_roleSlugsForWorkbench = array_values(array_unique(array_merge(array_column($_rolesForWorkbench, 'slug'), [$_adminRole]))); ?>
        <?php if (in_array('moderator', $_roleSlugsForWorkbench, true) && !adminCan('admin.settings')): ?>
        <a href="/admin.php?path=moderator-workbench" class="menu-link<?= adminMenuActive('moderator-workbench', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
          版主工作台
          <?= adminMenuBadge($_adminPendingReviewTotal) ?>
        </a>
        <?php endif; ?>
        <?php if (in_array('reviewer', $_roleSlugsForWorkbench, true) && !adminCan('admin.settings')): ?>
        <a href="/admin.php?path=reviewer-workbench" class="menu-link<?= adminMenuActive('reviewer-workbench', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          审核员工作台
          <?= adminMenuBadge($_adminPendingReviewTotal) ?>
        </a>
        <?php endif; ?>
        <?php if (adminCan('admin.settings') || adminCan('user.ban') || adminCan('section.manage')): ?>
        <a href="/admin.php?path=review" class="menu-link<?= adminMenuActive('review', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          审核中心
          <?= adminMenuBadge($_adminPendingReviewTotal) ?>
        </a>
        <?php endif; ?>
        <?php if (adminCan('admin.report') || adminCanAnyScope('moderator.report.handle') || adminCanAnyScope('thread.hide') || adminCanAnyScope('post.delete_any') || adminCanAnyScope('review.post')): ?>
        <a href="/admin.php?path=reports" class="menu-link<?= adminMenuActive('reports', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          举报管理
        </a>
        <?php endif; ?>
        <a href="/admin.php?path=group-manage" class="menu-link<?= adminMenuActive('group-manage', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          群聊管理
          <?php
          try { $_grpPending = (int)\App\Core\Database::connection()->query("SELECT COUNT(*) FROM group_reports WHERE status='pending'")->fetchColumn(); } catch (\Throwable $e) { $_grpPending = 0; }
          if ($_grpPending > 0): ?>
          <span class="menu-badge"><?= $_grpPending ?></span>
          <?php endif; ?>
        </a>
        <?php if (adminCan('review.bounty')): ?>
        <a href="/admin.php?path=bounties" class="menu-link<?= adminMenuActive('bounties', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/><path d="M12 8v8"/></svg>
          悬赏管理
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if (adminCan('user.ban') || adminCan('user.assign_role') || adminCan('admin.verification') || adminCan('admin.growth')): ?>
      <div class="admin-nav-group">
        <div class="admin-nav-label">用户与权限</div>
        <?php if (adminCan('user.ban')): ?>
        <a href="/admin.php?path=users" class="menu-link<?= adminMenuActive('users', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          用户管理
        </a>
        <a href="/admin.php?path=user-credit" class="menu-link<?= adminMenuActive('user-credit', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
          用户信用
        </a>
        <?php endif; ?>
        <?php if (adminCan('user.assign_role')): ?>
        <a href="/admin.php?path=roles" class="menu-link<?= adminMenuActive('roles', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          角色权限
        </a>
        <?php endif; ?>
        <?php if (adminCan('admin.verification')): ?>
        <a href="/admin.php?path=verifications" class="menu-link<?= adminMenuActive('verifications', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l7 3v6c0 4.2-2.7 7.4-7 8.8C7.7 19.4 5 16.2 5 12V6l7-3z"/><path d="M9 12l2 2 4-4"/></svg>
          认证管理
        </a>
        <?php endif; ?>
        <?php if (adminCan('admin.growth')): ?>
        <a href="/admin.php?path=growth" class="menu-link<?= adminMenuActive('growth', $_currentAdminPath) ?: adminMenuActive('tasks', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 17l6-6 4 4 7-9"/><path d="M14 6h6v6"/></svg>
          成长系统
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if (adminCan('admin.banner') || adminCan('admin.announcement') || adminCan('admin.message') || adminCan('admin.social')): ?>
      <div class="admin-nav-group">
        <div class="admin-nav-label">运营配置</div>
        <?php if (adminCan('admin.banner')): ?>
        <a href="/admin.php?path=banners" class="menu-link<?= adminMenuActive('banners', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="14" rx="2"/><line x1="3" y1="17" x2="21" y2="17"/><line x1="3" y1="21" x2="21" y2="21"/></svg>
          轮播管理
        </a>
        <?php endif; ?>
        <?php if (adminCan('admin.announcement')): ?>
        <a href="/admin.php?path=announcements" class="menu-link<?= adminMenuActive('announcements', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          公告管理
        </a>
        <?php endif; ?>
        <?php if (adminCan('admin.message')): ?>
        <a href="/admin.php?path=messages" class="menu-link<?= adminMenuActive('messages', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/><line x1="12" y1="2" x2="12" y2="1"/></svg>
          消息管理
        </a>
        <?php endif; ?>
        <?php if (adminCan('admin.social')): ?>
        <a href="/admin.php?path=social" class="menu-link<?= adminMenuActive('social', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>
          社交管理
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if (adminCan('finance.view') || adminCan('payment.view')): ?>
      <div class="admin-nav-group">
        <div class="admin-nav-label">财务与交易</div>
        <?php if (adminCan('finance.view')): ?>
        <a href="/admin.php?path=finance" class="menu-link<?= adminMenuActive('finance', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6"/></svg>
          财务管理
        </a>
        <?php endif; ?>
        <?php if (adminCan('payment.view')): ?>
        <a href="/admin.php?path=payments" class="menu-link<?= adminMenuActive('payments', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 10h18"/><path d="M8 15h3"/></svg>
          支付管理
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>


      <?php endif; ?>

      <?php if (trim($_adminPluginMenu) !== ''): ?>
      <div class="admin-nav-group">
        <div class="admin-nav-label">论坛插件</div>
        <?= $_adminPluginMenu ?>
      </div>
      <?php endif; ?>

      <?php if (adminCan('admin.update_center') || adminCan('admin.settings') || adminCan('admin.extension') || adminCan('admin.system_check') || adminCan('admin.backup') || adminCan('admin.audit_log')): ?>
      <div class="admin-nav-group">
        <div class="admin-nav-label">扩展与更新</div>
        <?php if (adminCan('admin.update_center')): ?>
        <a href="/admin.php?path=update-center" class="menu-link<?= adminMenuActive('update-center', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
          官方更新中心<?= adminMenuDot($_adminUpdateAvailable) ?>
        </a>
        <a href="/admin.php?path=license" class="menu-link<?= adminMenuActive('license', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l7 3v6c0 4.2-2.7 7.4-7 8.8C7.7 19.4 5 16.2 5 12V6l7-3z"/><path d="M9 12l2 2 4-4"/></svg>
          正版验证
        </a>
        <?php endif; ?>
        <?php if (adminCan('admin.settings')): ?>
        <a href="/admin.php?path=settings" class="menu-link<?= adminMenuActive('settings', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h.01A1.65 1.65 0 0 0 10 3.09V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h.01a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v.01a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          站点设置
        </a>
        <?php endif; ?>
        <?php if (adminCan('admin.extension')): ?>
        <a href="/admin.php?path=extensions" class="menu-link<?= adminMenuActive('extensions', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
          插件 &amp; 主题
        </a>
        <?php endif; ?>
        <?php if (adminCan('admin.software')): ?>
        <a href="/admin.php?path=software" class="menu-link<?= adminMenuActive('software', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
          软件库管理
        </a>
        <?php endif; ?>
        <?php if (adminCan('thread.delete_any') || adminCan('post.delete_any')): ?>
        <a href="/admin.php?path=recycle-bin" class="menu-link<?= adminMenuActive('recycle-bin', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
          内容回收站
        </a>
        <?php endif; ?>
        <?php if (adminCan('admin.audit_log')): ?>
        <a href="/admin.php?path=audit-logs" class="menu-link<?= adminMenuActive('audit-logs', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/></svg>
          操作审计
        </a>
        <?php endif; ?>
        <?php if (adminCan('admin.system_check')): ?>
        <a href="/admin.php?path=system-check" class="menu-link<?= adminMenuActive('system-check', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4"/><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
          系统自检
        </a>
        <a href="/admin.php?path=backups" class="menu-link<?= adminMenuActive('backups', $_currentAdminPath) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
          备份恢复
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </nav>
    <div class="admin-user">
      <?= user_avatar_html($_adminUser, 'admin-user-avatar', 32) ?>
      <div class="admin-user-info">
        <div class="admin-user-name"><?= htmlspecialchars($_adminUser['nickname'] ?? $_adminUser['username'] ?? '') ?></div>
        <div class="admin-user-role"><?= htmlspecialchars($_adminRole) ?></div>
      </div>
      <a href="/index.php?path=logout" class="admin-logout-link">退出</a>
    </div>
  </aside>
  
  <main class="admin-main">
    <div class="admin-top">
      <div class="admin-top-left">
        <button class="admin-menu-toggle" type="button" onclick="toggleAdminSidebar()" aria-label="打开菜单">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <h1><?php if ($_isModeratorShell && $_currentAdminPath === '') echo '版主工作台'; elseif ($_isReviewerShell && $_currentAdminPath === '') echo '审核员工作台'; else echo isset($pageTitle) ? htmlspecialchars($pageTitle) : '工作台'; ?></h1>
      </div>
      <div class="admin-top-actions">
        <a href="/index.php" class="admin-front-link">返回前台</a>
        <?php
        $_adminEasterOwner = '';
        try {
            $_adminUpdateCfg = is_file(dirname(__DIR__, 4) . '/config/update-center.php') ? (include dirname(__DIR__, 4) . '/config/update-center.php') : [];
            if (is_array($_adminUpdateCfg)) {
                $_adminEasterOwner = trim((string)($_adminUpdateCfg['owner'] ?? ''));
                if ($_adminEasterOwner === '' && !empty($_adminUpdateCfg['license_data'])) {
                    $_adminLicenseData = json_decode((string)$_adminUpdateCfg['license_data'], true);
                    if (is_array($_adminLicenseData)) {
                        $_adminEasterOwner = trim((string)($_adminLicenseData['payload']['owner'] ?? ''));
                    }
                }
            }
        } catch (\Throwable $e) { $_adminEasterOwner = ''; }
        if ($_adminEasterOwner === '') { $_adminEasterOwner = (string)($_adminUser['nickname'] ?? $_adminUser['username'] ?? ''); }
        if ($_adminEasterOwner === '') { $_adminEasterOwner = '站长'; }
        ?>
        <button class="admin-theme-toggle" type="button" data-theme-toggle data-clay-owner="<?= htmlspecialchars($_adminEasterOwner, ENT_QUOTES) ?>" onclick="if(window.ClayThemeToggleClick){window.ClayThemeToggleClick(this);return false;}" title="切换日夜模式" aria-label="切换日夜模式">
          <svg class="theme-toggle-icon theme-toggle-icon--system" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2"></rect><path d="M8 20h8M12 16v4"></path><circle cx="18" cy="7" r="1.4" fill="currentColor" stroke="none"></circle></svg>
          <svg class="theme-toggle-icon theme-toggle-icon--light" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path></svg>
          <svg class="theme-toggle-icon theme-toggle-icon--dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A8.5 8.5 0 1 1 11.21 3 6.5 6.5 0 0 0 21 12.79z"></path></svg>
        </button>
      </div>
    </div>
    <div class="admin-content">

<?php
try {
    $__licenseTrial = (new \App\Services\LicenseGuardService())->trialInfo();
} catch (\Throwable $e) { $__licenseTrial = []; }
if (!empty($__licenseTrial['is_trial'])):
    $__remain = max(0, (int)($__licenseTrial['remaining_seconds'] ?? 0));
    $__days = intdiv($__remain, 86400); $__hours = intdiv($__remain % 86400, 3600); $__mins = intdiv($__remain % 3600, 60);
?>
<div class="admin-trial-license-banner">
  <strong>当前为 ClayBBS 体验授权</strong>
  <span>剩余 <?= $__days ?> 天 <?= $__hours ?> 小时 <?= $__mins ?> 分钟，请尽快购买正式授权。</span>
</div>
<style>.admin-trial-license-banner{display:flex;gap:14px;align-items:center;justify-content:space-between;margin:0 0 16px;padding:14px 16px;border-radius:14px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-weight:800;box-shadow:0 8px 22px rgba(234,88,12,.08)}.admin-trial-license-banner span{font-weight:700}@media(max-width:768px){.admin-trial-license-banner{display:block;line-height:1.8}}</style>
<?php endif; ?>
<script>
function toggleAdminSidebar(){
  document.body.classList.toggle('admin-sidebar-open');
}
function closeAdminSidebar(){
  document.body.classList.remove('admin-sidebar-open');
}
document.addEventListener('click', function(e){
  if(window.innerWidth > 768) return;
  var sidebar = document.getElementById('adminSidebar');
  var toggle = document.querySelector('.admin-menu-toggle');
  if(!sidebar) return;
  if(document.body.classList.contains('admin-sidebar-open')){
    var clickedInsideSidebar = sidebar.contains(e.target);
    var clickedToggle = toggle && toggle.contains(e.target);
    if(!clickedInsideSidebar && !clickedToggle){
      closeAdminSidebar();
    }
  }
});
window.addEventListener('resize', function(){
  if(window.innerWidth > 768){
    closeAdminSidebar();
  }
});
document.querySelectorAll('#adminSidebar a.menu-link').forEach(function(link){
  link.addEventListener('click', function(){
    if(window.innerWidth <= 768){
      closeAdminSidebar();
    }
  });
});
</script>
