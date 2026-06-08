<?php
$account = $account ?? '';
$error = $error ?? '';
$success = $success ?? '';
$token = $token ?? '';
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>找回密码</title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
/* 找回密码页面 — premium 卡片表单，与登录页风格一致 */
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
    <div class="auth-icon">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    </div>
    <h2>找回密码</h2>
    <p class="auth-sub">输入您的注册邮箱，我们将向您发送密码重置链接。</p>

    <?php if (!empty($error)): ?>
      <div class="auth-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="auth-success"><?= htmlspecialchars($success) ?></div>
    <?php else: ?>
      <form method="post" action="/index.php?path=forgot-password">
        <?= csrf_field() ?>
        <div class="form-group">
          <label for="fp-email">邮箱地址</label>
          <input id="fp-email" class="auth-input" type="email" name="email" placeholder="请输入您的注册邮箱" required autocomplete="email">
        </div>
        <button class="auth-btn" type="submit">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          发送重置邮件
        </button>
      </form>
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
