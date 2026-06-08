<?php
$__pageTitle = '版本历史';
require dirname(__DIR__) . '/layouts/main.php';
$statusLabels = ['pending'=>'审核中','published'=>'已通过','rejected'=>'已拒绝'];
$statusColors = ['pending'=>'#f59e0b','published'=>'#10b981','rejected'=>'#ef4444'];
?>
<style>
.history-page{padding:0 0 88px;background:var(--bg-main,#f5f5f5);min-height:100vh}.history-shell{max-width:760px;margin:0 auto;padding:14px 12px}.history-card{background:var(--card-bg,#fff);border-radius:22px;padding:18px;box-shadow:0 14px 38px rgba(15,23,42,.06)}.back-link{display:inline-flex;margin-bottom:12px;color:var(--text-muted,#94a3b8);text-decoration:none;font-weight:850}.history-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px}.history-head h1{margin:0;font-size:22px;color:var(--text-main,#0f172a);font-weight:950}.history-head p{margin:5px 0 0;color:var(--text-muted,#94a3b8);font-size:13px}.new-btn{background:#0f172a;color:#fff;border-radius:999px;padding:9px 13px;text-decoration:none;font-size:13px;font-weight:950;white-space:nowrap}.history-list{display:grid;gap:10px}.history-item{padding:14px;border-radius:16px;background:var(--bg-soft,#f8fafc)}.history-item-head{display:flex;justify-content:space-between;gap:10px;align-items:center}.history-item strong{color:var(--text-main,#0f172a)}.badge{border-radius:999px;color:#fff;padding:4px 9px;font-size:11px;font-weight:850}.history-log{margin-top:8px;white-space:pre-wrap;color:var(--text-soft,#64748b);line-height:1.7;font-size:14px}.empty-tip{text-align:center;color:var(--text-muted,#94a3b8);padding:36px 10px}html[data-theme="dark"] .history-page{background:#0f172a}html[data-theme="dark"] .history-card{background:#1e293b}html[data-theme="dark"] .history-item{background:#0f172a}html[data-theme="dark"] .history-head h1,html[data-theme="dark"] .history-item strong{color:#e5e7eb}
</style>
<div class="history-page"><div class="history-shell">
  <a class="back-link" href="/index.php?path=software/submission#my-submissions">返回我的投稿</a>
  <div class="history-card">
    <div class="history-head"><div><h1>版本历史</h1><p><?= htmlspecialchars($software['name']) ?> · 当前 v<?= htmlspecialchars($software['version']) ?></p></div><?php if(($software['status'] ?? '') === 'published'): ?><a class="new-btn" href="/index.php?path=software/submission/version&id=<?= (int)$software['id'] ?>">提交新版本</a><?php endif; ?></div>
    <?php if(empty($submissions)): ?><div class="empty-tip">暂无版本记录</div><?php else: ?><div class="history-list">
      <?php foreach($submissions as $v): ?>
      <div class="history-item"><div class="history-item-head"><strong>v<?= htmlspecialchars((string)$v['version']) ?></strong><span class="badge" style="background:<?= htmlspecialchars($statusColors[$v['status']] ?? '#94a3b8') ?>"><?= htmlspecialchars($statusLabels[$v['status']] ?? $v['status']) ?></span></div><div class="history-log"><?= nl2br(htmlspecialchars((string)($v['changelog'] ?: '版本更新'))) ?></div></div>
      <?php endforeach; ?>
    </div><?php endif; ?>
  </div>
</div></div>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
