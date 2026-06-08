<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>账号设置</title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
.settings-page{min-height:100vh;padding:18px 16px 110px;background:var(--bg-main,#f6f8fb)}
.settings-shell{max-width:860px;margin:0 auto}.settings-top{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}.settings-title{margin:0;font-size:24px;font-weight:950;letter-spacing:-.03em;color:var(--text-main,#0f172a)}.settings-sub{margin-top:5px;color:var(--text-soft,#64748b);font-size:13px}.settings-back{height:34px;border-radius:999px;padding:0 12px;display:inline-flex;align-items:center;text-decoration:none;background:var(--card-bg,#fff);border:1px solid var(--line-soft,#e2e8f0);color:var(--text-soft,#64748b);font-size:13px;font-weight:900}.settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.settings-section{background:var(--card-bg,#fff);border:1px solid rgba(226,232,240,.9);border-radius:20px;box-shadow:0 10px 28px rgba(15,23,42,.045);overflow:hidden}.settings-section-head{padding:14px 16px;border-bottom:1px solid var(--line-soft,#e2e8f0)}.settings-section-title{font-size:15px;font-weight:950;color:var(--text-main,#0f172a)}.settings-section-desc{font-size:12px;color:var(--text-soft,#64748b);margin-top:4px;line-height:1.55}.settings-items{display:grid}.settings-item{display:flex;align-items:center;gap:12px;padding:14px 16px;text-decoration:none;color:inherit;border-bottom:1px solid var(--line-soft,#e2e8f0);transition:background .15s}.settings-item:last-child{border-bottom:0}.settings-item:hover{background:var(--input-bg,#f8fafc)}.settings-mark{width:8px;height:8px;border-radius:50%;background:var(--dot,#0284c7);box-shadow:0 0 0 4px color-mix(in srgb,var(--dot,#0284c7) 12%,transparent);flex:0 0 auto}.settings-main{min-width:0;flex:1}.settings-name{font-size:14px;font-weight:950;color:var(--text-main,#0f172a)}.settings-desc{font-size:12px;color:var(--text-soft,#64748b);margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.settings-go{color:var(--text-muted,#94a3b8);font-size:18px}.settings-danger .settings-name{color:#ef4444}.settings-danger .settings-mark{--dot:#ef4444}.settings-tip{margin-top:12px;color:var(--text-muted,#94a3b8);font-size:12px;line-height:1.7;text-align:center}html[data-theme="dark"] .settings-section,html[data-theme="dark"] .settings-back{background:#111827;border-color:#263244;box-shadow:0 10px 30px rgba(0,0,0,.25)}html[data-theme="dark"] .settings-item:hover{background:#0f172a}@media(max-width:760px){.settings-page{padding:14px 12px 104px}.settings-grid{grid-template-columns:1fr}.settings-top{align-items:flex-start}.settings-title{font-size:22px}.settings-section{border-radius:18px}.settings-desc{white-space:normal}}
</style>
</head>
<body>
<?php require __DIR__ . '/../layouts/topbar.php'; ?>
<main class="settings-page">
  <div class="settings-shell">
    <div class="settings-top">
      <div><h1 class="settings-title">账号设置</h1><div class="settings-sub">管理资料、隐私、通知与账号相关选项</div></div>
      <a class="settings-back" href="/index.php?path=me">返回个人中心</a>
    </div>

    <div class="settings-grid">
      <section class="settings-section">
        <div class="settings-section-head"><div class="settings-section-title">账号资料</div><div class="settings-section-desc">这些内容会展示在你的个人主页和帖子里。</div></div>
        <div class="settings-items">
          <a class="settings-item" href="/index.php?path=me/edit"><span class="settings-mark" style="--dot:#0284c7"></span><span class="settings-main"><span class="settings-name">编辑资料</span><span class="settings-desc">昵称、简介、头像、背景图与密码</span></span><span class="settings-go">›</span></a>
          <a class="settings-item" href="/index.php?path=verification/apply"><span class="settings-mark" style="--dot:#b51a00"></span><span class="settings-main"><span class="settings-name">认证管理</span><span class="settings-desc">申请、查看或撤销认证</span></span><span class="settings-go">›</span></a>
          <a class="settings-item" href="/index.php?path=tasks"><span class="settings-mark" style="--dot:#64748b"></span><span class="settings-main"><span class="settings-name">任务中心</span><span class="settings-desc">每日任务、新手任务与奖励领取</span></span><span class="settings-go">›</span></a>
          <a class="settings-item" href="/index.php?path=growth"><span class="settings-mark" style="--dot:#4f46e5"></span><span class="settings-main"><span class="settings-name">成长中心</span><span class="settings-desc">等级、经验与成长记录</span></span><span class="settings-go">›</span></a>
          <a class="settings-item" href="/index.php?path=oauth/bindings"><span class="settings-mark" style="--dot:#0ea5e9"></span><span class="settings-main"><span class="settings-name">登录方式</span><span class="settings-desc">绑定 QQ、GitHub、微信或彩虹聚合登录</span></span><span class="settings-go">›</span></a>
          <a class="settings-item" href="/index.php?path=settings/devices"><span class="settings-mark" style="--dot:#14b8a6"></span><span class="settings-main"><span class="settings-name">登录设备</span><span class="settings-desc">查看设备、IP，并退出其他设备</span></span><span class="settings-go">›</span></a>
        </div>
      </section>

      <section class="settings-section">
        <div class="settings-section-head"><div class="settings-section-title">权限与提醒</div><div class="settings-section-desc">控制别人如何与你互动，以及你接收哪些提醒。</div></div>
        <div class="settings-items">
          <a class="settings-item" href="/index.php?path=settings/privacy"><span class="settings-mark" style="--dot:#7c3aed"></span><span class="settings-main"><span class="settings-name">隐私设置</span><span class="settings-desc">关注权限、关注列表、粉丝列表可见性</span></span><span class="settings-go">›</span></a>
          <a class="settings-item" href="/index.php?path=notification-settings"><span class="settings-mark" style="--dot:#f97316"></span><span class="settings-main"><span class="settings-name">通知设置</span><span class="settings-desc">回复、提及、粉丝与关注发帖提醒</span></span><span class="settings-go">›</span></a>
        </div>
      </section>

      <section class="settings-section">
        <div class="settings-section-head"><div class="settings-section-title">资产</div><div class="settings-section-desc">查看账户资金、冻结中金额与交易流水。</div></div>
        <div class="settings-items">
          <a class="settings-item" href="/index.php?path=wallet"><span class="settings-mark" style="--dot:#16a34a"></span><span class="settings-main"><span class="settings-name">我的钱包</span><span class="settings-desc">余额、流水、冻结中资金</span></span><span class="settings-go">›</span></a>
        </div>
      </section>

      <section class="settings-section">
        <div class="settings-section-head"><div class="settings-section-title">会话</div><div class="settings-section-desc">当前登录状态与账号会话。</div></div>
        <div class="settings-items">
          <a class="settings-item settings-danger" href="/index.php?path=logout"><span class="settings-mark"></span><span class="settings-main"><span class="settings-name">退出登录</span><span class="settings-desc">退出当前账号</span></span><span class="settings-go">›</span></a>
        </div>
      </section>
    </div>
    <div class="settings-tip">设置项会随着论坛功能增加继续扩展。</div>
  </div>
</main>
<?php require __DIR__ . '/../../layouts/theme-toggle.php'; ?>
<?php require __DIR__ . '/../layouts/bottom-nav.php'; ?>
</body>
</html>
