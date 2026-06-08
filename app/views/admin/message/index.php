<?php
$pageTitle = '系统消息';
require dirname(__DIR__) . '/layouts/main.php';
$messages = $messages ?? [];
$error = $error ?? '';
$success = $success ?? '';
$editMsg = $editMsg ?? null;
?>
<div class="page-header">
  <div class="page-title">系统消息管理</div>
  <a href="/admin.php?path=messages/create" class="btn">+ 新建消息</a>
</div>

<?php if ($error): ?>
  <div class="admin-alert err"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="admin-alert-ok"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="table-responsive"><table class="table">
  <thead><tr><th>ID</th><th>标题</th><th>优先级</th><th>目标</th><th>状态</th><th>发送时间</th><th>操作</th></tr></thead>
  <tbody>
  <?php if (!empty($messages)): ?>
    <?php foreach ($messages as $m): ?>
    <tr>
      <td><?= (int)$m['id'] ?></td>
      <td class="admin-text-ellipsis"><?= htmlspecialchars($m['title']) ?></td>
      <td>
        <?php if ($m['priority'] == 2): ?>
          <span class="admin-danger admin-bold">紧急</span>
        <?php elseif ($m['priority'] == 1): ?>
          <span class="admin-warn-text admin-bold">重要</span>
        <?php else: ?>
          <span class="admin-muted">普通</span>
        <?php endif; ?>
      </td>
      <td><?= $m['target_type'] === 'all' ? '全部用户' : ($m['target_type'] === 'role' ? '指定角色' : '指定用户') ?></td>
      <td>
        <?php if ($m['status'] === 'active'): ?>
          <span class="admin-ok-text">已发布</span>
        <?php elseif ($m['status'] === 'draft'): ?>
          <span class="admin-muted">草稿</span>
        <?php else: ?>
          <span class="admin-muted">已归档</span>
        <?php endif; ?>
      </td>
      <td class="admin-muted"><?= $m['sent_at'] ? date('Y-m-d H:i', strtotime($m['sent_at'])) : '-' ?></td>
      <td>
        <div class="admin-inline-actions">
          <a href="/admin.php?path=messages/edit&id=<?= (int)$m['id'] ?>" class="btn btn-light" class="admin-action-sm">编辑</a>
          <form method="post" action="/admin.php?path=messages/delete" class="admin-display-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <button class="btn admin-danger-bg" type="submit" onclick="return confirm('确认删除？')">删除</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  <?php else: ?>
    <tr><td colspan="7" class="admin-empty">暂无系统消息</td></tr>
  <?php endif; ?>
  </tbody>
</table></div>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
