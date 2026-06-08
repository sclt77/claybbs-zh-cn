<style>
.bubble-page{min-height:100vh!important;padding:0 16px 112px!important;color:var(--text-main,#0f172a)!important}.bubble-page a{text-decoration:none!important}
.bubble-shell{max-width:1180px!important;margin:0 auto!important;display:grid!important;gap:16px!important}
.bubble-board{display:grid!important;grid-template-columns:minmax(0,1fr) 270px!important;gap:16px!important;align-items:stretch!important}
.bubble-panel{background:rgba(255,255,255,.88)!important;border:1px solid rgba(226,232,240,.86)!important;border-radius:26px!important;padding:20px!important;box-shadow:0 16px 44px rgba(15,23,42,.06)!important;backdrop-filter:blur(12px)!important}
.bubble-panel-head{display:flex!important;align-items:flex-end!important;justify-content:space-between!important;gap:14px!important;margin-bottom:16px!important}
.bubble-panel-head h2{margin:0!important;font-size:22px!important;letter-spacing:-.04em!important}
.bubble-panel-head p{margin:6px 0 0!important;color:#64748b!important;font-size:13px!important;line-height:1.65!important}
.bubble-count{font-size:12px!important;font-weight:950!important;color:#2563eb!important;background:#eff6ff!important;border:1px solid #bfdbfe!important;border-radius:999px!important;padding:7px 10px!important;white-space:nowrap!important}
.bubble-preview{min-height:198px!important;border:1px dashed rgba(148,163,184,.7)!important;border-radius:26px!important;background:radial-gradient(circle at 14% 0,rgba(14,165,233,.10),transparent 30%),#f8fafc!important;display:flex!important;align-items:center!important;justify-content:center!important;padding:18px!important;position:relative!important;overflow:hidden!important}
.bubble-preview-empty{color:#94a3b8!important;font-weight:950!important;font-size:14px!important;text-align:center!important}.bubble-preview-name{margin-top:10px!important;font-size:13px!important;font-weight:950!important;color:#64748b!important;text-align:center!important;position:relative;z-index:2!important}.bubble-chat-stage{width:100%!important;max-width:520px!important;display:flex!important;flex-direction:column!important;gap:10px!important}.bubble-chat-row{display:flex!important;align-items:flex-end!important;gap:8px!important;width:100%!important}.bubble-chat-row.mine{justify-content:flex-end!important}.bubble-chat-avatar{width:35px!important;height:35px!important;border-radius:5px!important;background:linear-gradient(135deg,#0284c7,#6366f1)!important;color:#fff!important;display:block!important;flex:0 0 35px!important;line-height:35px!important;text-align:center!important}.bubble-chat-stage .chat-msg{max-width:82%!important;padding:9px 11px!important;border-radius:14px!important;font-size:13px!important;line-height:1.5!important;white-space:pre-wrap!important;word-break:break-word!important;position:relative!important;overflow:visible!important;box-shadow:0 8px 20px rgba(15,23,42,.08)!important}.bubble-chat-stage .chat-msg.mine{align-self:flex-end!important;background:linear-gradient(135deg,#0ea5e9,#0284c7);color:#fff;border-bottom-right-radius:4px!important;box-shadow:0 10px 24px rgba(2,132,199,.20)}.bubble-chat-stage .chat-msg.their{align-self:flex-start!important;background:#fff;color:#0f172a;border:1px solid #e2e8f0;border-bottom-left-radius:4px!important;box-shadow:0 8px 20px rgba(15,23,42,.06)}.bubble-chat-stage .chat-msg.has-bubble{position:relative!important;overflow:visible!important}.bubble-chat-stage .chat-msg.has-bubble>*{position:relative!important;z-index:1!important}.bubble-chat-stage .chat-msg.has-bubble>.bubble-effect-container{position:absolute!important;inset:0!important;z-index:0!important;pointer-events:none!important;overflow:hidden!important;border-radius:inherit!important}.bubble-chat-stage .chat-msg .chat-msg-text{display:block!important;position:relative!important;z-index:1!important}.bubble-card-art .chat-msg{max-width:92%!important;font-size:12px!important}
.bubble-side-stats{padding:20px!important;display:grid!important;gap:12px!important}
.side-stat{border-radius:20px!important;padding:14px!important;background:#fff!important;border:1px solid #e2e8f0!important}
.side-stat strong{display:block!important;font-size:26px!important;letter-spacing:-.04em!important}
.side-stat span{display:block!important;margin-top:5px!important;color:#64748b!important;font-size:12px!important;font-weight:900!important}
.bubble-unequip{min-height:48px!important;border:1px dashed #fecaca!important;background:#fff7f7!important;color:#b91c1c!important;border-radius:20px!important;font-size:12px!important;font-weight:950!important;display:flex!important;align-items:center!important;justify-content:center!important;padding:0 14px!important;cursor:pointer!important}
.bubble-library{padding:18px!important}
.library-top{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:12px!important;flex-wrap:wrap!important;margin-bottom:14px!important}
.bubble-tabs{display:flex!important;gap:8px!important;flex-wrap:wrap!important}
.bubble-tab{border:1px solid #e2e8f0!important;background:#fff!important;color:#475569!important;border-radius:999px!important;padding:9px 14px!important;font-weight:950!important;cursor:pointer!important}
.bubble-tab.active{background:#0f172a!important;color:#fff!important;border-color:#0f172a!important}
.library-hint{font-size:12px!important;color:#64748b!important;font-weight:800!important}
.quality-tabs{display:flex!important;gap:7px!important;flex-wrap:wrap!important;margin:0 0 14px!important;padding:10px!important;border-radius:20px!important;background:#f8fafc!important;border:1px solid #e2e8f0!important}
.quality-tab{border:1px solid #e2e8f0!important;background:#fff!important;color:#64748b!important;border-radius:999px!important;padding:7px 12px!important;font-size:12px!important;font-weight:950!important;cursor:pointer!important}
.quality-tab.active{background:color-mix(in srgb,var(--quality-color,#2563eb) 12%,#fff)!important;border-color:color-mix(in srgb,var(--quality-color,#2563eb) 34%,#fff)!important;color:var(--quality-color,#1d4ed8)!important}
.bubble-grid{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:12px!important}
.bubble-card{min-height:220px!important;border:2px solid #e2e8f0!important;border-radius:22px!important;background:#fff!important;position:relative!important;transition:.16s ease!important;overflow:hidden!important;padding:0!important;display:flex!important;flex-direction:column!important;cursor:pointer!important}
.bubble-card.owned{border-color:rgba(37,99,235,.22)!important;box-shadow:0 12px 28px rgba(15,23,42,.04)!important}
.bubble-card.locked{filter:grayscale(1)!important;opacity:.48!important}
.bubble-card:hover{transform:translateY(-2px)!important;box-shadow:0 16px 32px rgba(15,23,42,.08)!important}
.bubble-card.equipped{border:2px solid var(--quality-color,#2563eb)!important;box-shadow:0 0 16px color-mix(in srgb,var(--quality-color,#2563eb) 32%,transparent)!important}
.bubble-card-art{width:100%!important;height:126px!important;display:flex!important;align-items:center!important;justify-content:center!important;position:relative!important;overflow:hidden!important;background:radial-gradient(circle at 16% 0,rgba(14,165,233,.10),transparent 32%),#f8fafc!important;padding:12px!important}
.bubble-card-body{padding:12px!important;flex:1!important;display:flex!important;flex-direction:column!important;gap:6px!important}
.bubble-card-name{font-size:15px!important;font-weight:950!important;color:#0f172a!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}
.bubble-card-desc{color:#64748b!important;font-size:12px!important;line-height:1.55!important;display:-webkit-box!important;-webkit-line-clamp:2!important;-webkit-box-orient:vertical!important;overflow:hidden!important}
.bubble-card-meta{display:flex!important;gap:6px!important;flex-wrap:wrap!important;margin-top:auto!important}
.bubble-act{margin-top:8px!important;width:100%!important;height:34px!important;border:0!important;border-radius:999px!important;background:#0f172a!important;color:#fff!important;font-size:12px!important;font-weight:900!important;cursor:pointer!important}
.bubble-act.free{background:linear-gradient(135deg,#10b981,#3cc9a4)!important}
.bubble-act.buy{background:linear-gradient(135deg,#f59e0b,#ef4444)!important}
.bubble-act[disabled]{background:#e2e8f0!important;color:#94a3b8!important;cursor:not-allowed!important}
.bubble-tag{font-size:11px!important;font-weight:950!important;border-radius:999px!important;padding:4px 8px!important;background:#f8fafc!important;border:1px solid #e2e8f0!important;color:#64748b!important}
.bubble-tag.quality{color:var(--quality-color,#2563eb)!important;background:color-mix(in srgb,var(--quality-color,#2563eb) 9%,#fff)!important;border-color:color-mix(in srgb,var(--quality-color,#2563eb) 28%,#fff)!important}
.bubble-card-status{position:absolute!important;right:9px!important;top:9px!important;width:10px!important;height:10px!important;border-radius:999px!important;background:#cbd5e1!important}
.bubble-card.owned .bubble-card-status{background:#2563eb!important}
.bubble-card.equipped .bubble-card-status{background:#16a34a!important}
.bubble-empty{grid-column:1/-1!important;text-align:center!important;color:#94a3b8!important;font-weight:900!important;padding:28px!important}
.bubble-pager{display:flex!important;gap:6px!important;flex-wrap:wrap!important;justify-content:center!important;margin-top:14px!important}
.bubble-page-btn{border:1px solid #e2e8f0!important;background:#fff!important;color:#475569!important;border-radius:999px!important;padding:7px 12px!important;font-size:12px!important;font-weight:900!important;cursor:pointer!important}
.bubble-page-btn.active{background:#0f172a!important;color:#fff!important;border-color:#0f172a!important}
.bubble-page-btn[disabled]{opacity:.4!important;cursor:not-allowed!important}
.bubble-toast{position:fixed!important;left:50%!important;bottom:86px!important;transform:translateX(-50%) translateY(10px)!important;z-index:1300!important;min-width:160px!important;max-width:min(460px,calc(100vw - 32px))!important;border-radius:999px!important;background:rgba(15,23,42,.92)!important;color:#fff!important;font-size:13px!important;font-weight:900!important;text-align:center!important;padding:10px 16px!important;box-shadow:0 18px 45px rgba(15,23,42,.22)!important;opacity:0!important;pointer-events:none!important;transition:opacity .18s ease!important}
.bubble-toast.show{opacity:1!important;pointer-events:auto!important}
html[data-theme="dark"] .bubble-panel,html[data-theme="dark"] .bubble-card,html[data-theme="dark"] .side-stat{background:#111827!important;border-color:#263244!important;color:#e5e7eb!important}
html[data-theme="dark"] .bubble-card-name{color:#e5e7eb!important}
html[data-theme="dark"] .bubble-preview,html[data-theme="dark"] .bubble-card-art{background:radial-gradient(circle at 14% 0,rgba(56,189,248,.14),transparent 30%),#0f172a!important}
html[data-theme="dark"] .bubble-chat-stage .chat-msg.their:not(.has-bubble){background:#111827!important;color:#e5e7eb!important;border-color:#263244!important}
html[data-theme="dark"] .bubble-panel-head p,html[data-theme="dark"] .library-hint,html[data-theme="dark"] .bubble-card-desc{color:#94a3b8!important}
html[data-theme="dark"] .bubble-tab{background:#111827!important;border-color:#263244!important;color:#cbd5e1!important}
html[data-theme="dark"] .bubble-tab.active{background:#2563eb!important;color:#fff!important;border-color:#2563eb!important}
html[data-theme="dark"] .quality-tab{background:#111827!important;border-color:#263244!important;color:#94a3b8!important}
html[data-theme="dark"] .quality-tab.active{color:#fff!important}
html[data-theme="dark"] .bubble-page-btn{background:#111827!important;border-color:#263244!important;color:#cbd5e1!important}
html[data-theme="dark"] .bubble-page-btn.active{background:#2563eb!important;color:#fff!important;border-color:#2563eb!important}
html[data-theme="dark"] .bubble-unequip{background:#1a0a0a!important;border-color:#5c2020!important;color:#fca5a5!important}
@media(max-width:980px){.bubble-board{grid-template-columns:1fr!important}.bubble-grid{grid-template-columns:repeat(3,minmax(0,1fr))!important}}
@media(max-width:640px){.bubble-page{padding:0 12px 160px!important}.bubble-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:8px!important}.bubble-card{min-height:180px!important;border-radius:16px!important}.bubble-card-art{height:90px!important}}

/* v6 preview layout fix: card previews need a stable centered stage, not full chat-row sizing */
.bubble-preview{overflow:visible!important}
.bubble-card{overflow:hidden!important}
.bubble-card-art{height:148px!important;overflow:visible!important;padding:28px 14px 18px!important;z-index:1!important}
.bubble-card-body{position:relative!important;z-index:8!important;background:#fff!important;border-top:1px solid rgba(226,232,240,.76)!important}
.bubble-card-art .bubble-chat-stage{height:100%!important;max-width:100%!important;display:flex!important;align-items:center!important;justify-content:center!important;overflow:visible!important;transform:none!important}
.bubble-card-art .bubble-chat-row{display:flex!important;justify-content:center!important;align-items:center!important;width:100%!important;overflow:visible!important}
.bubble-card-art .bubble-chat-row.mine{justify-content:center!important}
.bubble-card-art .chat-msg{max-width:170px!important;margin:0 auto!important;align-self:center!important;transform:scale(.78)!important;transform-origin:center center!important;overflow:visible!important}
.bubble-card-art .chat-msg .chat-msg-text{white-space:nowrap!important;font-size:12px!important}
.bubble-card-art .chat-msg.cute-bubble{animation:none!important;filter:drop-shadow(0 5px 9px rgba(148,80,120,.16))!important}
.bubble-card-art .chat-msg.cute-nova .cute-deco.face,.bubble-card-art .chat-msg.cute-liquid .cute-deco.face{top:-10px!important}
@media(max-width:640px){.bubble-card-art{height:122px!important;padding:24px 8px 14px!important}.bubble-card-art .chat-msg{transform:scale(.68)!important;max-width:145px!important}.bubble-card-body{padding:10px!important}.bubble-card{min-height:205px!important}}

/* v7 card preview hard fix: render as a compact standalone bubble, not a chat layout */
.bubble-card-art{height:150px!important;overflow:hidden!important;padding:18px 10px!important;display:flex!important;align-items:center!important;justify-content:center!important;background:linear-gradient(135deg,#f8fbff,#fff7fb)!important}
.bubble-card-preview-msg{position:relative!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;width:auto!important;min-width:118px!important;max-width:148px!important;min-height:38px!important;margin:0!important;padding:11px 15px!important;white-space:nowrap!important;overflow:visible!important;transform:none!important;align-self:center!important;line-height:1.35!important;font-size:12px!important}
.bubble-card-preview-msg .chat-msg-text{white-space:nowrap!important;position:relative!important;z-index:20!important;color:inherit!important;font-weight:800!important}
.bubble-card-preview-msg.cute-bubble{animation:none!important;filter:drop-shadow(0 8px 12px rgba(148,80,120,.16))!important}
.bubble-card-preview-msg .bubble-effect-container{display:none!important}
.bubble-card-preview-msg .cute-deco.face{top:-12px!important}
.bubble-card-preview-msg .cute-deco.face img{width:18px!important;height:18px!important}
.bubble-card-preview-msg .cute-deco.ear-l{top:-15px!important}.bubble-card-preview-msg .cute-deco.ear-r{top:-15px!important}
.bubble-card-preview-msg .cute-deco.ear-l img,.bubble-card-preview-msg .cute-deco.ear-r img{width:9px!important;height:16px!important}
.bubble-card-preview-msg .cute-deco.cloud img{width:20px!important;height:14px!important}
.bubble-card-preview-msg .cute-deco.star img,.bubble-card-preview-msg .cute-deco.heart img{width:8px!important;height:8px!important}
@media(max-width:640px){.bubble-card-art{height:132px!important;padding:18px 8px!important}.bubble-card-preview-msg{min-width:104px!important;max-width:128px!important;font-size:11px!important;padding:10px 12px!important}.bubble-card{min-height:222px!important}}

/* v8 top equipped preview hard fix */
.bubble-preview{min-height:260px!important;overflow:hidden!important;padding:30px 18px!important;display:flex!important;align-items:center!important;justify-content:center!important}
.bubble-equipped-preview-stage{width:100%!important;min-height:188px!important;display:flex!important;flex-direction:column!important;align-items:center!important;justify-content:center!important;gap:18px!important;overflow:visible!important}
.bubble-equipped-preview-msg{position:relative!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;min-width:220px!important;max-width:min(430px,88%)!important;min-height:54px!important;margin:0 auto!important;padding:16px 24px!important;white-space:normal!important;overflow:visible!important;align-self:center!important;text-align:center!important;font-size:15px!important;line-height:1.45!important}
.bubble-equipped-preview-msg .chat-msg-text{position:relative!important;z-index:20!important;font-weight:850!important;color:inherit!important}
.bubble-equipped-preview-msg.cute-bubble{animation:none!important;filter:drop-shadow(0 12px 18px rgba(148,80,120,.16))!important}
.bubble-equipped-preview-msg .bubble-effect-container{display:none!important}
.bubble-equipped-preview-msg .cute-deco.face{top:-10px!important}
.bubble-equipped-preview-msg .cute-deco.face img{width:24px!important;height:24px!important}
.bubble-equipped-preview-msg .cute-deco.ear-l{top:-20px!important}.bubble-equipped-preview-msg .cute-deco.ear-r{top:-20px!important}
.bubble-equipped-preview-msg .cute-deco.ear-l img,.bubble-equipped-preview-msg .cute-deco.ear-r img{width:12px!important;height:22px!important}
.bubble-equipped-preview-msg .cute-deco.cloud img{width:28px!important;height:19px!important}
.bubble-equipped-preview-msg .cute-deco.star img,.bubble-equipped-preview-msg .cute-deco.heart img{width:10px!important;height:10px!important}
@media(max-width:640px){.bubble-preview{min-height:245px!important;padding:28px 10px!important}.bubble-equipped-preview-stage{min-height:175px!important}.bubble-equipped-preview-msg{min-width:190px!important;max-width:86%!important;font-size:14px!important;padding:15px 20px!important}.bubble-preview-name{margin-top:0!important}}
</style>
<main class="bubble-page">
  <div class="bubble-shell" id="bubbleApp">
    <?php if (!empty($message)): ?><div class="bubble-toast show" id="bubbleToast"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="bubble-toast show" id="bubbleToastErr" style="background:rgba(153,27,27,.94)"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <section class="bubble-board">
      <div class="bubble-panel bubble-preview-panel">
        <div class="bubble-panel-head"><div><h2>我的气泡</h2><p>装备一个聊天气泡特效，让你的私聊和群聊消息与众不同。</p></div><div class="bubble-count"><?= $equippedBubble ? '已装备' : '未装备' ?></div></div>
        <div class="bubble-preview" id="bubblePreviewArea">
          <?php if ($equippedBubble): ?>
            <div class="bubble-equipped-preview-stage">
              <div class="chat-msg mine has-bubble bubble-equipped-preview-msg" data-effect="<?= htmlspecialchars((string)($equippedBubble['effect_type'] ?? '')) ?>" data-effect-params="<?= htmlspecialchars((string)($equippedBubble['effect_params'] ?? '{}'), ENT_QUOTES) ?>"><span class="chat-msg-text">这就是当前装备的聊天气泡效果</span></div>
              <div class="bubble-preview-name"><?= htmlspecialchars((string)($equippedBubble['name'] ?? '')) ?></div>
            </div>
          <?php else: ?>
            <div class="bubble-preview-empty">暂无装备气泡</div>
          <?php endif; ?>
        </div>
      </div>
      <aside class="bubble-panel bubble-side-stats">
        <div class="side-stat"><strong><?= (int)$bubbleOwnedCount ?></strong><span>已获得</span></div>
        <div class="side-stat"><strong><?= count($bubbles ?? []) ?></strong><span>全部气泡</span></div>
        <div class="bubble-unequip" id="bubbleUnequipHint" style="<?= $equippedBubble ? '' : 'display:none!important' ?>">点击已装备的气泡可卸下</div>
      </aside>
    </section>
    <section class="bubble-panel bubble-library">
      <div class="library-top"><div class="bubble-tabs"><button class="bubble-tab active" type="button" data-tab="all">全部气泡</button><button class="bubble-tab" type="button" data-tab="mine">我的气泡</button></div><div class="library-hint">已拥有的气泡可直接装备</div></div>
      <div class="quality-tabs" id="bubbleQualityTabs" aria-label="气泡品质筛选"></div>
      <div class="bubble-grid" id="bubbleGrid"></div>
      <div class="bubble-pager" id="bubblePager" aria-label="气泡分页"></div>
    </section>
  </div>
</main>
<div class="bubble-toast" id="bubbleToastMsg"></div>
<script src="https://cdn.jsdelivr.net/npm/animejs@3.2.2/lib/anime.min.js"></script>
<script src="/assets/js/bubble-effects.js?v=20260530-v10"></script>
<script>
(function(){
<?php
  $bubCurNames = [];
  try { foreach ((new \App\Models\WalletModel())->currencies() as $bc) { $bubCurNames[strtoupper((string)($bc['code'] ?? ''))] = (string)($bc['name'] ?? $bc['code'] ?? ''); } } catch (\Throwable $e) {}
  $bubBalances = [];
  $bubUid = (int)($_SESSION['auth_user']['id'] ?? 0);
  try { foreach ((new \App\Models\WalletModel())->balances($bubUid) as $bb) { $bcc = strtoupper((string)($bb['currency_code'] ?? $bb['code'] ?? '')); if ($bcc !== '') $bubBalances[$bcc] = (float)($bb['balance'] ?? 0); } } catch (\Throwable $e) {}
?>
  var bubbles=<?= json_encode(array_map(function($b) use ($bubCurNames, $bubBalances){
    $cur = strtoupper((string)($b['price_currency'] ?? ''));
    $amt = (float)($b['price_amount'] ?? 0);
    $amtLabel = rtrim(rtrim(number_format($amt, 6, '.', ''), '0'), '.');
    return ['id'=>(int)$b['id'],'name'=>(string)($b['name']??''),'description'=>(string)($b['description']??''),'effect_type'=>(string)($b['effect_type']??''),'effect_params'=>(string)($b['effect_params']??'{}'),'quality'=>(string)($b['quality']??'standard'),'quality_name'=>(string)($b['quality_name']??'标准'),'quality_color'=>preg_match('/^#[0-9a-fA-F]{6}$/',(string)($b['quality_color']??''))?(string)$b['quality_color']:'#64748b','owned'=>!empty($b['is_equipped'])||!empty($b['granted_at']),'equipped'=>!empty($b['is_equipped']),'obtain_method'=>(string)($b['obtain_method']??'grant'),'price_currency'=>$cur,'price_amount'=>$amtLabel,'price_label'=>$amtLabel.' '.($bubCurNames[$cur]??$cur),'can_afford'=>$cur!==''&&(($bubBalances[$cur]??0)>=$amt)];
  },$bubbles??[]),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  var grid=document.getElementById('bubbleGrid'),pager=document.getElementById('bubblePager'),qt=document.getElementById('bubbleQualityTabs');
  var toastEl=document.getElementById('bubbleToastMsg');
  var tab='all',quality='all',page=1,pageSize=12;
  var csrf=<?= json_encode($csrf,JSON_UNESCAPED_UNICODE) ?>;
  function esc(s){return String(s||'').replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
  function toastMsg(msg,bad){toastEl.textContent=msg;toastEl.style.background=bad?'rgba(153,27,27,.94)':'rgba(15,23,42,.92)';toastEl.classList.add('show');clearTimeout(toastEl._t);toastEl._t=setTimeout(function(){toastEl.classList.remove('show');},1800);}
  function qualities(){var pool=bubbles.filter(function(b){return !b.owned;});var map={all:{name:'全部品质',color:'#2563eb',count:pool.length}};pool.forEach(function(b){var q=b.quality||'standard';if(!map[q])map[q]={name:b.quality_name||q,color:b.quality_color||'#64748b',count:0};map[q].count++;});return Object.keys(map).map(function(k){return {code:k,name:map[k].name,color:map[k].color,count:map[k].count};});}
  function renderQuality(){if(!qt)return;qt.style.setProperty('display',tab==='all'?'flex':'none','important');qt.innerHTML=qualities().map(function(q){return '<button class="quality-tab '+(quality===q.code?'active':'')+'" type="button" data-quality="'+esc(q.code)+'" style="--quality-color:'+esc(q.color)+'">'+esc(q.name)+' <span>'+q.count+'</span></button>';}).join('');}
  function filtered(){return bubbles.filter(function(b){if(tab==='mine')return b.owned;if(b.owned)return false;if(quality!=='all'&&b.quality!==quality)return false;return true;});}
  function renderPager(total){if(!pager)return;var pages=Math.max(1,Math.ceil(total/pageSize));if(page>pages)page=pages;pager.style.setProperty('display',pages>1?'flex':'none','important');var h='<button class="bubble-page-btn" type="button" data-page="prev" '+(page<=1?'disabled':'')+'>上一页</button>';for(var i=1;i<=pages;i++){h+='<button class="bubble-page-btn '+(i===page?'active':'')+'" type="button" data-page="'+i+'">'+i+'</button>';}h+='<button class="bubble-page-btn" type="button" data-page="next" '+(page>=pages?'disabled':'')+'>下一页</button>';pager.innerHTML=h;}
  function activatePreviewEffects(scope){
    if(!window.BubbleEffects)return;
    (scope||document).querySelectorAll('.chat-msg.has-bubble[data-effect]').forEach(function(el){
      var et=el.dataset.effect;if(!et)return;
      try{
        delete el.dataset.fxActive;
        BubbleEffects.applyToChatMsg(el,et,JSON.parse(el.dataset.effectParams||'{}'));
        el.dataset.fxActive='1';
      }catch(e){}
    });
  }
  function renderTopPreview(){
    var box=document.getElementById('bubblePreviewArea');if(!box)return;
    var b=bubbles.find(function(x){return !!x.equipped;});
    if(!b){box.innerHTML='<div class="bubble-preview-empty">暂无装备气泡</div>';return;}
    box.innerHTML='<div class="bubble-equipped-preview-stage"><div class="chat-msg mine has-bubble bubble-equipped-preview-msg" data-effect="'+esc(b.effect_type)+'" data-effect-params="'+esc(b.effect_params)+'"><span class="chat-msg-text">这就是当前装备的聊天气泡效果</span></div><div class="bubble-preview-name">'+esc(b.name)+'</div></div>';
    activatePreviewEffects(box);
  }
  var obtainLabels={free:'免费领取',shop:'商城购买',task:'任务解锁',level:'等级解锁',grant:'管理员授予'};
  function cardAction(b){
    if(b.owned)return '<button class="bubble-act" type="button" data-bb-equip="'+b.id+'">'+(b.equipped?'已装备 · 点击卸下':'装备')+'</button>';
    if(b.obtain_method==='free')return '<button class="bubble-act free" type="button" data-bb-claim="'+b.id+'">免费领取</button>';
    if(b.obtain_method==='shop')return '<button class="bubble-act buy" type="button" data-bb-buy="'+b.id+'" '+(b.can_afford?'':'disabled')+'>'+(b.can_afford?('购买 '+esc(b.price_label)):'余额不足')+'</button>';
    return '<button class="bubble-act" type="button" disabled>'+(b.obtain_method==='grant'?'管理员授予':'未解锁')+'</button>';
  }
  function renderGrid(){var list=filtered();renderPager(list.length);var start=(page-1)*pageSize;list=list.slice(start,start+pageSize);grid.innerHTML=list.map(function(b){var cls='bubble-card '+(b.owned?'owned':'locked')+(b.equipped?' equipped':'');var metaRight=b.owned?'已拥有':(obtainLabels[b.obtain_method]||'未获得');var priceTag=(!b.owned&&b.obtain_method==='shop')?'<span class="bubble-tag">'+esc(b.price_label)+'</span>':'';return '<article class="'+cls+'" data-id="'+b.id+'" style="--quality-color:'+esc(b.quality_color)+'"><span class="bubble-card-status"></span><div class="bubble-card-art"><div class="chat-msg mine has-bubble bubble-card-preview-msg" data-effect="'+esc(b.effect_type)+'" data-effect-params="'+esc(b.effect_params)+'"><span class="chat-msg-text">聊天气泡预览</span></div></div><div class="bubble-card-body"><div class="bubble-card-name">'+esc(b.name)+'</div><div class="bubble-card-desc">'+esc(b.description)+'</div><div class="bubble-card-meta"><span class="bubble-tag quality">'+esc(b.quality_name)+'</span><span class="bubble-tag">'+metaRight+'</span>'+priceTag+'</div>'+cardAction(b)+'</div></article>';}).join('')||'<div class="bubble-empty">'+(tab==='mine'?'还没有获得任何气泡，去“全部气泡”看看吧':'太棒了，全部气泡都已获得，去“我的气泡”查看吧')+'</div>';
    activatePreviewEffects(grid);
  }
  function postAction(act,bid,confirmMsg){
    if(confirmMsg&&!confirm(confirmMsg))return;
    var fd=new FormData();fd.append('_csrf_token',csrf);fd.append('_action',act);fd.append('bubble_id',bid);
    fetch('/index.php?path=decoration&tab=bubbles',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}}).then(function(r){return r.json().catch(function(){return{ok:false,error:'操作失败'};});}).then(function(d){if(!d.ok)throw new Error(d.error||'操作失败');var b=bubbles.find(function(x){return x.id===bid;});if(b){b.owned=true;}renderGrid();renderTopPreview();toastMsg(act==='claim_bubble'?'领取成功':'购买成功');}).catch(function(e){toastMsg(e.message||'操作失败',true);});
  }
  function equip(bid){var fd=new FormData();fd.append('_csrf_token',csrf);fd.append('_action','equip_bubble');fd.append('bubble_id',bid);fetch('/index.php?path=decoration&tab=bubbles',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}}).then(function(r){return r.json().catch(function(){return{ok:false,error:'保存失败'};});}).then(function(d){if(!d.ok)throw new Error(d.error||'保存失败');bubbles.forEach(function(b){b.equipped=(bid>0&&b.id===bid);});renderGrid();renderTopPreview();var hint=document.getElementById('bubbleUnequipHint');if(hint)hint.style.setProperty('display',bid>0?'':'none','important');var cnt=document.querySelector('.bubble-count');if(cnt)cnt.textContent=bid>0?'已装备':'未装备';toastMsg(bid>0?'已装备气泡':'已卸下气泡');}).catch(function(e){toastMsg(e.message||'保存失败',true);});}
  document.querySelectorAll('.bubble-tab').forEach(function(b){b.addEventListener('click',function(){document.querySelectorAll('.bubble-tab').forEach(function(x){x.classList.remove('active');});b.classList.add('active');tab=b.dataset.tab||'all';quality='all';page=1;renderQuality();renderGrid();});});
  document.addEventListener('click',function(e){var q=e.target.closest('#bubbleQualityTabs .quality-tab');if(q){quality=q.dataset.quality||'all';page=1;renderQuality();renderGrid();return;}var pg=e.target.closest('.bubble-page-btn');if(pg&&!pg.disabled){var list=filtered(),pages=Math.max(1,Math.ceil(list.length/pageSize));var val=pg.dataset.page;if(val==='prev')page=Math.max(1,page-1);else if(val==='next')page=Math.min(pages,page+1);else page=parseInt(val,10)||1;renderGrid();return;}var eq=e.target.closest('[data-bb-equip]');if(eq){var eid=parseInt(eq.getAttribute('data-bb-equip'),10);var eb=bubbles.find(function(x){return x.id===eid;});if(eb&&eb.owned){eb.equipped?equip(0):equip(eid);}return;}var cl=e.target.closest('[data-bb-claim]');if(cl){postAction('claim_bubble',parseInt(cl.getAttribute('data-bb-claim'),10));return;}var bu=e.target.closest('[data-bb-buy]');if(bu&&!bu.disabled){var bid=parseInt(bu.getAttribute('data-bb-buy'),10);var bb=bubbles.find(function(x){return x.id===bid;});postAction('buy_bubble',bid,'确认花费 '+(bb?bb.price_label:'')+' 购买该气泡？');return;}});
  renderQuality();
  renderGrid();
  activatePreviewEffects(document.getElementById('bubblePreviewArea'));
  var t1=document.getElementById('bubbleToast');if(t1)setTimeout(function(){t1.classList.remove('show');},2000);
  var t2=document.getElementById('bubbleToastErr');if(t2)setTimeout(function(){t2.classList.remove('show');},2000);
})();
</script>
