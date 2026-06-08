<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>邮箱验证</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php require __DIR__ . '/../layouts/topbar.php'; ?>
<div class="form-wrap card" style="text-align:center;padding:40px 24px;">
  <?php if (!empty($success)): ?>
    <div style="width:48px;height:48px;margin:0 auto 16px;color:#16a34a;display:flex;align-items:center;justify-content:center;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:48px;height:48px;"><path d="M20 6 9 17l-5-5"></path></svg></div>
    <h2 style="margin:0 0 8px;color:#166534;"><?= htmlspecialchars($success) ?></h2>
    <p style="color:#64748b;">你现在可以正常使用论坛的所有功能。</p>
    <a href="/index.php?path=me" class="btn" style="margin-top:16px;display:inline-block;">进入个人中心</a>
  <?php else: ?>
    <div style="width:48px;height:48px;margin:0 auto 16px;color:#dc2626;display:flex;align-items:center;justify-content:center;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:48px;height:48px;"><circle cx="12" cy="12" r="9"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg></div>
    <h2 style="margin:0 0 8px;color:#b91c1c;"><?= htmlspecialchars($error ?? '验证失败') ?></h2>
    <p style="color:#64748b;">请确认链接是否正确，或重新注册。</p>
    <a href="/index.php" class="btn" style="margin-top:16px;display:inline-block;">返回首页</a>
  <?php endif; ?>
</div>
</body>
</html>
