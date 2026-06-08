<?php
$pageTitle = '角色管理';
require dirname(__DIR__) . '/layouts/main.php';
$users = $users ?? [];
$roles = $roles ?? [];
$error = $error ?? '';
$success = $success ?? '';
$keyword = trim($_GET['kw'] ?? '');
$hasSearch = $keyword !== '';
$tab = (string)($_GET['tab'] ?? 'users');
if (!in_array($tab, ['users','permissions','help'], true)) $tab = 'users';
function role_tab_active(string $name, string $tab): string { return $name === $tab ? 'active' : ''; }
?>
<div class="page-header">
  <div class="page-title">角色管理</div>
  <form method="get" action="/admin.php" class="admin-actions">
    <input type="hidden" name="path" value="roles">
    <input class="input" name="kw" placeholder="搜索用户名 / 昵称" value="<?= htmlspecialchars($keyword) ?>" class="admin-w-260">
    <button class="btn" type="submit">搜索用户</button>
    <?php if ($hasSearch): ?>
      <a class="btn btn-light admin-link-clean" href="/admin.php?path=roles">清空</a>
    <?php endif; ?>
  </form>
</div>

<div class="admin-tabs" role="tablist" aria-label="角色权限管理"><a class="<?= role_tab_active('users',$tab) ?>" href="/admin.php?path=roles&tab=users<?= $keyword !== '' ? '&kw=' . urlencode($keyword) : '' ?>">用户角色</a><a class="<?= role_tab_active('permissions',$tab) ?>" href="/admin.php?path=roles&tab=permissions">角色权限</a><a class="<?= role_tab_active('help',$tab) ?>" href="/admin.php?path=roles&tab=help">说明</a></div>

<?php if ($error): ?>
  <div class="admin-alert err">
    <?= htmlspecialchars($error) ?>
  </div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="admin-alert-ok">
    <?= htmlspecialchars($success) ?>
  </div>
<?php endif; ?>

<?php if ($tab === 'help'): ?>
<div class="card admin-mb-18">
  <div class="admin-small-title">使用说明</div>
  <div class="admin-desc">
    先搜索目标用户，再对该用户执行角色操作。<br>
    本页面只管理<strong>全站角色</strong>与角色权限点。<br>
    板块职位请到「板块管理」中编辑具体板块时添加。
  </div>
</div>
<?php endif; ?>

<?php if ($tab === 'users'): ?>
<div class="card admin-mb-18">
  <div class="admin-section-title">用户角色操作</div>

  <?php if (!$hasSearch): ?>
    <div class="admin-soft-box">
      当前未搜索用户，不显示任何用户账号及角色操作面板。请先搜索用户名或昵称，再对目标用户进行角色操作。
    </div>
  <?php elseif (empty($users)): ?>
    <div class="admin-soft-box">
      没有找到匹配的用户。
    </div>
  <?php else: ?>
    <?php foreach ($users as $u): ?>
      <?php $scoped = $userScopedRoles[(int)$u['id']] ?? []; ?>
      <details class="role-collapse" open>
        <summary class="role-collapse__summary">
          <div>
            <div class="role-collapse__title"><?= htmlspecialchars($u['username']) ?>（<?= htmlspecialchars($u['nickname'] ?? '') ?>）</div>
            <div class="role-collapse__meta">ID #<?= (int)$u['id'] ?> · 当前全站角色：<?= htmlspecialchars($u['role'] ?? 'user') ?></div>
          </div>
          <span class="role-collapse__hint">展开 / 收起</span>
        </summary>
        <div class="role-collapse__body">
          <div class="card admin-perm-group">
            <div class="admin-small-title">全站角色</div>
            <form method="post" action="/admin.php?path=roles" class="admin-inline-actions">
              <?= csrf_field() ?>
              <input type="hidden" name="_action" value="assign">
              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
              <input type="hidden" name="scope" value="global">
              <select class="select admin-w-180" name="role_id">
                <?php foreach ($roles as $r): ?>
                  <option value="<?= (int)$r['id'] ?>" <?= (($u['role'] ?? 'user') === ($r['slug'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn" type="submit">保存全站角色</button>
            </form>
          </div>
        </div>
      </details>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'permissions'): ?>
<div class="card">
  <div class="admin-section-title">角色权限配置</div>
  <div class="admin-muted admin-mb-14">按角色展开，勾选该角色拥有的权限点，保存后立即生效。</div>
  <?php $permGroups = []; foreach (($permissions ?? []) as $perm) { $permGroups[$perm['group_name'] ?? 'other'][] = $perm; } ?>
  <?php foreach (($roles ?? []) as $role): ?>
    <?php $checked = $rolePermissions[(int)$role['id']] ?? []; ?>
    <details class="role-collapse">
      <summary class="role-collapse__summary">
        <div>
          <div class="role-collapse__title"><?= htmlspecialchars($role['name']) ?></div>
          <div class="role-collapse__meta">slug: <?= htmlspecialchars($role['slug']) ?> · level: <?= (int)$role['level'] ?></div>
        </div>
        <span class="role-collapse__hint">展开权限点</span>
      </summary>
      <div class="role-collapse__body">
        <form method="post" action="/admin.php?path=roles" class="card">
          <?= csrf_field() ?>
          <input type="hidden" name="_action" value="save_role_permissions">
          <input type="hidden" name="role_id" value="<?= (int)$role['id'] ?>">
          <?php foreach ($permGroups as $groupName => $groupPerms): ?>
            <div class="admin-mb-12">
              <div class="admin-upper-label"><?= htmlspecialchars($groupName) ?></div>
              <div class="admin-grid-220">
                <?php foreach ($groupPerms as $perm): ?>
                  <label class="admin-checkbox-line">
                    <input type="checkbox" name="permission_ids[]" value="<?= (int)$perm['id'] ?>" <?= in_array((int)$perm['id'], $checked, true) ? 'checked' : '' ?>>
                    <span><?= htmlspecialchars($perm['name']) ?> <span class="admin-muted">(<?= htmlspecialchars($perm['slug']) ?>)</span></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <button class="btn" type="submit">保存该角色权限</button>
        </form>
      </div>
    </details>
  <?php endforeach; ?>
</div>
<?php endif; ?>




<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
