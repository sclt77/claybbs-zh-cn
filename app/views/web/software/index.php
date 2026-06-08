<?php
$__pageTitle = '软件库';
require dirname(__DIR__) . '/layouts/main.php';

$platformLabels = ['android' => 'Android', 'ios' => 'iOS', 'windows' => 'Windows', 'macos' => 'macOS'];
$orderOptions   = ['created' => '最新投稿', 'score' => '好评如潮', 'comments' => '讨论火热', 'random' => '随便看看'];
$currentPlatform = $_GET['platform'] ?? '';
$currentCategory = (int)($_GET['category'] ?? 0);
$currentOrder    = $_GET['order'] ?? 'created';
$currentQ       = trim((string)($_GET['q'] ?? ''));
$page            = max(1, (int)($_GET['page'] ?? 1));
?>

<style>
/* ── 页面容器 ── */
.software-store{padding:0 0 80px;background:var(--bg-main,#f5f5f5);min-height:100vh;position:relative;z-index:1}

/* 软件库页全局层级修复：头像下拉菜单必须高于软件库 sticky 标题/筛选栏 */
body:has(.software-store) .topbar{z-index:3000!important;overflow:visible!important}
body:has(.software-store) .user-dropdown{z-index:3010!important}
body:has(.software-store) .dropdown-menu{z-index:3020!important}

/* ── 自定义导航栏 ── */
.software-header{position:sticky;top:58px;z-index:120;background:var(--card-bg,#fff);padding:12px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--line-soft,#f0f0f0)}
.software-header .header-copy{flex:1;min-width:0;display:flex;align-items:center}
.software-header .header-title{font-size:18px;font-weight:900;flex:1;color:var(--text-main,#0f172a)}
.software-header .header-subtitle{display:none}
.software-header .header-search{position:relative;flex:0 0 auto}
.software-header .header-search input{width:0;padding:8px 0;border:none;background:transparent;font-size:14px;transition:width .3s}
.software-header .header-search.open input{width:160px;padding:8px 12px;border:1px solid var(--line-soft,#e2e8f0);border-radius:99px;background:var(--bg-soft,#f8fafc)}
.software-header .search-btn{background:none;border:none;color:var(--text-soft,#64748b);cursor:pointer;padding:4px;display:inline-flex;align-items:center;justify-content:center}
.software-header .search-btn svg{width:20px;height:20px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}

/* ── 软件库转播轮：点击展开 ── */
.broadcast-wrap{margin:12px 12px 14px;position:relative;border-radius:24px;overflow:hidden;padding-bottom:22px;isolation:isolate}
.broadcast-track{display:flex;transition:transform .55s cubic-bezier(.22,1,.36,1);will-change:transform}
.broadcast-slide{min-width:100%;padding:0 1px;box-sizing:border-box}
.broadcast-card{position:relative;display:block;height:172px;border:0;border-radius:24px;overflow:hidden;background:#111827;color:#fff;text-align:left;text-decoration:none;box-shadow:0 18px 42px rgba(15,23,42,.18);cursor:pointer;transform:translateZ(0);transition:height .46s cubic-bezier(.22,1,.36,1),border-radius .36s ease,box-shadow .36s ease,transform .2s ease}
.broadcast-card:active{transform:scale(.985)}
.broadcast-bg{position:absolute;inset:-16px;background-size:cover;background-position:center;filter:blur(16px);opacity:.68;transform:scale(1.08);transition:filter .46s ease,opacity .46s ease,transform .46s ease}
.broadcast-bg::after{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(15,23,42,.54),rgba(15,23,42,.24) 48%,rgba(60,201,164,.22))}
.broadcast-glow{position:absolute;inset:auto -30px -64px auto;width:190px;height:190px;border-radius:999px;background:radial-gradient(circle,rgba(60,201,164,.48),transparent 66%);filter:blur(2px);opacity:.85;transition:.46s ease}
.broadcast-cover{position:absolute;right:16px;top:18px;width:118px;height:118px;border-radius:28px;overflow:hidden;background:rgba(255,255,255,.92);box-shadow:0 18px 38px rgba(0,0,0,.28);transition:width .46s cubic-bezier(.22,1,.36,1),height .46s cubic-bezier(.22,1,.36,1),right .46s cubic-bezier(.22,1,.36,1),top .46s cubic-bezier(.22,1,.36,1),border-radius .46s ease}
.broadcast-cover img{width:100%;height:100%;object-fit:cover;display:block}
.broadcast-cover-placeholder{width:100%;height:100%;display:grid;place-items:center;color:#1f2937;background:linear-gradient(135deg,#eef2ff,#d1fae5)}
.broadcast-cover-placeholder svg{width:54px;height:54px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.broadcast-main{position:absolute;left:18px;right:148px;top:22px;z-index:2;min-width:0;transition:right .46s cubic-bezier(.22,1,.36,1),top .46s ease}
.broadcast-kicker{display:inline-flex;align-items:center;height:24px;padding:0 10px;border-radius:999px;background:rgba(255,255,255,.18);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.18);font-size:11px;font-weight:900;color:rgba(255,255,255,.92);margin-bottom:10px}
.broadcast-name{font-size:22px;line-height:1.08;font-weight:950;letter-spacing:-.04em;margin:0 0 8px;color:#fff;text-shadow:0 4px 18px rgba(0,0,0,.24);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.broadcast-desc{font-size:13px;line-height:1.5;color:rgba(255,255,255,.86);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;text-shadow:0 2px 10px rgba(0,0,0,.22)}
.broadcast-meta{position:absolute;left:18px;right:18px;bottom:16px;display:flex;align-items:center;gap:8px;z-index:2;transition:opacity .25s ease,transform .35s ease}
.broadcast-pill{display:inline-flex;align-items:center;gap:4px;height:26px;padding:0 10px;border-radius:999px;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.16);color:#fff;font-size:12px;font-weight:850;backdrop-filter:blur(12px);white-space:nowrap}.broadcast-pill.star{color:#fff2a8}.broadcast-expand-hint{margin-left:auto;font-size:12px;color:rgba(255,255,255,.82);font-weight:900}
.broadcast-detail{position:absolute;left:0;right:0;bottom:0;z-index:3;padding:132px 16px 16px;opacity:0;transform:translateY(24px);pointer-events:none;transition:opacity .3s ease .08s,transform .42s cubic-bezier(.22,1,.36,1)}
.broadcast-detail-card{border-radius:24px;background:rgba(255,255,255,.94);color:#0f172a;padding:16px;box-shadow:0 18px 44px rgba(15,23,42,.22);backdrop-filter:blur(18px)}
.broadcast-detail-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}.broadcast-detail-title{font-size:16px;font-weight:950;color:#0f172a}.broadcast-detail-version{font-size:12px;font-weight:900;color:#64748b;background:#f1f5f9;border-radius:999px;padding:5px 9px;white-space:nowrap}.broadcast-detail-desc{font-size:13px;line-height:1.65;color:#475569;margin:0 0 12px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.broadcast-detail-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-bottom:14px}.broadcast-stat{border-radius:16px;background:#f8fafc;padding:9px 6px;text-align:center}.broadcast-stat b{display:block;font-size:14px;line-height:1;color:#0f172a;font-weight:950}.broadcast-stat span{display:block;margin-top:5px;font-size:10px;color:#64748b;font-weight:850}.broadcast-actions{display:flex;gap:10px}.broadcast-btn{flex:1;height:42px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font-size:13px;font-weight:950}.broadcast-btn.primary{background:#3cc9a4;color:#fff;box-shadow:0 10px 24px rgba(60,201,164,.32)}.broadcast-btn.ghost{background:#f1f5f9;color:#0f172a}.broadcast-card.is-expanded{height:342px;border-radius:28px;box-shadow:0 24px 58px rgba(15,23,42,.24)}.broadcast-card.is-expanded .broadcast-bg{filter:blur(8px);opacity:.9;transform:scale(1.02)}.broadcast-card.is-expanded .broadcast-glow{width:260px;height:260px;opacity:1}.broadcast-card.is-expanded .broadcast-cover{right:18px;top:18px;width:96px;height:96px;border-radius:24px}.broadcast-card.is-expanded .broadcast-main{right:126px;top:22px}.broadcast-card.is-expanded .broadcast-meta{opacity:0;transform:translateY(8px);pointer-events:none}.broadcast-card.is-expanded .broadcast-detail{opacity:1;transform:translateY(0);pointer-events:auto}.broadcast-dots{position:absolute;left:0;right:0;bottom:-18px;display:flex;justify-content:center;gap:6px;z-index:5}.broadcast-dot{width:6px;height:6px;border:0;border-radius:999px;background:#cbd5e1;padding:0;transition:.25s;cursor:pointer}.broadcast-dot.active{width:20px;background:#3cc9a4}html[data-theme="dark"] .broadcast-detail-card{background:rgba(15,23,42,.94);color:#e5e7eb}html[data-theme="dark"] .broadcast-detail-title,html[data-theme="dark"] .broadcast-stat b{color:#f8fafc}html[data-theme="dark"] .broadcast-detail-desc{color:#cbd5e1}html[data-theme="dark"] .broadcast-detail-version,html[data-theme="dark"] .broadcast-stat,html[data-theme="dark"] .broadcast-btn.ghost{background:#1e293b;color:#e5e7eb}@media(min-width:720px){.broadcast-wrap{max-width:720px;margin:16px auto 18px}.broadcast-card{height:210px}.broadcast-card.is-expanded{height:380px}.broadcast-cover{width:142px;height:142px}.broadcast-main{right:178px}.broadcast-name{font-size:28px}.broadcast-detail{padding-top:158px}}@media(max-width:380px){.broadcast-card{height:168px}.broadcast-card.is-expanded{height:360px}.broadcast-cover{width:100px;height:100px;border-radius:24px}.broadcast-main{right:128px}.broadcast-name{font-size:19px}.broadcast-detail-stats{grid-template-columns:repeat(3,minmax(0,1fr));gap:6px}.broadcast-actions{gap:8px}.broadcast-btn{height:40px}}

/* ── 筛选栏 ── */
.filter-bar{display:flex;background:var(--card-bg,#fff);border-bottom:1px solid var(--line-soft,#f0f0f0);position:sticky;top:52px;z-index:90}
.filter-item{flex:1;text-align:center;padding:12px 4px;font-size:14px;color:var(--text-main,#0f172a);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:3px;transition:color .15s;user-select:none}
.filter-item.active{color:#3cc9a4;font-weight:700}
.filter-item .arrow{font-size:10px;color:var(--text-muted,#94a3b8);transition:transform .2s}
.filter-item.open .arrow{transform:rotate(180deg)}

/* ── 筛选弹窗（动态特效版） ── */
.filter-popup{position:fixed;inset:0;z-index:1200;display:none}
.filter-popup.show{display:block}
.filter-popup .popup-mask{position:absolute;inset:0;background:rgba(8,15,30,0);backdrop-filter:blur(0px);-webkit-backdrop-filter:blur(0px);transition:background .34s ease,backdrop-filter .34s ease}
.filter-popup.show .popup-mask{background:rgba(8,15,30,.42);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px)}
.filter-popup .popup-body{position:absolute;left:0;right:0;bottom:0;background:var(--card-bg,#fff);border-radius:28px 28px 0 0;padding:8px 20px calc(20px + env(safe-area-inset-bottom));box-shadow:0 -18px 60px rgba(15,23,42,.22);transform:translateY(105%);opacity:.4;transition:transform .42s cubic-bezier(.16,1,.3,1),opacity .3s ease;overflow:hidden}
.filter-popup .popup-body::before{content:'';position:absolute;left:0;right:0;top:0;height:4px;background:linear-gradient(90deg,#3cc9a4,#22d3ee,#818cf8,#3cc9a4);background-size:300% 100%;animation:popupHue 6s linear infinite;opacity:0;transition:opacity .4s ease .1s}
.filter-popup.show .popup-body{transform:translateY(0);opacity:1}
.filter-popup.show .popup-body::before{opacity:1}
@keyframes popupHue{0%{background-position:0 0}100%{background-position:300% 0}}
.popup-handle{width:42px;height:5px;border-radius:99px;background:var(--line-soft,#e2e8f0);margin:10px auto 14px;transition:width .3s ease}
.filter-popup.show .popup-handle{width:54px}
.popup-head{display:flex;align-items:center;gap:10px;margin-bottom:18px}
.popup-head .popup-ico{width:34px;height:34px;flex:0 0 auto;border-radius:12px;display:grid;place-items:center;background:linear-gradient(135deg,rgba(60,201,164,.16),rgba(34,211,238,.16));color:#0f9f83}
.popup-head .popup-ico svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.popup-body .popup-title{font-size:18px;font-weight:900;letter-spacing:-.02em;color:var(--text-main,#0f172a);line-height:1.2}
.popup-body .popup-title small{display:block;margin-top:2px;font-size:12px;font-weight:700;color:var(--text-muted,#94a3b8);letter-spacing:0}
.popup-close{margin-left:auto;width:34px;height:34px;border:0;border-radius:999px;background:var(--bg-soft,#f1f5f9);color:var(--text-soft,#64748b);font-size:20px;line-height:1;cursor:pointer;display:grid;place-items:center;transition:transform .2s ease,background .18s ease,color .18s ease}
.popup-close:hover{background:#fee2e2;color:#ef4444;transform:rotate(90deg)}
.popup-body .popup-options{display:flex;flex-wrap:wrap;gap:10px}
.popup-body .popup-option{position:relative;min-width:74px;min-height:42px;display:inline-flex;align-items:center;justify-content:center;gap:7px;text-align:center;border:1.5px solid var(--line-soft,#eef2f7);border-radius:16px;font-size:13.5px;font-weight:800;padding:0 16px;cursor:pointer;color:var(--text-soft,#64748b);background:var(--card-bg,#fff);overflow:hidden;opacity:0;transform:translateY(14px) scale(.96);transition:border-color .2s ease,color .2s ease,background .2s ease,transform .18s cubic-bezier(.34,1.56,.64,1),box-shadow .24s ease}
.filter-popup.show .popup-option{opacity:1;transform:translateY(0) scale(1)}
.popup-body .popup-option::after{content:'';position:absolute;inset:0;border-radius:inherit;background:radial-gradient(circle at center,rgba(60,201,164,.35),transparent 70%);opacity:0;transform:scale(.4);transition:opacity .4s ease,transform .5s ease;pointer-events:none}
.popup-body .popup-option:active{transform:scale(.94)}
.popup-body .popup-option:hover{border-color:#9be7d4;color:#0f9f83}
.popup-body .popup-option.active{background:linear-gradient(135deg,#3cc9a4,#22d3ee);color:#fff;border-color:transparent;box-shadow:0 10px 26px rgba(60,201,164,.34);transform:translateY(-2px) scale(1.02)}
.popup-body .popup-option.active::after{opacity:.9;transform:scale(1.4);animation:optionPulse .55s ease}
.popup-body .popup-option .opt-check{width:0;height:14px;opacity:0;overflow:hidden;display:inline-flex;align-items:center;transition:width .24s ease,opacity .24s ease}
.popup-body .popup-option .opt-check svg{width:14px;height:14px;fill:none;stroke:#fff;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}
.popup-body .popup-option.active .opt-check{width:14px;opacity:1}
@keyframes optionPulse{0%{box-shadow:0 0 0 0 rgba(60,201,164,.5)}100%{box-shadow:0 0 0 16px rgba(60,201,164,0)}}
.popup-body .popup-btns{display:flex;gap:12px;margin-top:22px}
.popup-body .popup-btns button{flex:1;padding:13px;border-radius:16px;font-size:14.5px;font-weight:900;cursor:pointer;border:none;transition:transform .16s ease,box-shadow .2s ease,filter .2s ease}
.popup-body .popup-btns button:active{transform:scale(.97)}
.popup-body .btn-reset{background:var(--bg-soft,#f1f5f9);color:var(--text-soft,#64748b)}
.popup-body .btn-reset:hover{filter:brightness(.97)}
.popup-body .btn-confirm{background:linear-gradient(135deg,#3cc9a4,#22d3ee);color:#fff;box-shadow:0 10px 26px rgba(60,201,164,.32)}
.popup-body .btn-confirm:hover{box-shadow:0 14px 32px rgba(60,201,164,.42)}
@media(prefers-reduced-motion:reduce){.filter-popup .popup-body,.popup-option,.popup-body .popup-body::before{transition:none!important;animation:none!important}.popup-option{opacity:1!important;transform:none!important}}
body.software-filter-lock{overflow:hidden}

/* ── 应用列表 ── */
.app-list{padding:8px 12px}
.app-box{display:flex;align-items:center;padding:14px 12px;background:var(--card-bg,#fff);border-radius:14px;margin-bottom:8px;transition:transform .1s}
.app-box:active{transform:scale(.985)}
.app-box>a:first-child{color:inherit;text-decoration:none;display:flex;align-items:center;flex:1;min-width:0}
.app-box-logo{width:52px;height:52px;border-radius:14px;flex-shrink:0;overflow:hidden;background:linear-gradient(135deg,#f0f1f3,#e2e8f0)}
.app-box-logo img{width:100%;height:100%;object-fit:cover;border-radius:14px}
.app-box-logo .logo-placeholder{width:100%;height:100%;display:grid;place-items:center;color:#1f2937;background:linear-gradient(135deg,#eef2ff,#dbeafe)}
.app-box-logo .logo-placeholder svg{width:30px;height:30px;stroke:currentColor;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.app-box-content{flex:1;margin-left:12px;min-width:0}
.app-box-title{font-size:15px;font-weight:800;margin-bottom:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text-main,#0f172a)}
.app-box-info{font-size:12px;color:var(--text-muted,#94a3b8);margin-bottom:5px;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.app-box-info .star-icon{color:#f59e0b;font-size:11px}
.app-box-tags{display:flex;align-items:center;flex-wrap:wrap;gap:5px}
.app-tag{padding:2px 8px;border-radius:6px;color:#fff;font-size:11px;font-weight:700;line-height:1.4}
.app-tag.t-original{background:#19be6b}
.app-tag.t-gold{background:#ff6600}
.app-tag.t-official{background:#2979ff}
.app-tag.t-repost{background:#7c72ff}
.app-category-tag{padding:2px 8px;border-radius:6px;background:var(--bg-soft,#f5f5f5);color:var(--text-muted,#666);font-size:11px;line-height:1.4}
.app-box-down{background:#3cc9a4;color:#fff;padding:7px 18px;border-radius:99px;font-size:13px;font-weight:700;white-space:nowrap;border:none;cursor:pointer;flex:none;flex-shrink:0;text-decoration:none;display:inline-block;margin-left:8px;width:auto}
.app-box-down:active{opacity:.85}

/* ── 加载更多 ── */
.load-more{text-align:center;padding:20px;font-size:13px;color:var(--text-muted,#94a3b8)}

/* ── 空状态 ── */
.empty-state{text-align:center;padding:60px 20px;color:var(--text-muted,#94a3b8)}
.empty-state .empty-icon{width:56px;height:56px;margin:0 auto 12px;opacity:.45;color:var(--text-muted,#94a3b8)}
.empty-state .empty-icon svg{width:100%;height:100%;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.empty-state p{font-size:14px}

/* ── 桌面版：从移动列表升级为软件目录页 ── */
@media (min-width: 900px){
  .software-store{padding:34px clamp(24px,4vw,56px) 96px;background:linear-gradient(180deg,var(--bg-main,#f8fafc) 0%,var(--bg-main,#f5f5f5) 38%,var(--bg-main,#f5f5f5) 100%)}
  .software-header{position:sticky;top:58px;z-index:120;max-width:1180px;margin:0 auto 22px;padding:14px 0;background:color-mix(in srgb,var(--bg-main,#f8fafc) 88%,transparent);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border-bottom:0;display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,440px);align-items:end;gap:28px}
  .software-header .header-copy{display:grid;gap:8px;min-width:0;align-items:start}
  .software-header .header-title{font-size:38px;line-height:1.05;font-weight:950;letter-spacing:-1.7px;color:var(--text-main,#0f172a);flex:none}
  .software-header .header-subtitle{display:block;font-size:15px;line-height:1.7;font-weight:600;color:var(--text-soft,#64748b)}
  .software-header .header-search{position:relative;width:100%;height:46px;background:var(--card-bg,#fff);border-radius:14px;box-shadow:0 0 0 1px rgba(15,23,42,.08),0 10px 30px rgba(15,23,42,.05);display:flex;align-items:center;overflow:hidden}
  .software-header .header-search input,.software-header .header-search.open input{width:100%;height:100%;padding:0 52px 0 16px;border:0;border-radius:14px;background:transparent;font-size:14px;color:var(--text-main,#0f172a);outline:none;transition:none}
  .software-header .header-search input::placeholder{color:var(--text-muted,#94a3b8)}
  .software-header .search-btn{position:absolute;right:10px;top:50%;transform:translateY(-50%);width:34px;height:34px;border-radius:10px;background:#0f172a;color:#fff;padding:0;transition:opacity .15s,transform .15s}
  .software-header .search-btn:hover{opacity:.9;transform:translateY(-50%) scale(1.03)}

  .broadcast-wrap{max-width:1180px;margin:0 auto 18px;border-radius:18px;padding-bottom:20px;overflow:hidden}
  .broadcast-card{height:244px;border-radius:18px;box-shadow:0 0 0 1px rgba(15,23,42,.08),0 18px 44px rgba(15,23,42,.12);background:#0f172a}
  .broadcast-card:hover{box-shadow:0 0 0 1px rgba(15,23,42,.08),0 22px 54px rgba(15,23,42,.16);transform:translateY(-1px)}
  .broadcast-bg{inset:-28px;filter:blur(22px);opacity:.55}
  .broadcast-bg::after{background:linear-gradient(90deg,rgba(15,23,42,.92) 0%,rgba(15,23,42,.74) 48%,rgba(15,23,42,.28) 100%)}
  .broadcast-cover{right:46px;top:38px;width:154px;height:154px;border-radius:34px;background:rgba(255,255,255,.95)}
  .broadcast-main{left:42px;right:260px;top:42px}
  .broadcast-kicker{height:28px;padding:0 12px;font-size:12px;background:rgba(255,255,255,.12)}
  .broadcast-name{font-size:38px;letter-spacing:-1.5px;margin-bottom:12px;max-width:620px}
  .broadcast-desc{font-size:15px;line-height:1.72;max-width:640px;-webkit-line-clamp:2;color:rgba(255,255,255,.82)}
  .broadcast-meta{left:42px;right:42px;bottom:32px;gap:10px}.broadcast-pill{height:30px;padding:0 12px;font-size:12px}.broadcast-expand-hint{font-size:12px}
  .broadcast-card.is-expanded{height:360px;border-radius:20px}.broadcast-card.is-expanded .broadcast-cover{right:46px;top:38px;width:132px;height:132px;border-radius:30px}.broadcast-card.is-expanded .broadcast-main{right:250px;top:42px}.broadcast-detail{padding:178px 42px 30px}.broadcast-detail-card{border-radius:18px;display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:18px;align-items:center;padding:18px}.broadcast-detail-head{margin-bottom:8px}.broadcast-detail-stats{margin-bottom:0}.broadcast-actions{align-self:stretch;align-items:center}.broadcast-btn{height:46px}.broadcast-dots{bottom:-16px}

  .filter-bar{position:sticky;top:160px;z-index:110;max-width:1180px;margin:0 auto 16px;padding:8px;background:var(--card-bg,#fff);border-bottom:0;border-radius:16px;box-shadow:0 0 0 1px rgba(15,23,42,.08),0 8px 24px rgba(15,23,42,.04);gap:8px}
  .filter-item{flex:0 0 auto;min-width:132px;padding:10px 14px;border-radius:12px;font-size:13px;font-weight:750;background:transparent;color:var(--text-soft,#64748b)}
  .filter-item:hover{background:var(--bg-soft,#f8fafc);color:var(--text-main,#0f172a)}
  .filter-item.active{background:rgba(60,201,164,.12);color:#0f9f83}

  .app-list{max-width:1180px;margin:0 auto;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px}
  .app-box{position:relative;align-items:flex-start;padding:18px;background:var(--card-bg,#fff);border-radius:18px;margin-bottom:0;box-shadow:0 0 0 1px rgba(15,23,42,.08),0 8px 24px rgba(15,23,42,.04);transition:transform .18s ease,box-shadow .18s ease}
  .app-box:hover{transform:translateY(-2px);box-shadow:0 0 0 1px rgba(15,23,42,.09),0 16px 38px rgba(15,23,42,.08)}
  .app-box:active{transform:translateY(-1px)}
  .app-box>a:first-child{align-items:flex-start;padding-right:0;min-height:112px}
  .app-box-logo{width:64px;height:64px;border-radius:16px;box-shadow:0 0 0 1px rgba(15,23,42,.08)}
  .app-box-logo img{border-radius:16px}.app-box-logo .logo-placeholder svg{width:34px;height:34px}
  .app-box-content{margin-left:14px;padding-right:4px}.app-box-title{font-size:17px;line-height:1.35;margin-bottom:6px;white-space:normal;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}.app-box-info{font-size:12px;margin-bottom:10px;gap:8px}.app-box-tags{gap:6px}.app-tag,.app-category-tag{border-radius:999px;padding:3px 9px;font-size:11px}
  .app-box-down{position:absolute;right:18px;bottom:18px;margin-left:0;padding:8px 18px;border-radius:10px;background:#0f172a;color:#fff;font-size:13px;font-weight:850;box-shadow:0 8px 20px rgba(15,23,42,.16)}
  .app-box-down:hover{opacity:.92}.load-more{max-width:1180px;margin:18px auto 0;border-radius:14px;background:var(--card-bg,#fff);box-shadow:0 0 0 1px rgba(15,23,42,.08);cursor:pointer}.empty-state{grid-column:1/-1;background:var(--card-bg,#fff);border-radius:18px;box-shadow:0 0 0 1px rgba(15,23,42,.08);padding:80px 20px}
  .software-store--home{padding:18px clamp(18px,3vw,42px) 96px;background:radial-gradient(circle at 18% 0%,rgba(59,130,246,.12),transparent 28%),radial-gradient(circle at 82% 5%,rgba(60,201,164,.16),transparent 26%),linear-gradient(180deg,var(--bg-main,#f8fafc),var(--bg-main,#f4f6f8))}
  .software-store--home .software-home-shell{display:grid;grid-template-columns:250px minmax(0,1fr) 270px;gap:18px;align-items:start;max-width:1320px;margin:0 auto}
  .software-store--home .software-header{display:none}
  .software-store--home .header-title{font-size:34px;letter-spacing:-1.2px}.software-store--home .header-subtitle{font-size:14px}
  .software-home-left,.software-home-right{position:sticky;top:80px;display:grid;gap:14px}.software-home-main{min-width:0;display:grid;gap:14px}.software-home-panel{background:rgba(255,255,255,.88);border:1px solid rgba(226,232,240,.86);border-radius:20px;box-shadow:0 14px 36px rgba(15,23,42,.055);overflow:hidden;backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}.software-home-pad{padding:15px}.software-home-title{margin:0 0 12px;font-size:15px;font-weight:950;color:var(--text-main,#0f172a);display:flex;align-items:center;justify-content:space-between}.software-home-title a{font-size:12px;color:var(--primary,#0284c7);text-decoration:none}.software-home-muted{font-size:12px;color:var(--text-muted,#94a3b8);line-height:1.6}
  .software-store--home .broadcast-wrap{display:block;max-width:none;margin:0 0 14px;border-radius:24px;padding-bottom:22px;overflow:hidden}.software-store--home .broadcast-card{height:172px;border-radius:24px;box-shadow:0 18px 42px rgba(15,23,42,.18)}.software-store--home .broadcast-bg{inset:-16px;filter:blur(16px);opacity:.68}.software-store--home .broadcast-bg::after{background:linear-gradient(135deg,rgba(15,23,42,.54),rgba(15,23,42,.24) 48%,rgba(60,201,164,.22))}.software-store--home .broadcast-cover{right:16px;top:18px;width:118px;height:118px;border-radius:28px}.software-store--home .broadcast-main{left:18px;right:148px;top:22px}.software-store--home .broadcast-kicker{height:24px;padding:0 10px;font-size:11px;margin-bottom:10px}.software-store--home .broadcast-name{font-size:22px;line-height:1.08;letter-spacing:-.04em;margin:0 0 8px}.software-store--home .broadcast-desc{font-size:13px;line-height:1.5;max-width:none}.software-store--home .broadcast-meta{left:18px;right:18px;bottom:16px;gap:8px}.software-store--home .broadcast-pill{height:26px;padding:0 10px;font-size:12px}.software-store--home .broadcast-expand-hint{font-size:12px}.software-store--home .broadcast-card.is-expanded{height:342px;border-radius:28px}.software-store--home .broadcast-card.is-expanded .broadcast-cover{right:18px;top:18px;width:96px;height:96px;border-radius:24px}.software-store--home .broadcast-card.is-expanded .broadcast-main{right:126px;top:22px}.software-store--home .broadcast-detail{padding:132px 16px 16px}.software-store--home .broadcast-detail-card{display:block;border-radius:24px;padding:16px}.software-store--home .broadcast-detail-stats{margin-bottom:14px}.software-store--home .broadcast-actions{display:flex}.software-store--home .broadcast-btn{height:42px}.software-store--home .broadcast-dots{bottom:-18px}
  .software-store--home .filter-bar{max-width:none;margin:0;top:158px;border-radius:18px;background:rgba(255,255,255,.92);box-shadow:0 10px 30px rgba(15,23,42,.055)}.software-store--home .app-list{max-width:none;width:100%;grid-template-columns:minmax(0,1fr);gap:12px}.software-store--home .app-box{border-radius:18px;min-height:112px;width:100%;box-sizing:border-box}.software-store--home .app-box>a:first-child{min-height:80px}.software-store--home .app-box-content{padding-right:90px}.software-store--home .app-box-title{font-size:18px}.software-store--home .app-box-down{right:18px;top:50%;bottom:auto;transform:translateY(-50%)}.software-store--home .load-more{max-width:none}
  .software-home-search-panel{padding:15px;min-height:112px;display:flex;align-items:center}.software-home-search{position:relative;width:100%;height:56px;border-radius:16px;background:var(--bg-soft,#f8fafc);box-shadow:inset 0 0 0 1px rgba(15,23,42,.06);display:flex;align-items:center;overflow:hidden}.software-home-search input{width:100%;height:100%;border:0;background:transparent;outline:0;padding:0 58px 0 16px;color:var(--text-main,#0f172a);font-size:14px;font-weight:750}.software-home-search input::placeholder{color:var(--text-muted,#94a3b8)}.software-home-search button{position:absolute;right:9px;top:50%;transform:translateY(-50%);width:38px;height:38px;border:0;border-radius:13px;background:#0f172a;color:#fff;display:grid;place-items:center;cursor:pointer}.software-home-search svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
  .software-home-hero{position:relative;min-height:250px;background:linear-gradient(135deg,#0f172a,#1d4ed8 52%,#14b8a6);color:#fff;border-radius:24px;overflow:hidden;padding:26px;box-shadow:0 26px 70px rgba(15,23,42,.22)}.software-home-hero::before{content:'';position:absolute;inset:-40px;background:radial-gradient(circle at 76% 18%,rgba(255,255,255,.24),transparent 28%),radial-gradient(circle at 18% 92%,rgba(45,212,191,.32),transparent 36%)}.software-home-hero>*{position:relative;z-index:1}.software-home-kicker{display:inline-flex;height:28px;align-items:center;padding:0 11px;border-radius:999px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.18);font-size:12px;font-weight:950}.software-home-hero h1{margin:16px 0 10px;font-size:36px;line-height:1.08;letter-spacing:-1.4px;font-weight:950}.software-home-hero p{max-width:560px;margin:0;color:rgba(255,255,255,.82);font-size:15px;line-height:1.75}.software-home-cta{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap}.software-home-cta a{display:inline-flex;align-items:center;justify-content:center;height:42px;padding:0 16px;border-radius:12px;text-decoration:none;font-size:13px;font-weight:950}.software-home-cta .primary{background:#fff;color:#0f172a}.software-home-cta .ghost{background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.18)}.software-home-hero-logo{position:absolute;right:30px;bottom:26px;width:96px;height:96px;border-radius:24px;background:rgba(255,255,255,.94);box-shadow:0 22px 52px rgba(0,0,0,.28);overflow:hidden}.software-home-hero-logo img{width:100%;height:100%;object-fit:cover}.software-home-hero-logo .logo-placeholder{width:100%;height:100%;display:grid;place-items:center;color:#0f172a;background:linear-gradient(135deg,#eef2ff,#d1fae5)}.software-home-hero-logo svg{width:46px;height:46px;stroke:currentColor;fill:none;stroke-width:1.8}
  .software-home-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.software-home-stat{border-radius:15px;background:var(--bg-soft,#f8fafc);padding:12px;text-align:center}.software-home-stat strong{display:block;font-size:20px;font-weight:950;color:var(--text-main,#0f172a);line-height:1}.software-home-stat span{display:block;margin-top:6px;font-size:11px;font-weight:850;color:var(--text-muted,#94a3b8)}.software-home-cat-list,.software-home-rank-list{display:grid;gap:7px}.software-home-cat,.software-home-rank{display:grid;grid-template-columns:36px minmax(0,1fr) auto;align-items:center;gap:10px;padding:9px;border-radius:14px;background:transparent;color:inherit;text-decoration:none}.software-home-cat:hover,.software-home-rank:hover{background:var(--bg-soft,#f8fafc)}.software-home-cat-icon,.software-home-rank-logo{width:36px;height:36px;border-radius:12px;background:linear-gradient(135deg,#e0f2fe,#eef2ff);display:grid;place-items:center;overflow:hidden;color:#0284c7;font-weight:950}.software-home-rank-logo img{width:100%;height:100%;object-fit:cover}.software-home-cat-main,.software-home-rank-main{min-width:0}.software-home-cat-name,.software-home-rank-name{font-size:13px;font-weight:950;color:var(--text-main,#0f172a);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.software-home-cat-meta,.software-home-rank-meta{margin-top:3px;font-size:11px;color:var(--text-muted,#94a3b8);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.software-home-rank-badge{font-size:11px;font-weight:950;color:#0284c7;background:rgba(2,132,199,.1);border-radius:999px;padding:4px 7px}.software-home-submit{display:grid;gap:10px}.software-home-submit a{height:38px;border-radius:12px;display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:13px;font-weight:950}.software-home-submit .primary{background:#0f172a;color:#fff}.software-home-submit .secondary{background:var(--bg-soft,#f8fafc);color:var(--text-main,#0f172a)}
  .filter-popup .popup-body{left:50%;right:auto;bottom:auto;top:50%;width:min(520px,calc(100vw - 48px));border-radius:20px;transform:translate(-50%,-46%) scale(.98);box-shadow:0 24px 80px rgba(15,23,42,.24)}
  .filter-popup.show .popup-body{transform:translate(-50%,-50%) scale(1)}
}
@media (min-width: 1200px){.app-list{grid-template-columns:repeat(3,minmax(0,1fr))}.software-store--home .app-list{grid-template-columns:minmax(0,1fr)}}
@media (min-width: 900px) and (max-width: 1180px){.software-store--home .software-home-shell{grid-template-columns:220px minmax(0,1fr)}.software-store--home .software-home-right{display:none}.software-store--home .app-list{grid-template-columns:minmax(0,1fr)}}
@media (min-width: 900px) and (max-width: 1080px){.filter-bar{top:144px}.software-header .header-title{font-size:32px}.software-header .header-subtitle{font-size:14px}}
@media (max-width: 899px){.filter-bar{top:110px}.software-home-shell,.software-home-main{display:contents}.software-home-left,.software-home-right,.software-home-hero{display:none!important}}

/* ── 暗色模式 ── */
html[data-theme="dark"] .software-header{background:rgba(17,24,39,.9);border-color:#263244}
html[data-theme="dark"] .filter-bar{background:#111827;border-color:#263244}
html[data-theme="dark"] .app-box{background:#1e293b}
html[data-theme="dark"] .app-box-title{color:#e5e7eb}
html[data-theme="dark"] .app-box-info{color:#94a3b8}
html[data-theme="dark"] .app-category-tag{background:#0f172a;color:#94a3b8}
html[data-theme="dark"] .filter-item{color:#cbd5e1}
html[data-theme="dark"] .filter-item.active{color:#3cc9a4}
html[data-theme="dark"] .popup-body{background:#1e293b}
html[data-theme="dark"] .popup-title{color:#e5e7eb}
html[data-theme="dark"] .popup-option{border-color:#334155;color:#94a3b8;background:#1e293b}
html[data-theme="dark"] .popup-option:hover{border-color:#0f9f83;color:#5eead4}
html[data-theme="dark"] .popup-handle{background:#334155}
html[data-theme="dark"] .popup-close{background:#0f172a;color:#94a3b8}
html[data-theme="dark"] .popup-head .popup-ico{background:linear-gradient(135deg,rgba(60,201,164,.18),rgba(34,211,238,.14));color:#5eead4}
html[data-theme="dark"] .btn-reset{background:#0f172a;color:#94a3b8}
html[data-theme="dark"] .software-store{background:#0f172a}
html[data-theme="dark"] .software-home-panel{background:rgba(17,24,39,.88);border-color:#263244;box-shadow:0 14px 36px rgba(0,0,0,.22)}
html[data-theme="dark"] .software-home-stat,html[data-theme="dark"] .software-home-cat:hover,html[data-theme="dark"] .software-home-rank:hover,html[data-theme="dark"] .software-home-submit .secondary,html[data-theme="dark"] .software-home-search{background:#0f172a;color:#cbd5e1}
html[data-theme="dark"] .software-home-cat-name,html[data-theme="dark"] .software-home-rank-name,html[data-theme="dark"] .software-home-title,html[data-theme="dark"] .software-home-stat strong{color:#e5e7eb}
html[data-theme="dark"] .app-box-down{background:#0ea5e9}
</style>

<div class="software-store<?= !empty($isStoreHome) ? ' software-store--home' : '' ?>">
  <?php if (!empty($isStoreHome)): ?>
  <div class="software-home-shell">
  <?php endif; ?>
  
  <div class="software-header">
    <div class="header-copy">
      <span class="header-title">软件库</span>
      <span class="header-subtitle">发现 ClayBBS 社区投稿的应用、插件和工具</span>
    </div>
    <div class="header-search<?= $currentQ !== '' ? ' open' : '' ?>" id="hSearch">
      <input type="text" placeholder="搜索应用、开发者或关键词..." id="hSearchInput" value="<?= htmlspecialchars($currentQ) ?>" onkeydown="if(event.key==='Enter')doSearch()">
      <button class="search-btn" onclick="toggleSearch()" aria-label="搜索"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg></button>
    </div>
  </div>

  <?php if (!empty($isStoreHome)): ?>
  <aside class="software-home-left" aria-label="软件库侧栏">
    <section class="software-home-panel software-home-pad">
      <h3 class="software-home-title">软件库概览</h3>
      <div class="software-home-stats">
        <div class="software-home-stat"><strong><?= number_format((int)($storeStats['apps'] ?? 0)) ?></strong><span>上架应用</span></div>
        <div class="software-home-stat"><strong><?= number_format((int)($storeStats['downloads'] ?? 0)) ?></strong><span>累计下载</span></div>
        <div class="software-home-stat"><strong><?= number_format((int)($storeStats['featured'] ?? 0)) ?></strong><span>推荐应用</span></div>
        <div class="software-home-stat"><strong><?= number_format((int)($storeStats['categories'] ?? 0)) ?></strong><span>分类</span></div>
      </div>
    </section>
    <section class="software-home-panel software-home-pad">
      <h3 class="software-home-title">分类导航 <a href="/index.php?path=software">全部</a></h3>
      <div class="software-home-cat-list">
        <?php foreach (array_slice($categories ?? [], 0, 8) as $c): ?>
        <a class="software-home-cat" href="/index.php?path=software&category=<?= (int)$c['id'] ?>">
          <span class="software-home-cat-icon">#</span>
          <span class="software-home-cat-main"><span class="software-home-cat-name"><?= htmlspecialchars((string)$c['name']) ?></span><span class="software-home-cat-meta">查看该分类应用</span></span>
          <span class="software-home-rank-badge">进入</span>
        </a>
        <?php endforeach; ?>
        <?php if (empty($categories)): ?><div class="software-home-muted">暂无分类</div><?php endif; ?>
      </div>
    </section>
  </aside>
  <main class="software-home-main">
  <?php endif; ?>

  
  <?php if (!empty($featured)): ?>
  <div class="broadcast-wrap" id="appBroadcast">
    <div class="broadcast-track" id="broadcastTrack">
      <?php foreach (array_slice($featured, 0, 5) as $i => $f): ?>
      <?php
        $rating = (float)($f['rating_avg'] ?? 0);
        $desc = trim((string)($f['description'] ?? '')) ?: trim((string)($f['developer'] ?? '优质应用推荐'));
        $platformText = $platformLabels[$f['platform'] ?? ''] ?? ($f['platform'] ?? '应用');
      ?>
      <div class="broadcast-slide">
        <article class="broadcast-card<?= $i === 0 ? ' is-current' : '' ?>" data-index="<?= (int)$i ?>" tabindex="0" role="button" aria-expanded="false" aria-label="展开 <?= htmlspecialchars($f['name']) ?> 推荐卡片">
          <?php if (!empty($f['icon'])): ?>
          <div class="broadcast-bg" style="background-image:url(<?= htmlspecialchars($f['icon']) ?>)"></div>
          <?php else: ?>
          <div class="broadcast-bg" style="background:linear-gradient(135deg,#0f172a,#164e63 55%,#3cc9a4)"></div>
          <?php endif; ?>
          <div class="broadcast-glow"></div>
          <div class="broadcast-main">
            <div class="broadcast-kicker">软件库推荐</div>
            <h2 class="broadcast-name"><?= htmlspecialchars($f['name']) ?></h2>
            <div class="broadcast-desc"><?= htmlspecialchars(mb_substr($desc, 0, 54)) ?></div>
          </div>
          <div class="broadcast-cover">
            <?php if (!empty($f['icon'])): ?>
            <img src="<?= htmlspecialchars($f['icon']) ?>" alt="<?= htmlspecialchars($f['name']) ?>">
            <?php else: ?>
            <div class="broadcast-cover-placeholder" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="7" y="2.5" width="10" height="19" rx="2.4"/><path d="M10 18h4"/><path d="M9 5.5h6"/></svg></div>
            <?php endif; ?>
          </div>
          <div class="broadcast-meta">
            <span class="broadcast-pill star">★ <?= number_format($rating, 1) ?></span>
            <span class="broadcast-pill"><?= htmlspecialchars($platformText) ?></span>
          </div>
          <div class="broadcast-detail" aria-hidden="true">
            <div class="broadcast-detail-card">
              <div class="broadcast-detail-head">
                <div class="broadcast-detail-title"><?= htmlspecialchars($f['name']) ?></div>
                <div class="broadcast-detail-version">v<?= htmlspecialchars((string)($f['version'] ?? '1.0')) ?></div>
              </div>
              <p class="broadcast-detail-desc"><?= htmlspecialchars(mb_substr($desc, 0, 90)) ?></p>
              <div class="broadcast-detail-stats">
                <div class="broadcast-stat"><b><?= number_format($rating, 1) ?></b><span>评分</span></div>
                <div class="broadcast-stat"><b><?= number_format((int)($f['download_count'] ?? 0)) ?></b><span>下载</span></div>
                <div class="broadcast-stat"><b><?= htmlspecialchars($platformText) ?></b><span>平台</span></div>
              </div>
              <div class="broadcast-actions">
                <a class="broadcast-btn ghost" href="/index.php?path=software/show&slug=<?= urlencode((string)$f['slug']) ?>">查看详情</a>
                <a class="broadcast-btn primary" href="/index.php?path=software/download&id=<?= (int)$f['id'] ?>">立即下载</a>
              </div>
            </div>
          </div>
        </article>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="broadcast-dots" id="broadcastDots" aria-label="推荐软件切换">
      <?php foreach (array_slice($featured, 0, 5) as $i => $f): ?>
      <button type="button" class="broadcast-dot<?= $i === 0 ? ' active' : '' ?>" data-index="<?= (int)$i ?>" aria-label="第 <?= (int)$i + 1 ?> 个推荐"></button>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  
  <div class="filter-bar">
    <div class="filter-item<?= $currentPlatform !== '' ? ' active' : '' ?>" onclick="openFilter('system')">
      <span id="systemLabel"><?= $currentPlatform ? ($platformLabels[$currentPlatform] ?? $currentPlatform) : '系统' ?></span>
      <span class="arrow">▼</span>
    </div>
    <div class="filter-item<?= $currentCategory > 0 ? ' active' : '' ?>" onclick="openFilter('category')">
      <span id="categoryLabel">
        <?php
          $catName = '分类';
          foreach ($categories as $c) { if ((int)$c['id'] === $currentCategory) { $catName = $c['name']; break; } }
          echo htmlspecialchars($catName);
        ?>
      </span>
      <span class="arrow">▼</span>
    </div>
    <div class="filter-item<?= $currentOrder !== 'created' ? ' active' : '' ?>" onclick="openFilter('order')">
      <span id="orderLabel"><?= htmlspecialchars($orderOptions[$currentOrder] ?? '最新投稿') ?></span>
      <span class="arrow">▼</span>
    </div>
  </div>

  
  <div class="app-list" id="appList">
    <?php if (empty($softwares)): ?>
    <div class="empty-state">
      <div class="empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7.5l8-4 8 4-8 4-8-4z"/><path d="M4 7.5v9l8 4 8-4v-9"/><path d="M12 11.5v9"/></svg></div>
      <p>暂无应用，快来投稿吧</p>
    </div>
    <?php else: ?>
    <?php foreach ($softwares as $s): ?>
    <div class="app-box">
      <a href="/index.php?path=software/show&slug=<?= htmlspecialchars($s['slug']) ?>">
        <div class="app-box-logo">
          <?php if (!empty($s['icon'])): ?>
          <img src="<?= htmlspecialchars($s['icon']) ?>" alt="" loading="lazy">
          <?php else: ?>
          <div class="logo-placeholder" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="7" y="2.5" width="10" height="19" rx="2.4"/><path d="M10 18h4"/><path d="M9 5.5h6"/></svg></div>
          <?php endif; ?>
        </div>
        <div class="app-box-content">
          <div class="app-box-title"><?= htmlspecialchars($s['name']) ?></div>
          <div class="app-box-info">
            <?php if (($s['rating_avg'] ?? 0) > 0): ?>
            <span class="star-icon">★</span> <?= number_format((float)$s['rating_avg'], 1) ?>
            <?php endif; ?>
            <span>v<?= htmlspecialchars($s['version']) ?></span>
            <?php if (!empty($s['platform'])): ?>
            <span><?= $platformLabels[$s['platform']] ?? $s['platform'] ?></span>
            <?php endif; ?>
          </div>
          <div class="app-box-tags">
            <?php
              $appType = $s['type'] ?? '';
              if ($appType !== '' && isset($typeMap[$appType])): ?>
            <span class="app-tag" style="background:<?= htmlspecialchars((string)$typeMap[$appType]['color']) ?>"><?= htmlspecialchars((string)$typeMap[$appType]['name']) ?></span>
            <?php endif; ?>
            <?php if (!empty($s['category_name'])): ?>
            <span class="app-category-tag"><?= htmlspecialchars($s['category_name']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </a>
      <a href="/index.php?path=software/download&id=<?= (int)$s['id'] ?>" class="app-box-down">下载</a>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php if ($total > 20): ?>
  <div class="load-more" id="loadMore" onclick="loadMore()">加载更多</div>
  <?php endif; ?>
  <?php if (!empty($isStoreHome)): ?>
  </main>
  <aside class="software-home-right" aria-label="软件库右侧栏">
    <section class="software-home-panel software-home-search-panel">
      <div class="software-home-search" role="search">
        <input type="text" placeholder="搜索应用..." value="<?= htmlspecialchars($currentQ) ?>" onkeydown="if(event.key==='Enter'){var u=new URL(location.href);u.searchParams.set('q',this.value.trim());u.searchParams.delete('page');location.href=u.toString();}">
        <button type="button" aria-label="搜索" onclick="var i=this.parentElement.querySelector('input');var u=new URL(location.href);u.searchParams.set('q',i.value.trim());u.searchParams.delete('page');location.href=u.toString();"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg></button>
      </div>
    </section>
    <section class="software-home-panel software-home-pad">
      <h3 class="software-home-title">下载榜 <a href="/index.php?path=software&order=comments">更多</a></h3>
      <div class="software-home-rank-list">
        <?php foreach (array_slice($topDownloaded ?? [], 0, 6) as $i => $r): ?>
        <a class="software-home-rank" href="/index.php?path=software/show&slug=<?= urlencode((string)$r['slug']) ?>">
          <span class="software-home-rank-logo"><?php if (!empty($r['icon'])): ?><img src="<?= htmlspecialchars((string)$r['icon']) ?>" alt=""><?php else: ?><?= (int)$i + 1 ?><?php endif; ?></span>
          <span class="software-home-rank-main"><span class="software-home-rank-name"><?= htmlspecialchars((string)$r['name']) ?></span><span class="software-home-rank-meta"><?= number_format((int)($r['download_count'] ?? 0)) ?> 次下载</span></span>
          <span class="software-home-rank-badge">#<?= (int)$i + 1 ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
    <section class="software-home-panel software-home-pad">
      <h3 class="software-home-title">好评应用</h3>
      <div class="software-home-rank-list">
        <?php foreach (array_slice($topRated ?? [], 0, 5) as $r): ?>
        <a class="software-home-rank" href="/index.php?path=software/show&slug=<?= urlencode((string)$r['slug']) ?>">
          <span class="software-home-rank-logo"><?php if (!empty($r['icon'])): ?><img src="<?= htmlspecialchars((string)$r['icon']) ?>" alt=""><?php else: ?>★<?php endif; ?></span>
          <span class="software-home-rank-main"><span class="software-home-rank-name"><?= htmlspecialchars((string)$r['name']) ?></span><span class="software-home-rank-meta">评分 <?= number_format((float)($r['rating_avg'] ?? 0), 1) ?></span></span>
          <span class="software-home-rank-badge">查看</span>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
    <section class="software-home-panel software-home-pad software-home-submit">
      <h3 class="software-home-title">社区发布</h3>
      <div class="software-home-muted">软件首页保留论坛式入口：发布内容、投稿应用、管理自己的帖子。</div>
      <a class="primary" href="/index.php?path=software/submission">软件投稿</a>
      <a class="secondary" href="/index.php?path=publish">发布帖子</a>
    </section>
  </aside>
  </div>
  <?php endif; ?>
</div>


<div class="filter-popup" id="filterSystem">
  <div class="popup-mask" onclick="closeFilter()"></div>
  <div class="popup-body">
    <div class="popup-handle"></div>
    <div class="popup-head">
      <span class="popup-ico"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg></span>
      <div class="popup-title">选择系统<small>按设备平台筛选应用</small></div>
      <button type="button" class="popup-close" onclick="closeFilter()" aria-label="关闭">&times;</button>
    </div>
    <div class="popup-options">
      <div class="popup-option<?= $currentPlatform === '' ? ' active' : '' ?>" data-value="">全部<span class="opt-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span></div>
      <div class="popup-option<?= $currentPlatform === 'android' ? ' active' : '' ?>" data-value="android">Android<span class="opt-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span></div>
      <div class="popup-option<?= $currentPlatform === 'ios' ? ' active' : '' ?>" data-value="ios">iOS<span class="opt-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span></div>
      <div class="popup-option<?= $currentPlatform === 'windows' ? ' active' : '' ?>" data-value="windows">Windows<span class="opt-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span></div>
      <div class="popup-option<?= $currentPlatform === 'macos' ? ' active' : '' ?>" data-value="macos">macOS<span class="opt-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span></div>
    </div>
    <div class="popup-btns">
      <button class="btn-reset" onclick="resetFilter('system')">重置</button>
      <button class="btn-confirm" onclick="confirmFilter('system')">确认</button>
    </div>
  </div>
</div>


<div class="filter-popup" id="filterCategory">
  <div class="popup-mask" onclick="closeFilter()"></div>
  <div class="popup-body">
    <div class="popup-handle"></div>
    <div class="popup-head">
      <span class="popup-ico"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg></span>
      <div class="popup-title">选择分类<small>按应用类别浏览</small></div>
      <button type="button" class="popup-close" onclick="closeFilter()" aria-label="关闭">&times;</button>
    </div>
    <div class="popup-options">
      <div class="popup-option<?= $currentCategory === 0 ? ' active' : '' ?>" data-value="0">全部分类<span class="opt-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span></div>
      <?php foreach ($categories as $c): ?>
      <div class="popup-option<?= (int)$c['id'] === $currentCategory ? ' active' : '' ?>" data-value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?><span class="opt-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span></div>
      <?php endforeach; ?>
    </div>
    <div class="popup-btns">
      <button class="btn-reset" onclick="resetFilter('category')">重置</button>
      <button class="btn-confirm" onclick="confirmFilter('category')">确认</button>
    </div>
  </div>
</div>


<div class="filter-popup" id="filterOrder">
  <div class="popup-mask" onclick="closeFilter()"></div>
  <div class="popup-body">
    <div class="popup-handle"></div>
    <div class="popup-head">
      <span class="popup-ico"><svg viewBox="0 0 24 24"><path d="M3 6h12M3 12h9M3 18h6"/><path d="M17 8l4 4-4 4"/><path d="M21 12h-8"/></svg></span>
      <div class="popup-title">最新投稿<small>切换应用排序方式</small></div>
      <button type="button" class="popup-close" onclick="closeFilter()" aria-label="关闭">&times;</button>
    </div>
    <div class="popup-options">
      <?php foreach ($orderOptions as $k => $v): ?>
      <div class="popup-option<?= $currentOrder === $k ? ' active' : '' ?>" data-value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?><span class="opt-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span></div>
      <?php endforeach; ?>
    </div>
    <div class="popup-btns">
      <button class="btn-reset" onclick="resetFilter('order')">重置</button>
      <button class="btn-confirm" onclick="confirmFilter('order')">确认</button>
    </div>
  </div>
</div>

<script>
// ── 软件库转播轮：点击后过渡展开 ──
(function(){
  var wrap = document.getElementById('appBroadcast');
  var track = document.getElementById('broadcastTrack');
  if (!wrap || !track) return;
  var cards = Array.prototype.slice.call(track.querySelectorAll('.broadcast-card'));
  var dots = Array.prototype.slice.call(document.querySelectorAll('#broadcastDots .broadcast-dot'));
  var idx = 0, total = cards.length, timer = null, expanded = null;
  function setExpanded(card, on){
    card.classList.toggle('is-expanded', !!on);
    card.setAttribute('aria-expanded', on ? 'true' : 'false');
    var detail = card.querySelector('.broadcast-detail');
    if (detail) detail.setAttribute('aria-hidden', on ? 'false' : 'true');
  }
  function collapseAll(){ cards.forEach(function(c){ setExpanded(c, false); }); expanded = null; }
  function go(n){
    idx = (n + total) % total;
    collapseAll();
    track.style.transform = 'translateX(-' + (idx * 100) + '%)';
    cards.forEach(function(c,i){ c.classList.toggle('is-current', i === idx); });
    dots.forEach(function(d,i){ d.classList.toggle('active', i === idx); });
  }
  function start(){
    stop();
    if (total > 1) timer = setInterval(function(){ if (!expanded) go(idx + 1); }, 4200);
  }
  function stop(){ if (timer) { clearInterval(timer); timer = null; } }
  cards.forEach(function(card,i){
    card.addEventListener('click', function(e){
      if (e.target.closest('a')) return;
      e.preventDefault();
      if (i !== idx) { go(i); setTimeout(function(){ setExpanded(card, true); expanded = card; stop(); }, 90); return; }
      var willOpen = !card.classList.contains('is-expanded');
      collapseAll();
      if (willOpen) { setExpanded(card, true); expanded = card; stop(); } else { start(); }
    });
    card.addEventListener('keydown', function(e){ if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); card.click(); } });
  });
  dots.forEach(function(dot,i){ dot.addEventListener('click', function(e){ e.stopPropagation(); go(i); start(); }); });
  document.addEventListener('click', function(e){ if (expanded && !wrap.contains(e.target)) { collapseAll(); start(); } });
  start();
})();

// ── 搜索 ──
function toggleSearch(){
  var s = document.getElementById('hSearch');
  s.classList.toggle('open');
  if (s.classList.contains('open')) document.getElementById('hSearchInput').focus();
}
function doSearch(){
  var q = document.getElementById('hSearchInput').value.trim();
  var u = new URL(location.href); u.searchParams.set('q', q); u.searchParams.delete('page');
  location.href = u.toString();
}

// ── 筛选弹窗（动态特效版） ──
var currentFilterType = '';
function openFilter(type){
  currentFilterType = type;
  closeFilter();
  var id = type === 'system' ? 'filterSystem' : type === 'category' ? 'filterCategory' : 'filterOrder';
  var popup = document.getElementById(id);
  if (!popup) return;
  // 选项错峰入场：逐个设置过渡延迟
  var opts = popup.querySelectorAll('.popup-option');
  opts.forEach(function(o, i){ o.style.transitionDelay = (0.04 + i * 0.035) + 's'; });
  popup.classList.add('show');
  document.body.classList.add('software-filter-lock');
  // 标记筛选栏
  document.querySelectorAll('.filter-item').forEach(function(el){ el.classList.remove('open'); });
  var idx = type === 'system' ? 0 : type === 'category' ? 1 : 2;
  var items = document.querySelectorAll('.filter-item');
  if (items[idx]) items[idx].classList.add('open');
}
function closeFilter(){
  document.querySelectorAll('.filter-popup').forEach(function(p){
    p.classList.remove('show');
    // 关闭后清掉延迟，下次重新错峰
    p.querySelectorAll('.popup-option').forEach(function(o){ o.style.transitionDelay = '0s'; });
  });
  document.querySelectorAll('.filter-item').forEach(function(el){ el.classList.remove('open'); });
  document.body.classList.remove('software-filter-lock');
}
// ESC 关闭弹窗
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape' && document.querySelector('.filter-popup.show')) closeFilter();
});
function confirmFilter(type){
  var id = type === 'system' ? 'filterSystem' : type === 'category' ? 'filterCategory' : 'filterOrder';
  var active = document.querySelector('#' + id + ' .popup-option.active');
  var val = active ? active.dataset.value : '';
  var u = new URL(location.href);
  if (type === 'system') u.searchParams.set('platform', val);
  else if (type === 'category') u.searchParams.set('category', val);
  else u.searchParams.set('order', val);
  u.searchParams.delete('page');
  location.href = u.toString();
}
function resetFilter(type){
  var id = type === 'system' ? 'filterSystem' : type === 'category' ? 'filterCategory' : 'filterOrder';
  document.querySelectorAll('#' + id + ' .popup-option').forEach(function(o){ o.classList.remove('active'); });
  var first = document.querySelector('#' + id + ' .popup-option');
  if (first) first.classList.add('active');
}
// 选项点击切换
document.querySelectorAll('.popup-option').forEach(function(opt){
  opt.addEventListener('click', function(){
    var siblings = this.parentElement.querySelectorAll('.popup-option');
    siblings.forEach(function(s){ s.classList.remove('active'); });
    this.classList.add('active');
  });
});
</script>

<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>