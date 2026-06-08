<?php
$pageTitle = '转播管理';
require dirname(__DIR__) . '/layouts/main.php';
$banners = $banners ?? [];
$error = $error ?? '';
$tab = in_array((string)($tab ?? ($_GET['tab'] ?? 'home')), ['home', 'section'], true) ? (string)($tab ?? ($_GET['tab'] ?? 'home')) : 'home';
$tabs = ['home' => '首页转播', 'section' => '板块转播'];
?>

<div class="page-header"><div class="page-title">转播管理</div></div>
<div class="broadcast-tabs">
  <?php foreach ($tabs as $tabKey => $tabLabel): ?>
    <a href="/admin.php?path=banners&amp;tab=<?= $tabKey ?>" class="<?= $tab === $tabKey ? 'active' : '' ?>"><?= htmlspecialchars($tabLabel) ?></a>
  <?php endforeach; ?>
</div>
<?php if ($error): ?><div class="admin-alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($tab === 'home'): ?>
  <div class="broadcast-note">首页转播就是当前首页顶部轮播，可手动新增图片、链接与描述。</div>
  <div class="admin-collapse">
    <button type="button" class="admin-collapse-toggle" data-admin-collapse>新增首页转播</button>
    <div class="admin-collapse-body card">
      <form method="post" action="/admin.php?path=banners&tab=home" enctype="multipart/form-data" class="admin-form-grid" data-no-ajax>
        <?= csrf_field() ?><input type="hidden" name="_action" value="create"><input type="hidden" name="placement" value="home">
        <div><label class="admin-label">标题</label><input class="input" name="title" placeholder="转播标题" required></div>
        <div><label class="admin-label">图片URL</label><input class="input" name="image" placeholder="https://..."></div>
        <div><label class="admin-label">上传图片</label><input class="input" name="image_file" type="file" accept="image/*"></div>
        <div><label class="admin-label">排序</label><input class="input" name="sort_order" type="number" value="0" min="0"></div>
        <div><label class="admin-label">跳转URL</label><input class="input" name="url" placeholder="https://..."></div>
        <div class="full-row"><label class="admin-label">描述</label><input class="input" name="description" placeholder="可选描述"></div>
        <button class="btn full-row" type="submit">添加首页转播</button>
      </form>
    </div>
  </div>
<?php else: ?>
  <div class="broadcast-note">板块转播只接收从“帖子管理”推送上来的帖子。可在这里调整排序、显示/隐藏或删除，不提供手动新增。</div>
<?php endif; ?>

<div class="card">
  <h3 class="admin-card-title"><?= $tab === 'home' ? '首页转播列表' : '板块转播列表' ?></h3>
  <div class="admin-grid-gap">
  <?php if (!empty($banners)): ?>
    <?php foreach ($banners as $b): $panel='banner-edit-'.(int)$b['id']; ?>
      <div class="broadcast-row">
        <div class="admin-row-summary">
          <div class="admin-section-row">
            <?php if (!empty($b['image'])): ?><img class="broadcast-thumb" src="<?= htmlspecialchars($b['image']) ?>" alt=""><?php else: ?><div class="broadcast-thumb"></div><?php endif; ?>
            <div class="admin-section-row-main"><div class="admin-row-title"><?= htmlspecialchars($b['title']) ?> <span class="admin-muted">#<?= (int)$b['id'] ?></span><?php if ($tab === 'section' && !empty($b['section_name'])): ?><span class="broadcast-chip"><?= htmlspecialchars($b['section_name']) ?></span><?php endif; ?></div><div class="admin-row-meta"><?= $tab === 'section' && !empty($b['thread_title']) ? '来源帖子：' . htmlspecialchars($b['thread_title']) . ' · ' : '' ?>排序 <?= (int)$b['sort_order'] ?> · <?= $b['status']==='active'?'显示':'隐藏' ?></div></div>
          </div>
          <div class="admin-flex-end">
            <?php if ($tab === 'section'): ?>
              <form method="post" action="/admin.php?path=banners&tab=section" class="admin-inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <input type="hidden" name="status" value="<?= $b['status']==='active'?'inactive':'active' ?>">
                <button class="admin-mini-btn" name="_action" value="status" onclick="return confirm('确认取消推送该转播？')">取消推送</button>
              </form>
            <?php endif; ?>
            <button type="button" class="admin-mini-btn" data-edit-target="<?= $panel ?>">编辑</button>
          </div>
        </div>
        <div class="admin-edit-panel" id="<?= $panel ?>">
          <form method="post" action="/admin.php?path=banners&tab=<?= htmlspecialchars($tab) ?>" enctype="multipart/form-data" class="admin-form-grid" data-no-ajax>
            <?= csrf_field() ?><input type="hidden" name="_action" value="update"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="thread_id" value="<?= (int)($b['thread_id'] ?? 0) ?>">
            <input class="input" name="title" value="<?= htmlspecialchars($b['title']) ?>" placeholder="标题" required>
            <input class="input" name="description" value="<?= htmlspecialchars($b['description'] ?? '') ?>" placeholder="描述">
            <input class="input" name="image" value="<?= htmlspecialchars($b['image'] ?? '') ?>" placeholder="图片URL">
            <?php if ($tab === 'home'): ?><input class="input" name="image_file" type="file" accept="image/*"><?php endif; ?>
            <input class="input" name="url" value="<?= htmlspecialchars($b['url'] ?? '') ?>" placeholder="跳转URL">
            <input class="input" name="sort_order" type="number" value="<?= (int)$b['sort_order'] ?>" min="0">
            <div class="admin-actions full-row"><button class="btn" type="submit">保存</button></div>
          </form>
          <div class="admin-actions admin-justify-start">
            <form method="post" action="/admin.php?path=banners&tab=<?= htmlspecialchars($tab) ?>" class="admin-inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="status" value="<?= $tab === 'section' ? 'inactive' : ($b['status']==='active'?'inactive':'active') ?>"><button class="btn btn-light" name="_action" value="status"><?= $tab === 'section' ? '取消推送' : ($b['status']==='active'?'隐藏':'显示') ?></button></form>
            <form method="post" action="/admin.php?path=banners&tab=<?= htmlspecialchars($tab) ?>" class="admin-inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="btn admin-danger-bg" name="_action" value="delete" onclick="return confirm('确认删除该转播？')">删除</button></form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?><div class="admin-empty"><?= $tab === 'home' ? '暂无首页转播' : '暂无板块转播，请先在帖子管理中推送帖子' ?></div><?php endif; ?>
  </div>
</div>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
