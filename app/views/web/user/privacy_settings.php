<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>隐私设置</title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
.privacy-page{min-height:100vh;padding:18px 16px 110px;background:var(--bg-main,#f6f8fb)}.privacy-shell{max-width:760px;margin:0 auto}.privacy-top{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}.privacy-title{margin:0;font-size:24px;font-weight:950;letter-spacing:-.03em;color:var(--text-main,#0f172a)}.privacy-sub{margin-top:5px;color:var(--text-soft,#64748b);font-size:13px}.privacy-back{height:34px;border-radius:999px;padding:0 12px;display:inline-flex;align-items:center;text-decoration:none;background:var(--card-bg,#fff);border:1px solid var(--line-soft,#e2e8f0);color:var(--text-soft,#64748b);font-size:13px;font-weight:900}.privacy-flash{margin:0 0 12px;padding:10px 12px;border-radius:14px;background:#dcfce7;color:#15803d;font-size:13px;font-weight:900}.privacy-card{background:var(--card-bg,#fff);border:1px solid rgba(226,232,240,.9);border-radius:20px;box-shadow:0 10px 28px rgba(15,23,42,.045);overflow:hidden}.privacy-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:17px 18px;border-bottom:1px solid var(--line-soft,#e2e8f0)}.privacy-row:last-child{border-bottom:0}.privacy-name{font-size:15px;font-weight:950;color:var(--text-main,#0f172a)}.privacy-desc{font-size:12px;color:var(--text-soft,#64748b);margin-top:4px;line-height:1.6}.privacy-state{font-size:12px;color:var(--text-muted,#94a3b8);margin-top:5px}.switch{position:relative;width:46px;height:26px;flex:0 0 46px}.switch input{display:none}.switch span{position:absolute;inset:0;border-radius:999px;background:#cbd5e1;transition:.18s}.switch span:before{content:"";position:absolute;width:20px;height:20px;left:3px;top:3px;border-radius:50%;background:#fff;transition:.18s;box-shadow:0 2px 8px rgba(15,23,42,.18)}.switch input:checked+span{background:var(--primary,#0284c7)}.switch input:checked+span:before{transform:translateX(20px)}.privacy-actions{display:flex;justify-content:flex-end;padding:16px 18px;background:var(--input-bg,#f8fafc)}.privacy-actions .btn{border-radius:999px;padding:0 18px;min-height:36px}html[data-theme="dark"] .privacy-card,html[data-theme="dark"] .privacy-back{background:#111827;border-color:#263244;box-shadow:0 10px 30px rgba(0,0,0,.25)}html[data-theme="dark"] .privacy-actions{background:#0f172a}@media(max-width:640px){.privacy-page{padding:14px 12px 104px}.privacy-top{align-items:flex-start}.privacy-title{font-size:22px}.privacy-card{border-radius:18px}.privacy-row{padding:16px}.privacy-actions .btn{width:100%}}
</style>
</head>
<body>
<?php require __DIR__ . '/../layouts/topbar.php'; ?>
<main class="privacy-page"><div class="privacy-shell">
  <div class="privacy-top"><div><h1 class="privacy-title">隐私设置</h1><div class="privacy-sub">控制关注关系和社交列表的可见性</div></div><a class="privacy-back" href="/index.php?path=settings">返回设置</a></div>
  <?php if(!empty($flashSuccess)): ?><div class="privacy-flash"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
  <form class="privacy-card" method="post" action="/index.php?path=settings/privacy"><?= csrf_field() ?>
    <label class="privacy-row"><span><span class="privacy-name">不可关注我</span><span class="privacy-desc">开启后，其他用户不能再关注你；已关注的人不会自动取消。</span><span class="privacy-state">影响：公开主页关注按钮、关注接口。</span></span><span class="switch"><input type="checkbox" name="disallow_follow" value="1" <?= !empty($settings['disallow_follow'])?'checked':'' ?>><span></span></span></label>
    <label class="privacy-row"><span><span class="privacy-name">隐藏我的关注</span><span class="privacy-desc">别人无法查看你关注了谁；你自己仍可查看。</span><span class="privacy-state">影响：关注数、关注列表。</span></span><span class="switch"><input type="checkbox" name="hide_following" value="1" <?= !empty($settings['hide_following'])?'checked':'' ?>><span></span></span></label>
    <label class="privacy-row"><span><span class="privacy-name">隐藏我的粉丝</span><span class="privacy-desc">别人无法查看谁关注了你；你自己仍可查看。</span><span class="privacy-state">影响：粉丝数、粉丝列表。</span></span><span class="switch"><input type="checkbox" name="hide_followers" value="1" <?= !empty($settings['hide_followers'])?'checked':'' ?>><span></span></span></label>
    <div class="privacy-actions"><button class="btn btn-primary" type="submit">保存隐私设置</button></div>
  </form>
</div></main>
<?php require __DIR__ . '/../../layouts/theme-toggle.php'; ?>
<?php require __DIR__ . '/../layouts/bottom-nav.php'; ?>
</body>
</html>
