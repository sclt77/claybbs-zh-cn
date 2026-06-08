<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>注册</title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
.register-subtitle{margin:6px 0 0;color:#64748b;font-size:14px;line-height:1.7;}
.register-error,.register-notice{margin:12px 0;padding:12px;border-radius:12px;font-size:14px;line-height:1.6;}
.register-error,.register-notice.is-error{background:#fff0f0;border:1px solid #fca5a5;color:#b91c1c;}
.register-notice.is-ok{background:#ecfdf5;border:1px solid #86efac;color:#047857;}
.register-code-row{display:grid;grid-template-columns:minmax(0,1fr) 112px;gap:8px;align-items:center;min-width:0;}
.register-code-row .input{width:100%;min-width:0;}
.register-code-btn{height:44px;white-space:nowrap;padding:0 10px;font-size:13px;min-width:112px;}
.register-code-btn:disabled{opacity:.68;cursor:not-allowed;}
</style>
</head>
<body>
<?php require __DIR__ . '/../layouts/topbar.php'; ?>

<?php
$settings = $settings ?? (new \App\Models\SettingModel())->all();
$verifyEnabled = isset($verifyEnabled) ? (bool)$verifyEnabled : (($settings['email_verify_required'] ?? '0') === '1');
?>
<?php if (($_GET["verify"] ?? '') === 'sent' && $verifyEnabled): ?>
<div class="form-wrap card" style="text-align:center;">
  <div aria-hidden="true" style="width:54px;height:54px;margin:0 auto 16px;border-radius:18px;background:linear-gradient(135deg,#0284c7,#6366f1);display:grid;place-items:center;color:#fff;box-shadow:0 14px 36px rgba(2,132,199,.24);">
    <svg class="clay-icon" viewBox="0 0 24 24" style="width:28px;height:28px;" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
  </div>
  <h2 style="margin-top:0;">验证邮件已发送</h2>
  <p style="color:#64748b;font-size:14px;line-height:1.8;">我们已向您的邮箱发送了验证链接，<br>请登录邮箱点击链接完成验证后再登录。</p>
  <a href="/index.php?path=login" class="btn" style="display:inline-block;margin-top:16px;text-decoration:none;">前往登录</a>
</div>
<?php else: ?>
<div class="form-wrap card register-card">
  <h2 style="margin-top:0;">注册账号</h2>
  <p class="register-subtitle"><?= $verifyEnabled ? '填写邮箱后先获取验证码，再输入验证码完成注册。' : '填写账号信息即可完成注册，当前未开启邮箱验证。' ?></p>
  <?php if (!empty($error)): ?>
    <div class="register-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <div id="registerCodeNotice" class="register-notice" hidden></div>
  <form method="post" action="/index.php?path=register" style="display:flex;flex-direction:column;gap:12px;margin-top:16px;">
    <?= csrf_field() ?>
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;opacity:0;pointer-events:none;">
    <input type="hidden" name="register_rendered_at" value="<?= time() ?>">
    <input class="input" name="username" placeholder="用户名（字母、数字、下划线）" required pattern="[A-Za-z0-9_]{2,30}" title="2-30位，仅限字母、数字、下划线" value="<?= htmlspecialchars($old['username'] ?? '') ?>">
    <input class="input" name="nickname" placeholder="昵称" required value="<?= htmlspecialchars($old['nickname'] ?? '') ?>">
    <input class="input" id="registerEmailInput" name="email" type="email" placeholder="邮箱" required value="<?= htmlspecialchars($old['email'] ?? '') ?>">
    <?php if ($verifyEnabled): ?>
    <div class="register-code-row">
      <input class="input" name="email_code" inputmode="numeric" autocomplete="one-time-code" placeholder="邮箱验证码" required pattern="[0-9]{6}" maxlength="6">
      <button class="btn register-code-btn" id="registerSendCodeBtn" type="button">获取验证码</button>
    </div>
    <?php endif; ?>
    <input class="input" name="password" type="password" placeholder="密码（至少 6 位）" required minlength="6">
    <button class="btn" type="submit">注册</button>
  </form>
  <p style="text-align:center;margin-top:16px;font-size:13px;color:#888;">已有账号？<a href="/index.php?path=login" style="text-decoration:none">登录</a></p>
</div>
<?php if ($verifyEnabled): ?>
<script>
(function(){
  var emailInput = document.getElementById('registerEmailInput');
  var sendBtn = document.getElementById('registerSendCodeBtn');
  var notice = document.getElementById('registerCodeNotice');
  var timer = null;
  function showNotice(message, ok) {
    notice.hidden = false;
    notice.textContent = message;
    notice.className = 'register-notice ' + (ok ? 'is-ok' : 'is-error');
  }
  function startCountdown(seconds) {
    var left = seconds;
    sendBtn.disabled = true;
    sendBtn.textContent = left + '秒后重发';
    clearInterval(timer);
    timer = setInterval(function(){
      left -= 1;
      if (left <= 0) {
        clearInterval(timer);
        sendBtn.disabled = false;
        sendBtn.textContent = '获取验证码';
        return;
      }
      sendBtn.textContent = left + '秒后重发';
    }, 1000);
  }
  sendBtn && sendBtn.addEventListener('click', function(){
    var email = (emailInput && emailInput.value || '').trim();
    if (!email) {
      showNotice('请先填写邮箱地址', false);
      emailInput && emailInput.focus();
      return;
    }
    sendBtn.disabled = true;
    sendBtn.textContent = '发送中...';
    var body = new URLSearchParams();
    body.set('email', email);
    fetch('/index.php?path=register/send-code', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'Accept': 'application/json'},
      body: body.toString()
    }).then(function(res){
      return res.json().catch(function(){ return {ok:false, message:'验证码发送失败，请稍后再试'}; }).then(function(data){
        data.status = res.status;
        return data;
      });
    }).then(function(data){
      if (data.ok) {
        showNotice(data.message || '验证码已发送，请查收邮箱', true);
        startCountdown(60);
      } else {
        showNotice(data.message || '验证码发送失败，请稍后再试', false);
        sendBtn.disabled = false;
        sendBtn.textContent = '获取验证码';
      }
    }).catch(function(){
      showNotice('网络异常，请稍后再试', false);
      sendBtn.disabled = false;
      sendBtn.textContent = '获取验证码';
    });
  });
})();
</script>
<?php endif; ?>
<?php endif; ?>
</body>
</html>
