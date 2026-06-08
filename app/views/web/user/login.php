<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>登录</title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
.login-error{margin:12px 0;padding:12px;border-radius:12px;background:#fff0f0;border:1px solid #fca5a5;color:#b91c1c;}
</style>
</head>
<body>
<?php require __DIR__ . '/../layouts/topbar.php'; ?>

<div class="form-wrap card">
  <h2 style="margin-top:0;">登录账号</h2>
  <?php if (!empty($error)): ?>
    <div class="login-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="post" action="/index.php?path=login" style="display:flex;flex-direction:column;gap:12px;margin-top:16px;">
    <?= csrf_field() ?>
    <input class="input" name="account" placeholder="邮箱 / 用户名" required>
    <input class="input" name="password" type="password" placeholder="请输入密码" required>
    <button class="btn" type="submit">登录</button>
  </form>
  <?php if (!empty($oauthProviders)): ?>
    <div style="margin:16px 0 0;border-top:1px solid var(--line-soft,#e2e8f0);padding-top:14px;display:grid;gap:8px;">
      <div style="font-size:12px;color:var(--text-soft,#64748b);font-weight:800;text-align:center;">第三方登录</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
        <?php foreach ($oauthProviders as $provider): ?>
          <a class="btn btn-light" style="height:32px;padding:0 12px;border-radius:999px;text-decoration:none;font-size:12px;" href="/index.php?path=oauth/redirect&provider=<?= urlencode($provider['key']) ?>"><?= htmlspecialchars($provider['name']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <p style="text-align:center;margin-top:16px;font-size:13px;color:#888;"><a href="/index.php?path=forgot-password" style="text-decoration:none">忘记密码？</a> | 没有账号？<a href="/index.php?path=register" style="text-decoration:none">注册</a></p>
  </div>
</body>
</html>
