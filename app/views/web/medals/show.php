<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>勋章详情 - ClayBBS</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    body{background:#f6f8fb;color:var(--text-main,#0f172a)}
    .medal-page{min-height:100vh;padding:18px 16px 112px}.medal-page a{text-decoration:none}
    .medal-shell{max-width:980px;margin:0 auto;display:grid;gap:16px}
    .medal-detail-card{display:grid;grid-template-columns:132px minmax(0,1fr);gap:22px;align-items:center;background:#fff;border:1px solid #e2e8f0;border-radius:26px;padding:24px;box-shadow:0 14px 38px rgba(15,23,42,.06)}
    .medal-detail-icon{width:132px;height:132px;border-radius:28px;background:#f8fafc;border:1px solid #e2e8f0;display:grid;place-items:center;box-shadow:inset 0 1px 0 rgba(255,255,255,.9)}
    .medal-detail-icon img{width:104px;height:104px;object-fit:contain;filter:drop-shadow(0 10px 18px rgba(15,23,42,.12))}
    .medal-detail-info{min-width:0}.medal-detail-title{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px}.medal-detail-title h1{margin:0;font-size:32px;line-height:1.12;letter-spacing:-.045em}.medal-level{display:inline-flex;align-items:center;height:26px;padding:0 10px;border-radius:999px;background:#f8fafc;border:1px solid #e2e8f0;color:#475569;font-size:12px;font-weight:950}.medal-detail-desc{margin:0;color:#475569;font-size:15px;line-height:1.8;max-width:680px}.medal-owned-state{display:inline-flex;align-items:center;margin-top:14px;border-radius:999px;padding:8px 12px;font-size:13px;font-weight:950;border:1px solid #dbeafe;background:#eff6ff;color:#1d4ed8}.medal-owned-state.not-owned{border-color:#e2e8f0;background:#f8fafc;color:#64748b}
    .medal-panel{background:#fff;border:1px solid #e2e8f0;border-radius:24px;padding:20px;box-shadow:0 12px 34px rgba(15,23,42,.045)}.medal-panel-head{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-bottom:14px}.medal-panel h2{margin:0;font-size:20px;letter-spacing:-.035em}.medal-panel p{margin:5px 0 0;color:#64748b;font-size:13px;line-height:1.65}.medal-rule{border-radius:18px;background:#f8fafc;border:1px solid #e2e8f0;padding:13px 14px;color:#475569;font-weight:850;line-height:1.7}
    .medal-users{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:10px}.medal-user{display:flex;align-items:center;gap:10px;border:1px solid #e2e8f0;border-radius:18px;background:#fff;padding:10px;color:#0f172a;transition:.16s ease}.medal-user:hover{transform:translateY(-1px);box-shadow:0 10px 24px rgba(15,23,42,.06);border-color:#cbd5e1}.medal-user-avatar{width:38px;height:38px;border-radius:999px;object-fit:cover}.medal-user span{display:block;font-size:13px;font-weight:950;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.medal-user small{display:block;margin-top:2px;color:#94a3b8;font-size:11px;font-weight:800}.medal-user div{min-width:0}.medal-empty{grid-column:1/-1;border:1px dashed #cbd5e1;border-radius:18px;padding:22px;text-align:center;color:#94a3b8;font-weight:900;background:#f8fafc}
    html[data-theme="dark"] body{background:#0b1120;color:#e5e7eb}html[data-theme="dark"] .medal-detail-card,html[data-theme="dark"] .medal-panel,html[data-theme="dark"] .medal-user{background:#111827;border-color:#263244;color:#e5e7eb}html[data-theme="dark"] .medal-detail-icon,html[data-theme="dark"] .medal-level,html[data-theme="dark"] .medal-rule{background:#0f172a;border-color:#263244}html[data-theme="dark"] .medal-detail-desc,html[data-theme="dark"] .medal-panel p,html[data-theme="dark"] .medal-rule{color:#94a3b8}
    @media(max-width:640px){.medal-page{padding:12px 10px 98px}.medal-detail-card{grid-template-columns:1fr;text-align:center;padding:20px;border-radius:22px}.medal-detail-icon{margin:0 auto;width:118px;height:118px;border-radius:24px}.medal-detail-icon img{width:94px;height:94px}.medal-detail-title{justify-content:center}.medal-detail-title h1{font-size:28px}.medal-users{grid-template-columns:1fr}}
  </style>
  <?= function_exists('user_avatar_verify_styles') ? user_avatar_verify_styles() : '' ?>
</head>
<body>
<?php require dirname(__DIR__) . '/layouts/topbar.php'; ?>
<main class="medal-page medal-detail-page">
  <div class="medal-shell">
    <section class="medal-detail-card" style="--medal-color:<?= htmlspecialchars((string)$medal['color']) ?>">
      <div class="medal-detail-icon"><?php if(!empty($medal['icon'])): ?><img src="<?= htmlspecialchars((string)$medal['icon']) ?>" alt="" loading="lazy"><?php endif; ?></div>
      <div class="medal-detail-info">
        <div class="medal-detail-title"><h1><?= htmlspecialchars((string)$medal['name']) ?></h1><span class="medal-level"><?= htmlspecialchars((string)($medal['level'] ?? 'standard')) ?></span></div>
        <p class="medal-detail-desc"><?= htmlspecialchars((string)($medal['description'] ?? '')) ?></p>
        <div class="medal-owned-state <?= isset($owned[(int)$medal['id']]) ? '' : 'not-owned' ?>"><?= isset($owned[(int)$medal['id']]) ? '已获得' : '尚未获得' ?></div>
      </div>
    </section>
    <section class="medal-panel">
      <div class="medal-panel-head"><div><h2>获取方式</h2><p>当前勋章的获取条件</p></div></div>
      <div class="medal-rule"><?= htmlspecialchars((string)($progress['label'] ?? '由管理员手动发放')) ?></div>
    </section>
    <section class="medal-panel">
      <div class="medal-panel-head"><div><h2>最近获得的用户</h2><p>最新拥有该勋章的成员</p></div></div>
      <div class="medal-users">
        <?php foreach ($recentUsers as $u): ?>
          <a class="medal-user" href="/index.php?path=user&id=<?= (int)$u['id'] ?>">
            <?= user_avatar_html($u, 'medal-user-avatar', 38) ?>
            <div><span><?= htmlspecialchars((string)($u['nickname'] ?: $u['username'])) ?></span><small><?= htmlspecialchars((string)($u['public_id'] ?? '')) ?></small></div>
          </a>
        <?php endforeach; ?>
        <?php if (!$recentUsers): ?><div class="medal-empty">暂无用户获得该勋章</div><?php endif; ?>
      </div>
    </section>
  </div>
</main>
<?php require dirname(__DIR__, 2) . '/layouts/theme-toggle.php'; ?>
<?php require dirname(__DIR__) . '/layouts/bottom-nav.php'; ?>
</body>
</html>
