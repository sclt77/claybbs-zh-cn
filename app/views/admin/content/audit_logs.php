<?php $pageTitle = '操作审计'; require dirname(__DIR__) . '/layouts/main.php'; ?>
<div class="page-header"><div class="page-title">操作审计</div></div>
<div class="card">
  <form method="get" action="/admin.php" class="admin-form-grid admin-mb-14">
    <input type="hidden" name="path" value="audit-logs">
    <input class="input" name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="搜索管理员 / 动作 / 目标 / 详情">
    <button class="btn" type="submit">搜索</button><a class="btn btn-light" href="/admin.php?path=audit-logs">重置</a>
  </form>
  <div class="table-responsive"><table class="table"><thead><tr><th>时间</th><th>管理员</th><th>动作</th><th>目标</th><th>详情</th><th>IP</th></tr></thead><tbody>
  <?php foreach (($logs ?? []) as $log): ?><tr><td><?= htmlspecialchars((string)$log['created_at']) ?></td><td><?= htmlspecialchars((string)($log['admin_name'] ?? '')) ?><div class="muted">ID <?= (int)($log['admin_id'] ?? 0) ?></div></td><td><?= htmlspecialchars((string)$log['action']) ?></td><td><?= htmlspecialchars((string)($log['target_type'] ?? '')) ?><?php if(!empty($log['target_id'])): ?> #<?= (int)$log['target_id'] ?><?php endif; ?></td><td class="admin-pre-460"><?= htmlspecialchars((string)($log['detail'] ?? '')) ?></td><td><?= htmlspecialchars((string)($log['ip'] ?? '')) ?></td></tr><?php endforeach; ?>
  <?php if (empty($logs)): ?><tr><td colspan="6" class="admin-empty">暂无审计记录</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
