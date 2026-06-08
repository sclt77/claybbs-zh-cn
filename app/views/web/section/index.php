<?php
$section    = $section    ?? null;
$threads    = $threads    ?? [];
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$moderators = $moderators ?? [];
$filter = in_array((string)($filter ?? 'all'), ['all', 'hot', 'recommended', 'featured'], true) ? (string)$filter : 'all';
$filterLabels = ['all' => '全部帖子', 'hot' => '热门', 'recommended' => '推荐', 'featured' => '精华'];
$filterUrl = function (string $value) use ($section): string {
    $url = '/index.php?path=section&id=' . (int)($section['id'] ?? 0);
    if ($value !== 'all') $url .= '&filter=' . urlencode($value);
    return $url;
};
$pageUrl = function (int $pageNo) use ($section, $filter): string {
    $url = '/index.php?path=section&id=' . (int)($section['id'] ?? 0);
    if ($filter !== 'all') $url .= '&filter=' . urlencode($filter);
    return $url . '&page=' . $pageNo;
};
$sectionIcon = trim((string)($section['icon'] ?? ''));
$sectionInitial = $section ? mb_substr((string)($section['name'] ?? '板'), 0, 1) : '板';
$isSectionIconImage = $sectionIcon !== '' && (preg_match('/^(https?:)?\/\//i', $sectionIcon) || str_starts_with($sectionIcon, '/') || preg_match('/\.(png|jpe?g|gif|webp|svg)(\?.*)?$/i', $sectionIcon));
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($section['name'] ?? '板块') ?> - 论坛</title>
<link rel="stylesheet" href="/assets/css/style.css">
<?= user_avatar_verify_styles() ?>
<?= user_level_badge_styles() ?>
<?= top_thread_strip_styles() ?>
<style>
body{background:var(--bg-main,#f6f8fb)}.plate-page{padding:18px 0 104px}.plate-layout{display:grid;grid-template-columns:minmax(0,1fr) 292px;gap:18px;align-items:start}.soft-panel{background:var(--card-bg,#fff);border:1px solid rgba(226,232,240,.86);border-radius:18px;box-shadow:0 10px 30px rgba(15,23,42,.045);overflow:hidden}.plate-hero{position:relative;overflow:hidden;padding:26px;background:linear-gradient(135deg,#eef7ff,#ffffff 52%,#f4f7ff);border-color:#dbeafe}.section-follow-corner{position:absolute;right:18px;top:18px;z-index:2}.section-follow-corner .section-follow-btn{height:34px;font-size:12px;padding:0 12px;background:rgba(255,255,255,.78);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}.section-follow-form{margin:0;display:inline-flex}.plate-actions-row .section-follow-btn{height:42px;border:1px solid rgba(2,132,199,.18);border-radius:999px;background:rgba(255,255,255,.86);color:var(--primary,#0284c7);padding:0 16px;font-size:13px;font-weight:950;display:inline-flex;align-items:center;justify-content:center;gap:7px;cursor:pointer;box-shadow:0 8px 22px rgba(15,23,42,.045);transition:transform .16s ease,box-shadow .16s ease,background .16s ease}.plate-actions-row .section-follow-btn svg{width:15px;height:15px;stroke-width:2.25}.plate-actions-row .section-follow-btn:hover{transform:translateY(-1px);box-shadow:0 12px 26px rgba(2,132,199,.13);background:#fff}.plate-actions-row .section-follow-btn.is-following{background:linear-gradient(135deg,rgba(2,132,199,.12),rgba(14,165,233,.08));border-color:rgba(2,132,199,.28);color:#0369a1}
.plate-hero::before{content:"";position:absolute;right:-70px;top:-90px;width:240px;height:240px;border-radius:999px;background:rgba(2,132,199,.12)}.plate-hero::after{content:"";position:absolute;left:32%;bottom:-110px;width:260px;height:260px;border-radius:999px;background:rgba(99,102,241,.08);pointer-events:none}.plate-hero-inner{position:relative;z-index:1;display:grid;grid-template-columns:minmax(0,1fr) 150px;gap:22px;align-items:center}.plate-main-info{min-width:0}.plate-kicker{display:inline-flex;align-items:center;border-radius:999px;background:rgba(2,132,199,.10);color:var(--primary,#0284c7);font-size:12px;font-weight:950;padding:6px 10px;margin-bottom:14px}.plate-title{margin:0;font-size:32px;line-height:1.16;letter-spacing:-.045em;color:var(--text-main,#0f172a)}.plate-desc{margin:12px 0 0;color:var(--text-soft,#64748b);font-size:15px;line-height:1.75;max-width:620px}.plate-meta-line{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:18px}.plate-meta-line span{display:inline-flex;align-items:center;gap:5px;border-radius:999px;background:rgba(255,255,255,.76);border:1px solid rgba(226,232,240,.9);color:var(--text-soft,#64748b);font-size:13px;font-weight:850;padding:7px 10px}.plate-visual{justify-self:end;width:140px;height:140px;border-radius:32px;background:linear-gradient(135deg,#0284c7,#6366f1);display:grid;place-items:center;color:#fff;font-size:52px;font-weight:950;box-shadow:0 18px 44px rgba(2,132,199,.20);overflow:hidden}.plate-visual img{width:100%;height:100%;object-fit:cover;display:block}.plate-visual.is-image{background:#fff;padding:10px}.plate-visual.is-image img{border-radius:24px}.plate-visual.is-text{background:linear-gradient(135deg,#0284c7,#6366f1)}.plate-actions-row{position:relative;z-index:1;display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px}.section-follow-form{display:inline-flex}.section-follow-btn{height:42px;border-radius:999px;border:1px solid rgba(2,132,199,.22);background:rgba(255,255,255,.75);color:var(--primary,#0284c7);padding:0 14px;font-size:14px;font-weight:950;cursor:pointer}.section-follow-btn.is-following{background:var(--primary,#0284c7);border-color:var(--primary,#0284c7);color:#fff}.publish-pill,.soft-link-pill{height:42px;border-radius:999px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;padding:0 15px;font-size:14px;font-weight:900}.publish-pill{background:var(--primary,#0284c7);color:#fff;box-shadow:0 10px 24px rgba(2,132,199,.20)}.soft-link-pill{background:rgba(255,255,255,.75);border:1px solid rgba(226,232,240,.9);color:var(--text-soft,#64748b)}.publish-pill svg,.soft-link-pill svg{width:18px;height:18px}.plate-head,.plate-stats,.plate-icon,.plate-actions,.plate-flame,.plate-top-actions,.plate-logo-box,.plate-logo-letter{display:none}.moderator-pill{height:42px;border-radius:999px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;padding:0 13px;font-size:14px;font-weight:900;background:rgba(255,255,255,.75);border:1px solid rgba(226,232,240,.9);color:var(--text-soft,#64748b);cursor:pointer}.moderator-compact-card{position:relative;z-index:1;margin-top:14px;display:inline-flex;align-items:center;gap:10px;max-width:min(100%,360px);padding:8px 10px 8px 12px;border:1px solid rgba(226,232,240,.86);border-radius:16px;background:rgba(255,255,255,.58);box-shadow:0 8px 24px rgba(15,23,42,.035);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}.moderator-compact-label{flex:0 0 auto;color:var(--text-muted,#94a3b8);font-size:12px;font-weight:950}.moderator-compact-btn{min-width:0;height:34px;border:0;border-radius:12px;background:rgba(248,250,252,.92);color:var(--text-main,#0f172a);display:inline-flex;align-items:center;gap:8px;padding:0 9px 0 6px;cursor:pointer;font-size:13px;font-weight:950;box-shadow:inset 0 0 0 1px rgba(226,232,240,.86);transition:background .16s ease,transform .16s ease,color .16s ease}.moderator-compact-btn:hover{background:#fff;color:var(--primary,#0284c7);transform:translateY(-1px)}.moderator-compact-text{min-width:0;max-width:190px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.moderator-compact-arrow{flex:0 0 auto;width:14px;height:14px;color:var(--text-muted,#94a3b8)}.moderator-mini-avatars{display:flex;align-items:center}.moderator-mini-avatars .avatar-verify-wrap{margin-left:-7px}.moderator-mini-avatars .avatar-verify-wrap:first-child{margin-left:0}.moderator-mini-avatars .avatar-verify-badge{border-color:rgba(255,255,255,.95)!important}.moderator-mini-avatars .mini-avatar{width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,#0284c7,#6366f1);display:grid;place-items:center;color:#fff;font-size:10px;font-weight:950;border:2px solid rgba(255,255,255,.92);overflow:hidden}.mod-modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.48);z-index:900;display:none;align-items:center;justify-content:center;padding:18px}.mod-modal-backdrop.is-open{display:flex}.mod-modal{width:min(560px,100%);background:#24262b;color:#fff;border-radius:18px;padding:22px 22px 10px;box-shadow:0 28px 80px rgba(0,0,0,.34)}.mod-modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}.mod-modal-title{display:flex;align-items:center;gap:10px;font-size:20px;font-weight:950}.mod-modal-title svg{width:24px;height:24px;color:#60a5fa}.mod-close{border:0;background:transparent;color:rgba(255,255,255,.35);font-size:34px;line-height:1;cursor:pointer}.mod-list{display:grid}.mod-row{display:grid;grid-template-columns:64px minmax(0,1fr);gap:14px;align-items:center;padding:15px 0;text-decoration:none;color:inherit;border-radius:14px;transition:.15s ease}.mod-row:hover{background:rgba(255,255,255,.06);padding-left:8px;padding-right:8px}.mod-row .avatar-verify-badge{border-color:#24262b!important}.mod-avatar{width:58px;height:58px;border-radius:16px;background:#fff;display:grid;place-items:center;color:#0284c7;font-size:22px;font-weight:950;overflow:hidden}.mod-name{font-size:18px;font-weight:900;display:flex;gap:7px;align-items:center;flex-wrap:wrap}.mod-verify{display:none}.mod-role{display:inline-flex;margin-top:8px;border-radius:8px;background:rgba(37,99,235,.18);color:#60a5fa;padding:4px 8px;font-size:13px;font-weight:800}.mod-name .level-badge{background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.16);color:#93c5fd}.mod-follow{display:none;color:#fb7185;font-size:16px;font-weight:900;white-space:nowrap}.top-title-panel{border-bottom:1px solid var(--line-soft,#e2e8f0);padding:8px 16px}.top-title-list{display:grid}.top-title-item{display:flex;align-items:center;gap:8px;min-width:0;padding:7px 0;color:var(--text-main,#0f172a);text-decoration:none;font-size:14px;font-weight:850;line-height:1.45;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.top-title-item span{flex:0 0 auto;color:#dc2626;font-size:12px;font-weight:950}.top-title-item:hover{color:var(--primary,#0284c7)}.filter-tabs{display:flex;gap:8px;padding:12px 16px;overflow-x:auto;border-bottom:1px solid var(--line-soft,#e2e8f0)}.filter-tabs a{flex:0 0 auto;text-decoration:none;color:var(--text-soft,#64748b);background:var(--input-bg,#f8fafc);border-radius:999px;padding:8px 13px;font-size:13px;font-weight:900}.filter-tabs a.active,.filter-tabs a:hover{background:var(--primary,#0284c7);color:#fff}.thread-feed{display:grid}.feed-item{display:grid;grid-template-columns:52px minmax(0,1fr) 150px;gap:14px;padding:18px 20px;border-bottom:1px solid var(--line-soft,#e2e8f0);text-decoration:none;color:inherit}.feed-item:last-child{border-bottom:none}.feed-item.no-media{grid-template-columns:52px minmax(0,1fr)}.feed-avatar{width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,#0284c7,#6366f1);display:grid;place-items:center;color:#fff;font-weight:950;overflow:hidden}.feed-main{min-width:0}.feed-badges{display:flex;gap:5px;flex-wrap:wrap;margin-bottom:7px}.feed-badge{font-size:11px;font-weight:950;border-radius:999px;padding:3px 7px;background:#f1f5f9;color:#64748b}.feed-badge.top{background:#fef3c7;color:#b45309}.feed-badge.featured{background:#fff1db;color:#e56a00}.feed-badge.recommended{background:#e0f2fe;color:#0284c7}.feed-badge.locked{background:#e2e8f0;color:#475569}.feed-title{font-size:18px;font-weight:950;color:var(--text-main,#0f172a);line-height:1.42;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.feed-excerpt{margin-top:7px;color:var(--text-soft,#64748b);font-size:13px;line-height:1.65;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.feed-meta{display:flex;gap:8px;flex-wrap:wrap;margin-top:9px;color:var(--text-muted,#94a3b8);font-size:12px}.feed-side{display:flex;flex-direction:column;gap:8px;align-items:flex-end;justify-content:center}.feed-cover{width:150px;height:92px;border-radius:14px;overflow:hidden;background:linear-gradient(135deg,#e0f2fe,#eef2ff);display:grid;place-items:center;color:#0284c7;font-weight:950}.feed-cover img{width:100%;height:100%;object-fit:cover;display:block}.feed-counts{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}.feed-counts span{font-size:11px;color:var(--text-muted,#94a3b8);background:var(--input-bg,#f8fafc);border-radius:999px;padding:4px 7px}.empty-section{text-align:center;color:var(--text-muted,#94a3b8);padding:54px 16px}.pagination{display:flex;gap:6px;justify-content:center;padding:18px;flex-wrap:wrap}.pagination a{padding:7px 12px;border-radius:999px;background:var(--input-bg,#f8fafc);color:var(--text-soft,#64748b);text-decoration:none;font-size:13px;font-weight:900}.pagination a.active,.pagination a:hover{background:var(--primary,#0284c7);color:#fff}.side-column{position:sticky;top:78px;display:grid;gap:16px}.side-panel{padding:18px}.side-title{margin:0 0 12px;font-size:17px;font-weight:950;color:var(--text-main,#0f172a)}.side-desc{color:var(--text-soft,#64748b);font-size:13px;line-height:1.8}.side-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.side-stat{background:var(--input-bg,#f8fafc);border-radius:14px;padding:12px;text-align:center}.side-stat strong{display:block;font-size:19px;color:var(--text-main,#0f172a)}.side-stat span{display:block;margin-top:5px;color:var(--text-muted,#94a3b8);font-size:12px}.side-links{display:grid;gap:8px}.side-links a{text-decoration:none;color:var(--text-soft,#64748b);background:var(--input-bg,#f8fafc);border-radius:12px;padding:10px 12px;font-size:13px;font-weight:900;display:flex;justify-content:space-between}.not-found{padding:32px 18px;color:#ef4444}
@media(max-width:980px){.plate-layout{grid-template-columns:1fr}.side-column{display:none}.feed-item{grid-template-columns:48px minmax(0,1fr) 132px}.feed-item.no-media{grid-template-columns:48px minmax(0,1fr)}.feed-cover{width:132px;height:82px}}
@media(max-width:700px){.plate-page{padding-top:0}.container.plate-layout{padding:0}.soft-panel{border-radius:0;border-left:0;border-right:0}.plate-hero{padding:22px 18px;border-radius:0}.plate-hero-inner{grid-template-columns:minmax(0,1fr) 82px;gap:14px}.plate-title{font-size:28px}.plate-desc{font-size:14px}.plate-meta-line{gap:7px;margin-top:14px}.plate-meta-line span{font-size:12px;padding:6px 8px}.plate-visual{width:78px;height:78px;border-radius:22px;font-size:32px}.plate-visual.is-image{padding:6px}.plate-visual.is-image img{border-radius:16px}.plate-actions-row{margin-top:18px;display:grid;grid-template-columns:1fr 1fr;gap:10px}.publish-pill,.soft-link-pill,.plate-actions-row .section-follow-btn{width:100%;height:40px;font-size:13px;padding:0 12px}.section-follow-form{width:100%}.moderator-compact-card{margin-top:12px;max-width:100%;padding:7px 8px 7px 10px;border-radius:14px}.moderator-compact-btn{height:32px;flex:1}.moderator-compact-text{max-width:none}.mod-modal{border-radius:14px;padding:20px 18px 8px}.mod-row{grid-template-columns:56px minmax(0,1fr)}.mod-avatar{width:52px;height:52px}.mod-name{font-size:16px}.mod-follow{font-size:14px}.feed-item,.feed-item.no-media{grid-template-columns:40px minmax(0,1fr);gap:11px;padding:16px 18px}.feed-avatar{width:38px;height:38px}.feed-side{grid-column:2;align-items:flex-start}.feed-cover{width:100%;height:150px}.feed-counts{justify-content:flex-start}.feed-title{font-size:16px}.filter-tabs{padding:10px 14px}}
html[data-theme="dark"] .soft-panel{background:#111827;border-color:#263244;box-shadow:0 10px 30px rgba(0,0,0,.22)}html[data-theme="dark"] .plate-hero{background:linear-gradient(135deg,#13233a,#111827 54%,#1e1b4b);border-color:#263244}html[data-theme="dark"] .plate-stat{background:rgba(15,23,42,.72);border-color:#263244}html[data-theme="dark"] .feed-counts span,html[data-theme="dark"] .filter-tabs a,html[data-theme="dark"] .side-stat,html[data-theme="dark"] .side-links a{background:#0f172a}html[data-theme="dark"] .plate-meta-line span,html[data-theme="dark"] .moderator-pill,html[data-theme="dark"] .soft-link-pill{background:rgba(15,23,42,.72);border-color:#334155;color:#e5e7eb}html[data-theme="dark"] .moderator-compact-card{background:rgba(15,23,42,.52);border-color:#334155}html[data-theme="dark"] .moderator-compact-btn{background:rgba(15,23,42,.82);color:#e5e7eb;box-shadow:inset 0 0 0 1px #334155}html[data-theme="dark"] .moderator-compact-btn:hover{background:#0f172a;color:#38bdf8}html[data-theme="dark"] .publish-pill{background:#0ea5e9;color:#fff;box-shadow:0 10px 24px rgba(14,165,233,.24)}html[data-theme="dark"] .plate-kicker{background:rgba(14,165,233,.16);color:#38bdf8}html[data-theme="dark"] .moderator-mini-avatars .mini-avatar{border-color:#111827}html[data-theme="dark"] .plate-visual.is-image{background:#f8fafc}html[data-theme="dark"] .plate-actions-row .section-follow-btn{background:rgba(15,23,42,.72);border-color:#334155;color:#38bdf8}html[data-theme="dark"] .plate-actions-row .section-follow-btn.is-following{background:rgba(14,165,233,.16);border-color:rgba(56,189,248,.32);color:#7dd3fc}
</style>
</head>
<body>
<?php require __DIR__ . '/../layouts/topbar.php'; ?>
<main class="plate-page">
<?php if ($section): ?>
<div class="container plate-layout">
  <div>
    <section class="soft-panel plate-hero">
      <div class="plate-hero-inner">
        <div class="plate-main-info">
          <div class="plate-kicker">社区板块</div>
          <h1 class="plate-title"><?= htmlspecialchars($section['name']) ?></h1>
          <?php if (!empty($section['description'])): ?><p class="plate-desc"><?= htmlspecialchars($section['description']) ?></p><?php else: ?><p class="plate-desc">欢迎在这里交流想法、反馈问题和分享经验。</p><?php endif; ?>
          <div class="plate-meta-line"><span><?= (int)($total ?? 0) ?> 帖子</span><span><?= (int)($sectionFollowerCount ?? 0) ?> 关注</span><span><?= array_sum(array_map(fn($t)=>(int)($t['reply_count']??0), $threads)) ?> 互动</span></div>
        </div>
        <?php if ($isSectionIconImage): ?><div class="plate-visual is-image"><img src="<?= htmlspecialchars($sectionIcon) ?>" alt="<?= htmlspecialchars($section['name']) ?>"></div><?php elseif ($sectionIcon !== ''): ?><div class="plate-visual is-text"><?= htmlspecialchars(mb_substr($sectionIcon, 0, 2)) ?></div><?php else: ?><div class="plate-visual is-text"><?= htmlspecialchars($sectionInitial) ?></div><?php endif; ?>
      </div>
      <?php if (!empty($moderators)): ?><div class="moderator-compact-card"><div class="moderator-compact-label">版主</div><button class="moderator-compact-btn" type="button" id="showModerators"><span class="moderator-mini-avatars"><?php foreach (array_slice($moderators, 0, 3) as $m): ?><?= user_avatar_html($m, 'mini-avatar', 24) ?><?php endforeach; ?></span><span class="moderator-compact-text"><?php $firstModerator = $moderators[0] ?? []; echo htmlspecialchars((string)($firstModerator['nickname'] ?: $firstModerator['username'] ?: '查看版主')); ?><?= count($moderators) > 1 ? '等 ' . count($moderators) . ' 人' : '' ?></span><svg class="moderator-compact-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></button></div><?php endif; ?>
      <div class="plate-actions-row"><a class="publish-pill" href="/index.php?path=publish&section_id=<?= (int)$section['id'] ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>发布帖子</a><?php if (function_exists('auth_check') && auth_check()): ?><form class="section-follow-form" method="post" action="/index.php?path=section/follow" data-ajax-refresh><?= csrf_field() ?><input type="hidden" name="section_id" value="<?= (int)$section['id'] ?>"><button class="section-follow-btn <?= !empty($sectionFollowed) ? 'is-following' : '' ?>" type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.35-9-9.5C1.7 8.16 3.72 5 7.1 5c1.98 0 3.25 1.08 4.9 2.9C13.65 6.08 14.92 5 16.9 5c3.38 0 5.4 3.16 4.1 6.5C19 16.65 12 21 12 21Z"/></svg><?= !empty($sectionFollowed) ? '已关注板块' : '关注板块' ?></button></form><?php endif; ?></div>
    </section>
    <section class="soft-panel" style="margin-top:16px;">
      <nav class="filter-tabs"><?php foreach ($filterLabels as $value => $label): ?><a class="<?= $filter === $value ? 'active' : '' ?>" href="<?= htmlspecialchars($filterUrl($value)) ?>"><?= htmlspecialchars($label) ?></a><?php endforeach; ?></nav>
      <?php if (!empty($topThreads)): ?><?= render_top_thread_strip($topThreads, 'section') ?><?php endif; ?>
      <?php if (!empty($threads)): ?><?= thread_card_styles() ?><div class="thread-card-grid thread-feed"><?php foreach ($threads as $thread): ?><?= render_thread_card($thread) ?><?php endforeach; ?></div><?php else: ?><div class="empty-section"><?= htmlspecialchars($filter === 'all' ? '该板块暂时无帖子' : '该板块暂时没有' . ($filterLabels[$filter] ?? '相关') . '帖子') ?></div><?php endif; ?>
      <?php if ($totalPages > 1): ?><div class="pagination"><?php for ($pp = 1; $pp <= $totalPages; $pp++): ?><a href="<?= htmlspecialchars($pageUrl($pp)) ?>" class="<?= $pp === $page ? 'active' : '' ?>"><?= $pp ?></a><?php endfor; ?></div><?php endif; ?>
    </section>
  </div>
  <aside class="side-column"><section class="soft-panel side-panel"><h3 class="side-title">板块信息</h3><div class="side-desc"><?= htmlspecialchars($section['description'] ?: '欢迎在这里交流想法、反馈问题和分享经验。') ?></div></section><section class="soft-panel side-panel"><h3 class="side-title">数据概览</h3><div class="side-grid"><div class="side-stat"><strong><?= (int)($total ?? 0) ?></strong><span>帖子</span></div><div class="side-stat"><strong><?= (int)$totalPages ?></strong><span>页数</span></div></div></section><section class="soft-panel side-panel"><h3 class="side-title">快捷入口</h3><div class="side-links"><a href="/index.php?path=publish&section_id=<?= (int)$section['id'] ?>">发布帖子 <span>›</span></a><a href="/index.php?path=sections">全部板块 <span>›</span></a><a href="/index.php">返回首页 <span>›</span></a></div></section></aside>
</div>
<?php else: ?><section class="container not-found">板块不存在</section><?php endif; ?>
</main>
<?php if (!empty($moderators)): ?>
<div class="mod-modal-backdrop" id="moderatorModal">
  <div class="mod-modal" role="dialog" aria-modal="true" aria-label="查看版主">
    <div class="mod-modal-head"><div class="mod-modal-title"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 3 7v10l9 5 9-5V7l-9-5Zm0 2.3L18.7 8 12 11.7 5.3 8 12 4.3Z"/></svg>查看版主</div><button class="mod-close" type="button" id="closeModeratorModal">×</button></div>
    <div class="mod-list">
      <?php foreach ($moderators as $m): ?>
        <?php $modName = (string)($m['nickname'] ?: $m['username'] ?: '版主'); ?>
        <a class="mod-row" href="/index.php?path=user&id=<?= (int)($m['id'] ?? 0) ?>"><?= user_avatar_html($m, 'mod-avatar', 58) ?><div><div class="mod-name"><span class="name-level-inline"><?= htmlspecialchars($modName) ?><?= user_level_badge_html($m, 'level-badge small') ?></span></div><div class="mod-role"><?= htmlspecialchars((string)($m['role_name'] ?? '版主')) ?></div><?php  ?></div></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script>(function(){var b=document.getElementById('showModerators'),m=document.getElementById('moderatorModal'),c=document.getElementById('closeModeratorModal');if(!b||!m)return;b.addEventListener('click',function(){m.classList.add('is-open')});function close(){m.classList.remove('is-open')}if(c)c.addEventListener('click',close);m.addEventListener('click',function(e){if(e.target===m)close();});document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});})();</script>
<?php endif; ?>
<?php require __DIR__ . '/../../layouts/theme-toggle.php'; ?>
<?php require __DIR__ . '/../layouts/bottom-nav.php'; ?>
<script>

(function(){
  if(window.__clayThreadCardGestures)return;window.__clayThreadCardGestures=true;
  var touchTimer=null,touchCard=null,startX=0,startY=0,currentDx=0,moved=false,lastTapTime=0,lastTapCard=null,suppressClickUntil=0,pendingNavTimer=null;
  var SWIPE_THRESHOLD=88;
  function resetCard(card){
    if(!card)return;
    card.style.setProperty('--card-swipe-x','0px');
    card.style.setProperty('--swipe-progress','0');
    card.classList.remove('swiping-left','swiping-right','swipe-ready','is-dragging');
  }
  function postAction(card,type){
    var id=card&&card.dataset.threadId,csrf=card&&card.dataset.csrf;if(!id||!csrf)return;
    suppressClickUntil=Date.now()+650;
    var fd=new FormData();
    if(type==='favorite'){
      fd.append('_csrf_token',csrf);fd.append('thread_id',id);
      fetch('/index.php?path=thread/favorite',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(){done(card,'favorite');}).catch(function(){resetCard(card);});
    }else{
      fd.append('_csrf_token',csrf);fd.append('target_type','thread');fd.append('target_id',id);fd.append('thread_id',id);
      fetch('/index.php?path=content/like',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(){done(card,'like');}).catch(function(){resetCard(card);});
    }
  }
  function done(card,type){
    if(!card)return;
    resetCard(card);
    card.classList.add('swipe-done');
    if(type==='like'){
      card.classList.remove('like-pop');void card.offsetWidth;card.classList.add('like-pop');
      setTimeout(function(){card.classList.remove('like-pop');},720);
    }
    setTimeout(function(){card.classList.remove('swipe-done');},460);
  }
  function openPreview(src){
    suppressClickUntil=Date.now()+650;
    var box=document.querySelector('.thread-image-preview');
    if(!box){box=document.createElement('div');box.className='thread-image-preview';box.innerHTML='<button type="button" aria-label="关闭">×</button><img alt="">';document.body.appendChild(box);box.addEventListener('click',function(e){if(e.target===box||e.target.tagName==='BUTTON')box.classList.remove('is-open');});}
    box.querySelector('img').src=src;box.classList.add('is-open');
  }
  document.addEventListener('click',function(e){
    if(Date.now()<suppressClickUntil){e.preventDefault();e.stopPropagation();return;}
    var copy=e.target.closest('[data-copy-link]');
    if(copy){
      e.preventDefault();e.stopPropagation();
      var url=new URL(copy.getAttribute('data-copy-link'),location.origin).href;
      if(navigator.clipboard){navigator.clipboard.writeText(url).then(function(){copy.textContent='已复制';setTimeout(function(){copy.textContent='复制链接';},1200);});}
      return;
    }
    var card=e.target.closest('.thread-card-v2[data-href],.home-latest-card[data-href]');
    if(!card)return;
    if(e.target.closest('a,button,input,select,textarea'))return;
    e.preventDefault();e.stopPropagation();
    if(pendingNavTimer)clearTimeout(pendingNavTimer);
    var href=card.dataset.href;
    if(matchMedia('(hover: none) and (pointer: coarse)').matches){
      pendingNavTimer=setTimeout(function(){if(Date.now()>=suppressClickUntil)window.location.href=href;},300);
    }else{
      window.location.href=href;
    }
  },true);
  document.addEventListener('touchstart',function(e){
    var card=e.target.closest('.thread-card-v2[data-href],.home-latest-card[data-href]');
    if(!card||e.target.closest('a,button,input,select,textarea'))return;
    var t=e.touches[0];startX=t.clientX;startY=t.clientY;currentDx=0;moved=false;touchCard=card;card.classList.add('is-touching');
    card.style.setProperty('--card-swipe-x','0px');
    touchTimer=setTimeout(function(){
      if(!touchCard||moved)return;
      var img=e.target.closest('.thread-card-img img,.home-feed-img img');
      try{e.preventDefault();}catch(err){}
      if(img){openPreview(img.currentSrc||img.src);}else{touchCard.classList.add('is-quick-open');suppressClickUntil=Date.now()+650;}
    },520);
  },{passive:false});
  document.addEventListener('touchmove',function(e){
    if(!touchCard)return;
    var t=e.touches[0],dx=t.clientX-startX,dy=t.clientY-startY;
    currentDx=dx;
    if(Math.abs(dx)>8||Math.abs(dy)>8)moved=true;
    if(Math.abs(dx)>12&&Math.abs(dx)>Math.abs(dy)*1.15){
      if(touchTimer)clearTimeout(touchTimer);
      e.preventDefault();
      var damped=Math.max(-118,Math.min(118,dx*.72));
      touchCard.classList.add('is-dragging');
      touchCard.style.setProperty('--card-swipe-x',damped+'px');
      touchCard.classList.toggle('swiping-left',dx<-32);
      touchCard.classList.toggle('swiping-right',dx>32);
    }else if(Math.abs(dx)<20){
      touchCard.classList.remove('swiping-left','swiping-right','swipe-ready');
      touchCard.style.setProperty('--card-swipe-x','0px');
      touchCard.style.setProperty('--swipe-progress','0');
    }
  },{passive:false});
  document.addEventListener('touchend',function(e){
    if(touchTimer)clearTimeout(touchTimer);
    var card=touchCard;
    if(card){
      var changed=e.changedTouches&&e.changedTouches[0];
      var dx=changed?changed.clientX-startX:currentDx,dy=changed?changed.clientY-startY:0;
      if(Math.abs(dx)>SWIPE_THRESHOLD&&Math.abs(dx)>Math.abs(dy)*1.25){
        suppressClickUntil=Date.now()+700;
        postAction(card,dx<0?'favorite':'like');
      }else{
        resetCard(card);
      }
      setTimeout(function(c){c.classList.remove('is-touching');},80,card);
    }
    touchTimer=null;touchCard=null;currentDx=0;
  },{passive:true});
  document.addEventListener('dblclick',function(e){
    var card=e.target.closest('.thread-card-v2[data-thread-id],.home-latest-card[data-thread-id]');
    if(!card||e.target.closest('a,button,input,select,textarea'))return;
    e.preventDefault();e.stopPropagation();if(pendingNavTimer)clearTimeout(pendingNavTimer);suppressClickUntil=Date.now()+900;postAction(card,'like');
  },true);
  document.addEventListener('touchend',function(e){
    var card=e.target.closest('.thread-card-v2[data-thread-id],.home-latest-card[data-thread-id]');
    if(!card||moved||e.target.closest('a,button,input,select,textarea'))return;
    var now=Date.now();
    if(lastTapCard===card&&now-lastTapTime<280){
      e.preventDefault();e.stopPropagation();if(pendingNavTimer)clearTimeout(pendingNavTimer);suppressClickUntil=Date.now()+900;postAction(card,'like');lastTapTime=0;lastTapCard=null;return;
    }
    lastTapCard=card;lastTapTime=now;
  },{passive:false});
  document.addEventListener('click',function(e){
    document.querySelectorAll('.thread-card-v2.is-quick-open').forEach(function(card){if(!card.contains(e.target))card.classList.remove('is-quick-open');});
  },true);
})();
</script>
</body>
</html>
