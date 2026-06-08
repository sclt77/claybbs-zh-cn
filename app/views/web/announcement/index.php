<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>公告列表</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    .notice-page{padding:20px 0;}
    .notice-wrap{background:var(--card-bg,#fff);border-radius:16px;padding:18px;box-shadow:0 1px 8px rgba(15,23,42,.06);}
    .notice-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
    .notice-head h1{font-size:20px;margin:0;color:var(--text-main,#0f172a);}
    .notice-back{font-size:13px;color:#0284c7;text-decoration:none;}
    .notice-list{display:flex;flex-direction:column;gap:12px;}
    .notice-item{display:block;padding:14px 16px;background:var(--input-bg,#f8fafc);border-radius:12px;text-decoration:none;color:var(--text-soft,#334155);border:1px solid var(--line-soft,#eef2f7);}
    .notice-item:hover{background:var(--line-soft,#f1f5f9);}
    .notice-title{font-size:15px;font-weight:700;color:var(--text-main,#0f172a);margin-bottom:6px;}
    .notice-image{width:100%;max-height:240px;object-fit:cover;border-radius:10px;margin:8px 0 10px;display:block;}
    .notice-text{font-size:13px;color:var(--text-soft,#64748b);line-height:1.7;}
    .notice-meta{margin-top:8px;font-size:12px;color:var(--text-muted,#94a3b8);}
    .notice-empty{padding:48px 20px;text-align:center;color:var(--text-muted,#94a3b8);}
  </style>
</head>
<body>
<?php require __DIR__ . '/../layouts/topbar.php'; ?>
<div class="container notice-page">
  <div class="notice-wrap">
    <div class="notice-head">
      <h1>公告列表</h1>
      <a href="/index.php" class="notice-back">← 返回首页</a>
    </div>

    <?php if (!empty($announcements)): ?>
      <div class="notice-list">
        <?php foreach ($announcements as $notice): ?>
          <?php $noticeUrl = trim((string)($notice['url'] ?? '')); ?>
          <?php $noticeLink = $noticeUrl !== '' ? $noticeUrl : ('/index.php?path=announcement&id=' . (int)$notice['id']); ?>
          <a class="notice-item" href="<?= htmlspecialchars($noticeLink) ?>">
            <div class="notice-title"><?php echo !empty($notice['is_pinned']) ? '【置顶】' : ''; ?><?php echo htmlspecialchars($notice['title']); ?></div>
            <?php if (!empty($notice['image'])): ?>
              <img class="notice-image" src="<?= htmlspecialchars($notice['image']) ?>" alt="<?= htmlspecialchars($notice['title']) ?>">
            <?php endif; ?>
            <?php if (!empty($notice['content'])): ?>
              <div class="notice-text"><?= nl2br(htmlspecialchars($notice['content'])) ?></div>
            <?php else: ?>
              <div class="notice-text">暂无详细内容</div>
            <?php endif; ?>
            <div class="notice-meta">发布时间：<?= htmlspecialchars(date('Y-m-d H:i', strtotime($notice['created_at'] ?? 'now'))) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="notice-empty">暂时还没有公告</div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../../layouts/theme-toggle.php'; ?>
<?php require __DIR__ . '/../layouts/bottom-nav.php'; ?>
</body>
</html>
