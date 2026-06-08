<?php
$pageTitle = '用户管理';
require dirname(__DIR__) . '/layouts/main.php';
$users = $users ?? [];
$total = $total ?? 0;
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$roleLabels = ['user'=>'普通用户','reviewer'=>'审核员','moderator'=>'版主','admin'=>'管理员','superadmin'=>'超级管理员'];
?>
<div class="page-header">
  <div class="page-title">用户管理</div>
  <form method="get" action="/admin.php" class="admin-filter-bar">
    <input type="hidden" name="path" value="users">
    <input class="input" name="kw" placeholder="搜索账号ID/用户名/昵称/邮箱" value="<?= htmlspecialchars($_GET['kw'] ?? '') ?>" class="admin-w-200">
    <select class="select admin-w-120" name="status"><option value="">全部状态</option><option value="active" <?= (($_GET['status'] ?? '')==='active')?'selected':'' ?>>正常</option><option value="banned" <?= (($_GET['status'] ?? '')==='banned')?'selected':'' ?>>封禁</option></select>
    <select class="select admin-w-130" name="role"><option value="">全部角色</option><?php foreach ($roleLabels as $value=>$label): ?><option value="<?= $value ?>" <?= (($_GET['role'] ?? '')===$value)?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select>
    <button class="btn" type="submit">筛选</button>
  </form>
</div>
<div class="admin-tabs" role="tablist" aria-label="用户管理"><a class="active" href="/admin.php?path=users">用户列表 <?= (int)$total ?></a><a href="/admin.php?path=user-credit">用户信用</a><a href="/admin.php?path=roles">角色权限</a></div>
<div class="admin-muted">共 <?= (int)$total ?> 个用户</div>
<div class="table-responsive"><table class="table">
  <thead><tr><th>#</th><th>账号ID</th><th>用户名</th><th>昵称</th><th>邮箱</th><th>角色</th><th>信用</th><th>状态</th><th>注册时间</th><th>操作</th></tr></thead>
  <tbody>
  <?php if (!empty($users)): ?>
    <?php foreach ($users as $i => $u): ?>
    <tr id="user-row-<?= (int)$u['id'] ?>">
      <td class="admin-muted"><?= (($page - 1) * 20) + $i + 1 ?></td>
      <td class="admin-primary-text admin-bold"><?= htmlspecialchars((string)($u['public_id'] ?? '')) ?></td>
      <td class="admin-bold"><?= htmlspecialchars($u['username']) ?></td>
      <td><?= htmlspecialchars($u['nickname'] ?? '') ?></td>
      <td class="admin-muted"><?= htmlspecialchars($u['email'] ?? '') ?></td>
      <td><span class="badge badge-ok"><?= htmlspecialchars($roleLabels[$u['role'] ?? 'user'] ?? ($u['role'] ?? 'user')) ?></span></td>
      <td><a href="/admin.php?path=user-credit&kw=<?= urlencode((string)($u['public_id'] ?: $u['username'])) ?>" class="admin-primary-text admin-bold admin-link-clean"><?= (int)($u['credit_score'] ?? 100) ?></a></td>
      <td><span class="badge <?= $u['status']==='active'?'badge-ok':'badge-err' ?>"><?= $u['status']==='active'?'正常':'封禁' ?></span></td>
      <td class="admin-muted"><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
      <td>
        <div class="admin-collapse admin-action-collapse admin-no-margin"><button type="button" class="admin-mini-btn" data-admin-collapse>操作</button><div class="admin-collapse-body admin-spacer-top"><div class="admin-inline-actions">
          <a href="/admin.php?path=users/edit&id=<?= (int)$u['id'] ?>" class="btn btn-light">编辑</a>
          <?php if ($u['status'] === 'active'): ?>
            <button class="btn btn-light" onclick="openBanModal(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">封禁</button>
          <?php else: ?>
            <form method="post" action="/admin.php?path=users/action" class="admin-display-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <input type="hidden" name="act" value="unban">
              <button class="btn">解封</button>
            </form>
          <?php endif; ?>
          <?php if ((int)$u['id'] !== (int)($_SESSION['auth_user']['id'] ?? 0)): ?>
            <form method="post" action="/admin.php?path=users/delete" class="admin-display-inline" onsubmit="return confirmDelete(this)">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <button class="btn btn-light admin-danger">删除</button>
            </form>
          <?php endif; ?>
        </div></div></div>
      </td>
    </tr>
    <?php endforeach; ?>
  <?php else: ?>
    <tr><td colspan="10" class="admin-empty">暂无用户</td></tr>
  <?php endif; ?>
  </tbody>
</table></div>
<?php if ($totalPages > 1): ?>
  <div class="admin-actions admin-mt-14">
    <?php $base = $_GET; $base['path'] = 'users'; ?>
    <?php if ($page > 1): $base['page']=$page-1; ?><a class="btn btn-light admin-link-clean" href="/admin.php?<?= http_build_query($base) ?>">上一页</a><?php endif; ?>
    <span class="admin-muted">第 <?= (int)$page ?> / <?= (int)$totalPages ?> 页</span>
    <?php if ($page < $totalPages): $base['page']=$page+1; ?><a class="btn btn-light admin-link-clean" href="/admin.php?<?= http_build_query($base) ?>">下一页</a><?php endif; ?>
  </div>
<?php endif; ?>


<div id="ban-modal" class="admin-modal-shell">
  <div class="admin-modal-box">
    <div class="admin-modal-title">封禁用户：<span id="ban-username"></span></div>
    <form method="post" action="/admin.php?path=users/action" id="ban-form">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="ban-user-id">
      <div class="admin-mb-16">
        <label class="admin-help-block">封禁类型</label>
        <div class="admin-field-stack">
          <label class="admin-user-row-click">
            <input type="radio" name="act" value="ban_until" checked onchange="toggleDays(true)"> 自定义期限
          </label>
          <div id="days-input">
            <input type="number" name="days" id="ban-days" min="1" max="3650" value="7" class="input admin-w-120"> 天
          </div>
          <label class="admin-user-row-click">
            <input type="radio" name="act" value="permanent" onchange="toggleDays(false)"> 永久封禁
          </label>
        </div>
      </div>
      <div class="admin-actions">
        <button type="button" class="btn btn-light" onclick="closeBanModal()">取消</button>
        <button type="submit" class="btn admin-danger-bg">确认封禁</button>
      </div>
    </form>
  </div>
</div>

<script>
function openBanModal(id, username) {
  document.getElementById('ban-user-id').value = id;
  document.getElementById('ban-username').textContent = username;
  document.querySelector('input[name="act"][value="ban_until"]').checked = true;
  toggleDays(true);
  const modal = document.getElementById('ban-modal');
  modal.style.display = 'flex';
}
function closeBanModal() {
  document.getElementById('ban-modal').style.display = 'none';
}
function toggleDays(show) {
  document.getElementById('days-input').style.display = show ? 'block' : 'none';
}
function confirmDelete(form) {
  return confirm('确认删除该用户？此操作不可恢复。');
}
document.getElementById('ban-modal').addEventListener('click', function(e) {
  if (e.target === this) closeBanModal();
});
</script>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
