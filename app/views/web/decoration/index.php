<?php if(!defined('CLAY_ACCESS')){http_response_code(404);exit;}
$pageTitle='装饰中心';
$hideContainer=$hideBottomNav=false;
$bodyClass='layout-default';
$breadcrumb=[['label'=>'个人中心','url'=>'/index.php?path=me'],['label'=>'装饰中心']];
$siteCfg = $siteCfg ?? (new \App\Models\SettingModel())->getSiteConfig();
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($siteCfg['site_name'] ?? 'ClayBBS') ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="<?= htmlspecialchars($bodyClass) ?>">
<?php
require __DIR__.'/../layouts/topbar.php';
$frames = $frames ?? [];
$equipped = $equipped ?? null;
$user = $user ?? ($_SESSION['auth_user'] ?? []);
$frameOwnedCount = 0;
$frameTotalCount = count($frames);
$equippedId = $equipped ? (int)($equipped['id'] ?? 0) : 0;
foreach ($frames as $f) { if (!empty($f['owned'])) $frameOwnedCount++; }
?>
<style>
.deco-tabs{display:flex!important;gap:6px!important;padding:8px 16px!important;margin:0!important;background:linear-gradient(180deg,#f8fbff,#f4f7fb)!important;border-radius:18px!important;box-shadow:0 2px 8px rgba(15,23,42,.06)!important}.deco-tabs button{flex:1!important;border:0!important;border-radius:999px!important;background:rgba(255,255,255,.7)!important;color:#64748b!important;font-size:13px!important;font-weight:800!important;padding:10px 0!important;cursor:pointer!important;transition:all .2s!important}.deco-tabs button.active{background:linear-gradient(135deg,#0f172a,#1e3a8a)!important;color:#fff!important;box-shadow:0 6px 16px rgba(37,99,235,.25)!important}.deco-tab-pane{display:none!important}.deco-tab-pane.active{display:block!important}.decoration-page{padding-top:70px!important}
html[data-theme="dark"] .deco-tabs{background:#0b1120!important}html[data-theme="dark"] .deco-tabs button{background:#111827!important;color:#94a3b8!important}html[data-theme="dark"] .deco-tabs button.active{background:linear-gradient(135deg,#1e3a8a,#7c3aed)!important;color:#fff!important}
.frame-center{min-height:100vh!important;padding:0 16px 112px!important;color:var(--text-main,#0f172a)!important}.frame-shell{max-width:1180px!important;margin:0 auto!important;display:grid!important;gap:16px!important}.frame-board{display:grid!important;grid-template-columns:minmax(0,1fr) 270px!important;gap:16px!important;align-items:stretch!important}.frame-panel{background:rgba(255,255,255,.88)!important;border:1px solid rgba(226,232,240,.86)!important;border-radius:26px!important;padding:20px!important;box-shadow:0 16px 44px rgba(15,23,42,.06)!important;backdrop-filter:blur(12px)!important}.frame-preview-panel{padding:20px!important}.frame-panel-head{display:flex!important;align-items:flex-end!important;justify-content:space-between!important;gap:14px!important;margin-bottom:16px!important}.frame-panel-head h1,.frame-panel-head h2{margin:0!important;font-size:22px!important;letter-spacing:-.04em!important}.frame-panel-head p{margin:6px 0 0!important;color:#64748b!important;font-size:13px!important;line-height:1.65!important}.frame-count{font-size:12px!important;font-weight:950!important;color:#2563eb!important;background:#eff6ff!important;border:1px solid #bfdbfe!important;border-radius:999px!important;padding:7px 10px!important;white-space:nowrap!important}.frame-stage{min-height:198px!important;border:1px dashed rgba(148,163,184,.7)!important;border-radius:26px!important;background:linear-gradient(180deg,#fff,rgba(248,250,252,.86))!important;display:grid!important;place-items:center!important;text-align:center!important;position:relative!important;overflow:hidden!important}.frame-stage:before{content:"";position:absolute!important;inset:auto -80px -120px auto!important;width:260px!important;height:260px!important;border-radius:999px!important;background:radial-gradient(circle,rgba(37,99,235,.12),rgba(37,99,235,0) 65%)!important}.frame-stage .avatar-verify-wrap{--avatar-size:112px!important;position:relative!important;z-index:1!important}.frame-stage-empty{position:relative!important;z-index:1!important;color:#94a3b8!important;font-weight:950!important}.frame-stage-empty span{display:block!important;width:68px!important;height:68px!important;border-radius:24px!important;border:1px dashed #cbd5e1!important;background:#f8fafc!important;margin:0 auto 10px!important}.frame-equipped-name{position:relative!important;z-index:1!important;margin-top:12px!important;font-size:13px!important;font-weight:950!important;color:#475569!important}.frame-side-stats{padding:20px!important;display:grid!important;gap:12px!important}.side-stat{border-radius:20px!important;padding:14px!important;background:#fff!important;border:1px solid #e2e8f0!important}.side-stat strong{display:block!important;font-size:26px!important;letter-spacing:-.04em!important}.side-stat span{display:block!important;margin-top:5px!important;color:#64748b!important;font-size:12px!important;font-weight:900!important}.frame-unequip{min-height:48px!important;border:1px dashed #fecaca!important;background:#fff7f7!important;color:#b91c1c!important;border-radius:20px!important;font-size:12px!important;font-weight:950!important;display:flex!important;align-items:center!important;justify-content:center!important;padding:0 14px!important;cursor:pointer!important}.frame-library{padding:18px!important}.library-top{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:12px!important;flex-wrap:wrap!important;margin-bottom:14px!important}.frame-tabs{display:flex!important;gap:8px!important;flex-wrap:wrap!important}.frame-tab{border:1px solid #e2e8f0!important;background:#fff!important;color:#475569!important;border-radius:999px!important;padding:9px 14px!important;font-weight:950!important;cursor:pointer!important}.frame-tab.active{background:#0f172a!important;color:#fff!important;border-color:#0f172a!important}.library-hint{font-size:12px!important;color:#64748b!important;font-weight:800!important}.quality-tabs{display:flex!important;gap:7px!important;flex-wrap:wrap!important;margin:0 0 14px!important;padding:10px!important;border-radius:20px!important;background:#f8fafc!important;border:1px solid #e2e8f0!important}.quality-tab{border:1px solid #e2e8f0!important;background:#fff!important;color:#64748b!important;border-radius:999px!important;padding:7px 12px!important;font-size:12px!important;font-weight:950!important;cursor:pointer!important}.quality-tab.active{background:color-mix(in srgb,var(--quality-color,#2563eb) 12%,#fff)!important;border-color:color-mix(in srgb,var(--quality-color,#2563eb) 34%,#fff)!important;color:var(--quality-color,#1d4ed8)!important}.frame-grid{display:grid!important;grid-template-columns:repeat(auto-fill,minmax(160px,1fr))!important;gap:12px!important}.frame-card{min-height:196px!important;border:1px solid #e2e8f0!important;border-radius:22px!important;background:#fff!important;position:relative!important;transition:.16s ease!important;overflow:hidden!important;padding:14px!important;display:grid!important;grid-template-rows:104px auto!important;gap:10px!important}.frame-card.owned{border-color:rgba(37,99,235,.22)!important;box-shadow:0 14px 30px rgba(15,23,42,.045)!important}.frame-card.locked{filter:grayscale(1)!important;opacity:.48!important}.frame-card:hover{transform:translateY(-2px)!important;box-shadow:0 18px 34px rgba(15,23,42,.08)!important}.frame-card.equipped{border-color:rgba(22,163,74,.36)!important}.frame-art{border-radius:18px!important;background:linear-gradient(180deg,#f8fafc,#eef2ff)!important;display:grid!important;place-items:center!important;position:relative!important}.frame-art img{width:92px!important;height:92px!important;object-fit:contain!important;filter:drop-shadow(0 12px 18px rgba(15,23,42,.12))!important}.frame-status{position:absolute!important;right:9px!important;top:9px!important;width:10px!important;height:10px!important;border-radius:999px!important;background:#cbd5e1!important}.frame-card.owned .frame-status{background:#2563eb!important}.frame-card.equipped .frame-status{background:#16a34a!important}.frame-name{font-size:15px!important;font-weight:950!important;color:#0f172a!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}.frame-desc{margin-top:4px!important;color:#64748b!important;font-size:12px!important;line-height:1.55!important;display:-webkit-box!important;-webkit-line-clamp:2!important;-webkit-box-orient:vertical!important;overflow:hidden!important}.frame-meta{display:flex!important;gap:6px!important;flex-wrap:wrap!important;margin-top:9px!important}.frame-tag{font-size:11px!important;font-weight:950!important;border-radius:999px!important;padding:4px 8px!important;background:#f8fafc!important;border:1px solid #e2e8f0!important;color:#64748b!important}.frame-tag.quality{color:var(--quality-color,#2563eb)!important;background:color-mix(in srgb,var(--quality-color,#2563eb) 9%,#fff)!important;border-color:color-mix(in srgb,var(--quality-color,#2563eb) 28%,#fff)!important}.frame-actions{margin-top:10px!important}.frame-actions form{margin:0!important}.frame-action{height:34px!important;width:100%!important;border:0!important;border-radius:999px!important;background:#0f172a!important;color:#fff!important;font-size:12px!important;font-weight:950!important;cursor:pointer!important}.frame-action.equipped{background:#16a34a!important}.frame-action[disabled]{background:#e2e8f0!important;color:#94a3b8!important;cursor:not-allowed!important}.frame-empty{grid-column:1/-1!important;text-align:center!important;color:#94a3b8!important;font-weight:900!important;padding:28px!important}
.frame-pager{display:flex!important;gap:6px!important;flex-wrap:wrap!important;justify-content:center!important;margin-top:14px!important}.frame-page-btn{border:1px solid #e2e8f0!important;background:#fff!important;color:#475569!important;border-radius:999px!important;padding:7px 12px!important;font-size:12px!important;font-weight:900!important;cursor:pointer!important}.frame-page-btn.active{background:#0f172a!important;color:#fff!important;border-color:#0f172a!important}.frame-page-btn[disabled]{opacity:.4!important;cursor:not-allowed!important}.frame-toast{position:fixed!important;left:50%!important;bottom:86px!important;transform:translateX(-50%) translateY(10px)!important;z-index:1300!important;min-width:160px!important;max-width:min(460px,calc(100vw - 32px))!important;border-radius:999px!important;background:rgba(15,23,42,.92)!important;color:#fff!important;font-size:13px!important;font-weight:900!important;text-align:center!important;padding:10px 16px!important;box-shadow:0 18px 45px rgba(15,23,42,.22)!important}
html[data-theme="dark"] body{background:#0b1120!important}html[data-theme="dark"] .frame-panel,html[data-theme="dark"] .frame-card,html[data-theme="dark"] .side-stat{background:#111827!important;border-color:#263244!important;color:#e5e7eb!important}html[data-theme="dark"] .frame-stage,html[data-theme="dark"] .frame-art{background:#0f172a!important;border-color:#334155!important}html[data-theme="dark"] .frame-panel-head p,html[data-theme="dark"] .library-hint,html[data-theme="dark"] .frame-desc{color:#94a3b8!important}html[data-theme="dark"] .frame-name{color:#e5e7eb!important}html[data-theme="dark"] .frame-tab{background:#111827!important;border-color:#263244!important;color:#cbd5e1!important}html[data-theme="dark"] .frame-tab.active{background:#2563eb!important;color:#fff!important;border-color:#2563eb!important}html[data-theme="dark"] .quality-tab{background:#111827!important;border-color:#263244!important;color:#94a3b8!important}html[data-theme="dark"] .quality-tab.active{color:#fff!important}html[data-theme="dark"] .frame-action{background:#2563eb!important}html[data-theme="dark"] .frame-action.equipped{background:#16a34a!important}html[data-theme="dark"] .frame-unequip{background:#1a0a0a!important;border-color:#5c2020!important;color:#fca5a5!important}html[data-theme="dark"] .frame-page-btn{background:#111827!important;border-color:#263244!important;color:#cbd5e1!important}html[data-theme="dark"] .frame-page-btn.active{background:#2563eb!important;color:#fff!important;border-color:#2563eb!important}
body,html{overflow-x:hidden!important}
body .topbar{position:fixed!important;top:0;left:0;right:0;z-index:10!important}

@media(max-width:900px){.frame-board{grid-template-columns:1fr!important}.frame-side-stats{grid-template-columns:1fr 1fr!important}.frame-unequip{grid-column:1/-1!important}.frame-grid{grid-template-columns:repeat(auto-fill,minmax(138px,1fr))!important}}@media(max-width:640px){.frame-center{padding:0 10px 160px!important}.frame-panel{border-radius:22px!important}.frame-panel-head{align-items:flex-start!important}.frame-stage{min-height:168px!important}.frame-stage .avatar-verify-wrap{--avatar-size:96px!important}.frame-side-stats{grid-template-columns:1fr 1fr!important}.side-stat strong{font-size:22px!important}.frame-grid{grid-template-columns:repeat(auto-fill,minmax(128px,1fr))!important;gap:8px!important}.frame-card{min-height:184px!important;border-radius:18px!important;padding:10px!important;grid-template-rows:92px auto!important}.frame-art img{width:78px!important;height:78px!important}.library-top{align-items:flex-start!important}.quality-tabs{padding:8px!important;border-radius:16px!important}}
@media(max-width:768px){
  .decoration-page .theme-toggle-fab{display:none!important}
  .decoration-page .chat-dock-toggle{display:none!important}
  .decoration-page .medal-hero{position:relative!important;z-index:auto!important;overflow:visible!important}
  .decoration-page .topbar{z-index:900!important}
  .decoration-page .dropdown-menu{z-index:950!important}
}
</style>
<div class="decoration-page"><div class="deco-tabs">
  <button type="button" data-tab="medals" class="<?=$tab==='medals'?'active':''?>" onclick="switchDecoTab('medals')">勋章</button>
  <button type="button" data-tab="frames" class="<?=$tab==='frames'?'active':''?>" onclick="switchDecoTab('frames')">头像框</button>
  <button type="button" data-tab="bubbles" class="<?=$tab==='bubbles'?'active':''?>" onclick="switchDecoTab('bubbles')">气泡</button>
  <button type="button" data-tab="nameplates" class="<?=$tab==='nameplates'?'active':''?>" onclick="switchDecoTab('nameplates')">名字特效</button>
</div>
<div id="decoTabMedals" class="deco-tab-pane <?=$tab==='medals'?'active':''?>">
<?php require __DIR__.'/../medals/_content.php'; ?>
</div>
<div id="decoTabFrames" class="deco-tab-pane <?=$tab==='frames'?'active':''?>">
<main class="frame-center">
  <div class="frame-shell" id="avatarFrameApp">
    <?php if (!empty($message)): ?><div class="frame-toast"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="frame-toast" style="background:#991b1b"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <section class="frame-board">
      <div class="frame-panel frame-preview-panel">
        <div class="frame-panel-head"><div><h2>我的头像框</h2><p>选择适合当前身份的头像框，打造更有辨识度的个人形象。</p></div><div class="frame-count"><?= $equippedId > 0 ? '已装备' : '未装备' ?></div></div>
        <div class="frame-stage">
          <?php if ($equippedId > 0): ?>
            <div><div><?= user_avatar_html($user, 'me-avatar', 112) ?></div><div class="frame-equipped-name"><?= htmlspecialchars((string)($equipped['name'] ?? '当前头像框')) ?></div></div>
          <?php else: ?>
            <div class="frame-stage-empty"><span></span>暂无装备头像框</div>
          <?php endif; ?>
        </div>
      </div>
      <aside class="frame-panel frame-side-stats">
        <div class="side-stat"><strong><?= (int)$frameOwnedCount ?></strong><span>已获得</span></div>
        <div class="side-stat"><strong><?= (int)$frameTotalCount ?></strong><span>全部头像框</span></div>
        <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="_action" value="equip_frame"><input type="hidden" name="frame_id" value="0"><button class="frame-unequip" type="submit">卸下头像框</button></form>
      </aside>
    </section>
    <section class="frame-panel frame-library">
      <div class="library-top"><div class="frame-tabs"><button class="frame-tab active" type="button" data-tab="all">全部头像框</button><button class="frame-tab" type="button" data-tab="mine">我的头像框</button></div><div class="library-hint" id="frameHint">已拥有的头像框可直接装备，未获得的头像框保持灰显</div></div>
      <div class="quality-tabs" id="frameQualityTabs" aria-label="头像框品质筛选"></div>
      <div class="frame-grid" id="frameGrid">
        <?php
          $npFrameBalances = [];
          foreach (($walletBalances ?? []) as $wb) { $c = strtoupper((string)($wb['currency_code'] ?? $wb['code'] ?? '')); if ($c !== '') $npFrameBalances[$c] = (float)($wb['balance'] ?? 0); }
          $frameCurNames = [];
          try { foreach ((new \App\Models\WalletModel())->currencies() as $fc) { $frameCurNames[strtoupper((string)($fc['code'] ?? ''))] = (string)($fc['name'] ?? $fc['code'] ?? ''); } } catch (\Throwable $e) {}
          $frameObtainLabels = ['free'=>'免费领取','shop'=>'商城购买','task'=>'任务解锁','level'=>'等级解锁','grant'=>'管理员授予'];
        ?>
        <?php foreach ($frames as $f): ?>
          <?php
            $owned = !empty($f['owned']); $isEquipped = !empty($f['is_equipped']);
            $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($f['quality_color'] ?? '')) ? (string)$f['quality_color'] : '#64748b';
            $fObtain = (string)($f['obtain_method'] ?? 'grant');
            $fCur = strtoupper((string)($f['price_currency'] ?? ''));
            $fAmt = (float)($f['price_amount'] ?? 0);
            $fAmtLabel = rtrim(rtrim(number_format($fAmt, 6, '.', ''), '0'), '.');
            $fCurLabel = $frameCurNames[$fCur] ?? $fCur;
            $fCanAfford = $fCur !== '' && ($npFrameBalances[$fCur] ?? 0) >= $fAmt;
          ?>
          <article class="frame-card <?= $owned ? 'owned' : 'locked' ?> <?= $isEquipped ? 'equipped' : '' ?>" data-owned="<?= $owned ? '1' : '0' ?>" data-quality="<?= htmlspecialchars((string)($f['quality'] ?? 'standard')) ?>" style="--quality-color:<?= htmlspecialchars($color) ?>">
            <span class="frame-status"></span>
            <div class="frame-art"><?php if (!empty($f['image'])): ?><img src="<?= htmlspecialchars((string)$f['image']) ?>" alt="<?= htmlspecialchars((string)$f['name']) ?>" loading="lazy"><?php endif; ?></div>
            <div>
              <div class="frame-name"><?= htmlspecialchars((string)$f['name']) ?></div>
              <div class="frame-desc"><?= htmlspecialchars((string)($f['description'] ?? '')) ?></div>
              <div class="frame-meta"><span class="frame-tag quality"><?= htmlspecialchars((string)($f['quality_name'] ?? '标准')) ?></span><span class="frame-tag"><?= $owned ? '已拥有' : htmlspecialchars($frameObtainLabels[$fObtain] ?? '未获得') ?></span><?php if (!$owned && $fObtain === 'shop' && $fCur !== ''): ?><span class="frame-tag"><?= htmlspecialchars($fAmtLabel . ' ' . $fCurLabel) ?></span><?php endif; ?></div>
              <div class="frame-actions">
                <?php if ($owned): ?>
                  <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="equip_frame"><input type="hidden" name="frame_id" value="<?= (int)$f['id'] ?>"><button class="frame-action <?= $isEquipped ? 'equipped' : '' ?>" type="submit"><?= $isEquipped ? '当前装备' : '装备' ?></button></form>
                <?php elseif ($fObtain === 'free'): ?>
                  <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="claim_frame"><input type="hidden" name="frame_id" value="<?= (int)$f['id'] ?>"><button class="frame-action" type="submit" style="background:linear-gradient(135deg,#10b981,#3cc9a4)">免费领取</button></form>
                <?php elseif ($fObtain === 'shop'): ?>
                  <form method="post" onsubmit="return confirm('确认花费 <?= htmlspecialchars($fAmtLabel . ' ' . $fCurLabel, ENT_QUOTES) ?> 购买该头像框？')"><?= csrf_field() ?><input type="hidden" name="_action" value="buy_frame"><input type="hidden" name="frame_id" value="<?= (int)$f['id'] ?>"><button class="frame-action" type="submit" style="background:linear-gradient(135deg,#f59e0b,#ef4444)" <?= $fCanAfford ? '' : 'disabled' ?>><?= $fCanAfford ? ('购买 ' . htmlspecialchars($fAmtLabel . ' ' . $fCurLabel)) : '余额不足' ?></button></form>
                <?php else: ?>
                  <button class="frame-action" type="button" disabled><?= $fObtain === 'grant' ? '管理员授予' : '未解锁' ?></button>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
        <div class="frame-pager" id="framePager" aria-label="头像框分页"></div>
    </section>
  </div>
</main>
<script>
(function(){
  var cards=[].slice.call(document.querySelectorAll('.frame-card'));
  var grid=document.getElementById('frameGrid');
  var pager=document.getElementById('framePager');
  var tabs=[].slice.call(document.querySelectorAll('.frame-tab'));
  var qt=document.getElementById('frameQualityTabs');
  var tab='all', quality='all', page=1, pageSize=12;
  function esc(s){return String(s||'').replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
  function qualities(){var pool=cards.filter(function(c){return c.dataset.owned!=='1';});var map={all:{name:'全部品质',color:'#2563eb',count:pool.length}};pool.forEach(function(c){var q=c.dataset.quality||'standard';var tag=c.querySelector('.frame-tag.quality');if(!map[q])map[q]={name:tag?tag.textContent.trim():q,color:getComputedStyle(c).getPropertyValue('--quality-color').trim()||'#64748b',count:0};map[q].count++;});return Object.keys(map).map(function(k){return {code:k,name:map[k].name,color:map[k].color,count:map[k].count};});}
  function renderQuality(){if(!qt)return;qt.style.setProperty('display',tab==='all'?'flex':'none','important');qt.innerHTML=qualities().map(function(q){return '<button class="quality-tab '+(quality===q.code?'active':'')+'" type="button" data-quality="'+esc(q.code)+'" style="--quality-color:'+esc(q.color)+'">'+esc(q.name)+' <span>'+q.count+'</span></button>';}).join('');}
  function filtered(){return cards.filter(function(c){if(tab==='mine')return c.dataset.owned==='1';if(c.dataset.owned==='1')return false;if(quality!=='all'&&c.dataset.quality!==quality)return false;return true;});}
  function renderPager(total){if(!pager)return;var pages=Math.max(1,Math.ceil(total/pageSize));if(page>pages)page=pages;pager.style.setProperty('display',pages>1?'flex':'none','important');var h='<button class="frame-page-btn" type="button" data-page="prev" '+(page<=1?'disabled':'')+'>上一页</button>';for(var i=1;i<=pages;i++){h+='<button class="frame-page-btn '+(i===page?'active':'')+'" type="button" data-page="'+i+'">'+i+'</button>';}h+='<button class="frame-page-btn" type="button" data-page="next" '+(page>=pages?'disabled':'')+'>下一页</button>';pager.innerHTML=h;}
  function filter(){var list=filtered();renderPager(list.length);var start=(page-1)*pageSize;var end=start+pageSize;var shown=0;cards.forEach(function(c){c.style.setProperty('display','none','important');});list.forEach(function(c,i){if(i>=start&&i<end){c.style.setProperty('display','grid','important');shown++;}});var empty=grid.querySelector('.frame-empty');if(empty)empty.remove();if(!shown){var div=document.createElement('div');div.className='frame-empty';div.style.setProperty('display','block','important');div.textContent=tab==='mine'?'还没有获得任何头像框，去“全部头像框”看看吧':'太棒了，全部头像框都已获得，去“我的头像框”查看吧';grid.appendChild(div);}renderQuality();var hint=document.getElementById('frameHint');if(hint)hint.style.setProperty('display',tab==='all'?'block':'none','important');}
  tabs.forEach(function(b){b.addEventListener('click',function(){tabs.forEach(function(x){x.classList.remove('active');});b.classList.add('active');tab=b.dataset.tab||'all';quality='all';page=1;filter();});});
  document.addEventListener('click',function(e){var b=e.target.closest('#frameQualityTabs .quality-tab');if(!b)return;quality=b.dataset.quality||'all';page=1;filter();});
  document.addEventListener('click',function(e){var pg=e.target.closest('.frame-page-btn');if(pg&&!pg.disabled){var list=filtered(),pages=Math.max(1,Math.ceil(list.length/pageSize));var val=pg.dataset.page;if(val==='prev')page=Math.max(1,page-1);else if(val==='next')page=Math.min(pages,page+1);else page=parseInt(val,10)||1;filter();}});
  filter();
})();
</script>
</div></div>
<div id="decoTabBubbles" class="deco-tab-pane <?=$tab==='bubbles'?'active':''?>">
<?php require __DIR__.'/../bubbles/_content.php'; ?>
</div>
<div id="decoTabNameplates" class="deco-tab-pane <?=$tab==='nameplates'?'active':''?>">
<?php require __DIR__.'/../nameplates/_content.php'; ?>
</div>
<script>
function switchDecoTab(tab){
  document.querySelectorAll('.deco-tabs button').forEach(function(b){b.classList.remove('active')});
  document.querySelectorAll('.deco-tab-pane').forEach(function(p){p.classList.remove('active')});
  var btns=document.querySelectorAll('.deco-tabs button');
  var panes={medals:'decoTabMedals',frames:'decoTabFrames',bubbles:'decoTabBubbles',nameplates:'decoTabNameplates'};
  if(panes[tab]){document.getElementById(panes[tab]).classList.add('active');}
  btns.forEach(function(b){if(b.getAttribute('data-tab')===tab)b.classList.add('active');});
  var url=new URL(window.location.href);url.searchParams.set('tab',tab);history.replaceState(null,'',url);
}
</script>
<?php require dirname(__DIR__, 2) . '/layouts/theme-toggle.php'; ?><?php require dirname(__DIR__) . '/layouts/bottom-nav.php'; ?></body></html>
