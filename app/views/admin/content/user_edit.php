<?php
$pageTitle = '编辑用户';
require dirname(__DIR__) . '/layouts/main.php';
$u = $user ?? [];
$roles = ['user' => '普通用户', 'reviewer' => '审核员', 'moderator' => '版主', 'admin' => '管理员', 'superadmin' => '超级管理员'];
$canAssignRole = \App\Middleware\Permission::can('user.assign_role');
?>

<div class="page-header">
  <div class="page-title">编辑用户 #<?= (int)($u['id'] ?? 0) ?></div>
  <div class="admin-actions"><a href="/admin.php?path=user-credit&kw=<?= urlencode((string)($u['public_id'] ?: $u['username'])) ?>" class="btn btn-light">信用 <?= (int)($u['credit_score'] ?? 100) ?></a><a href="/admin.php?path=users" class="btn btn-light">← 返回列表</a></div>
</div>
<?php if (!empty($error)): ?>
  <div class="admin-error-alert"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
  <div class="admin-ok-alert"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<div class="user-edit-panel">
  <form class="user-edit-form" method="post" action="/admin.php?path=users/edit&id=<?= (int)($u['id'] ?? 0) ?>">
    <?= csrf_field() ?>
    <div class="user-edit-field">
      <label>账号ID</label>
      <div class="user-edit-control">
        <input class="input" name="public_id" value="<?= htmlspecialchars($u['public_id'] ?? '') ?>" required pattern="[A-Z0-9]{2,32}" title="2-32位大写字母或数字">
        <div class="user-edit-help">用于好友搜索和添加，后续可扩展靓号/动画标识。</div>
      </div>
    </div>
    <div class="user-edit-field">
      <label>用户名</label>
      <div class="user-edit-control">
        <input class="input" name="username" value="<?= htmlspecialchars($u['username'] ?? '') ?>" required pattern="[A-Za-z0-9_]{2,30}" title="2-30位字母数字下划线">
      </div>
    </div>
    <div class="user-edit-field">
      <label>昵称</label>
      <div class="user-edit-control">
        <input class="input" name="nickname" value="<?= htmlspecialchars($u['nickname'] ?? '') ?>">
      </div>
    </div>
    <div class="user-edit-field">
      <label>邮箱</label>
      <div class="user-edit-control">
        <input class="input" type="email" name="email" value="<?= htmlspecialchars($u['email'] ?? '') ?>" required>
      </div>
    </div>
    <div class="user-edit-field">
      <label>角色</label>
      <div class="user-edit-control is-short">
        <select class="select" name="role" <?= $canAssignRole ? '' : 'disabled' ?>>
          <?php foreach ($roles as $val => $label): ?>
            <option value="<?= $val ?>" <?= ($u['role'] ?? 'user') === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (!$canAssignRole): ?><input type="hidden" name="role" value="<?= htmlspecialchars($u['role'] ?? 'user') ?>"><div class="user-edit-help">你没有角色分配权限，不能修改角色。</div><?php endif; ?>
      </div>
    </div>
    <div class="user-edit-field">
      <label>状态</label>
      <div class="user-edit-control is-short">
        <select class="select" name="status">
          <option value="active" <?= ($u['status'] ?? '') === 'active' ? 'selected' : '' ?>>正常</option>
          <option value="banned" <?= ($u['status'] ?? '') === 'banned' ? 'selected' : '' ?>>封禁</option>
        </select>
      </div>
    </div>
    <div class="user-edit-field">
      <label>邮箱验证状态</label>
      <div class="user-edit-control is-short">
        <select class="select" name="email_verified" <?= $canAssignRole ? '' : 'disabled' ?>>
          <option value="1" <?= ($u['email_verified'] ?? 0) == 1 ? 'selected' : '' ?>>已验证</option>
          <option value="0" <?= ($u['email_verified'] ?? 0) == 0 ? 'selected' : '' ?>>未验证</option>
        </select>
        <?php if (!$canAssignRole): ?><input type="hidden" name="email_verified" value="<?= (int)($u['email_verified'] ?? 0) ?>"><?php endif; ?>
      </div>
    </div>
    <div class="user-edit-field">
      <label>新密码</label>
      <div class="user-edit-control">
        <input class="input" type="password" name="password" placeholder="留空则不修改密码" minlength="6">
        <div class="user-edit-help">留空不修改当前密码。</div>
      </div>
    </div>
    <div class="user-edit-actions">
      <button class="btn" type="submit">保存修改</button>
      <a href="/admin.php?path=users" class="btn btn-light">取消</a>
    </div>
  </form>
</div>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
