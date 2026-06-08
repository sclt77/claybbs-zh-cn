<?php
$pageTitle = '工作台';
$siteCfg = (new \App\Models\SettingModel())->getSiteConfig();
require dirname(__DIR__) . '/layouts/main.php';
?>
<div class="card admin-page-hero">
  <h2 data-clay-easter-logo><?= htmlspecialchars($workspaceTitle ?? (($siteCfg['site_name'] ?? 'ClayBBS') . ' 后台')) ?></h2>
  <div class="admin-muted"><?= htmlspecialchars($workspaceSubtitle ?? ($siteCfg['site_tagline'] ?? '一个轻量、可持续迭代的社区论坛系统。')) ?></div>
</div>

<?php $visibleStats = array_filter($stats, static fn($v) => $v !== null); ?>
<?php if (!empty($visibleStats)): ?>
<div class="stat-grid dashboard-stat-grid">
  <?php if ($stats['users'] !== null): ?>
  <div class="stat-card">
    <div class="num"><?= number_format($stats['users']) ?></div>
    <div class="lbl">注册用户</div>
  </div>
  <?php endif; ?>
  <?php if ($stats['threads'] !== null): ?>
  <div class="stat-card">
    <div class="num"><?= number_format($stats['threads']) ?></div>
    <div class="lbl">发布帖子</div>
  </div>
  <?php endif; ?>
  <?php if ($stats['posts'] !== null): ?>
  <div class="stat-card">
    <div class="num"><?= number_format($stats['posts']) ?></div>
    <div class="lbl">回复数量</div>
  </div>
  <?php endif; ?>
  <?php if ($stats['pending'] !== null): ?>
  <div class="stat-card warn">
    <div class="num"><?= number_format($stats['pending']) ?></div>
    <div class="lbl">待审核内容</div>
  </div>
  <?php endif; ?>
  <?php if ($stats['today_threads'] !== null): ?><div class="stat-card"><div class="num"><?= number_format($stats['today_threads']) ?></div><div class="lbl">今日新帖</div></div><?php endif; ?>
  <?php if ($stats['today_posts'] !== null): ?><div class="stat-card"><div class="num"><?= number_format($stats['today_posts']) ?></div><div class="lbl">今日回复</div></div><?php endif; ?>
  <?php if ($stats['today_users'] !== null): ?><div class="stat-card"><div class="num"><?= number_format($stats['today_users']) ?></div><div class="lbl">今日注册</div></div><?php endif; ?>
  <?php if ($stats['reports'] !== null): ?><div class="stat-card warn"><div class="num"><?= number_format($stats['reports']) ?></div><div class="lbl">待处理举报</div></div><?php endif; ?>
  <?php if ($stats['ai_rejected'] !== null): ?><div class="stat-card warn"><div class="num"><?= number_format($stats['ai_rejected']) ?></div><div class="lbl">今日 AI 拦截</div></div><?php endif; ?>
  <?php if ($stats['image_rejected'] !== null): ?><div class="stat-card warn"><div class="num"><?= number_format($stats['image_rejected']) ?></div><div class="lbl">今日图片拦截</div></div><?php endif; ?>
  <?php if ($stats['revoked_private'] !== null): ?><div class="stat-card"><div class="num"><?= number_format($stats['revoked_private']) ?></div><div class="lbl">今日撤回私聊</div></div><?php endif; ?>
  <?php if ($stats['blocks_today'] !== null): ?><div class="stat-card"><div class="num"><?= number_format($stats['blocks_today']) ?></div><div class="lbl">今日屏蔽关系</div></div><?php endif; ?>
</div>

<?php endif; ?>

<?php if (!empty($ops['hot_threads']) || !empty($ops['recent_reports'])): ?>
<div class="ops-grid admin-grid-2">
  <div class="card card"><div class="admin-ops-title">热帖观察</div><?php foreach (($ops['hot_threads'] ?? []) as $t): ?><a href="/index.php?path=thread&id=<?= (int)$t['id'] ?>" target="_blank" class="admin-ops-link"><strong><?= htmlspecialchars($t['title']) ?></strong><div class="admin-ops-meta">阅读 <?= (int)$t['view_count'] ?> · 回复 <?= (int)$t['reply_count'] ?> · 赞 <?= (int)$t['like_count'] ?> · 收藏 <?= (int)$t['favorite_count'] ?></div></a><?php endforeach; ?><?php if (empty($ops['hot_threads'])): ?><div class="admin-muted">暂无数据</div><?php endif; ?></div>
  <div class="card card"><div class="admin-ops-title">待处理事项</div><?php foreach (($ops['recent_reports'] ?? []) as $r): ?><a href="<?= htmlspecialchars($reportLink ?? '/admin.php?path=reports') ?>" class="admin-ops-link"><strong><?= htmlspecialchars($r['target_type']) ?> #<?= (int)$r['target_id'] ?></strong><div class="admin-ops-meta"><?= htmlspecialchars(mb_substr((string)$r['reason'],0,60)) ?> · <?= htmlspecialchars($r['created_at']) ?></div></a><?php endforeach; ?><?php if (empty($ops['recent_reports'])): ?><div class="admin-muted">暂无待处理举报</div><?php endif; ?></div>
</div>

<?php endif; ?>


<?php if (!empty($ops['risk_users']) || !empty($ops['top_reported']) || !empty($ops['sensitive_hits']) || !empty($ops['recent_ai_blocks'])): ?>
<div class="ops-grid admin-grid-2">
  <?php if (empty($limitedWorkbench)): ?><div class="card card"><div class="admin-ops-title">风控用户观察</div><?php foreach (($ops['risk_users'] ?? []) as $u): ?><a href="/admin.php?path=users&kw=<?= urlencode((string)($u['username'] ?? '')) ?>" class="admin-ops-link"><strong><?= htmlspecialchars($u['nickname'] ?: $u['username'] ?: ('用户#'.(int)$u['id'])) ?></strong><div class="admin-ops-meta">近 7 日发起举报 <?= (int)$u['reports'] ?> 次</div></a><?php endforeach; ?><?php if (empty($ops['risk_users'])): ?><div class="admin-muted">暂无异常用户</div><?php endif; ?></div><?php endif; ?>
  <div class="card card"><div class="admin-ops-title">高频被举报内容</div><?php foreach (($ops['top_reported'] ?? []) as $r): ?><a href="<?= htmlspecialchars($reportLink ?? '/admin.php?path=reports') ?>" class="admin-ops-link"><strong><?= htmlspecialchars($r['target_type']) ?> #<?= (int)$r['target_id'] ?></strong><div class="admin-ops-meta">累计举报 <?= (int)$r['total'] ?> 次 · <?= htmlspecialchars($r['last_at']) ?></div></a><?php endforeach; ?><?php if (empty($ops['top_reported'])): ?><div class="admin-muted">暂无举报聚集</div><?php endif; ?></div>
  <div class="card card"><div class="admin-ops-title">敏感类型命中</div><?php foreach (($ops['sensitive_hits'] ?? []) as $hit): ?><div class="admin-ops-link"><strong><?= htmlspecialchars($hit['category']) ?></strong><div class="admin-ops-meta">近 7 日 <?= (int)$hit['total'] ?> 次</div></div><?php endforeach; ?><?php if (empty($ops['sensitive_hits'])): ?><div class="admin-muted">暂无敏感命中</div><?php endif; ?></div>
  <?php if (empty($limitedWorkbench)): ?><div class="card card"><div class="admin-ops-title">最近 AI 拦截</div><?php foreach (($ops['recent_ai_blocks'] ?? []) as $a): ?><a href="/admin.php?path=review&tab=logs" class="admin-ops-link"><strong><?= htmlspecialchars($a['target_type']) ?> · 用户 #<?= (int)$a['user_id'] ?></strong><div class="admin-ops-meta"><?= htmlspecialchars(mb_substr((string)$a['reason'],0,60)) ?> · <?= htmlspecialchars($a['created_at']) ?></div></a><?php endforeach; ?><?php if (empty($ops['recent_ai_blocks'])): ?><div class="admin-muted">暂无 AI 拦截</div><?php endif; ?></div><?php endif; ?>
</div>

<?php endif; ?>

<div class="card card">
  <div class="admin-ops-title">系统信息</div>
  <div class="admin-list-item">
    <span class="admin-muted">PHP 版本</span>
    <span class="admin-bold"><?= PHP_VERSION ?></span>
  </div>
  <div class="admin-list-item">
    <span class="admin-muted">服务器时间</span>
    <span class="admin-bold"><?= date('Y-m-d H:i:s') ?></span>
  </div>
  <div class="admin-list-item">
    <span class="admin-muted">当前用户</span>
    <span class="admin-bold"><?= htmlspecialchars($_SESSION['auth_user']['nickname'] ?? '') ?></span>
  </div>
  <div class="admin-system-row">
    <span class="admin-muted">角色</span>
    <span class="admin-bold"><?= htmlspecialchars($_SESSION['auth_user']['role'] ?? '') ?></span>
  </div>
</div>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
