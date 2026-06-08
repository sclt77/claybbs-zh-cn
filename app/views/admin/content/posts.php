<?php
$pageTitle = '回复管理';
require dirname(__DIR__) . '/layouts/main.php';
$posts = $posts ?? [];
$total = $total ?? 0;
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$statusLabels = ['published'=>'已发布','pending'=>'待审核','hidden'=>'已屏蔽','deleted'=>'已删除'];
?>
<div class="page-header">
  <div class="page-title">回复管理</div>
  <form method="get" action="/admin.php" class="admin-filter-bar">
    <input type="hidden" name="path" value="posts">
    <input class="input admin-w-200" name="kw" placeholder="搜索回复/帖子标题" value="<?= htmlspecialchars($_GET['kw'] ?? '') ?>">
    <select class="select admin-w-130" name="status">
      <option value="">全部状态</option>
      <?php foreach ($statusLabels as $value => $label): ?><option value="<?= $value ?>" <?= (($_GET['status'] ?? '') === $value) ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
    </select>
    <button class="btn" type="submit">筛选</button>
  </form>
</div>
<div class="admin-muted">共 <?= (int)$total ?> 条回复</div>
<div class="table-responsive"><table class="table">
  <thead><tr><th>内容</th><th>所属帖子</th><th>作者</th><th>状态</th><th>时间</th><th>操作</th></tr></thead>
  <tbody>
  <?php if (!empty($posts)): ?>
    <?php foreach ($posts as $p): ?>
    <tr>
      <td class="admin-table-title"><?= htmlspecialchars(mb_substr(trim(strip_tags((string)$p['content'])), 0, 90)) ?></td>
      <td><a href="/index.php?path=thread&id=<?= (int)$p['thread_id'] ?>" target="_blank"><?= htmlspecialchars($p['thread_title'] ?? '') ?></a></td>
      <td><?= htmlspecialchars($p['author_name'] ?? '匿名') ?></td>
      <td><span class="badge <?= ($p['status'] ?? '')==='published'?'badge-ok':(($p['status'] ?? '')==='pending'?'badge-warn':'badge-err') ?>"><?= $statusLabels[$p['status'] ?? ''] ?? htmlspecialchars($p['status'] ?? '') ?></span></td>
      <td class="admin-muted"><?= htmlspecialchars(date('m-d H:i', strtotime($p['created_at'] ?? 'now'))) ?></td>
      <td><div class="admin-collapse admin-action-collapse admin-no-margin"><button type="button" class="admin-mini-btn" data-admin-collapse>操作</button><div class="admin-collapse-body admin-spacer-top"><div class="admin-inline-actions">
        <a class="btn btn-light admin-action-sm" href="/admin.php?path=posts/edit&id=<?= (int)$p['id'] ?>">编辑</a>
        <form method="post" action="/admin.php?path=posts/action" class="admin-display-contents">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <?php if (($p['status'] ?? '') !== 'published'): ?><button class="btn" name="status" value="published">通过</button><?php endif; ?>
          <?php if (($p['status'] ?? '') !== 'hidden'): ?><button class="btn btn-light" name="status" value="hidden">屏蔽</button><?php endif; ?>
          <button class="btn admin-danger-bg" name="status" value="delete" onclick="return confirm('确认真实删除该回复？此操作会同步清理点赞、举报、提及、附件、AI 评分等关联记录，且不会进入已删除状态。')">真实删除</button>
        </form>
      </div></div></div></td>
    </tr>
    <?php endforeach; ?>
  <?php else: ?>
    <tr><td colspan="6" class="admin-empty">暂无回复</td></tr>
  <?php endif; ?>
  </tbody>
</table></div>
<?php if ($totalPages > 1): ?>
  <div class="admin-actions admin-mt-14">
    <?php $base = $_GET; $base['path'] = 'posts'; ?>
    <?php if ($page > 1): $base['page']=$page-1; ?><a class="btn btn-light admin-link-clean" href="/admin.php?<?= http_build_query($base) ?>">上一页</a><?php endif; ?>
    <span class="admin-muted">第 <?= (int)$page ?> / <?= (int)$totalPages ?> 页</span>
    <?php if ($page < $totalPages): $base['page']=$page+1; ?><a class="btn btn-light admin-link-clean" href="/admin.php?<?= http_build_query($base) ?>">下一页</a><?php endif; ?>
  </div>
<?php endif; ?>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
