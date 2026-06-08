<?php
$pageTitle = '公告管理';
require dirname(__DIR__) . '/layouts/main.php';
$announcements = $announcements ?? [];
$error = $error ?? '';
?>
<div class="page-header"><div class="page-title">公告管理</div></div>
<?php if ($error): ?><div class="admin-alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="admin-collapse">
  <button type="button" class="admin-collapse-toggle" data-admin-collapse>新增公告</button>
  <div class="admin-collapse-body card">
    <form method="post" action="/admin.php?path=announcements" enctype="multipart/form-data" class="admin-form-grid" data-no-ajax>
      <?= csrf_field() ?><input type="hidden" name="_action" value="create">
      <div><label class="admin-label">标题</label><input class="input" name="title" placeholder="公告标题" required></div>
      <div><label class="admin-label">跳转链接</label><input class="input" name="url" placeholder="可选，https://..."></div>
      <div><label class="admin-label">排序</label><input class="input" name="sort_order" type="number" value="0" min="0"></div>
      <div><label class="admin-label">图片URL</label><input class="input" name="image" placeholder="https://..."></div>
      <div><label class="admin-label">上传图片</label><input class="input" name="image_file" type="file" accept="image/*"></div>
      <label class="admin-checkline"><input type="checkbox" name="is_pinned" value="1"> 置顶</label><label class="admin-checkline"><input type="checkbox" name="popup_enabled" value="1"> 前台弹窗</label><label class="admin-checkline"><input type="checkbox" name="popup_once" value="1" checked> 每人只弹一次</label>
      <div class="full-row"><label class="admin-label">内容</label><textarea class="input" name="content" rows="4" placeholder="公告内容"></textarea></div>
      <button class="btn full-row" type="submit">添加公告</button>
    </form>
  </div>
</div>

<div class="card">
  <h3 class="admin-card-title">公告列表</h3>
  <div class="admin-grid-gap">
  <?php if (!empty($announcements)): ?>
    <?php foreach ($announcements as $a): $panel='ann-edit-'.(int)$a['id']; ?>
      <div class="admin-row-card">
        <div class="admin-row-summary">
          <div class="admin-section-row-main"><div class="admin-row-title"><?= htmlspecialchars($a['title']) ?> <span class="admin-muted">#<?= (int)$a['id'] ?></span></div><div class="admin-row-meta">排序 <?= (int)$a['sort_order'] ?> · <?= !empty($a['is_pinned'])?'置顶 · ':'' ?><?= !empty($a['popup_enabled'])?'弹窗 · ':'' ?><?= $a['status']==='active'?'显示':'隐藏' ?></div></div>
          <button type="button" class="admin-mini-btn" data-edit-target="<?= $panel ?>">编辑</button>
        </div>
        <div class="admin-edit-panel" id="<?= $panel ?>">
          <form method="post" action="/admin.php?path=announcements" enctype="multipart/form-data" class="admin-form-grid" data-no-ajax>
            <?= csrf_field() ?><input type="hidden" name="_action" value="update"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
            <input class="input" name="title" value="<?= htmlspecialchars($a['title']) ?>" placeholder="标题" required>
            <input class="input" name="url" value="<?= htmlspecialchars($a['url'] ?? '') ?>" placeholder="跳转链接">
            <input class="input" name="image" value="<?= htmlspecialchars($a['image'] ?? '') ?>" placeholder="图片URL">
            <input class="input" name="image_file" type="file" accept="image/*">
            <input class="input" name="sort_order" type="number" value="<?= (int)$a['sort_order'] ?>" min="0">
            <label class="admin-checkline"><input type="checkbox" name="is_pinned" value="1" <?= !empty($a['is_pinned']) ? 'checked' : '' ?>> 置顶</label><label class="admin-checkline"><input type="checkbox" name="popup_enabled" value="1" <?= !empty($a['popup_enabled']) ? 'checked' : '' ?>> 前台弹窗</label><label class="admin-checkline"><input type="checkbox" name="popup_once" value="1" <?= !empty($a['popup_once']) ? 'checked' : '' ?>> 每人只弹一次</label>
            <?php if (!empty($a['image'])): ?><img src="<?= htmlspecialchars($a['image']) ?>" class="admin-preview-img"><?php endif; ?>
            <div class="full-row"><textarea class="input" name="content" rows="3" placeholder="内容"><?= htmlspecialchars($a['content'] ?? '') ?></textarea></div>
            <div class="admin-actions full-row"><button class="btn" type="submit">保存</button></div>
          </form>
          <div class="admin-actions admin-justify-start">
            <form method="post" action="/admin.php?path=announcements" class="admin-inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="status" value="<?= $a['status']==='active'?'inactive':'active' ?>"><button class="btn btn-light" name="_action" value="status"><?= $a['status']==='active'?'隐藏':'显示' ?></button></form>
            <form method="post" action="/admin.php?path=announcements" class="admin-inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn admin-danger-bg" name="_action" value="delete" onclick="return confirm('确认删除该公告？')">删除</button></form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?><div class="admin-empty">暂无公告</div><?php endif; ?>
  </div>
</div>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
