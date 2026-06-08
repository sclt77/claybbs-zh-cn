<?php
function section_icon_render(?string $icon, string $class = 'section-icon'): string {
    $icon = trim((string)$icon);
    if ($icon === '') return '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . ' section-icon-fallback">#</span>';
    $isImage = preg_match('#^(https?://|/uploads/|/storage/|/assets/).+\.(png|jpe?g|gif|webp|svg)(\?.*)?$#i', $icon);
    if ($isImage) return '<img src="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" alt="">';
    return '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . ' section-icon-text">' . htmlspecialchars(mb_substr($icon, 0, 2), ENT_QUOTES, 'UTF-8') . '</span>';
}
function section_short_number(int $num): string {
    if ($num >= 10000) {
        $value = round($num / 10000, $num >= 100000 ? 0 : 1);
        return rtrim(rtrim((string)$value, '0'), '.') . 'W+';
    }
    return (string)$num;
}
function section_stat_icon(string $type): string {
    if ($type === 'lock') {
        return '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>';
    }
    return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H8l-4 4V5Z"/><path d="M8 9h8M8 13h5"/></svg>';
}
$grouped = $grouped ?? [];
$activeTab = 'boards';
$discoverModules = (string)\App\Core\Hook::filter('sections.discover.modules', '');
$totalSections = 0;
$totalThreads = 0;
foreach ($grouped as $list) { foreach ($list as $s) { $totalSections++; $totalThreads += (int)($s['thread_count'] ?? 0); } }
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>板块列表 - 论坛</title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
.sections-tabs{display:inline-flex;align-items:center;gap:3px;margin:0 0 12px;padding:3px;background:rgba(148,163,184,.10);border:1px solid rgba(148,163,184,.16);border-radius:999px}.sections-tabs button{appearance:none;border:0;display:inline-flex;align-items:center;justify-content:center;height:28px;min-width:58px;padding:0 12px;border-radius:999px;text-decoration:none;background:transparent;color:var(--text-soft,#64748b);font-size:13px;font-weight:850;cursor:pointer}.sections-tabs button.active{background:var(--bg-card,#fff);color:var(--text,#0f172a);box-shadow:0 4px 12px rgba(15,23,42,.08)}.sections-discover{display:grid;gap:12px}.discover-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.discover-module-card{display:grid;grid-template-columns:54px minmax(0,1fr) auto;gap:12px;align-items:center;text-decoration:none;color:inherit;background:var(--bg-card,#fff);border:1px solid var(--line-soft,#e2e8f0);border-radius:18px;padding:14px;box-shadow:0 10px 30px rgba(15,23,42,.045);transition:.16s ease}.discover-module-card:hover{transform:translateY(-1px);border-color:rgba(2,132,199,.28)}.discover-module-icon{width:54px;height:54px;border-radius:17px;display:grid;place-items:center;background:linear-gradient(135deg,#fff7ed,#fee2e2);color:#d97706}.discover-module-icon svg{width:28px;height:28px}.discover-module-main strong{display:block;font-size:16px;font-weight:950;color:var(--text,#0f172a)}.discover-module-main span{display:block;margin-top:5px;font-size:12px;line-height:1.55;color:var(--text-soft,#64748b)}.discover-module-enter{font-size:12px;font-weight:950;color:var(--primary,#0284c7);background:rgba(2,132,199,.08);border-radius:999px;padding:7px 10px}.discover-empty{padding:24px;text-align:center;color:var(--text-muted,#94a3b8);background:var(--bg-card,#fff);border:1px dashed var(--line-soft,#e2e8f0);border-radius:18px}.sections-boards[hidden],.sections-discover[hidden]{display:none!important}@media(max-width:640px){.discover-grid{grid-template-columns:1fr}.discover-module-card{grid-template-columns:48px minmax(0,1fr);}.discover-module-enter{grid-column:2;justify-self:start}.discover-module-icon{width:48px;height:48px;border-radius:15px}}html[data-theme="dark"] .sections-tabs{background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.06)}html[data-theme="dark"] .sections-tabs button.active{background:#303133;color:#f5f5f6}html[data-theme="dark"] .discover-module-card,html[data-theme="dark"] .discover-empty{background:#303133;border-color:rgba(255,255,255,.04)}html[data-theme="dark"] .discover-module-main strong{color:#f5f5f6}html[data-theme="dark"] .discover-module-main span{color:#90919a}
.sections-page{padding:16px 16px 96px;}.category-block{margin-bottom:22px}.category-head{display:flex;align-items:center;gap:10px;margin:0 2px 12px}.category-head::before{content:"";width:5px;height:24px;border-radius:999px;background:var(--primary)}.category-title{font-size:17px;font-weight:950;color:var(--text);letter-spacing:-.02em}.category-count{margin-left:auto;color:var(--text-muted);font-size:12px;font-weight:800}.section-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.section-card{position:relative;display:grid;grid-template-columns:56px minmax(0,1fr);gap:10px;align-items:center;min-height:96px;text-decoration:none;color:inherit;background:var(--bg-card);border:1px solid var(--line-soft);border-radius:17px;padding:13px 12px;box-shadow:0 8px 26px rgba(15,23,42,.045);transition:.16s ease;overflow:hidden}.section-card:hover{border-color:rgba(2,132,199,.28);transform:translateY(-1px)}.section-card:active{transform:scale(.985)}.section-icon{width:50px;height:50px;border-radius:50%;object-fit:cover;background:linear-gradient(135deg,#f8fafc,#eef2ff);display:inline-flex;align-items:center;justify-content:center;font-size:22px;font-weight:950;color:var(--primary);border:1px solid var(--line-soft)}.section-icon-text,.section-icon-fallback{background:linear-gradient(135deg,#e0f2fe,#eef2ff)}.section-main{min-width:0}.section-name{font-size:15px;font-weight:950;color:var(--text);line-height:1.24;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.section-desc{display:none}.section-meta{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:8px;color:var(--text-muted);font-size:12px;font-weight:780}.section-meta span{display:inline-flex;align-items:center;gap:4px;white-space:nowrap}.section-meta svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}.section-lock{color:var(--text-soft)}.section-hot-dot{position:absolute;right:14px;top:0;width:24px;height:30px;border-radius:0 0 10px 10px;background:linear-gradient(180deg,#fb7185,#f43f5e);opacity:.95}.section-hot-dot::after{content:"";position:absolute;left:50%;top:9px;width:7px;height:7px;border-radius:50%;background:#fff;transform:translateX(-50%)}.section-broadcast-panel{position:relative;margin:0 0 18px;height:176px;border-radius:18px;overflow:hidden;background:linear-gradient(135deg,#0284c7,#6366f1);box-shadow:0 12px 34px rgba(15,23,42,.12)}.section-broadcast-item{position:absolute;inset:0;opacity:0;transition:opacity .28s ease;color:#fff;text-decoration:none}.section-broadcast-item.active{opacity:1}.section-broadcast-item img{width:100%;height:100%;object-fit:cover;display:block}.section-broadcast-empty{height:100%;background:linear-gradient(135deg,#0284c7,#6366f1)}.section-broadcast-caption{position:absolute;left:0;right:0;bottom:0;padding:48px 16px 15px;background:linear-gradient(180deg,transparent,rgba(15,23,42,.78));color:#fff}.section-broadcast-caption strong{display:block;font-size:18px;line-height:1.35}.section-broadcast-caption span{display:block;margin-top:5px;font-size:13px;line-height:1.5;opacity:.9}.section-broadcast-tag{position:absolute;left:13px;top:13px;z-index:2;border-radius:999px;background:rgba(15,23,42,.52);backdrop-filter:blur(8px);color:#fff;font-size:12px;font-weight:900;padding:6px 10px}.section-broadcast-dots{position:absolute;right:13px;bottom:12px;display:flex;gap:6px;z-index:3}.section-broadcast-dot{width:7px;height:7px;border-radius:999px;background:rgba(255,255,255,.56);transition:.2s}.section-broadcast-dot.active{width:20px;background:#fff}.empty-card{background:var(--bg-card);border:1px solid var(--line-soft);border-radius:18px;color:var(--text-muted);text-align:center;padding:36px 18px}@media(min-width:760px){.section-list{grid-template-columns:repeat(3,minmax(0,1fr));}.sections-page{padding-top:20px}.section-card{min-height:104px;padding:15px}.section-icon{width:54px;height:54px}.section-name{font-size:16px}}@media(max-width:380px){.section-card{grid-template-columns:48px minmax(0,1fr);gap:8px;padding:12px 10px;min-height:88px}.section-icon{width:44px;height:44px;font-size:19px}.section-name{font-size:14px}.section-meta{font-size:11px;gap:6px}.section-hot-dot{right:10px;width:21px;height:27px}}
html[data-theme="dark"] body{background:#27272c}html[data-theme="dark"] .sections-page{background:radial-gradient(circle at 12% 8%,rgba(244,114,182,.10),transparent 28%)}html[data-theme="dark"] .category-head::before{background:#ff5ab7}html[data-theme="dark"] .section-card{background:#303133;border-color:rgba(255,255,255,.025);box-shadow:0 14px 34px rgba(0,0,0,.10)}html[data-theme="dark"] .section-card:hover{background:#35363a;border-color:rgba(255,90,183,.16)}html[data-theme="dark"] .section-icon{background:linear-gradient(135deg,#f8d5e8,#d8f3ff);border-color:rgba(255,255,255,.08);color:#334155}html[data-theme="dark"] .section-name{color:#f5f5f6}html[data-theme="dark"] .section-meta{color:#90919a}html[data-theme="dark"] .category-title{color:#f8fafc}html[data-theme="dark"] .section-hot-dot{background:linear-gradient(180deg,#ff7668,#f04c42)}

/* Desktop-only sections layout: mobile keeps old page untouched */
.sections-desktop-v2{display:none}
@media(min-width:769px){
  .sections-page.container{max-width:1320px}.sections-page>.section-broadcast-panel,.sections-page>.category-block,.sections-page>.empty-card{display:none!important}
  .sections-desktop-v2{display:grid;grid-template-columns:260px minmax(0,1fr) 260px;gap:18px;align-items:start}.sections-v2-left,.sections-v2-right{position:sticky;top:76px;display:grid;gap:14px}.sections-v2-main{min-width:0;display:grid;gap:18px}
  .sections-v2-panel{background:var(--bg-card,#fff);border:1px solid var(--line-soft,#e2e8f0);border-radius:18px;box-shadow:0 10px 30px rgba(15,23,42,.045);overflow:hidden}.sections-v2-pad{padding:15px}.sections-v2-title{margin:0 0 12px;font-size:15px;font-weight:950;color:var(--text,#0f172a)}
  .sections-v2-left .section-broadcast-panel{margin:0;height:154px;border-radius:18px}.sections-v2-left .section-broadcast-caption{padding:42px 12px 11px}.sections-v2-left .section-broadcast-caption strong{font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.sections-v2-left .section-broadcast-caption span{font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.sections-v2-left .section-broadcast-dots{right:10px;top:12px;bottom:auto}.sections-v2-left .section-broadcast-dot{width:6px;height:6px}.sections-v2-left .section-broadcast-dot.active{width:16px}
  .sections-v2-nav{display:grid;gap:6px}.sections-v2-nav a{display:flex;justify-content:space-between;align-items:center;text-decoration:none;color:var(--text-soft,#64748b);background:var(--input-bg,#f8fafc);border-radius:12px;padding:9px 10px;font-size:13px;font-weight:900}.sections-v2-nav a:hover{color:var(--primary,#0284c7);background:rgba(2,132,199,.07)}.sections-v2-nav span{color:var(--text-muted,#94a3b8);font-size:11px}
  .sections-v2-main .section-list{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.sections-v2-main .section-card{grid-template-columns:54px minmax(0,1fr) auto;min-height:104px;border-radius:18px;background:var(--bg-card,#fff)}.sections-v2-main .section-desc{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-top:5px;color:var(--text-soft,#64748b);font-size:12px;line-height:1.55}.sections-v2-enter{font-size:12px;font-weight:950;color:var(--primary,#0284c7);background:rgba(2,132,199,.08);border-radius:999px;padding:6px 9px;white-space:nowrap}.sections-v2-stats{display:grid;grid-template-columns:1fr 1fr;gap:8px}.sections-v2-stat{background:var(--input-bg,#f8fafc);border-radius:14px;padding:11px;text-align:center}.sections-v2-stat strong{display:block;font-size:18px;color:var(--text,#0f172a)}.sections-v2-stat span{display:block;margin-top:4px;font-size:11px;color:var(--text-muted,#94a3b8)}.sections-v2-hot{display:grid;gap:8px}.sections-v2-hot a{display:flex;justify-content:space-between;text-decoration:none;color:var(--text-soft,#64748b);font-size:13px;font-weight:900;background:var(--input-bg,#f8fafc);border-radius:12px;padding:9px 10px}
  html[data-theme="dark"] .sections-v2-panel{background:#111827!important;border-color:#263244!important}html[data-theme="dark"] .sections-v2-nav a,html[data-theme="dark"] .sections-v2-stat,html[data-theme="dark"] .sections-v2-hot a{background:#0f172a;color:#cbd5e1}
}
@media(min-width:769px) and (max-width:1180px){.sections-desktop-v2{grid-template-columns:230px minmax(0,1fr) 240px;gap:14px}.sections-v2-main .section-list{grid-template-columns:1fr}}

/* Section broadcast mobile fix 2026-05-10 */
.section-broadcast-panel.is-single .section-broadcast-dots{display:none!important}
.section-broadcast-panel .section-broadcast-item.active{display:block!important;opacity:1!important;z-index:1}
.section-broadcast-panel .section-broadcast-item:not(.active){pointer-events:none}
@media(max-width:640px){.section-broadcast-panel{overflow:hidden!important;position:relative;z-index:2}.section-broadcast-dots{right:12px!important;bottom:10px!important;top:auto!important;transform:none!important}.section-broadcast-dot{width:6px!important;height:6px!important}.section-broadcast-dot.active{width:16px!important}}

</style>
</head>
<body>
<?php require __DIR__ . '/../layouts/topbar.php'; ?>

<div class="sections-page container">
  <nav class="sections-tabs" aria-label="板块页面切换" data-sections-tabs>
    <button type="button" class="active" data-sections-tab="boards" aria-selected="true">板块</button>
    <button type="button" data-sections-tab="discover" aria-selected="false">发现</button>
  </nav>

  <div class="sections-boards" data-sections-panel="boards">
  <div class="sections-desktop-v2">
    <aside class="sections-v2-left">
      <?php if (!empty($sectionBroadcasts)): ?><section class="sections-v2-panel section-broadcast-panel" aria-label="板块转播"><?php foreach ($sectionBroadcasts as $idx => $b): ?><a class="section-broadcast-item <?= $idx === 0 ? 'active' : '' ?>" href="<?= htmlspecialchars($b['url'] ?: ('/index.php?path=thread&id=' . (int)($b['thread_id'] ?? 0))) ?>"><?php if (!empty($b['image'])): ?><img src="<?= htmlspecialchars($b['image']) ?>" alt="<?= htmlspecialchars($b['title']) ?>"><?php else: ?><div class="section-broadcast-empty"></div><?php endif; ?><div class="section-broadcast-tag">板块转播</div><div class="section-broadcast-caption"><strong><?= htmlspecialchars($b['title']) ?></strong><?php if (!empty($b['section_name'])): ?><span><?= htmlspecialchars($b['section_name']) ?></span><?php endif; ?></div></a><?php endforeach; ?><?php if (count($sectionBroadcasts) > 1): ?><div class="section-broadcast-dots"><?php foreach ($sectionBroadcasts as $idx => $_): ?><span class="section-broadcast-dot <?= $idx === 0 ? 'active' : '' ?>" data-section-broadcast-dot="<?= $idx ?>"></span><?php endforeach; ?></div><?php endif; ?></section><?php endif; ?>
      <section class="sections-v2-panel sections-v2-pad"><h2 class="sections-v2-title">分类导航</h2><nav class="sections-v2-nav"><a href="#all-sections">全部板块 <span><?= (int)$totalSections ?></span></a><?php $questionCount=0; foreach (($grouped ?? []) as $sectionList) foreach ($sectionList as $s) if (!empty($s['is_question'])) $questionCount++; ?><a href="#all-sections">问答板块 <span><?= (int)$questionCount ?></span></a><?php foreach ($grouped as $catName => $sectionList): ?><a href="#cat-<?= md5((string)$catName) ?>"><?= htmlspecialchars((string)$catName) ?> <span><?= count($sectionList) ?></span></a><?php endforeach; ?></nav></section>
    </aside>
    <section class="sections-v2-main" id="all-sections">
      <?php if (!empty($grouped)): ?><?php foreach ($grouped as $catName => $sectionList): ?><section class="category-block" id="cat-<?= md5((string)$catName) ?>"><div class="category-head"><div class="category-title"><?= htmlspecialchars($catName) ?></div><div class="category-count"><?= count($sectionList) ?> 个板块</div></div><div class="section-list"><?php foreach ($sectionList as $s): ?><?php $threadCount = (int)($s['thread_count'] ?? 0); $restricted = ($s['post_permission'] ?? 'login') !== 'login'; $hot = $threadCount >= 10; ?><a href="/index.php?path=section&id=<?= (int)$s['id'] ?>" class="section-card"><?php if ($hot): ?><span class="section-hot-dot" aria-hidden="true"></span><?php endif; ?><?= section_icon_render($s['icon'] ?? '') ?><div class="section-main"><div class="section-name"><?= htmlspecialchars($s['name']) ?><?php if (!empty($s['is_question'])): ?> <span style="font-size:10px;color:#0e7490;background:#ecfeff;border-radius:999px;padding:1px 6px;vertical-align:2px;">问答</span><?php endif; ?></div><?php if (!empty($s['description'])): ?><div class="section-desc"><?= htmlspecialchars($s['description']) ?></div><?php endif; ?><div class="section-meta"><?php if ($restricted): ?><span class="section-lock"><?= section_stat_icon('lock') ?></span><?php endif; ?><span><?= section_stat_icon('comment') ?><?= section_short_number($threadCount) ?></span></div></div><span class="sections-v2-enter">进入</span></a><?php endforeach; ?></div></section><?php endforeach; ?><?php else: ?><div class="empty-card">暂无板块</div><?php endif; ?>
    </section>
    <aside class="sections-v2-right"><section class="sections-v2-panel sections-v2-pad"><h2 class="sections-v2-title">社区概览</h2><div class="sections-v2-stats"><div class="sections-v2-stat"><strong><?= (int)$totalSections ?></strong><span>板块</span></div><div class="sections-v2-stat"><strong><?= section_short_number((int)$totalThreads) ?></strong><span>帖子</span></div></div></section><section class="sections-v2-panel sections-v2-pad"><h2 class="sections-v2-title">热门板块</h2><div class="sections-v2-hot"><?php foreach (array_slice($hotSections ?? [],0,5) as $hs): ?><a href="/index.php?path=section&id=<?= (int)$hs['id'] ?>"><span><?= htmlspecialchars((string)$hs['name']) ?></span><span><?= (int)($hs['thread_count'] ?? 0) ?></span></a><?php endforeach; ?></div></section></aside>
  </div>

  <?php if (!empty($sectionBroadcasts)): ?>
    <section class="section-broadcast-panel" aria-label="板块转播">
      <?php foreach ($sectionBroadcasts as $idx => $b): ?>
        <a class="section-broadcast-item <?= $idx === 0 ? 'active' : '' ?>" href="<?= htmlspecialchars($b['url'] ?: ('/index.php?path=thread&id=' . (int)($b['thread_id'] ?? 0))) ?>">
          <?php if (!empty($b['image'])): ?><img src="<?= htmlspecialchars($b['image']) ?>" alt="<?= htmlspecialchars($b['title']) ?>"><?php else: ?><div class="section-broadcast-empty"></div><?php endif; ?>
          <div class="section-broadcast-tag">板块转播</div>
          <div class="section-broadcast-caption"><strong><?= htmlspecialchars($b['title']) ?></strong><?php if (!empty($b['section_name'])): ?><span><?= htmlspecialchars($b['section_name']) ?></span><?php endif; ?></div>
        </a>
      <?php endforeach; ?>
      <?php if (count($sectionBroadcasts) > 1): ?><div class="section-broadcast-dots"><?php foreach ($sectionBroadcasts as $idx => $_): ?><span class="section-broadcast-dot <?= $idx === 0 ? 'active' : '' ?>" data-section-broadcast-dot="<?= $idx ?>"></span><?php endforeach; ?></div><?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if (!empty($grouped)): ?>
    <?php foreach ($grouped as $catName => $sectionList): ?>
      <section class="category-block" id="cat-<?= md5((string)$catName) ?>">
        <div class="category-head">
          <div class="category-title"><?= htmlspecialchars($catName) ?></div>
          <div class="category-count"><?= count($sectionList) ?> 个板块</div>
        </div>
        <div class="section-list">
          <?php foreach ($sectionList as $s): ?>
            <?php
              $threadCount = (int)($s['thread_count'] ?? 0);
              $restricted = ($s['post_permission'] ?? 'login') !== 'login';
              $hot = $threadCount >= 10;
            ?>
            <a href="/index.php?path=section&id=<?= (int)$s['id'] ?>" class="section-card">
              <?php if ($hot): ?><span class="section-hot-dot" aria-hidden="true"></span><?php endif; ?>
              <?= section_icon_render($s['icon'] ?? '') ?>
              <div class="section-main">
                <div class="section-name"><?= htmlspecialchars($s['name']) ?></div>
                <?php if (!empty($s['description'])): ?>
                  <div class="section-desc"><?= htmlspecialchars($s['description']) ?></div>
                <?php endif; ?>
                <div class="section-meta">
                  <?php if ($restricted): ?><span class="section-lock"><?= section_stat_icon('lock') ?></span><?php endif; ?>
                  <span><?= section_stat_icon('comment') ?><?= section_short_number($threadCount) ?></span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="empty-card">暂无板块</div>
  <?php endif; ?>
  </div>

  <section class="sections-discover" data-sections-panel="discover" hidden>
    <div class="category-head"><div class="category-title">发现</div><div class="category-count">功能模块</div></div>
    <?php if ($discoverModules !== ''): ?>
      <div class="discover-grid"><?= $discoverModules ?></div>
    <?php else: ?>
      <div class="discover-empty">暂无发现功能</div>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/../../layouts/theme-toggle.php'; ?>
<?php require __DIR__ . '/../layouts/bottom-nav.php'; ?>
<script>
(function(){
  var tabs=document.querySelectorAll('[data-sections-tab]');
  var panels=document.querySelectorAll('[data-sections-panel]');
  if(tabs.length&&panels.length){
    tabs.forEach(function(tab){
      tab.addEventListener('click',function(){
        var target=tab.dataset.sectionsTab;
        tabs.forEach(function(t){var active=t===tab;t.classList.toggle('active',active);t.setAttribute('aria-selected',active?'true':'false');});
        panels.forEach(function(panel){panel.hidden=panel.dataset.sectionsPanel!==target;});
      });
    });
  }
  document.querySelectorAll('.section-broadcast-panel').forEach(function(panel){
    var items=panel.querySelectorAll('.section-broadcast-item');
    var dots=panel.querySelectorAll('.section-broadcast-dot');
    if(!items.length)return;
    var cur=0;
    items.forEach(function(it,i){it.classList.toggle('active',i===0);});
    dots.forEach(function(d,i){d.classList.toggle('active',i===0);});
    if(items.length<=1){panel.classList.add('is-single');return;}
    function show(i){
      items[cur].classList.remove('active');
      if(dots[cur])dots[cur].classList.remove('active');
      cur=(i+items.length)%items.length;
      items[cur].classList.add('active');
      if(dots[cur])dots[cur].classList.add('active');
    }
    dots.forEach(function(d){d.addEventListener('click',function(e){e.preventDefault();show(parseInt(d.dataset.sectionBroadcastDot||'0',10));});});
    setInterval(function(){show(cur+1);},4200);
  });
})();
</script>
</body>
</html>
