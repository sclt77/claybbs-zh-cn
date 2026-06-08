<?php
$_currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$_currentRoute = '/' . trim((string)($_GET['path'] ?? ''), '/');
if (($_currentRoute === '/' || $_currentRoute === '') && (($_SERVER['REQUEST_URI'] ?? '/') === '/' || str_starts_with((string)($_SERVER['REQUEST_URI'] ?? ''), '/?'))) {
    try {
        $_bottomSiteMode = (new \App\Models\SettingModel())->get('site_mode', 'forum');
        $_bottomSoftwareEnabled = (new \App\Models\SettingModel())->get('software_store_enabled', '1') === '1';
        if ($_bottomSiteMode === 'store' && $_bottomSoftwareEnabled) {
            $_currentRoute = '/software';
        }
    } catch (\Throwable $e) {}
}
if ($_currentRoute === '/') {
    $_currentRoute = $_currentPath === '/index.php' ? '/' : $_currentPath;
}
function _mobileNavActive(array $paths, string $current): string {
    foreach ($paths as $path) {
        if ($current === $path || ($path !== '/' && str_starts_with($current, rtrim($path, '/') . '/'))) return ' is-active';
    }
    return '';
}
?>
<?php
$_publishEntrySettings = [];
try { $_publishEntrySettings = (new \App\Models\SettingModel())->all(); } catch (\Throwable $e) { $_publishEntrySettings = []; }
$_publishNoticeTitle = trim((string)($_publishEntrySettings['publish_entry_notice_title'] ?? '发帖规范'));
$_publishNoticeContent = trim((string)($_publishEntrySettings['publish_entry_notice_content'] ?? '欢迎来到社区，发布内容前请遵守社区规则。'));
$_softwareStoreEnabled = (string)($_publishEntrySettings['software_store_enabled'] ?? '1') === '1';
?>
<style id="clay-mobile-critical-guard">
/* 移动底栏/发布面板防闪：完整样式在后方输出前，先锁定默认隐藏态 */
.m-tabbar{display:none;}
.m-publish-sheet{position:fixed;inset:0;z-index:520;opacity:0;visibility:hidden;pointer-events:none;}
.m-publish-sheet.is-open{opacity:1;visibility:visible;pointer-events:auto;}
@media(max-width:768px){.m-tabbar{position:fixed;left:10px;right:10px;bottom:10px;z-index:260;display:flex;}}
@media(min-width:769px){.m-publish-sheet{display:none!important;}}
</style>
<nav class="m-tabbar" aria-label="移动端底部导航">
  <a href="/index.php" class="m-tabbar__item<?= _mobileNavActive(['/', '/software'], $_currentRoute) ?>">
    <span class="m-tabbar__icon" aria-hidden="true">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="none">
        <path d="M40 116.2 123 40.7a8 8 0 0 1 10 0L216 116.2" stroke="currentColor" stroke-width="18" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M56 103.8V208a8 8 0 0 0 8 8h128a8 8 0 0 0 8-8V103.8" stroke="currentColor" stroke-width="18" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </span>
    <span class="m-tabbar__text">首页</span>
  </a>

  <a href="/index.php?path=sections" class="m-tabbar__item<?= _mobileNavActive(['/sections','/section'], $_currentRoute) ?>">
    <span class="m-tabbar__icon" aria-hidden="true">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="none">
        <rect x="36" y="52" width="184" height="152" rx="14" stroke="currentColor" stroke-width="18"/>
        <path d="M36 108h184M128 52v152" stroke="currentColor" stroke-width="18" stroke-linecap="round"/>
      </svg>
    </span>
    <span class="m-tabbar__text">板块</span>
  </a>

  <button type="button" class="m-tabbar__item m-tabbar__item--publish<?= _mobileNavActive(['/publish'], $_currentRoute) ?>" id="mobilePublishOpen">
    <span class="m-tabbar__publish-btn" aria-hidden="true">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="none">
        <path d="M128 72v112" stroke="currentColor" stroke-width="24" stroke-linecap="round"/>
        <path d="M72 128h112" stroke="currentColor" stroke-width="24" stroke-linecap="round"/>
      </svg>
    </span>
    <span class="m-tabbar__text">发帖</span>
  </button>

  <a href="/index.php?path=messages" class="m-tabbar__item<?= _mobileNavActive(['/messages'], $_currentRoute) ?>" id="msgTabBtn">
    <span class="m-tabbar__icon" aria-hidden="true" style="position:relative;">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="none">
        <path d="M128 28C72.56 28 28 68.26 28 118c0 26.1 11.4 49.7 29.9 66.5L44 228l47.2-15.7C99.5 215.3 113.5 218 128 218c55.44 0 100-40.26 100-100S183.44 28 128 28z" stroke="currentColor" stroke-width="18" stroke-linejoin="round"/>
      </svg>
      <span id="msgBadge" style="display:none;position:absolute;top:-4px;right:-6px;min-width:16px;height:16px;background:#ef4444;color:#fff;font-size:10px;font-weight:700;border-radius:8px;padding:0 4px;line-height:16px;text-align:center;"></span>
    </span>
    <span class="m-tabbar__text">消息</span>
  </a>

  <a href="/index.php?path=me" class="m-tabbar__item<?= _mobileNavActive(['/me','/me/edit','/login','/register'], $_currentRoute) ?>">
    <span class="m-tabbar__icon" aria-hidden="true">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="none">
        <circle cx="128" cy="92" r="44" stroke="currentColor" stroke-width="18"/>
        <path d="M52 204c16-30.2 44-48 76-48s60 17.8 76 48" stroke="currentColor" stroke-width="18" stroke-linecap="round"/>
      </svg>
    </span>
    <span class="m-tabbar__text">我的</span>
  </a>
</nav>

<div class="m-publish-sheet" id="mobilePublishSheet" aria-hidden="true">
  <div class="m-publish-sheet__mask" data-publish-close></div>
  <div class="m-publish-sheet__panel" role="dialog" aria-modal="true" aria-labelledby="mobilePublishTitle">
    <div class="m-publish-notice">
      <?php if ($_publishNoticeTitle !== ''): ?><div class="m-publish-notice__title" id="mobilePublishTitle"><?= htmlspecialchars($_publishNoticeTitle) ?></div><?php endif; ?>
      <?php if ($_publishNoticeContent !== ''): ?><div class="m-publish-notice__content"><?= nl2br(htmlspecialchars($_publishNoticeContent)) ?></div><?php endif; ?>
    </div>
    <div class="m-publish-actions" aria-label="发布类型">
      <a class="m-publish-action" href="/index.php?path=publish">
        <span class="m-publish-action__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="4" width="14" height="16" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
        <span>发布帖子</span>
      </a>
      <?php if ($_softwareStoreEnabled): ?>
      <a class="m-publish-action" href="/index.php?path=software/submission">
        <span class="m-publish-action__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="5" width="14" height="14" rx="4"/><path d="M9 9h6M9 13h4"/><path d="M16.5 16.5l2.5 2.5"/></svg></span>
        <span>软件投稿</span>
      </a>
      <?php endif; ?>
      <a class="m-publish-action" href="/index.php?path=drafts">
        <span class="m-publish-action__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4h9l3 3v13H6z"/><path d="M14 4v4h4M9 12h6M9 16h4"/></svg></span>
        <span>草稿箱</span>
      </a>
      <a class="m-publish-action" href="/index.php?path=me&tab=threads">
        <span class="m-publish-action__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 6h14M5 12h14M5 18h9"/><circle cx="18" cy="18" r="2"/></svg></span>
        <span>我的帖子</span>
      </a>
    </div>
    <button class="m-publish-close" type="button" data-publish-close aria-label="关闭"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
  </div>
</div>

<style>
.m-tabbar{
  position:fixed;
  left:10px;
  right:10px;
  bottom:10px;
  z-index:260;
  display:none;
  align-items:flex-end;
  justify-content:space-between;
  gap:4px;
  padding:8px 8px calc(8px + env(safe-area-inset-bottom));
  background:rgba(255,255,255,.98);
  border:1px solid rgba(226,232,240,.95);
  border-radius:24px;
  box-shadow:0 12px 32px rgba(15,23,42,.16);
  backdrop-filter:blur(14px);
  -webkit-backdrop-filter:blur(14px);
}
.m-tabbar__item{
  flex:1 1 0;
  min-width:0;
  height:60px;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  gap:4px;
  color:#64748b;
  text-decoration:none;
  border-radius:18px;
  transition:background-color .18s ease,color .18s ease;
}
.m-tabbar__item:active,
.m-tabbar__item:hover,
.m-tabbar__item:focus{
  text-decoration:none;
  outline:none;
}
.m-tabbar__icon,
.m-tabbar__publish-btn{
  width:24px;
  height:24px;
  display:flex;
  align-items:center;
  justify-content:center;
  flex:0 0 24px;
}
.m-tabbar__icon svg,
.m-tabbar__publish-btn svg{
  width:24px;
  height:24px;
  display:block;
  color:currentColor;
}
.m-tabbar__text{
  font-size:11px;
  line-height:1;
  font-weight:600;
  white-space:nowrap;
}
.m-tabbar__item.is-active{
  color:#0284c7;
  background:rgba(2,132,199,.10);
}
.m-tabbar__item--publish{
  color:#0f172a;
  background:transparent;
  appearance:none;
  -webkit-appearance:none;
  border:0;
  padding:0;
}
.m-tabbar__item--publish .m-tabbar__publish-btn{
  flex:0 0 46px;
  width:46px!important;
  min-width:46px!important;
  max-width:46px!important;
  height:46px!important;
  min-height:46px!important;
  max-height:46px!important;
  aspect-ratio:1/1;
  border-radius:50%!important;
  background:#0284c7;
  border:1px solid rgba(255,255,255,.62);
  box-shadow:0 10px 22px rgba(2,132,199,.24),0 2px 8px rgba(15,23,42,.10);
  color:#fff!important;
  transform:translateY(-10px);
  transition:transform .16s ease,background .16s ease,box-shadow .16s ease,color .16s ease;
}
.m-tabbar__item--publish .m-tabbar__publish-btn svg{
  width:22px;
  height:22px;
  color:currentColor;
  stroke-width:22;
}
.m-tabbar__item--publish .m-tabbar__text{
  margin-top:-8px;
  color:#334155;
  font-weight:900;
}
.m-tabbar__item--publish:active .m-tabbar__publish-btn,
.m-tabbar__item--publish:hover .m-tabbar__publish-btn{
  transform:translateY(-8px) scale(.96);
  background:#0369a1;
  box-shadow:0 8px 18px rgba(2,132,199,.22),0 2px 7px rgba(15,23,42,.10);
}
.m-tabbar__item--publish.is-active{
  background:transparent;
  color:#0284c7;
}
.m-tabbar__item--publish.is-active .m-tabbar__publish-btn{
  background:#0284c7;
  border-color:rgba(255,255,255,.62);
  color:#fff;
}
.m-tabbar__item--publish.is-active .m-tabbar__text{color:#0284c7;}

.m-publish-sheet{position:fixed;inset:0;z-index:520;display:block;color:#e5e7eb;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .32s ease,visibility .32s ease;}
.m-publish-sheet.is-open{opacity:1;visibility:visible;pointer-events:auto;}
.m-publish-sheet__mask{position:absolute;inset:0;background:rgba(15,23,42,.72);backdrop-filter:blur(18px) saturate(1.05);-webkit-backdrop-filter:blur(18px) saturate(1.05);opacity:0;transition:opacity .34s ease;}
.m-publish-sheet.is-open .m-publish-sheet__mask{opacity:1;} 
.m-publish-sheet__panel{position:absolute;inset:0;display:flex;flex-direction:column;padding:clamp(42px,8vh,72px) 24px calc(108px + env(safe-area-inset-bottom));background:radial-gradient(circle at 50% 18%,rgba(255,255,255,.06),transparent 34%);}
.m-publish-notice{max-width:420px;margin:0 auto;width:100%;padding:14px 16px;border:1px solid rgba(255,255,255,.10);border-radius:18px;background:rgba(255,255,255,.07);box-shadow:0 10px 28px rgba(0,0,0,.12);opacity:0;transform:translateY(26px) scale(.975);transition:opacity .34s ease .06s,transform .42s cubic-bezier(.16,1,.3,1) .06s;}
.m-publish-sheet.is-open .m-publish-notice{opacity:1;transform:translateY(0) scale(1);}
.m-publish-notice__title{font-size:15px;font-weight:950;letter-spacing:0;line-height:1.3;margin-bottom:6px;color:rgba(255,255,255,.92);}
.m-publish-notice__content{font-size:13px;font-weight:600;line-height:1.65;color:rgba(255,255,255,.76);white-space:normal;}
.m-publish-actions{margin:auto auto 44px;display:grid;grid-template-columns:repeat(auto-fit,minmax(76px,1fr));gap:14px;width:min(420px,100%);justify-items:center;opacity:0;transform:translateY(30px);transition:opacity .36s ease .14s,transform .46s cubic-bezier(.16,1,.3,1) .14s;}
.m-publish-sheet.is-open .m-publish-actions{opacity:1;transform:translateY(0);}
.m-publish-action{display:grid;gap:10px;justify-items:center;color:rgba(255,255,255,.86);text-decoration:none;font-size:13px;font-weight:750;}
.m-publish-action__icon{width:58px;height:58px;border-radius:18px;background:rgba(17,24,39,.66);box-shadow:0 14px 34px rgba(0,0,0,.24);display:grid;place-items:center;color:#dbe4ef;}
.m-publish-action__icon svg{width:28px;height:28px;}
.m-publish-close{position:absolute;left:50%;bottom:calc(34px + env(safe-area-inset-bottom));transform:translate(-50%,18px) scale(.9);width:66px;height:66px;border:0;border-radius:50%;background:rgba(255,255,255,.14);color:#e5e7eb;display:grid;place-items:center;cursor:pointer;opacity:0;transition:opacity .32s ease .18s,transform .42s cubic-bezier(.16,1,.3,1) .18s;}
.m-publish-sheet.is-open .m-publish-close{opacity:1;transform:translate(-50%,0) scale(1);}
.m-publish-close svg{width:36px;height:36px;}
.m-tabbar__item--publish{border:0;font:inherit;cursor:pointer;}
@media(prefers-reduced-motion:reduce){.m-publish-sheet,.m-publish-sheet__mask,.m-publish-notice,.m-publish-actions,.m-publish-close{transition:none!important;}}


@media(min-width:769px){.m-publish-sheet{display:none!important;}}
@media(max-width:390px){.m-publish-sheet__panel{padding-left:26px;padding-right:26px}.m-publish-notice__content{font-size:20px}.m-publish-action__icon{width:66px;height:66px}.m-publish-close{width:60px;height:60px}}

@media (max-width: 768px){
  .m-tabbar{display:flex;}
}

html[data-theme="dark"] .m-tabbar{background:rgba(17,24,39,.96);border-color:#263244;box-shadow:0 18px 42px rgba(0,0,0,.42);}
html[data-theme="dark"] .m-tabbar__item{color:#cbd5e1;}
html[data-theme="dark"] .m-tabbar__item.is-active{color:#38bdf8;background:rgba(56,189,248,.14);}
html[data-theme="dark"] .m-tabbar__item--publish{color:#e5e7eb;}
html[data-theme="dark"] .m-tabbar__item--publish .m-tabbar__publish-btn{background:#0284c7;border-color:rgba(125,211,252,.36);color:#fff;box-shadow:0 10px 22px rgba(0,0,0,.34),0 2px 8px rgba(0,0,0,.24);}
html[data-theme="dark"] .m-tabbar__item--publish .m-tabbar__text{color:#cbd5e1;}
html[data-theme="dark"] .m-tabbar__item--publish.is-active{background:transparent;color:#38bdf8;}
html[data-theme="dark"] .m-tabbar__item--publish.is-active .m-tabbar__publish-btn{background:#0284c7;border-color:rgba(125,211,252,.36);color:#fff;}
html[data-theme="dark"] .m-tabbar__item--publish.is-active .m-tabbar__text{color:#38bdf8;}

/* 20260508 refined static mobile tabbar - centered publish */
@media(max-width:768px){
  .m-tabbar{
    left:16px!important;
    right:16px!important;
    bottom:12px!important;
    height:60px!important;
    align-items:center!important;
    justify-content:space-between!important;
    gap:4px!important;
    padding:7px 11px calc(7px + env(safe-area-inset-bottom))!important;
    border-radius:24px!important;
    background:linear-gradient(180deg,rgba(255,255,255,.92),rgba(255,255,255,.82))!important;
    border:1px solid rgba(226,232,240,.70)!important;
    box-shadow:0 18px 42px rgba(15,23,42,.13),0 4px 14px rgba(15,23,42,.06),inset 0 1px 0 rgba(255,255,255,.90)!important;
    overflow:visible!important;
  }
  .m-tabbar::after{
    content:'';
    position:absolute;
    left:50%;
    bottom:5px;
    width:38px;
    height:3px;
    border-radius:999px;
    background:rgba(148,163,184,.22);
    transform:translateX(-50%);
    pointer-events:none;
  }
  .m-tabbar__item{
    flex:1 1 0!important;
    width:auto!important;
    min-width:0!important;
    height:46px!important;
    border-radius:17px!important;
    position:relative!important;
    z-index:1!important;
    overflow:visible!important;
    display:inline-flex!important;
    flex-direction:column!important;
    align-items:center!important;
    justify-content:center!important;
    gap:0!important;
    color:#7b8797!important;
    background:transparent!important;
    transition:color .22s ease,transform .2s ease!important;
    -webkit-tap-highlight-color:transparent;
  }
  .m-tabbar__item::before{
    content:'';
    position:absolute;
    left:50%;
    top:50%;
    width:42px;
    height:42px;
    z-index:-1;
    border-radius:15px;
    background:linear-gradient(180deg,rgba(14,165,233,.14),rgba(14,165,233,.07));
    box-shadow:inset 0 1px 0 rgba(255,255,255,.45);
    opacity:0;
    transform:translate(-50%,-50%) scale(.74);
    transition:transform .34s cubic-bezier(.16,1,.3,1),opacity .22s ease,width .34s cubic-bezier(.16,1,.3,1);
  }
  .m-tabbar__item::after{
    content:'';
    position:absolute;
    left:50%;
    top:41px;
    width:4px;
    height:4px;
    border-radius:50%;
    background:#0ea5e9;
    box-shadow:0 0 0 4px rgba(14,165,233,.10);
    opacity:0;
    transform:translate(-50%,4px) scale(.55);
    transition:opacity .22s ease,transform .34s cubic-bezier(.16,1,.3,1);
  }
  .m-tabbar__item:not(.m-tabbar__item--publish).is-active,
  .m-tabbar__item:not(.m-tabbar__item--publish):focus-visible{color:#0284c7!important;}
  .m-tabbar__item:not(.m-tabbar__item--publish).is-active::before,
  .m-tabbar__item:not(.m-tabbar__item--publish):focus-visible::before{opacity:1;width:42px;transform:translate(-50%,-50%) scale(1);}
  .m-tabbar__item:not(.m-tabbar__item--publish).is-active::after,
  .m-tabbar__item:not(.m-tabbar__item--publish):focus-visible::after{opacity:1;transform:translate(-50%,0) scale(1);}
  .m-tabbar__item:not(.m-tabbar__item--publish):active{transform:scale(.94)!important;}
  .m-tabbar__icon{
    position:relative!important;
    left:auto!important;
    top:auto!important;
    width:24px!important;
    height:24px!important;
    flex:0 0 24px!important;
    transform:translateY(0)!important;
    transition:transform .34s cubic-bezier(.16,1,.3,1),opacity .2s ease,filter .22s ease!important;
  }
  .m-tabbar__icon svg{width:24px!important;height:24px!important;stroke-width:17!important;}
  .m-tabbar__text{
    position:static!important;
    width:auto!important;
    height:auto!important;
    padding:0!important;
    margin:0!important;
    overflow:visible!important;
    clip:auto!important;
    white-space:nowrap!important;
    border:0!important;
    opacity:1!important;
    pointer-events:auto!important;
    font-size:10px!important;
    line-height:1!important;
    font-weight:850!important;
    color:currentColor!important;
  }
   .m-tabbar__item:not(.m-tabbar__item--publish).is-active .m-tabbar__icon,
  .m-tabbar__item:not(.m-tabbar__item--publish):focus-visible .m-tabbar__icon{transform:translateY(0) scale(1.06)!important;filter:drop-shadow(0 5px 8px rgba(14,165,233,.18));}
  .m-tabbar__item--publish{
    flex:1 1 0!important;
    width:auto!important;
    min-width:0!important;
    overflow:visible!important;
    background:transparent!important;
  }
  .m-tabbar__item--publish::before,
  .m-tabbar__item--publish::after{display:none!important;}
  .m-tabbar__item--publish .m-tabbar__publish-btn{
    flex:0 0 50px!important;
    width:50px!important;min-width:50px!important;max-width:50px!important;height:50px!important;min-height:50px!important;max-height:50px!important;
    position:relative!important;
    overflow:hidden!important;
    transform:translateY(-8px)!important;
    background:linear-gradient(145deg,#0ea5e9,#0284c7)!important;
    color:#fff!important;
    border:3px solid rgba(255,255,255,.94)!important;
    box-shadow:0 16px 30px rgba(2,132,199,.30),0 4px 12px rgba(15,23,42,.12),inset 0 1px 0 rgba(255,255,255,.35)!important;
    transition:transform .22s cubic-bezier(.16,1,.3,1),box-shadow .22s ease,filter .22s ease!important;
  }
  .m-tabbar__item--publish .m-tabbar__publish-btn::after{
    content:'';
    position:absolute;
    inset:8px;
    border-radius:50%;
    background:rgba(255,255,255,.32);
    opacity:0;
    transform:scale(.55);
    pointer-events:none;
  }
  .m-tabbar__item--publish.is-tapping .m-tabbar__publish-btn{animation:clayPublishTap .42s cubic-bezier(.16,1,.3,1) both!important;}
  .m-tabbar__item--publish.is-tapping .m-tabbar__publish-btn::after{animation:clayPublishRipple .42s ease-out both!important;}
  .m-tabbar__item--publish .m-tabbar__publish-btn svg{width:25px!important;height:25px!important;position:relative;z-index:1;color:#fff!important;stroke:#fff!important;}
  .m-tabbar__item--publish .m-tabbar__text{
    width:auto!important;
    padding:0!important;
    position:absolute!important;
    left:50%!important;
    bottom:3px!important;
    transform:translateX(-50%)!important;
    opacity:1!important;
    color:#475569!important;
    font-size:10px!important;
    font-weight:850!important;
  }
  .m-tabbar__item--publish:active .m-tabbar__publish-btn{transform:translateY(-6px) scale(.94)!important;box-shadow:0 10px 20px rgba(2,132,199,.24),0 2px 8px rgba(15,23,42,.10)!important;}
  @keyframes clayPublishTap{0%{transform:translateY(-8px) scale(1);}38%{transform:translateY(-5px) scale(.90) rotate(-4deg);}72%{transform:translateY(-10px) scale(1.06) rotate(2deg);}100%{transform:translateY(-8px) scale(1) rotate(0);}}
  @keyframes clayPublishRipple{0%{opacity:.0;transform:scale(.45);}35%{opacity:.34;transform:scale(1.05);}100%{opacity:0;transform:scale(1.9);}}

  html[data-theme="dark"] .m-tabbar{background:linear-gradient(180deg,rgba(17,24,39,.90),rgba(15,23,42,.82))!important;border-color:rgba(51,65,85,.78)!important;box-shadow:0 18px 44px rgba(0,0,0,.42),inset 0 1px 0 rgba(255,255,255,.06)!important;}
  html[data-theme="dark"] .m-tabbar::after{background:rgba(148,163,184,.18)!important;}
  html[data-theme="dark"] .m-tabbar__item{color:#a8b3c4!important;}
  html[data-theme="dark"] .m-tabbar__item::before{background:linear-gradient(180deg,rgba(56,189,248,.18),rgba(56,189,248,.08))!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.08)!important;}
  html[data-theme="dark"] .m-tabbar__item:not(.m-tabbar__item--publish).is-active{color:#38bdf8!important;}
  html[data-theme="dark"] .m-tabbar__item::after{background:#38bdf8!important;box-shadow:0 0 0 4px rgba(56,189,248,.12)!important;}
  html[data-theme="dark"] .m-tabbar__item--publish .m-tabbar__publish-btn{border-color:rgba(17,24,39,.96)!important;box-shadow:0 15px 28px rgba(2,132,199,.30),0 4px 12px rgba(0,0,0,.28),inset 0 1px 0 rgba(255,255,255,.18)!important;}
  html[data-theme="dark"] .m-tabbar__item--publish .m-tabbar__text{color:#cbd5e1!important;}
}
@media(max-width:360px){
  .m-tabbar{left:10px!important;right:10px!important;padding-left:8px!important;padding-right:8px!important;}
  .m-tabbar__item:not(.m-tabbar__item--publish).is-active::before{width:min(56px,100%);}
}
@media(prefers-reduced-motion:reduce){
  .m-tabbar__item,.m-tabbar__item::before,.m-tabbar__item::after,.m-tabbar__icon,.m-tabbar__text,.m-tabbar__publish-btn{animation:none!important;transition:none!important;}
}

</style>

<script>

(function(){
  var open=document.getElementById('mobilePublishOpen');
  var sheet=document.getElementById('mobilePublishSheet');
  if(!open||!sheet)return;
  function close(){sheet.classList.remove('is-open');sheet.setAttribute('aria-hidden','true');document.body.style.overflow='';}
  open.addEventListener('click',function(e){
    e.preventDefault();
    open.classList.remove('is-tapping');
    void open.offsetWidth;
    open.classList.add('is-tapping');
    setTimeout(function(){open.classList.remove('is-tapping');},460);
    sheet.classList.add('is-open');
    sheet.setAttribute('aria-hidden','false');
    document.body.style.overflow='hidden';
  });
  sheet.addEventListener('click',function(e){if(e.target.closest('[data-publish-close]'))close();});
  document.addEventListener('keydown',function(e){if(e.key==='Escape'&&sheet.classList.contains('is-open'))close();});
})();

(function(){
  var badge = document.getElementById('msgBadge');
  if (!badge) return;
  function checkUnread(){
    fetch('/api.php?path=messages/unread', {credentials:'same-origin'})
      .then(function(r){ return r.json(); })
      .then(function(d){
        var count = d.count || 0;
        if (count > 0){
          badge.style.display = 'block';
          badge.textContent = count > 99 ? '99+' : count;
        } else {
          badge.style.display = 'none';
          badge.textContent = '';
        }
      })
      .catch(function(){});
  }
  checkUnread();
  setInterval(checkUnread, 2000);
})();
</script>
