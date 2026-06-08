<?php
$pageTitle = '分区 / 板块管理';
require dirname(__DIR__) . '/layouts/main.php';
$sections = $sections ?? [];
$categories = $categories ?? [];
$error = $error ?? '';
$tab = (string)($_GET['tab'] ?? 'manage');
if (!in_array($tab, ['manage','create'], true)) $tab = 'manage';
function section_tab_active(string $name, string $tab): string { return $name === $tab ? 'active' : ''; }
function section_icon_html(?string $icon, string $class = ''): string {
    $icon = trim((string)$icon);
    if ($icon === '') return '';
    $safe = htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');
    $isImage = preg_match('#^(https?://|/uploads/|/storage/|/assets/).+\.(png|jpe?g|gif|webp|svg)(\?.*)?$#i', $icon);
    if ($isImage) {
        return '<img src="' . $safe . '" class="' . htmlspecialchars($class ?: 'section-avatar', ENT_QUOTES, 'UTF-8') . '" alt="">';
    }
    return '<span class="section-avatar-text">' . $safe . '</span>';
}
?>
<div class="page-header">
  <div class="page-title">分区 / 板块管理</div>
</div>

<?php if ($error): ?>
  <div class="admin-alert err">
    <?= htmlspecialchars($error) ?>
  </div>
<?php endif; ?>

<div class="admin-tabs" role="tablist" aria-label="分区板块管理"><a class="<?= section_tab_active('manage',$tab) ?>" href="/admin.php?path=sections&tab=manage">分区与板块</a><a class="<?= section_tab_active('create',$tab) ?>" href="/admin.php?path=sections&tab=create">新增入口</a></div>
<?php if ($tab === 'manage'): $totalThreads=array_sum(array_map(fn($x)=>(int)($x['thread_count']??0),$sections)); $todayThreads=array_sum(array_map(fn($x)=>(int)($x['today_thread_count']??0),$sections)); $openReports=array_sum(array_map(fn($x)=>(int)($x['open_report_count']??0),$sections)); $pendingTotal=array_sum(array_map(fn($x)=>(int)($x['pending_thread_count']??0)+(int)($x['pending_post_count']??0),$sections)); ?>
<div class="section-stat-grid"><div><strong><?= (int)count($sections) ?></strong><span>板块总数</span></div><div><strong><?= (int)$totalThreads ?></strong><span>累计帖子</span></div><div><strong><?= (int)$todayThreads ?></strong><span>今日新帖</span></div><div><strong><?= (int)$pendingTotal ?></strong><span>待审内容</span></div><div><strong><?= (int)$openReports ?></strong><span>待处理举报</span></div></div>
<?php endif; ?>

<div class="admin-section-stack">
<?php if ($tab === 'create'): ?>
  <div class="admin-collapse open">
    <button type="button" class="admin-collapse-toggle" data-admin-collapse>新增分区</button>
    <div class="admin-collapse-body card">
      <form method="post" action="/admin.php?path=sections" class="admin-form-grid" enctype="multipart/form-data" data-no-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="create_category">
        <div><label class="admin-label">分区名称</label><input class="input" name="name" placeholder="例如：综合讨论" required></div>
        <div><label class="admin-label">slug</label><input class="input" name="slug" placeholder="general" required></div>
        <div><label class="admin-label">排序</label><input class="input" type="number" name="sort" value="0" min="0"></div>
        <div class="full-row"><label class="admin-label">描述</label><input class="input" name="description" placeholder="可选描述"></div>
        <button class="btn full-row" type="submit">添加分区</button>
      </form>
    </div>
  </div>

  <div class="admin-collapse open">
    <button type="button" class="admin-collapse-toggle" data-admin-collapse>新增板块</button>
    <div class="admin-collapse-body card">
      <form method="post" action="/admin.php?path=sections" class="admin-form-grid" enctype="multipart/form-data" data-no-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="create_section">
        <div><label class="admin-label">板块名称</label><input class="input" name="name" placeholder="例如：新人报到" required></div>
        <div><label class="admin-label">slug</label><input class="input" name="slug" placeholder="newbie" required></div>
        <div><label class="admin-label">所属分区</label><select class="select" name="category_id" required><option value="">请选择分区</option><?php foreach ($categories as $cat): ?><option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option><?php endforeach; ?></select></div>
        <div><label class="admin-label">排序</label><input class="input" type="number" name="sort" value="0" min="0"></div>
        <div><label class="admin-label">图标/图片URL</label><input class="input" name="icon" placeholder="例如：/uploads/section-icons/icon.png"></div>
        <div><label class="admin-label">上传图标</label><input class="input" name="icon_file" type="file" accept="image/*"></div>
        <div><label class="admin-label">谁可发帖</label><select class="select" name="post_permission"><option value="login">所有登录用户</option><option value="role">管理员/版主/审核员</option><option value="section_role">仅板块职位</option><option value="admin">仅管理员</option></select></div>
        <label class="admin-role-check"><input type="checkbox" name="is_question" value="1"> 问答板块</label>
        <div class="full-row"><label class="admin-label">描述</label><input class="input" name="description" placeholder="可选描述"></div>
        <button class="btn full-row" type="submit">添加板块</button>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($tab === 'manage'): ?>
  <div class="card">
    <h3 class="admin-card-title">分区与板块</h3>
    <?php
      $sectionsByCategory = [];
      foreach ($sections as $sec) {
          $sectionsByCategory[(int)($sec['category_id'] ?? 0)][] = $sec;
      }
      $uncategorized = $sectionsByCategory[0] ?? [];
      unset($sectionsByCategory[0]);
    ?>
    <div class="admin-grid-stack">
    <?php if (!empty($categories)): ?>
      <?php foreach ($categories as $cat): $catId=(int)$cat['id']; $catPanel='cat-edit-'.$catId; $catSections=$sectionsByCategory[$catId] ?? []; ?>
        <section class="admin-panel">
          <div class="admin-panel-head">
            <div>
              <div class="admin-row-title"><?= htmlspecialchars($cat['name']) ?> <span class="admin-muted">#<?= $catId ?></span></div>
              <div class="admin-row-meta">slug: <?= htmlspecialchars($cat['slug'] ?? '') ?> · 排序 <?= (int)($cat['sort_order'] ?? 0) ?> · <?= ($cat['status'] ?? '') === 'active' ? '显示' : '隐藏' ?> · 板块 <?= count($catSections) ?></div>
              <?php if (!empty($cat['description'])): ?><div class="admin-row-meta"><?= htmlspecialchars($cat['description']) ?></div><?php endif; ?>
            </div>
            <button type="button" class="admin-mini-btn" data-edit-target="<?= $catPanel ?>">编辑分区</button>
          </div>
          <div class="admin-edit-panel admin-panel-edit" id="<?= $catPanel ?>">
            <form method="post" action="/admin.php?path=sections" class="admin-form-grid">
              <?= csrf_field() ?><input type="hidden" name="_action" value="update_category"><input type="hidden" name="id" value="<?= $catId ?>">
              <input class="input" name="name" value="<?= htmlspecialchars($cat['name']) ?>" required>
              <input class="input" name="slug" value="<?= htmlspecialchars($cat['slug'] ?? '') ?>" required>
              <input class="input" name="description" value="<?= htmlspecialchars($cat['description'] ?? '') ?>" placeholder="描述">
              <input class="input" type="number" name="sort" value="<?= (int)($cat['sort_order'] ?? 0) ?>" min="0">
              <select class="select" name="status"><option value="active" <?= ($cat['status'] ?? '') === 'active' ? 'selected' : '' ?>>显示</option><option value="inactive" <?= ($cat['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>隐藏</option></select>
              <div class="admin-actions full-row"><button class="btn" type="submit">保存分区</button></div>
            </form>
            <form method="post" action="/admin.php?path=sections" class="admin-inline-form admin-spacer-top">
              <?= csrf_field() ?><input type="hidden" name="_action" value="delete_category"><input type="hidden" name="id" value="<?= $catId ?>"><button class="btn admin-danger-bg" type="submit" onclick="return confirm('确认删除该分区？删除前请先处理其下板块')">删除分区</button>
            </form>
          </div>

          <div class="admin-panel-body">
            <?php if (!empty($catSections)): ?>
              <?php foreach ($catSections as $s): $panel='sec-edit-'.(int)$s['id']; ?>
                <div class="admin-row-card">
                  <div class="admin-row-summary">
                    <div class="admin-section-row">
                      <div class="admin-section-row-main"><div class="admin-section-inline-meta"><span class="admin-section-name"><?= htmlspecialchars($s['name']) ?></span><span class="admin-section-slug">slug: <?= htmlspecialchars($s['slug'] ?? '') ?></span><span class="admin-section-threads">帖子 <?= (int)($s['thread_count'] ?? 0) ?></span></div></div>
                    </div>
                    <button type="button" class="admin-mini-btn" data-edit-target="<?= $panel ?>">编辑板块</button>
                  </div>
                  <div class="admin-edit-panel" id="<?= $panel ?>">
                    <form method="post" action="/admin.php?path=sections" class="admin-form-grid" enctype="multipart/form-data" data-no-ajax>
                      <?= csrf_field() ?><input type="hidden" name="_action" value="update_section"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                      <input class="input" name="name" value="<?= htmlspecialchars($s['name']) ?>" placeholder="板块名称" required>
                      <input class="input" name="slug" value="<?= htmlspecialchars($s['slug'] ?? '') ?>" placeholder="slug" required>
                      <select class="select" name="category_id" required><?php foreach ($categories as $cat2): ?><option value="<?= (int)$cat2['id'] ?>" <?= (int)$cat2['id'] === (int)$s['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat2['name']) ?></option><?php endforeach; ?></select>
                      <div><?= section_icon_html($s['icon'] ?? '') ?><input class="input" name="icon" value="<?= htmlspecialchars($s['icon'] ?? '') ?>" placeholder="图标/图片URL" class="admin-spacer-top"></div>
                      <input class="input" name="icon_file" type="file" accept="image/*">
                      <select class="select" name="post_permission"><option value="login" <?= ($s['post_permission'] ?? 'login')==='login'?'selected':'' ?>>所有登录用户</option><option value="role" <?= ($s['post_permission'] ?? '')==='role'?'selected':'' ?>>管理员/版主/审核员</option><option value="section_role" <?= ($s['post_permission'] ?? '')==='section_role'?'selected':'' ?>>仅板块职位</option><option value="admin" <?= ($s['post_permission'] ?? '')==='admin'?'selected':'' ?>>仅管理员</option></select>
                      <select class="select" name="status"><option value="active" <?= ($s['status'] ?? '') === 'active' ? 'selected' : '' ?>>显示</option><option value="inactive" <?= ($s['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>隐藏</option></select>
                      <label class="admin-role-check"><input type="checkbox" name="is_question" value="1" <?= !empty($s['is_question']) ? 'checked' : '' ?>> 问答板块</label>
                      <input class="input" type="number" name="sort" value="<?= (int)($s['sort_order'] ?? 0) ?>" min="0">
                      <div class="full-row"><input class="input" name="description" value="<?= htmlspecialchars($s['description'] ?? '') ?>" placeholder="板块描述"></div>
                      <div class="admin-actions full-row"><button class="btn" type="submit">保存板块</button></div>
                    </form>
                    <div class="admin-divider-top">
                      <div class="admin-card-title">版主 / 审核员</div>
                      <div class="admin-muted admin-mb-12">在这里设置该板块的负责人员；他们只会看到并处理自己负责板块的待审内容。</div>
                      <?php $assignedRoles = $sectionRoles[(int)$s['id']] ?? []; ?>
                      <?php if (!empty($assignedRoles)): ?><div class="admin-inline-actions admin-mb-12"><?php foreach ($assignedRoles as $ar): ?><form method="post" action="/admin.php?path=sections" class="admin-inline-form-row" data-refresh-on-success><?= csrf_field() ?><input type="hidden" name="_action" value="revoke_section_role"><input type="hidden" name="section_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="user_role_id" value="<?= (int)($ar['user_role_id'] ?? $ar['id'] ?? 0) ?>"><span class="badge badge-warn"><?= htmlspecialchars(($ar['nickname'] ?: $ar['username']) ?? '') ?> · <?= htmlspecialchars($ar['name'] ?? '') ?></span><button class="btn btn-light" type="submit" onclick="return confirm('确认移除该板块负责人员？')">移除</button></form><?php endforeach; ?></div><?php else: ?><div class="admin-muted admin-mb-12">暂无负责人员</div><?php endif; ?>
                      <form method="post" action="/admin.php?path=sections" class="admin-form-grid section-role-form" data-refresh-on-success><?= csrf_field() ?><input type="hidden" name="_action" value="assign_section_role"><input type="hidden" name="section_id" value="<?= (int)$s['id'] ?>"><div class="section-user-picker"><input type="hidden" class="section-user-id" name="user_id" required><input class="input section-user-search" type="search" placeholder="搜索并选择用户昵称 / 用户名 / UID" autocomplete="off"><div class="section-user-results" role="listbox"><?php foreach (($users ?? []) as $u): ?><?php $displayName = (string)(($u['nickname'] ?: $u['username']) ?? ''); ?><button type="button" class="section-user-option" data-user-id="<?= (int)$u['id'] ?>" data-label="<?= htmlspecialchars($displayName . ' #' . (int)$u['id']) ?>" data-keyword="<?= htmlspecialchars(mb_strtolower($displayName . ' ' . ($u['username'] ?? '') . ' ' . ($u['email'] ?? '') . ' ' . (int)$u['id'])) ?>"><?= htmlspecialchars($displayName) ?> <span>#<?= (int)$u['id'] ?></span></button><?php endforeach; ?></div><div class="section-user-search-empty">没有匹配的用户</div></div><select class="select" name="role_id" required><option value="">选择职责</option><?php foreach (($roles ?? []) as $r): ?><option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option><?php endforeach; ?></select><button class="btn" type="submit">添加负责人员</button></form>
                    </div>
                    <form method="post" action="/admin.php?path=sections" class="admin-inline-form admin-spacer-top"><?= csrf_field() ?><input type="hidden" name="_action" value="delete_section"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn admin-danger-bg" type="submit" onclick="return confirm('确认删除该板块？')">删除板块</button></form>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="admin-empty">该分区下暂无板块</div>
            <?php endif; ?>
          </div>
        </section>
      <?php endforeach; ?>
    <?php else: ?><div class="admin-empty">暂无分区</div><?php endif; ?>

    <?php if (!empty($uncategorized)): ?>
      <section class="admin-warning-panel">
        <div class="admin-warning-head">未归类板块</div>
        <div class="admin-warning-body">存在未绑定分区的板块，请编辑后移动到正确分区。</div>
      </section>
    <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
</div>


<script>
(function(){
  if(window.__sectionUserSearchLoaded) return;
  window.__sectionUserSearchLoaded = true;
  function normalize(s){ return (s || '').toString().trim().toLowerCase(); }
  function filterPicker(picker){
    var input = picker.querySelector('.section-user-search');
    var q = normalize(input ? input.value : '');
    var visible = 0;
    picker.querySelectorAll('.section-user-option').forEach(function(btn){
      var key = normalize(btn.getAttribute('data-keyword') || btn.textContent);
      var hit = q === '' || key.indexOf(q) !== -1;
      btn.style.display = hit ? 'flex' : 'none';
      if(hit) visible++;
    });
    picker.classList.toggle('is-empty', visible === 0);
  }
  document.addEventListener('focusin', function(e){
    var input = e.target.closest('.section-user-search');
    if(!input) return;
    var picker = input.closest('.section-user-picker');
    picker.classList.add('is-open');
    filterPicker(picker);
  });
  document.addEventListener('input', function(e){
    var input = e.target.closest('.section-user-search');
    if(!input) return;
    var picker = input.closest('.section-user-picker');
    var hidden = picker.querySelector('.section-user-id');
    if(hidden) hidden.value = '';
    picker.classList.add('is-open');
    filterPicker(picker);
  });
  document.addEventListener('click', function(e){
    var option = e.target.closest('.section-user-option');
    if(option){
      var picker = option.closest('.section-user-picker');
      picker.querySelector('.section-user-id').value = option.getAttribute('data-user-id') || '';
      picker.querySelector('.section-user-search').value = option.getAttribute('data-label') || option.textContent.trim();
      picker.classList.remove('is-open','is-empty');
      return;
    }
    document.querySelectorAll('.section-user-picker.is-open').forEach(function(picker){
      if(!picker.contains(e.target)) picker.classList.remove('is-open');
    });
  });
})();
</script>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
