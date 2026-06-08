<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?= !empty($announcement['title']) ? htmlspecialchars($announcement['title']) : '公告详情' ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    .notice-show-page{padding:20px 0;}
    .notice-show-wrap{background:var(--card-bg,#fff);border-radius:18px;padding:20px;box-shadow:0 1px 8px rgba(15,23,42,.06);}
    .notice-show-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;}
    .notice-show-title{font-size:24px;font-weight:800;color:var(--text-main,#0f172a);margin:0;line-height:1.4;}
    .notice-show-meta{font-size:13px;color:var(--text-muted,#94a3b8);margin-bottom:16px;}
    .notice-show-image{width:100%;max-height:360px;object-fit:cover;border-radius:14px;margin-bottom:18px;display:block;}
    .notice-show-content{font-size:15px;line-height:1.9;color:var(--text-soft,#334155);word-break:break-word;}
    .notice-show-back{font-size:13px;color:#0284c7;text-decoration:none;}
    .notice-empty{padding:60px 20px;text-align:center;color:var(--text-muted,#94a3b8);}
    .notice-link{display:inline-flex;align-items:center;gap:8px;margin-top:18px;padding:10px 14px;background:#eff6ff;color:#1d4ed8;text-decoration:none;border-radius:10px;font-size:14px;font-weight:600;}
  </style>
</head>
<body>
<?php require __DIR__ . '/../layouts/topbar.php'; ?>
<div class="container notice-show-page">
  <div class="notice-show-wrap">
    <?php if (!empty($announcement)): ?>
      <div class="notice-show-head">
        <h1 class="notice-show-title"><?php echo !empty($announcement['is_pinned']) ? '【置顶】' : ''; ?><?= htmlspecialchars($announcement['title']) ?></h1>
        <a href="/index.php?path=announcements" class="notice-show-back">← 返回公告列表</a>
      </div>
      <div class="notice-show-meta">发布时间：<?= htmlspecialchars(date('Y-m-d H:i', strtotime($announcement['created_at'] ?? 'now'))) ?></div>

      <?php if (!empty($announcement['image'])): ?>
        <img class="notice-show-image" src="<?= htmlspecialchars($announcement['image']) ?>" alt="<?= htmlspecialchars($announcement['title']) ?>">
      <?php endif; ?>

      <div class="notice-show-content">
        <?php if (!empty($announcement['content'])): ?>
          <?= nl2br(htmlspecialchars($announcement['content'])) ?>
        <?php else: ?>
          暂无详细内容。
        <?php endif; ?>
      </div>

      <?php if (!empty($announcement['url'])): ?>
        <a class="notice-link" href="<?= htmlspecialchars($announcement['url']) ?>">查看相关链接 →</a>
      <?php endif; ?>
    <?php else: ?>
      <div class="notice-empty">
        <div style="font-size:18px;font-weight:700;color:var(--text-main,#0f172a);margin-bottom:8px;">公告不存在或已下线</div>
        <a href="/index.php?path=announcements" class="notice-show-back">返回公告列表</a>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../../layouts/theme-toggle.php'; ?>
<?php require __DIR__ . '/../layouts/bottom-nav.php'; ?>
</body>
</html>
