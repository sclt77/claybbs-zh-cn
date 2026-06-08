<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>重置密码</title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
.auth-form-wrap{max-width:420px;margin:44px auto 0;padding:0 16px 40px}
.auth-card{background:var(--card-bg,#fff);border:1px solid var(--line-soft,#e2e8f0);border-radius:16px;box-shadow:0 8px 24px rgba(15,23,42,.06);padding:28px 24px}
.auth-card h2{margin:0 0 4px;font-size:20px;font-weight:950;color:var(--text-main,#0f172a)}
.auth-sub{font-size:13px;color:var(--text-soft,#64748b);margin:0 0 16px;line-height:1.55}
.auth-input{display:block;width:100%;height:44px;padding:0 14px;border:1.5px solid var(--line-soft,#e2e8f0);border-radius:10px;background:var(--card-bg,#fff);color:var(--text-main,#0f172a);font-size:14px;outline:0;transition:border-color .18s ease,box-shadow .18s ease;box-sizing:border-box}
.auth-input:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12)}
.auth-input::placeholder{color:#94a3b8}
.auth-btn{display:flex;align-items:center;justify-content:center;width:100%;height:44px;border:0;border-radius:10px;background:linear-gradient(135deg,#2563eb,#0284c7);color:#fff;font-size:15px;font-weight:900;cursor:pointer;transition:opacity .18s ease,transform .18s ease,box-shadow .18s ease}
.auth-btn:hover{opacity:.92;transform:translateY(-.5px);box-shadow:0 6px 18px rgba(2,132,199,.22)}
.auth-btn:active{transform:translateY(0)}
.auth-btn:disabled{opacity:.55;cursor:not-allowed;transform:none;box-shadow:none}
.auth-error{margin:0 0 14px;padding:12px 14px;border-radius:10px;background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c;font-size:13px;font-weight:700;line-height:1.5}
.auth-success{margin:0 0 14px;padding:12px 14px;border-radius:10px;background:#f0fdf4;border:1px solid #86efac;color:#166534;font-size:13px;font-weight:700;line-height:1.5}
.auth-icon{display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,#dbeafe,#e0f2fe);margin-bottom:12px}
.auth-icon svg{width:24px;height:24px;color:#2563eb}
.auth-back{margin-top:18px;text-align:center;font-size:13px}
.auth-back a{color:var(--primary,#0284c7);font-weight:900;text-decoration:none}
.auth-back a:hover{color:var(--primary,#0284c7);opacity:.8}
.form-group{margin-bottom:14px}
.form-group label{display:block;margin-bottom:6px;font-size:12px;font-weight:800;color:var(--text-soft,#475569);text-transform:uppercase;letter-spacing:.03em}
.auth-inline-btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 24px;border:0;border-radius:10px;background:linear-gradient(135deg,#2563eb,#0284c7);color:#fff;font-size:14px;font-weight:900;text-decoration:none;cursor:pointer;transition:opacity .18s ease,transform .18s ease,box-shadow .18s ease}
.auth-inline-btn:hover{opacity:.92;transform:translateY(-.5px);box-shadow:0 6px 18px rgba(2,132,199,.22);color:#fff}
html[data-theme="dark"] .auth-card{background:#111827;border-color:#263244}
html[data-theme="dark"] .auth-input{background:#0f172a;border-color:#334155;color:#e5e7eb}
html[data-theme="dark"] .auth-input:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.18)}
html[data-theme="dark"] .auth-error{background:#450a0a;border-color:#991b1b;color:#fecaca}
html[data-theme="dark"] .auth-success{background:#052e1b;border-color:#166534;color:#86efac}
html[data-theme="dark"] .auth-icon{background:linear-gradient(135deg,#1e3a5f,#172554)}
html[data-theme="dark"] .auth-icon svg{color:#93c5fd}
@media(max-width:768px){.auth-form-wrap{margin:24px auto 0}.auth-card{padding:20px 16px;border-radius:14px}}
</style>
</head>
<body>
<?php require __DIR__ . '/../layouts/topbar.php'; ?>

<div class="auth-form-wrap">
  <div class="auth-card">

<?php if (!empty($success)): ?>
    <div class="auth-icon">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    </div>
    <h2>密码重置成功</h2>
    <div class="auth-success"><?= htmlspecialchars($success) ?></div>
    <div class="auth-back">
      <a href="/index.php?path=login" class="auth-inline-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>
        返回登录
      </a>
    </div>

<?php elseif ($user): ?>
    <div class="auth-icon">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    </div>
    <h2>设置新密码</h2>
    <p class="auth-sub">请输入您的新密码，长度至少 6 位。</p>

    <?php if (!empty($error)): ?>
      <div class="auth-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/index.php?path=reset-password&token=<?= htmlspecialchars((string)($_GET['token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="rp-password">新密码</label>
        <input id="rp-password" class="auth-input" name="password" type="password" placeholder="至少 6 位" required minlength="6" autocomplete="new-password">
      </div>
      <div class="form-group">
        <label for="rp-confirm">确认密码</label>
        <input id="rp-confirm" class="auth-input" name="confirm_password" type="password" placeholder="再次输入新密码" required minlength="6" autocomplete="new-password">
      </div>
      <button class="auth-btn" type="submit">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
        确认重置
      </button>
    </form>

<?php else: ?>
    <div class="auth-icon">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
    </div>
    <h2>链接无效</h2>
    <div class="auth-error"><?= htmlspecialchars($error ?: '重置链接无效或已过期') ?></div>
    <div class="auth-back">
      <a href="/index.php?path=forgot-password" class="auth-inline-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 0 0 4.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 0 1-15.357-2m15.357 2H15"/></svg>
        重新申请
      </a>
    </div>
<?php endif; ?>

    <div class="auth-back">
      <a href="/index.php?path=login">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:3px"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>
        返回登录
      </a>
    </div>
  </div>
</div>
</body>
</html>
