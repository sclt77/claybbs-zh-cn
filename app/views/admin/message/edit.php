<?php
$pageTitle = isset($editMsg) ? '编辑系统消息' : '新建系统消息';
require dirname(__DIR__) . '/layouts/main.php';
$error = $error ?? '';
$success = $success ?? '';
$editMsg = $editMsg ?? null;
$formAction = '/admin.php?path=messages/edit&id=' . (int)($editMsg['id'] ?? 0);
?>
<div class="page-header">
  <div class="page-title"><?= $pageTitle ?></div>
  <a href="/admin.php?path=messages" class="btn btn-light">← 返回列表</a>
</div>

<?php if ($error): ?>
  <div class="admin-alert err"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="admin-alert-ok"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="card">
  <form method="post" action="<?= $formAction ?>">
    <?= csrf_field() ?>
    <?php if ($editMsg): ?>
      <input type="hidden" name="id" value="<?= (int)$editMsg['id'] ?>">
    <?php endif; ?>

    <div class="admin-mb-16">
      <label class="admin-help-block">消息标题 <span class="admin-danger">*</span></label>
      <input class="input" name="title" required placeholder="请输入消息标题" value="<?= htmlspecialchars($editMsg['title'] ?? '') ?>">
    </div>

    <div class="admin-mb-16">
      <label class="admin-help-block">消息内容 <span class="admin-danger">*</span></label>
      <textarea class="input" name="content" required rows="6" placeholder="请输入消息内容"><?= htmlspecialchars($editMsg['content'] ?? '') ?></textarea>
    </div>

    <div class="admin-grid-2">
      <div>
        <label class="admin-help-block">优先级</label>
        <select class="input" name="priority">
          <option value="0" <?= ($editMsg['priority'] ?? 0) == 0 ? 'selected' : '' ?>>普通</option>
          <option value="1" <?= ($editMsg['priority'] ?? 0) == 1 ? 'selected' : '' ?>>重要</option>
          <option value="2" <?= ($editMsg['priority'] ?? 0) == 2 ? 'selected' : '' ?>>紧急</option>
        </select>
      </div>
      <div>
        <label class="admin-help-block">状态</label>
        <select class="input" name="status">
          <option value="active" <?= ($editMsg['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>发布</option>
          <option value="draft" <?= ($editMsg['status'] ?? '') === 'draft' ? 'selected' : '' ?>>草稿</option>
          <option value="archived" <?= ($editMsg['status'] ?? '') === 'archived' ? 'selected' : '' ?>>归档</option>
        </select>
      </div>
    </div>

    <div class="admin-mb-16">
      <label class="admin-help-block">消息目标</label>
      <select class="input" name="target_type" id="targetType" onchange="toggleTarget(this.value)">
        <option value="all" <?= ($editMsg['target_type'] ?? 'all') === 'all' ? 'selected' : '' ?>>全部用户</option>
        <option value="role" <?= ($editMsg['target_type'] ?? '') === 'role' ? 'selected' : '' ?>>指定角色</option>
        <option value="user" <?= ($editMsg['target_type'] ?? '') === 'user' ? 'selected' : '' ?>>指定用户</option>
      </select>
    </div>

    <div id="targetRoles" class="admin-dynamic-show" style="display:<?= ($editMsg['target_type'] ?? 'all') === 'role' ? 'block' : 'none' ?>">
      <label class="admin-help-block">目标角色</label>
      <div class="admin-checkbox-grid">
        <?php foreach (($roles ?? []) as $role): ?>
          <label class="admin-checkbox-card">
            <input type="checkbox" name="target_roles[]" value="<?= (int)$role['id'] ?>" <?= in_array((int)$role['id'], $editMsg['target_role_ids'] ?? [], true) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars($role['name'] ?? $role['slug']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div id="targetUsers" class="admin-dynamic-show" style="display:<?= ($editMsg['target_type'] ?? 'all') === 'user' ? 'block' : 'none' ?>">
      <label class="admin-help-block">指定用户</label>
      <input class="input" id="userSearch" placeholder="输入用户名、昵称或邮箱搜索" autocomplete="off">
      <input type="hidden" name="target_users" id="targetUsersInput" value="<?= htmlspecialchars($editMsg['target_users_str'] ?? '') ?>">
      <div id="selectedUsers" class="admin-inline-actions admin-mt-8"></div>
      <div id="userSearchResults" class="badge-select-menu admin-mt-8"></div>
    </div>

    <div class="admin-actions">
      <a href="/admin.php?path=messages" class="btn btn-light">取消</a>
      <button class="btn" type="submit"><?= $editMsg ? '保存修改' : '发布消息' ?></button>
    </div>
  </form>
</div>

<script>
function toggleTarget(val) {
  document.getElementById('targetRoles').style.display = val === 'role' ? 'block' : 'none';
  document.getElementById('targetUsers').style.display = val === 'user' ? 'block' : 'none';
}
var userInput = document.getElementById('userSearch');
var userIdsInput = document.getElementById('targetUsersInput');
var selectedUsers = document.getElementById('selectedUsers');
var resultsBox = document.getElementById('userSearchResults');
var selected = (userIdsInput && userIdsInput.value ? userIdsInput.value.split(',') : []).filter(Boolean);
function renderSelected(){
  selectedUsers.innerHTML = selected.length ? selected.map(function(id){return '<span class="admin-user-chip">用户 #'+id+' <button type="button" data-remove="'+id+'" class="admin-clickable admin-primary-text">×</button></span>';}).join('') : '<span class="admin-muted">未选择用户</span>';
  userIdsInput.value = selected.join(',');
}
if (selectedUsers) {
  renderSelected();
  selectedUsers.addEventListener('click', function(e){
    var id = e.target && e.target.getAttribute('data-remove');
    if (!id) return;
    selected = selected.filter(function(x){ return x !== id; });
    renderSelected();
  });
}
var timer = null;
if (userInput) {
  userInput.addEventListener('input', function(){
    clearTimeout(timer);
    var q = userInput.value.trim();
    if (q.length < 1) { resultsBox.style.display='none'; return; }
    timer = setTimeout(function(){
      fetch('/api.php?path=users/search&q=' + encodeURIComponent(q), {credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(data){
          var users = data.users || [];
          resultsBox.innerHTML = users.length ? users.map(function(u){return '<button type="button" data-id="'+u.id+'" class="section-user-option">'+escapeHtml(u.name)+' <span class="admin-muted">#'+u.id+' '+escapeHtml(u.email||'')+'</span></button>';}).join('') : '<div class="admin-muted admin-p-12">无匹配用户</div>';
          resultsBox.style.display = 'block';
        }).catch(function(){ resultsBox.style.display='none'; });
    }, 250);
  });
  resultsBox.addEventListener('click', function(e){
    var id = e.target && e.target.closest('button') && e.target.closest('button').getAttribute('data-id');
    if (!id) return;
    if (!selected.includes(id)) selected.push(id);
    userInput.value = '';
    resultsBox.style.display = 'none';
    renderSelected();
  });
}
function escapeHtml(s){return String(s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
</script>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
