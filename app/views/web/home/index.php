<?php
function home_section_icon(?string $icon): string {
    $icon = trim((string)$icon);
    if ($icon === '') return '<span class="hot-section-icon hot-section-text">#</span>';
    $safe = htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');
    $isImage = preg_match('#^(https?://|/uploads/|/storage/|/assets/).+\.(png|jpe?g|gif|webp|svg)(\?.*)?$#i', $icon);
    if ($isImage) return '<img src="' . $safe . '" class="hot-section-icon" alt="">';
    return '<span class="hot-section-icon hot-section-text">' . $safe . '</span>';
}
$feed = in_array((string)($feed ?? 'latest'), ['latest','hot','featured','following'], true) ? (string)$feed : 'latest';
$feedLabels = ['latest'=>'最新', 'hot'=>'热门', 'featured'=>'精华', 'following'=>'关注'];
$feedUrl = function(string $key, int $pageNo = 1): string {
    $url = '/index.php';
    $params = [];
    if ($key !== 'latest') $params['feed'] = $key;
    if ($pageNo > 1) $params['page'] = $pageNo;
    return $url . ($params ? '?' . http_build_query($params) : '');
};
function home_broadcast_item(array $banner, int $i): void {
    $title = trim((string)($banner['title'] ?? ''));
    $desc = trim((string)($banner['description'] ?? ''));
    $image = trim((string)($banner['image'] ?? ''));
    $url = trim((string)($banner['url'] ?? ''));
    $safeTitle = htmlspecialchars($title !== '' ? $title : '转播内容', ENT_QUOTES, 'UTF-8');
    $safeDesc = htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
    $safeImage = htmlspecialchars($image, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    ?>
    <article class="banner-item <?= $i === 0 ? 'active' : '' ?>">
      <?php if ($image !== ''): ?>
        <img src="<?= $safeImage ?>" alt="<?= $safeTitle ?>">
      <?php else: ?>
        <div class="banner-placeholder"><strong><?= $safeTitle ?></strong><div><?= $safeDesc ?></div></div>
      <?php endif; ?>
      <div class="banner-caption"><strong><?= $safeTitle ?></strong><?php if ($desc !== ''): ?><span><?= $safeDesc ?></span><?php endif; ?></div>
      <?php if ($url !== ''): ?><a class="banner-link" href="<?= $safeUrl ?>" aria-label="查看 <?= $safeTitle ?>"></a><?php endif; ?>
    </article>
    <?php
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($site['site_name'] ?? 'ClayBBS') ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
<?= thread_card_styles() ?>
<?= top_thread_strip_styles() ?>
<style>
.home-page{padding:18px 16px 96px;}
.home-top-grid{display:grid;gap:12px;align-items:stretch;margin-bottom:18px;}
.home-section-title{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px;}
.home-feed-tabs{display:flex;gap:8px;align-items:center;overflow-x:auto;padding:2px 2px 12px;margin:0 0 8px;scrollbar-width:none}.home-feed-tabs::-webkit-scrollbar{display:none}.home-feed-tab{flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;min-width:64px;height:34px;padding:0 14px;border-radius:999px;text-decoration:none;background:rgba(15,23,42,.055);color:var(--text-soft,#64748b);font-size:13px;font-weight:950;transition:.16s ease}.home-feed-tab.active,.home-feed-tab:hover{background:var(--primary,#0284c7);color:#fff;box-shadow:0 8px 18px rgba(2,132,199,.16)}html[data-theme="dark"] .home-feed-tab{background:#0f172a;color:#cbd5e1}html[data-theme="dark"] .home-feed-tab.active,html[data-theme="dark"] .home-feed-tab:hover{background:#0ea5e9;color:#fff}@media(max-width:640px){.home-feed-tabs{margin-left:0;margin-right:0;padding-bottom:10px}.home-feed-tab{height:32px;min-width:58px;font-size:12px;padding:0 12px}}

.home-section-title h2{margin:0;font-size:18px;font-weight:800;color:#0f172a;letter-spacing:-.02em;}
.home-more{font-size:13px;color:#0284c7;text-decoration:none;font-weight:700;}
.panel{background:#fff;border:1px solid #eef2f7;border-radius:20px;box-shadow:0 10px 30px rgba(15,23,42,.055);overflow:hidden;}
.banner-panel{position:relative;height:210px;background:linear-gradient(135deg,#e0f2fe,#f8fafc);}
.banner-item{position:absolute;inset:0;opacity:0;transition:opacity .28s ease;}
.banner-item.active{opacity:1;}
.banner-item img{width:100%;height:100%;object-fit:cover;display:block;}
.banner-placeholder{height:100%;display:flex;flex-direction:column;justify-content:center;padding:24px;background:linear-gradient(135deg,#0284c7,#4f46e5);color:#fff;}
.banner-placeholder strong{font-size:24px;line-height:1.2;margin-bottom:8px;}
.banner-caption{position:absolute;left:0;right:0;bottom:0;padding:44px 18px 16px;background:linear-gradient(180deg,transparent,rgba(15,23,42,.76));color:#fff;}
.banner-caption strong{display:block;font-size:17px;line-height:1.35;}
.banner-caption span{display:block;margin-top:5px;font-size:13px;line-height:1.5;opacity:.88;}
.banner-link{position:absolute;inset:0;z-index:3;}
.banner-dots{position:absolute;right:14px;bottom:12px;display:flex;gap:6px;z-index:4;}
.banner-dot{width:7px;height:7px;border-radius:999px;background:rgba(255,255,255,.55);transition:.2s;}
.banner-dot.active{width:20px;background:#fff;}
 .notice-ticker{display:flex;align-items:center;gap:10px;height:46px;padding:0 12px;margin-top:12px;border-radius:16px;background:#fff;border:1px solid #eef2f7;box-shadow:0 8px 26px rgba(15,23,42,.045);overflow:hidden;}
.notice-label{flex:0 0 auto;font-size:12px;font-weight:900;color:#0284c7;background:#e0f2fe;border-radius:999px;padding:5px 9px;}
.notice-viewport{position:relative;flex:1;min-width:0;height:46px;overflow:hidden;}
.notice-slide{position:absolute;left:0;right:0;top:0;height:46px;display:flex;align-items:center;text-decoration:none;color:#0f172a;font-size:14px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;transform:translateY(100%);opacity:0;transition:.28s ease;}
.notice-slide.active{transform:translateY(0);opacity:1;}
.notice-slide.prev{transform:translateY(-100%);opacity:0;}
.notice-more{flex:0 0 auto;color:#0284c7;text-decoration:none;font-size:12px;font-weight:800;}

.hot-section-panel{margin:14px 0 18px;}
.hot-section-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;}
.hot-section-card{display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;background:#fff;border:1px solid #eef2f7;border-radius:16px;padding:11px 12px;box-shadow:0 8px 26px rgba(15,23,42,.045);min-width:0;}
.hot-section-icon{width:38px;height:38px;min-width:38px;border-radius:12px;object-fit:cover;border:1px solid #e2e8f0;background:#f8fafc;display:inline-flex;align-items:center;justify-content:center;font-size:20px;font-weight:900;color:#0284c7;}
.hot-section-main{min-width:0;flex:1;}
.hot-section-name{font-size:14px;font-weight:900;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.hot-section-meta{margin-top:3px;color:#94a3b8;font-size:11px;white-space:nowrap;}
@media(max-width:640px){.home-latest-list{margin-left:-12px;margin-right:-12px}.home-latest-card{padding:18px 12px;border-radius:0}.home-feed-head{gap:12px;margin-bottom:13px}.home-feed-author{gap:4px}.home-latest-top{grid-template-columns:auto minmax(0,1fr);gap:8px}.home-latest-badge{padding:4px 9px}.home-latest-section{padding:4px 9px}.home-latest-avatar{width:48px;height:48px;min-width:48px}.home-latest-title{font-size:18px}.home-latest-excerpt{font-size:14px;-webkit-line-clamp:2}.home-feed-images{gap:7px}.home-latest-footer{align-items:center;flex-direction:row}.home-latest-meta{justify-content:flex-end;gap:10px;font-size:13px}.home-section-pill{min-width:0;font-size:13px;padding:7px 14px;max-width:42vw}.hide-mobile{display:none!important;}.hot-section-row{grid-template-columns:repeat(2,minmax(0,1fr));}.hot-section-card{padding:10px;gap:8px}.hot-section-icon{width:34px;height:34px;min-width:34px;border-radius:10px}.hot-section-name{font-size:13px}}
.thread-panel{padding:0;background:transparent!important;border:0!important;box-shadow:none!important;overflow:visible;}
.home-latest-list{display:block;background:var(--card-bg,#fff);border-radius:0;}
.home-latest-card{display:block;color:inherit;background:transparent;border:0;border-radius:0;padding:20px 10px 18px;box-shadow:none;transition:.16s ease;overflow:hidden;border-bottom:1px solid rgba(226,232,240,.78);position:relative;cursor:pointer;}
.home-latest-card:hover{transform:none;background:rgba(2,132,199,.022);}.home-latest-card:last-child{border-bottom:0;}
.home-feed-head{display:flex;gap:13px;align-items:center;margin-bottom:14px;min-height:58px;}
.home-latest-avatar{width:58px;height:58px;min-width:58px;border-radius:50%;background:linear-gradient(135deg,#e0f2fe,#eef2ff);display:grid;place-items:center;color:#0284c7;font-weight:950;font-size:21px;overflow:hidden;box-shadow:0 10px 24px rgba(15,23,42,.08);}
.home-latest-avatar{position:relative;z-index:2;text-decoration:none;}.home-latest-avatar img{width:100%;height:100%;object-fit:cover;display:block;}
.home-feed-author{min-width:0;flex:1;display:flex;flex-direction:column;justify-content:center;gap:5px;}
.home-feed-name-line{display:flex;gap:7px;align-items:center;flex-wrap:wrap;line-height:1.25;}
.home-feed-name{font-size:16px;font-weight:950;color:var(--text-main,#0f172a);text-decoration:none;position:relative;z-index:2;letter-spacing:-.01em;}.role-badge{display:inline-flex;align-items:center;border-radius:999px;background:rgba(2,132,199,.10);color:var(--primary,#0284c7);padding:2px 7px;font-size:11px;font-weight:950;line-height:1.35;}
.home-feed-date{color:var(--text-muted,#94a3b8);font-size:13px;line-height:1.25;}
.home-latest-top{display:grid;grid-template-columns:auto minmax(0,1fr);gap:10px;margin:12px 0 8px;align-items:start;}
.home-latest-section,.home-latest-badge{font-size:12px;font-weight:950;border-radius:9px;padding:5px 10px;background:rgba(2,132,199,.09);color:#0284c7;line-height:1.35;white-space:nowrap;}
.home-latest-badge.top{background:#fff7ed;color:#b45309}.home-latest-badge.featured{background:#fff1db;color:#e56a00}.home-latest-badge.recommended{background:#e0f2fe;color:#0284c7}.home-latest-badge.locked{background:#e2e8f0;color:#475569}
.home-latest-title{font-size:21px;font-weight:950;line-height:1.42;color:var(--text-main,#0f172a)!important;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;letter-spacing:-.025em;text-decoration:none!important;min-width:0;}
.home-latest-title:hover{color:var(--primary,#0284c7)!important;}
.home-latest-excerpt{margin-top:9px;font-size:15px;line-height:1.78;color:var(--text-soft,#64748b)!important;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;text-decoration:none!important;}
.home-feed-images{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:14px;max-width:560px;}
.home-feed-img{position:relative;aspect-ratio:1/1;border-radius:12px;overflow:hidden;background:#f1f5f9;}
.home-feed-img img{width:100%;height:100%;object-fit:cover;display:block;transition:.16s ease;}.home-feed-img:hover img{transform:scale(1.025);}
.home-feed-more{position:absolute;inset:0;background:rgba(15,23,42,.48);color:#fff;display:grid;place-items:center;font-size:28px;font-weight:950;}
.home-latest-footer{display:flex;justify-content:space-between;gap:14px;align-items:center;margin-top:15px;}
.home-section-pill{min-width:110px;text-align:center;border-radius:999px;background:#dbe3ee;color:var(--text-main,#0f172a);font-size:14px;font-weight:950;padding:8px 16px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-decoration:none;display:inline-block;position:relative;z-index:2;box-shadow:inset 0 0 0 1px rgba(148,163,184,.18);}.home-section-pill:hover{color:var(--primary,#0284c7);background:#dbeafe;}
.home-latest-meta{display:flex;gap:16px;flex-wrap:wrap;justify-content:flex-end;font-size:14px;color:var(--text-soft,#64748b);align-items:center;}
.home-latest-meta span{white-space:nowrap;}
.empty-box{color:#94a3b8;text-align:center;padding:28px 12px;font-size:13px;}
.pagination{display:flex;gap:6px;justify-content:center;padding:16px 0 0;flex-wrap:wrap;}
.pagination a{padding:6px 12px;border-radius:8px;background:#f1f5f9;color:#334155;text-decoration:none;font-size:13px;}
.pagination a.active,.pagination a:hover{background:#0284c7;color:#fff;}

.home-desktop-shell{display:block;}
.home-sidebar{display:none;}
.user-card{background:#fff;border:1px solid #eef2f7;border-radius:20px;padding:18px;box-shadow:0 10px 30px rgba(15,23,42,.055);position:sticky;top:82px;}
.user-card-head{display:flex;align-items:center;gap:12px;margin-bottom:14px;}
.user-avatar{width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#0284c7,#6366f1);display:grid;place-items:center;color:#fff;font-size:20px;font-weight:900;overflow:hidden;}
.user-name{font-size:16px;font-weight:900;color:#0f172a;line-height:1.3;}
.user-sub{margin-top:3px;color:#94a3b8;font-size:12px;}
.user-actions{display:grid;gap:9px;margin-top:12px;}
.user-actions a{display:block;text-align:center;text-decoration:none;border-radius:12px;padding:10px 12px;font-size:14px;font-weight:800;}
.user-primary{background:#0284c7;color:#fff;}.user-secondary{background:#f1f5f9;color:#334155;}.user-link{background:#f8fafc;color:#0284c7;}
.side-nav{margin-top:14px;border-top:1px solid #f1f5f9;padding-top:12px;display:grid;gap:8px;}
.side-nav a{display:flex;justify-content:space-between;align-items:center;text-decoration:none;color:#334155;background:#f8fafc;border-radius:12px;padding:10px 12px;font-size:13px;font-weight:800;}
.side-nav a span{color:#94a3b8;}
.side-nav a.plugin-entry{background:linear-gradient(135deg,rgba(245,158,11,.14),rgba(239,68,68,.10));color:#92400e;border:1px solid rgba(245,158,11,.22);}
.side-nav a.plugin-entry svg{width:18px;height:18px;margin-right:8px;flex:0 0 18px;color:#d97706;}
.side-nav a.plugin-entry .side-nav-label{display:inline-flex;align-items:center;min-width:0;}
.side-nav a.plugin-entry.is-active{background:linear-gradient(135deg,#f59e0b,#ef4444);color:#fff;box-shadow:0 10px 24px rgba(245,158,11,.20);}
.side-nav a.plugin-entry.is-active span,.side-nav a.plugin-entry.is-active svg{color:#fff;}

@media(min-width:900px){
  .home-page{padding-top:24px;padding-bottom:36px;max-width:1180px;}
  .home-desktop-shell{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:22px;align-items:start;}
  .home-sidebar{display:block;}
  .banner-panel{height:320px;}
  .thread-panel{padding:18px 22px;}

}
@media(max-width:640px){.hide-mobile{display:none!important;}
  .home-page{padding-left:12px;padding-right:12px;}
  .banner-panel{height:176px;border-radius:18px;}
  .thread-panel{border-radius:18px;padding:14px;}
  .notice-ticker{height:42px;border-radius:14px;margin-top:10px;}
  .notice-viewport,.notice-slide{height:42px;}

  .banner-caption{padding:36px 14px 13px;}
  .banner-caption strong{font-size:15px;}
}

html[data-theme="dark"] .hot-section-card{background:#111827!important;border-color:#263244!important;}
html[data-theme="dark"] .hot-section-name{color:#e5e7eb!important;}
html[data-theme="dark"] .hot-section-meta{color:#94a3b8!important;}
html[data-theme="dark"] .thread-panel{background:transparent!important;border-color:transparent!important;}
html[data-theme="dark"] .home-latest-list{background:#111827!important;}
html[data-theme="dark"] .home-latest-card{background:transparent!important;border-color:#263244!important;box-shadow:none!important;}
html[data-theme="dark"] .home-latest-card:hover{background:rgba(125,211,252,.04)!important;}
html[data-theme="dark"] .home-latest-avatar,html[data-theme="dark"] .home-feed-img{background:#0f172a!important;color:#7dd3fc!important;}html[data-theme="dark"] .home-section-pill{background:#1e293b!important;color:#e5e7eb!important;}html[data-theme="dark"] .home-section-pill:hover{background:#1e3a8a!important;color:#bfdbfe!important;}html[data-theme="dark"] .home-latest-title{color:#e5e7eb!important;}html[data-theme="dark"] .home-latest-excerpt{color:#cbd5e1!important;}html[data-theme="dark"] .home-latest-title:hover{color:#7dd3fc!important;}
html[data-theme="dark"] .empty-box{color:#94a3b8!important;}
@media(max-width:640px){.home-section-title{margin-bottom:10px}.hot-section-panel{margin-top:20px}.hot-section-row{gap:10px}.hot-section-card{min-height:60px}.notice-more{display:none}.notice-ticker{padding-right:10px}}

/* Latest feed polish 2026-05-06 */
.thread-panel .home-section-title{margin-bottom:8px;padding:0 4px 8px!important;border-bottom:1px solid rgba(226,232,240,.72)}
.thread-panel .home-section-title h2{display:inline-flex;align-items:center;gap:9px;font-size:19px;letter-spacing:-.03em}

.home-latest-list{background:transparent;border-radius:18px;overflow:visible}
.home-latest-card{padding:22px 16px 20px;border-bottom:1px solid rgba(226,232,240,.68);border-radius:18px;background:linear-gradient(180deg,rgba(255,255,255,.72),rgba(255,255,255,.38));isolation:isolate}
.home-latest-card::before{content:'';position:absolute;inset:10px auto 10px 0;width:3px;border-radius:99px;background:transparent;transition:.16s ease}
.home-latest-card:hover{background:linear-gradient(180deg,rgba(248,252,255,.95),rgba(248,250,252,.62));box-shadow:0 14px 34px rgba(15,23,42,.055)}
.home-latest-card:hover::before{background:linear-gradient(180deg,#0284c7,#38bdf8)}
.home-feed-head{gap:12px;margin-bottom:12px;min-height:48px}
.home-latest-avatar{width:48px;height:48px;min-width:48px;font-size:18px;box-shadow:0 10px 22px rgba(2,132,199,.10);border:2px solid rgba(255,255,255,.9)}
.home-feed-author{gap:4px;align-self:center}
.home-feed-name-line{gap:6px;align-items:center}
.home-feed-name{font-size:15px;letter-spacing:-.015em}
.home-feed-date{font-size:12px;color:var(--text-muted,#94a3b8)}
.home-latest-top{grid-template-columns:auto minmax(0,1fr);gap:9px;margin:8px 0 7px;align-items:center}
.home-latest-top > div:first-child:empty{display:none}
.home-latest-top:has(> div:first-child:empty){grid-template-columns:minmax(0,1fr)}
.home-latest-badge{border-radius:999px;padding:4px 9px;font-size:11px;box-shadow:inset 0 0 0 1px rgba(255,255,255,.55)}
.home-latest-title{font-size:19px;line-height:1.48;letter-spacing:-.02em;font-weight:950}
.home-latest-excerpt{margin-top:7px;font-size:14px;line-height:1.75;color:var(--text-soft,#64748b)!important;max-width:760px}
.home-feed-images{gap:7px;margin-top:13px;max-width:520px}
.home-feed-img{border-radius:14px;background:linear-gradient(135deg,#f1f5f9,#e0f2fe);box-shadow:inset 0 0 0 1px rgba(255,255,255,.55)}
.home-feed-images:has(.home-feed-img:first-child:last-child){grid-template-columns:minmax(0,360px)}
.home-feed-images:has(.home-feed-img:nth-child(2):last-child){grid-template-columns:repeat(2,minmax(0,180px))}
.home-latest-footer{margin-top:14px;padding-top:12px;border-top:1px dashed rgba(226,232,240,.72);align-items:center}
.home-section-pill{min-width:0;max-width:220px;background:rgba(15,23,42,.055);color:var(--text-soft,#64748b);font-size:13px;padding:7px 13px;box-shadow:none}
.home-section-pill:hover{background:rgba(2,132,199,.10);color:var(--primary,#0284c7)}
.home-latest-meta{gap:8px;font-size:12px;color:var(--text-muted,#94a3b8)}
.home-latest-meta span{display:inline-flex;align-items:center;height:28px;border-radius:999px;background:rgba(15,23,42,.045);padding:0 10px;color:var(--text-soft,#64748b);font-weight:800}
html[data-theme="dark"] .thread-panel .home-section-title{border-color:#263244}
html[data-theme="dark"] .home-latest-card{background:linear-gradient(180deg,rgba(17,24,39,.58),rgba(17,24,39,.20))!important;border-color:#263244!important}
html[data-theme="dark"] .home-latest-card:hover{background:linear-gradient(180deg,rgba(15,23,42,.86),rgba(17,24,39,.56))!important;box-shadow:0 14px 34px rgba(0,0,0,.22)!important}
html[data-theme="dark"] .home-latest-avatar{border-color:#1e293b;box-shadow:0 10px 24px rgba(0,0,0,.22)}
html[data-theme="dark"] .home-latest-footer{border-color:#263244}
html[data-theme="dark"] .home-latest-meta span,html[data-theme="dark"] .home-section-pill{background:#0f172a!important;color:#cbd5e1!important}
@media(max-width:640px){.thread-panel .home-section-title{padding:0 0 8px!important;margin-bottom:2px}.thread-panel .home-section-title h2{font-size:18px}.home-latest-list{margin-left:0;margin-right:0}.home-latest-card{padding:18px 2px 17px;border-radius:0}.home-latest-card::before{display:none}.home-feed-head{min-height:44px;margin-bottom:10px}.home-latest-avatar{width:42px;height:42px;min-width:42px;font-size:16px}.home-feed-name{font-size:14px}.home-feed-date{font-size:12px}.home-latest-title{font-size:17px;line-height:1.5}.home-latest-excerpt{font-size:13px;line-height:1.7;margin-top:6px}.home-feed-images{max-width:none;grid-template-columns:repeat(3,1fr);gap:6px}.home-feed-img{border-radius:11px}.home-latest-footer{gap:10px;margin-top:12px;padding-top:10px}.home-section-pill{max-width:38vw;font-size:12px;padding:6px 10px}.home-latest-meta{gap:6px;font-size:11px}.home-latest-meta span{height:25px;padding:0 8px}}


/* Latest feed rounder polish 2026-05-06 */
.thread-panel .home-section-title{border-bottom:0!important;margin-bottom:10px!important;padding:0 4px!important}

.home-latest-list{display:grid;gap:12px;background:transparent!important;border-radius:0!important}
.home-latest-card{padding:20px 18px 18px!important;border:1px solid rgba(226,232,240,.72)!important;border-radius:24px!important;background:linear-gradient(180deg,rgba(255,255,255,.94),rgba(255,255,255,.82))!important;box-shadow:0 10px 30px rgba(15,23,42,.045)!important;border-bottom-color:rgba(226,232,240,.72)!important}
.home-latest-card::before{display:none!important}
.home-latest-card:hover{background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(248,252,255,.92))!important;box-shadow:0 16px 40px rgba(15,23,42,.07)!important;transform:translateY(-1px)}
.home-feed-head{min-height:44px;margin-bottom:12px}.home-latest-avatar{width:44px;height:44px;min-width:44px;border-radius:50%;box-shadow:0 8px 18px rgba(15,23,42,.07);border:1px solid rgba(226,232,240,.9)}
.home-feed-name{font-size:15px;font-weight:900;color:#b45309}.home-feed-date{font-size:13px;color:#b7bec9}.role-badge{border-radius:8px;padding:2px 6px;font-size:10px;background:linear-gradient(90deg,rgba(251,191,36,.14),rgba(236,72,153,.12));color:#d97706}
.home-latest-top{margin:10px 0 7px;gap:10px}.home-latest-badge{border-radius:9px;background:#f7f4f2!important;color:#b45309!important;font-size:14px;padding:6px 12px}.home-latest-title{font-size:21px!important;line-height:1.42;font-weight:850;letter-spacing:-.02em}.home-latest-excerpt{font-size:17px!important;line-height:1.72!important;color:#8b8f99!important;-webkit-line-clamp:2;margin-top:8px!important}

/* Simplified footer meta icons 2026-05-30: avoid raw inline SVG data URLs breaking subsequent CSS parsing */
.home-latest-footer{border-top:0!important;margin-top:16px!important;padding-top:0!important}
.home-section-pill{border-radius:999px;background:#f0f1f3!important;color:#4b5563!important;font-size:15px;font-weight:950;padding:9px 22px;min-width:150px;max-width:240px;text-align:center}
.home-latest-meta{gap:15px}
.home-latest-meta span{position:relative;display:inline-flex!important;align-items:center;gap:5px;background:transparent!important;padding:0!important;height:auto!important;color:#8b8f99!important;font-size:15px;font-weight:500;line-height:1}
.home-latest-meta span::before{content:'';display:inline-flex;width:16px;height:16px;background-color:#a3a8b1;flex:0 0 auto;-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;-webkit-mask-position:center;mask-position:center;-webkit-mask-size:contain;mask-size:contain}
.home-latest-meta span:nth-child(1)::before{-webkit-mask-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12z'/%3E%3Ccircle cx='12' cy='12' r='3'/%3E%3C/svg%3E");mask-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12z'/%3E%3Ccircle cx='12' cy='12' r='3'/%3E%3C/svg%3E")}
.home-latest-meta span:nth-child(2)::before{-webkit-mask-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M21 11.5a8.38 8.38 0 0 1-9 8.3 8.5 8.5 0 0 1-3.8-.9L3 20l1.1-3.3A8.38 8.38 0 0 1 3 11.5a8.5 8.5 0 0 1 9-8.3 8.38 8.38 0 0 1 9 8.3z'/%3E%3C/svg%3E");mask-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M21 11.5a8.38 8.38 0 0 1-9 8.3 8.5 8.5 0 0 1-3.8-.9L3 20l1.1-3.3A8.38 8.38 0 0 1 3 11.5a8.5 8.5 0 0 1 9-8.3 8.38 8.38 0 0 1 9 8.3z'/%3E%3C/svg%3E")}
.home-latest-meta span:nth-child(3)::before{-webkit-mask-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M20.8 5.6a5.5 5.5 0 0 0-7.8 0L12 6.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 22l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z'/%3E%3C/svg%3E");mask-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M20.8 5.6a5.5 5.5 0 0 0-7.8 0L12 6.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 22l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z'/%3E%3C/svg%3E")}


/* Latest feed correction 2026-05-06: restore name/badge and reduce image whitespace */
.home-feed-name{color:var(--text-main,#0f172a)!important;font-weight:950!important;font-size:15px!important}
.home-feed-name:hover{color:var(--primary,#0284c7)!important}
.home-feed-name-line .role-badge{display:inline-flex!important;align-items:center!important;border-radius:999px!important;background:rgba(2,132,199,.10)!important;color:var(--primary,#0284c7)!important;padding:2px 7px!important;font-size:11px!important;font-weight:950!important;line-height:1.35!important}


html[data-theme="dark"] .home-feed-name{color:#e5e7eb!important}html[data-theme="dark"] .home-feed-name:hover{color:#7dd3fc!important}html[data-theme="dark"] .home-feed-name-line .role-badge{background:rgba(56,189,248,.14)!important;color:#7dd3fc!important}

.top-title-panel{margin:6px 0 18px;border-top:1px solid rgba(226,232,240,.86);border-bottom:1px solid rgba(226,232,240,.86);padding:8px 0}.top-title-list{display:grid;gap:0}.top-title-item{display:flex;align-items:center;gap:8px;min-width:0;padding:8px 2px;color:var(--text-main,#0f172a);text-decoration:none;font-size:15px;font-weight:850;line-height:1.45;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.top-title-item span{flex:0 0 auto;color:#dc2626;font-size:12px;font-weight:950}.top-title-item:hover{color:var(--primary,#0284c7)}html[data-theme="dark"] .top-title-panel{border-color:#263244}html[data-theme="dark"] .top-title-item{color:#e5e7eb}html[data-theme="dark"] .top-title-item:hover{color:#7dd3fc}@media(max-width:640px){.top-title-panel{margin:4px 0 14px}.top-title-item{font-size:14px;padding:7px 0}}

/* mobile feed width refinement */
@media(max-width:640px){.home-page.container{padding-left:6px!important;padding-right:6px!important}.thread-panel{padding-left:0!important;padding-right:0!important}.thread-panel .home-section-title{padding-left:0!important;padding-right:0!important}.thread-panel .home-section-title h2::before{display:none!important}.thread-card-v2.home-latest-card{padding-left:10px!important;padding-right:10px!important;border-radius:18px!important}.thread-card-images.single .thread-card-img{aspect-ratio:16/8!important}.thread-card-images.two,.thread-card-images.multi{gap:6px!important}}

/* Desktop-only restored layout: keep mobile old layout untouched */
.home-desktop-v2{display:none}
@media(min-width:769px){
  .home-page>.home-desktop-shell{display:none!important}
  .home-desktop-v2{display:grid;grid-template-columns:260px minmax(0,1fr) 260px;gap:18px;align-items:start;max-width:1320px;margin:0 auto}
  .home-v2-left,.home-v2-right{position:sticky;top:76px;display:grid;gap:14px}.home-v2-main{min-width:0;display:grid;gap:14px}
  .home-v2-panel{background:var(--card-bg,#fff);border:1px solid rgba(226,232,240,.86);border-radius:18px;box-shadow:0 10px 30px rgba(15,23,42,.045);overflow:hidden}.home-v2-pad{padding:15px}.home-v2-title{margin:0 0 12px;font-size:15px;font-weight:950;color:var(--text-main,#0f172a);display:flex;justify-content:space-between;align-items:center}.home-v2-title a{font-size:12px;color:var(--primary,#0284c7);text-decoration:none}
  .home-v2-broadcast{height:154px;position:relative;border-radius:18px;overflow:hidden;background:linear-gradient(135deg,#0284c7,#6366f1)}.home-v2-broadcast .banner-item{position:absolute;inset:0;opacity:0;transition:opacity .28s ease}.home-v2-broadcast .banner-item.active{opacity:1}.home-v2-broadcast img{width:100%;height:100%;object-fit:cover}.home-v2-broadcast .banner-caption{padding:42px 12px 11px}.home-v2-broadcast .banner-caption strong{font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.home-v2-broadcast .banner-caption span{font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.home-v2-broadcast .banner-dots{right:10px;top:10px;bottom:auto}.home-v2-broadcast .banner-dot{width:6px;height:6px}.home-v2-broadcast .banner-dot.active{width:16px}
  .home-v2-section-list{display:grid;gap:5px}.home-v2-section{display:grid;grid-template-columns:32px minmax(0,1fr) auto;gap:9px;align-items:center;padding:8px;border-radius:13px;text-decoration:none;color:inherit}.home-v2-section:hover{background:var(--input-bg,#f8fafc)}.home-v2-section .hot-section-icon{width:32px;height:32px;min-width:32px;border-radius:10px}.home-v2-section-name{font-size:13px;font-weight:950;color:var(--text-main,#0f172a);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.home-v2-count{font-size:11px;color:var(--text-muted,#94a3b8);font-weight:900}.home-v2-more{margin-top:8px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:12px;background:var(--input-bg,#f8fafc);color:var(--primary,#0284c7);font-size:12px;font-weight:950;text-decoration:none}
  .home-v2-feed-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px 10px}.home-v2-feed-title{font-size:18px;font-weight:950;color:var(--text-main,#0f172a)}.home-v2-main .home-feed-tabs{margin:0;padding:0}.home-v2-main .thread-panel{padding:0;background:transparent!important;border:0!important;box-shadow:none!important}.home-v2-main .home-latest-list{display:grid;gap:12px;background:transparent!important}.home-v2-main .home-latest-card{border-radius:18px!important;border:1px solid rgba(226,232,240,.78)!important;background:var(--card-bg,#fff)!important;box-shadow:0 8px 26px rgba(15,23,42,.04)!important;padding:17px 18px!important}.home-v2-main .home-latest-card:hover{transform:translateY(-1px);box-shadow:0 14px 34px rgba(15,23,42,.065)!important}.home-v2-main .home-latest-title{font-size:19px!important}.home-v2-main .home-latest-excerpt{font-size:14px!important;line-height:1.7!important}.home-v2-main .home-section-title{display:none}.home-v2-main .home-top-strip{padding:0 0 10px}
  .home-v2-user .user-card{position:static;box-shadow:none;border:0;padding:0}.home-v2-user .side-nav a{background:var(--input-bg,#f8fafc)}.home-v2-stats{display:grid;grid-template-columns:1fr 1fr;gap:8px}.home-v2-stat{background:var(--input-bg,#f8fafc);border-radius:14px;padding:11px;text-align:center}.home-v2-stat strong{display:block;font-size:18px;color:var(--text-main,#0f172a)}.home-v2-stat span{display:block;margin-top:4px;font-size:11px;color:var(--text-muted,#94a3b8)}
  html[data-theme="dark"] .home-v2-panel,html[data-theme="dark"] .home-v2-main .home-latest-card{background:#111827!important;border-color:#263244!important;box-shadow:0 10px 30px rgba(0,0,0,.22)!important}html[data-theme="dark"] .home-v2-section:hover,html[data-theme="dark"] .home-v2-more,html[data-theme="dark"] .home-v2-stat,html[data-theme="dark"] .home-v2-user .side-nav a{background:#0f172a;color:#cbd5e1}
}
@media(min-width:769px) and (max-width:1180px){.home-desktop-v2{grid-template-columns:230px minmax(0,1fr) 240px;gap:14px}}

/* Broadcast mobile fix 2026-05-10: scoped carousel + no oversized controls */
.banner-panel.is-single .banner-dots,.home-v2-broadcast.is-single .banner-dots{display:none!important}
.banner-panel .banner-item.active,.home-v2-broadcast .banner-item.active{display:block!important;opacity:1!important;z-index:1}
.banner-panel .banner-item:not(.active),.home-v2-broadcast .banner-item:not(.active){pointer-events:none}
@media(max-width:640px){.banner-panel{overflow:hidden!important}.banner-panel .banner-dots{right:12px!important;bottom:10px!important;left:auto!important;top:auto!important;transform:none!important}.banner-panel .banner-dot{width:6px!important;height:6px!important}.banner-panel .banner-dot.active{width:16px!important}.banner-panel .banner-caption:empty{display:none!important}}

/* UI/UX Pro Max homepage polish 2026-05-10: restrained, tab-safe, mobile-first */
.home-feed-tabs[role="tablist"]{align-items:center;gap:7px;scroll-padding-inline:6px}
.home-feed-tab[role="tab"]{min-height:36px;touch-action:manipulation;outline:none;border:1px solid transparent;box-shadow:none}
.home-feed-tab[role="tab"]:focus-visible{border-color:rgba(2,132,199,.55);box-shadow:0 0 0 3px rgba(2,132,199,.16)}
.home-feed-tab[aria-selected="true"]{background:var(--primary,#0284c7);color:#fff}
.home-v2-feed-head{position:sticky;top:64px;z-index:3;background:color-mix(in srgb,var(--bg-main,#f6f8fb) 88%,transparent);backdrop-filter:blur(14px);border-bottom:1px solid rgba(226,232,240,.72)}
.home-v2-feed-title{letter-spacing:-.03em}.home-v2-main .home-feed-tabs{max-width:100%;overflow-x:auto;scrollbar-width:none}.home-v2-main .home-feed-tabs::-webkit-scrollbar{display:none}
.home-v2-panel,.home-latest-card,.hot-section-card,.notice-ticker{transition:box-shadow .18s ease,border-color .18s ease,background .18s ease,transform .18s ease}
.home-v2-panel:hover,.hot-section-card:hover{border-color:rgba(2,132,199,.22)}
.user-actions{grid-template-columns:1fr 1fr}.user-actions .user-primary{grid-column:1/-1}.user-actions a{min-height:40px;display:flex!important;align-items:center;justify-content:center;padding:0 12px!important;border-radius:12px!important}
.home-v2-user .side-nav a,.home-v2-more{min-height:38px;touch-action:manipulation}.side-nav a:focus-visible,.home-v2-more:focus-visible,.user-actions a:focus-visible,.home-more:focus-visible{outline:3px solid rgba(2,132,199,.18);outline-offset:2px}
@media(max-width:640px){.home-feed-tabs[role="tablist"]{padding:2px 2px 10px;gap:7px}.home-feed-tab[role="tab"]{min-height:36px;border-radius:999px}.home-section-title h2{font-size:17px}.notice-label{padding:5px 8px}.banner-panel{box-shadow:0 10px 26px rgba(15,23,42,.10)}.home-latest-card{padding-top:18px!important;padding-bottom:18px!important}.home-latest-footer{gap:10px}.home-latest-meta{font-size:12px;gap:9px}.home-section-pill{padding:7px 12px!important}}
@media(prefers-reduced-motion:reduce){.home-feed-tab,.home-v2-panel,.home-latest-card,.hot-section-card,.notice-slide,.banner-item{transition:none!important}}

/* Emergency homepage layout guard 2026-05-30: desktop v2 must hide legacy mobile shell */
@media(min-width:1024px){
  .home-page>.home-desktop-shell{display:none!important;}
  .home-page>.home-desktop-v2{display:grid!important;}
}
@media(max-width:1023px){
  .home-page>.home-desktop-v2{display:none!important;}
  .home-page>.home-desktop-shell{display:block!important;}
}



/* Forum broadcast carousel restore 2026-05-30: old forum banner, never software-store card */
.home-page .banner-panel{position:relative!important;background:linear-gradient(135deg,#e0f2fe,#f8fafc)!important;border:1px solid #eef2f7!important;box-shadow:0 10px 30px rgba(15,23,42,.055)!important;overflow:hidden!important;}
.home-page .banner-panel .banner-item{position:absolute!important;inset:0!important;display:block!important;opacity:0!important;transform:none!important;transition:opacity .28s ease!important;pointer-events:none!important;padding:0!important;}
.home-page .banner-panel .banner-item.active{opacity:1!important;pointer-events:auto!important;z-index:1!important;}
.home-page .banner-panel .banner-item>img{width:100%!important;height:100%!important;object-fit:cover!important;display:block!important;filter:none!important;transform:none!important;border-radius:0!important;}
.home-page .banner-panel .banner-placeholder{height:100%!important;display:flex!important;flex-direction:column!important;justify-content:center!important;padding:24px!important;background:linear-gradient(135deg,#0284c7,#4f46e5)!important;color:#fff!important;}
.home-page .banner-panel .banner-caption{position:absolute!important;left:0!important;right:0!important;bottom:0!important;z-index:2!important;padding:44px 18px 16px!important;background:linear-gradient(180deg,transparent,rgba(15,23,42,.76))!important;color:#fff!important;display:block!important;}
.home-page .banner-panel .banner-caption strong{display:block!important;font-size:17px!important;line-height:1.35!important;color:#fff!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;}
.home-page .banner-panel .banner-caption span{display:block!important;margin-top:5px!important;font-size:13px!important;line-height:1.5!important;opacity:.88!important;color:#fff!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;}
.home-page .banner-panel .banner-link{position:absolute!important;inset:0!important;z-index:3!important;background:transparent!important;border:0!important;display:block!important;}
.home-page .banner-panel .banner-dots{position:absolute!important;right:14px!important;bottom:12px!important;left:auto!important;top:auto!important;display:flex!important;gap:6px!important;z-index:4!important;transform:none!important;}
.home-page .banner-panel .banner-dot{width:7px!important;height:7px!important;border-radius:999px!important;background:rgba(255,255,255,.55)!important;transition:.2s!important;}
.home-page .banner-panel .banner-dot.active{width:20px!important;background:#fff!important;}
.home-page .home-v2-broadcast{height:154px!important;border-radius:18px!important;}
@media(min-width:900px){.home-page .home-desktop-shell .banner-panel{height:320px!important;}}
@media(max-width:640px){.home-page .home-desktop-shell .banner-panel{height:176px!important;border-radius:18px!important;}.home-page .banner-panel .banner-caption{padding:36px 14px 13px!important}.home-page .banner-panel .banner-caption strong{font-size:15px!important}}
</style>

</head>
<body>
<?php require __DIR__ . '/../layouts/topbar.php'; ?>

<main class="home-page container">
  <div class="home-desktop-v2">
    <aside class="home-v2-left">
      <?php if (!empty($banners)): ?><section class="home-v2-panel home-v2-broadcast"><?php foreach ($banners as $i => $banner): ?><?php home_broadcast_item($banner, $i); ?><?php endforeach; ?><?php if (count($banners) > 1): ?><div class="banner-dots"><?php foreach ($banners as $i => $_b): ?><span class="banner-dot <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>"></span><?php endforeach; ?></div><?php endif; ?></section><?php endif; ?>
      <section class="home-v2-panel home-v2-pad"><h2 class="home-v2-title">热门板块 <a href="/index.php?path=sections">全部</a></h2><div class="home-v2-section-list"><a class="home-v2-section" href="/index.php"><span class="hot-section-icon hot-section-text">⌂</span><span class="home-v2-section-name">首页</span><span class="home-v2-count">›</span></a><?php foreach (array_slice($hotSections ?: ($sections ?? []), 0, 5) as $s): ?><a class="home-v2-section" href="/index.php?path=section&id=<?= (int)$s['id'] ?>"><?= home_section_icon($s['icon'] ?? '') ?><span class="home-v2-section-name"><?= htmlspecialchars($s['name']) ?></span><span class="home-v2-count"><?= (int)($s['thread_count'] ?? 0) ?></span></a><?php endforeach; ?></div><a class="home-v2-more" href="/index.php?path=sections">查看更多板块</a></section>
    </aside>
    <section class="home-v2-main"><section class="thread-panel"><div class="home-v2-feed-head"><div class="home-v2-feed-title"><?= htmlspecialchars($feedLabels[$feed] ?? '最新') ?>帖子</div><nav class="home-feed-tabs" role="tablist" aria-label="首页信息流切换"><?php foreach ($feedLabels as $feedKey => $feedLabel): ?><?php if ($feedKey === 'following' && (!function_exists('auth_check') || !auth_check())): ?><a class="home-feed-tab" role="tab" aria-selected="false" href="/index.php?path=login">关注</a><?php else: ?><a class="home-feed-tab <?= $feed === $feedKey ? 'active' : '' ?>" role="tab" aria-selected="<?= $feed === $feedKey ? 'true' : 'false' ?>" href="<?= htmlspecialchars($feedUrl($feedKey)) ?>"><?= htmlspecialchars($feedLabel) ?></a><?php endif; ?><?php endforeach; ?></nav></div><?php if (!empty($topThreads)): ?><div class="home-top-strip"><?= render_top_thread_strip($topThreads, 'home') ?></div><?php endif; ?><?php if (!empty($threads)): ?><div class="home-latest-list"><?php foreach ($threads as $thread): ?><?= render_thread_card($thread) ?><?php endforeach; ?></div><?php else: ?><div class="empty-box">暂无帖子</div><?php endif; ?><?php if (isset($totalPages) && $totalPages > 1): ?><div class="pagination"><?php for ($pp = 1; $pp <= $totalPages; $pp++): ?><a href="<?= htmlspecialchars($feedUrl($feed, $pp)) ?>" class="<?= $pp === $page ? 'active' : '' ?>"><?= $pp ?></a><?php endfor; ?></div><?php endif; ?></section></section>
    <aside class="home-v2-right"><section class="home-v2-panel home-v2-pad home-v2-user"><?php $u = $_SESSION['auth_user'] ?? null; $displayName = $u ? (($u['nickname'] ?? '') ?: ($u['username'] ?? '用户')) : '游客'; $avatar = $u['avatar'] ?? ''; ?><div class="user-card"><div class="user-card-head"><?= user_avatar_html($u ?: ['nickname'=>$displayName, 'avatar'=>$avatar], 'user-avatar', 52) ?><div><div class="user-name"><?= htmlspecialchars($displayName) ?></div><div class="user-sub"><?= $u ? '欢迎回来' : '登录后参与讨论' ?></div></div></div><div class="user-actions"><?php if ($u): ?><a class="user-primary" href="/index.php?path=publish">发布帖子</a><a class="user-secondary" href="/index.php?path=me">个人中心</a><?php else: ?><a class="user-primary" href="/index.php?path=login">登录</a><a class="user-secondary" href="/index.php?path=register">注册账号</a><?php endif; ?></div><div class="side-nav"><?= \App\Core\Hook::filter('web.sidebar.nav.before', '') ?><a href="/index.php?path=sections">全部板块 <span>›</span></a><a href="/index.php?path=search">搜索帖子 <span>›</span></a><a href="/index.php?path=announcements">公告中心 <span>›</span></a><?= \App\Core\Hook::filter('web.sidebar.nav.after', '') ?></div></div></section><section class="home-v2-panel home-v2-pad"><h2 class="home-v2-title">社区概览</h2><div class="home-v2-stats"><div class="home-v2-stat"><strong><?= (int)($total ?? 0) ?></strong><span>当前筛选</span></div><div class="home-v2-stat"><strong><?= count($sections ?? []) ?></strong><span>板块</span></div></div></section></aside>
  </div>

  <div class="home-desktop-shell">
  <div class="home-top-grid">
    <section class="panel banner-panel">
      <?php if (!empty($banners)): ?>
        <?php foreach ($banners as $i => $banner): ?><?php home_broadcast_item($banner, $i); ?><?php endforeach; ?>
        <?php if (count($banners) > 1): ?><div class="banner-dots"><?php foreach ($banners as $i => $_b): ?><span class="banner-dot <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>"></span><?php endforeach; ?></div><?php endif; ?>
      <?php else: ?>
        <div class="banner-placeholder"><strong><?= htmlspecialchars($site['site_name'] ?? 'ClayBBS') ?></strong><div><?= htmlspecialchars($site['site_tagline'] ?? '一个轻量、可持续迭代的社区论坛系统。') ?></div></div>
      <?php endif; ?>
    </section>

    <?php if (!empty($announcements)): ?>
    <section class="notice-ticker">
      <span class="notice-label">公告</span>
      <div class="notice-viewport">
        <?php foreach (array_slice($announcements, 0, 6) as $i => $notice): ?>
          <?php $noticeUrl = trim((string)($notice['url'] ?? '')); $noticeLink = $noticeUrl !== '' ? $noticeUrl : ('/index.php?path=announcement&id=' . (int)$notice['id']); ?>
          <a class="notice-slide <?= $i === 0 ? 'active' : '' ?>" href="<?= htmlspecialchars($noticeLink) ?>"><?= !empty($notice['is_pinned']) ? '【置顶】' : '' ?><?= htmlspecialchars($notice['title']) ?></a>
        <?php endforeach; ?>
      </div>
      <a class="notice-more" href="/index.php?path=announcements">更多</a>
    </section>
    <?php endif; ?>
  </div>



  <?php if (!empty($hotSections)): ?>
  <section class="hot-section-panel">
    <div class="home-section-title"><h2>热门板块</h2></div>
    <div class="hot-section-row">
      <?php foreach (array_slice($hotSections, 0, 4) as $s): ?>
        <a class="hot-section-card" href="/index.php?path=section&id=<?= (int)$s['id'] ?>">
          <?= home_section_icon($s['icon'] ?? '') ?>
          <div class="hot-section-main">
            <div class="hot-section-name"><?= htmlspecialchars($s['name']) ?></div>
            <div class="hot-section-meta"><?= (int)($s['thread_count'] ?? 0) ?> 帖子</div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="thread-panel">
    <div class="home-section-title" style="padding:0 2px 0;"><h2><?= htmlspecialchars($feedLabels[$feed] ?? '最新') ?>帖子</h2></div>
    <nav class="home-feed-tabs" role="tablist" aria-label="首页信息流切换">
      <?php foreach ($feedLabels as $feedKey => $feedLabel): ?>
        <?php if ($feedKey === 'following' && (!function_exists('auth_check') || !auth_check())): ?>
          <a class="home-feed-tab" role="tab" aria-selected="false" href="/index.php?path=login">关注</a>
        <?php else: ?>
          <a class="home-feed-tab <?= $feed === $feedKey ? 'active' : '' ?>" role="tab" aria-selected="<?= $feed === $feedKey ? 'true' : 'false' ?>" href="<?= htmlspecialchars($feedUrl($feedKey)) ?>"><?= htmlspecialchars($feedLabel) ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <?php if (!empty($topThreads)): ?><?= render_top_thread_strip($topThreads, 'home') ?><?php endif; ?>
    <?php if (!empty($threads)): ?>
      <div class="home-latest-list">
        <?php foreach ($threads as $thread): ?><?= render_thread_card($thread) ?><?php endforeach; ?>
      </div>
    <?php else: ?><div class="empty-box">暂无帖子</div><?php endif; ?>
    <?php if (isset($totalPages) && $totalPages > 1): ?>
      <div class="pagination"><?php for ($pp = 1; $pp <= $totalPages; $pp++): ?><a href="<?= htmlspecialchars($feedUrl($feed, $pp)) ?>" class="<?= $pp === $page ? 'active' : '' ?>"><?= $pp ?></a><?php endfor; ?></div>
    <?php endif; ?>
  </section>
  <aside class="home-sidebar">
    <?php $u = $_SESSION['auth_user'] ?? null; $displayName = $u ? (($u['nickname'] ?? '') ?: ($u['username'] ?? '用户')) : '游客'; $avatar = $u['avatar'] ?? ''; ?>
    <div class="user-card">
      <div class="user-card-head">
        <?= user_avatar_html($u ?: ['nickname'=>$displayName, 'avatar'=>$avatar], 'user-avatar', 52) ?>
        <div><div class="user-name"><?= htmlspecialchars($displayName) ?></div><div class="user-sub"><?= $u ? '欢迎回来' : '登录后参与讨论' ?></div></div>
      </div>
      <div class="user-actions">
        <?php if ($u): ?>
          <a class="user-primary" href="/index.php?path=publish">发布帖子</a>
          <a class="user-secondary" href="/index.php?path=me">个人中心</a>
        <?php else: ?>
          <a class="user-primary" href="/index.php?path=login">登录</a>
          <a class="user-secondary" href="/index.php?path=register">注册账号</a>
        <?php endif; ?>
      </div>
      <div class="side-nav">
        <a href="/index.php?path=sections">全部板块 <span>›</span></a>
        <a href="/index.php?path=search">搜索帖子 <span>›</span></a>
        <a href="/index.php?path=announcements">公告中心 <span>›</span></a>
      </div>
    </div>
  </aside></div>
</main>

<?php require __DIR__ . '/../../layouts/theme-toggle.php'; ?>
<?php require __DIR__ . '/../layouts/bottom-nav.php'; ?>

<div class="broadcast-modal" id="broadcastModal" aria-hidden="true">
  <div class="broadcast-modal-backdrop" data-broadcast-close></div>
  <article class="broadcast-modal-card" role="dialog" aria-modal="true" aria-label="转播详情">
    <button type="button" class="broadcast-modal-close" data-broadcast-close aria-label="关闭">×</button>
    <div class="broadcast-modal-image"><img alt=""></div>
    <div class="broadcast-modal-content">
      <span class="broadcast-modal-label">现已推出</span>
      <h2></h2>
      <p></p>
      <div class="broadcast-modal-divider"></div>
      <div class="broadcast-modal-bottom">
        <div class="broadcast-app-icon"><img alt=""></div>
        <div class="broadcast-app-copy"><strong></strong><span></span></div>
        <a class="broadcast-modal-cta" href="#">获取</a>
      </div>
    </div>
  </article>
</div>
<script>
(function(){
  document.querySelectorAll('.banner-panel,.home-v2-broadcast').forEach(function(panel){
    var items=panel.querySelectorAll('.banner-item');
    var dots=panel.querySelectorAll('.banner-dot');
    if(!items.length)return;
    var cur=0;
    items.forEach(function(it,i){it.classList.toggle('active',i===0);});
    dots.forEach(function(d,i){d.classList.toggle('active',i===0);});
    if(items.length<=1){
      panel.classList.add('is-single');
      return;
    }
    function show(i){
      items[cur].classList.remove('active');
      if(dots[cur]) dots[cur].classList.remove('active');
      cur=(i+items.length)%items.length;
      items[cur].classList.add('active');
      if(dots[cur]) dots[cur].classList.add('active');
    }
    dots.forEach(function(d){d.addEventListener('click',function(e){e.preventDefault();show(parseInt(d.dataset.index||'0',10));});});
    setInterval(function(){show(cur+1);},4200);
  });
  var notices=document.querySelectorAll('.notice-slide');
  if(notices.length>1){
    var n=0;
    setInterval(function(){
      notices[n].classList.remove('active');
      notices[n].classList.add('prev');
      var old=n;
      n=(n+1)%notices.length;
      notices[n].classList.remove('prev');
      notices[n].classList.add('active');
      setTimeout(function(){notices[old].classList.remove('prev');},320);
    },3000);
  }
})();

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
