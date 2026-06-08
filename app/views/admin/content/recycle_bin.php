<?php $pageTitle = '内容回收站'; require dirname(__DIR__) . '/layouts/main.php'; ?>
<div class="page-header"><div class="page-title">内容回收站</div></div>
<div class="card">
  <form method="get" action="/admin.php" class="admin-form-grid admin-mb-14"><input type="hidden" name="path" value="recycle-bin"><select class="select" name="type"><option value="">全部类型</option><option value="thread" <?= ($_GET['type'] ?? '')==='thread'?'selected':'' ?>>帖子</option><option value="post" <?= ($_GET['type'] ?? '')==='post'?'selected':'' ?>>回复</option></select><button class="btn" type="submit">筛选</button><a class="btn btn-light" href="/admin.php?path=recycle-bin">重置</a></form>
  <div class="table-responsive"><table class="table"><thead><tr><th>类型</th><th>标题/摘要</th><th>删除人</th><th>删除时间</th><th>操作</th></tr></thead><tbody>
  <?php foreach (($items ?? []) as $it): ?><tr><td><?= $it['target_type']==='thread'?'帖子':'回复' ?> #<?= (int)$it['target_id'] ?></td><td><?= htmlspecialchars((string)$it['title']) ?></td><td><?= htmlspecialchars((string)($it['nickname'] ?: $it['username'] ?: '')) ?></td><td><?= htmlspecialchars((string)$it['deleted_at']) ?></td><td class="admin-inline-actions"><form method="post" action="/admin.php?path=recycle-bin" data-refresh-on-success><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$it['id'] ?>"><button class="btn small" name="_action" value="restore">恢复</button></form><form method="post" action="/admin.php?path=recycle-bin" data-refresh-on-success onsubmit="return confirm('确定从回收站移除记录？原内容不会恢复。')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$it['id'] ?>"><button class="btn small btn-light" name="_action" value="purge">移出</button></form></td></tr><?php endforeach; ?>
  <?php if (empty($items)): ?><tr><td colspan="5" class="admin-empty">回收站为空</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
