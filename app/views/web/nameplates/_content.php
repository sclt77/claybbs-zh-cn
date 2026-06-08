<?php


$npList = $nameplates ?? [];
$npEquipped = $equippedNameplate ?? null;
$npProgress = $nameplateProgress ?? [];
$npOwned = (int)($nameplateOwnedCount ?? 0);
$npTotal = count($npList);
$npBalances = [];
foreach (($walletBalances ?? []) as $b) {
    $code = strtoupper((string)($b['currency_code'] ?? $b['code'] ?? ''));
    if ($code !== '') $npBalances[$code] = (float)($b['balance'] ?? 0);
}
$npSelfName = user_display_name($user ?? [], '用户');
$obtainLabels = ['free' => '免费领取', 'shop' => '商城购买', 'task' => '任务解锁', 'level' => '等级解锁', 'grant' => '管理员授予'];

$npCurrencyNames = [];
try {
    foreach ((new \App\Models\WalletModel())->currencies() as $c) {
        $npCurrencyNames[strtoupper((string)($c['code'] ?? ''))] = (string)($c['name'] ?? $c['code'] ?? '');
    }
} catch (\Throwable $e) {  }
$npCurLabel = static function (string $code) use ($npCurrencyNames): string {
    $code = strtoupper($code);
    return $npCurrencyNames[$code] ?? $code;
};
?>
<style>
.np-center{min-height:100vh;padding:0 16px 120px;color:var(--text-main,#0f172a)}
.np-shell{max-width:1180px;margin:0 auto;display:grid;gap:16px}
.np-toast{background:#16a34a;color:#fff;border-radius:14px;padding:11px 16px;font-size:13px;font-weight:800}
.np-board{display:grid;grid-template-columns:minmax(0,1fr) 260px;gap:16px;align-items:stretch}
.np-panel{background:rgba(255,255,255,.9);border:1px solid rgba(226,232,240,.86);border-radius:24px;padding:20px;box-shadow:0 14px 40px rgba(15,23,42,.05)}
.np-panel-head{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;margin-bottom:16px}
.np-panel-head h2{margin:0;font-size:21px;letter-spacing:-.03em}
.np-panel-head p{margin:6px 0 0;color:#64748b;font-size:13px;line-height:1.6}
.np-pill{font-size:12px;font-weight:900;color:#7c3aed;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:999px;padding:7px 11px;white-space:nowrap}
.np-stage{min-height:150px;border:1px dashed rgba(148,163,184,.6);border-radius:22px;background:linear-gradient(135deg,#0f172a,#1e293b);display:grid;place-items:center;text-align:center;position:relative;overflow:hidden}
.np-stage .np-stage-name{font-size:24px;font-weight:900}
.np-stage-empty{color:#94a3b8;font-weight:800;font-size:14px}
.np-side{padding:20px;display:grid;gap:12px;align-content:start}
.np-side .np-stat{border-radius:18px;padding:14px;background:#fff;border:1px solid #e2e8f0}
.np-side .np-stat strong{display:block;font-size:24px;letter-spacing:-.03em}
.np-side .np-stat span{display:block;margin-top:4px;color:#64748b;font-size:12px;font-weight:800}
.np-unequip{min-height:46px;border:1px dashed #fecaca;background:#fff7f7;color:#b91c1c;border-radius:18px;font-size:12px;font-weight:900;display:flex;align-items:center;justify-content:center;cursor:pointer}
.np-library{padding:18px}
.np-libtop{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.np-tabs{display:flex;gap:8px;flex-wrap:wrap}
.np-libtab{border:1px solid #e2e8f0;background:#fff;color:#475569;border-radius:999px;padding:9px 16px;font-weight:900;font-size:13px;cursor:pointer;transition:.15s}
.np-libtab:hover{background:#f1f5f9}
.np-libtab.active{background:#7c3aed;color:#fff;border-color:#7c3aed}
.np-libhint{font-size:12px;color:#64748b;font-weight:800}
html[data-theme="dark"] body .np-libtab{background:#111827;border-color:#263244;color:#cbd5e1}
html[data-theme="dark"] body .np-libtab.active{background:#7c3aed;color:#fff;border-color:#7c3aed}
html[data-theme="dark"] body .np-libhint{color:#94a3b8}
.np-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px}
.np-card{border:1px solid #e2e8f0;border-radius:20px;background:#fff;padding:14px;display:grid;gap:10px;position:relative;transition:.16s ease}
.np-card:hover{transform:translateY(-2px);box-shadow:0 16px 32px rgba(15,23,42,.07)}
.np-card.equipped{border-color:rgba(124,58,237,.4);box-shadow:0 0 0 1px rgba(124,58,237,.18)}
.np-card.locked{opacity:.62}
.np-preview{min-height:64px;border-radius:14px;background:linear-gradient(135deg,#0f172a,#1e293b);display:grid;place-items:center;padding:8px}
.np-preview .np-fx{font-size:18px;font-weight:900}
.np-card-name{font-size:14px;font-weight:900;color:#0f172a}
.np-card-desc{color:#64748b;font-size:12px;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:34px}
.np-meta{display:flex;gap:6px;flex-wrap:wrap}
.np-tag{font-size:11px;font-weight:900;border-radius:999px;padding:3px 8px;background:#f1f5f9;border:1px solid #e2e8f0;color:#64748b}
.np-tag.method{color:#7c3aed;background:#f5f3ff;border-color:#ddd6fe}
.np-progress{height:6px;border-radius:999px;background:#eef2f7;overflow:hidden}
.np-progress span{display:block;height:100%;background:linear-gradient(90deg,#7c3aed,#3b82f6);border-radius:999px}
.np-rule{font-size:11px;color:#94a3b8;font-weight:700}
.np-act{height:34px;border:0;border-radius:999px;background:#0f172a;color:#fff;font-size:12px;font-weight:900;cursor:pointer;width:100%}
.np-act.equip{background:#7c3aed}
.np-act.equipped{background:#16a34a}
.np-act.buy{background:linear-gradient(135deg,#f59e0b,#ef4444)}
.np-act.free{background:linear-gradient(135deg,#10b981,#3cc9a4)}
.np-act[disabled]{background:#e2e8f0;color:#94a3b8;cursor:not-allowed}
.np-empty{grid-column:1/-1;text-align:center;color:#94a3b8;font-weight:800;padding:30px}
html[data-theme="dark"] body .np-panel,html[data-theme="dark"] body .np-card,html[data-theme="dark"] body .np-side .np-stat{background:#111827;border-color:#263244;color:#e5e7eb}
html[data-theme="dark"] body .np-panel-head p,html[data-theme="dark"] body .np-card-desc{color:#94a3b8}
html[data-theme="dark"] body .np-card-name{color:#e5e7eb}
html[data-theme="dark"] body .np-tag{background:#0f172a;border-color:#263244;color:#94a3b8}
html[data-theme="dark"] body .np-progress{background:#0f172a}
@media(max-width:900px){.np-board{grid-template-columns:1fr}.np-side{grid-template-columns:1fr 1fr;display:grid}}
@media(max-width:640px){.np-center{padding:0 10px 150px}.np-grid{grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}}
</style>
<main class="np-center">
  <div class="np-shell">
    <?php if (!empty($message)): ?><div class="np-toast"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="np-toast" style="background:#991b1b"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <section class="np-board">
      <div class="np-panel">
        <div class="np-panel-head"><div><h2>我的名字特效</h2><p>装备后，你的昵称将在全站（帖子、评论、聊天、个人主页等）展示炫酷动态特效。</p></div><div class="np-pill"><?= $npEquipped ? '已装备' : '未装备' ?></div></div>
        <div class="np-stage">
          <?php if ($npEquipped): ?>
            <div class="np-stage-name"><?= \App\Services\NameplateService::wrap(htmlspecialchars($npSelfName, ENT_QUOTES, 'UTF-8'), $npEquipped) ?></div>
          <?php else: ?>
            <div class="np-stage-empty">暂未装备名字特效</div>
          <?php endif; ?>
        </div>
      </div>
      <aside class="np-panel np-side">
        <div class="np-stat"><strong><?= $npOwned ?></strong><span>已获得</span></div>
        <div class="np-stat"><strong><?= $npTotal ?></strong><span>全部特效</span></div>
        <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="_action" value="equip_nameplate"><input type="hidden" name="nameplate_id" value="0"><button class="np-unequip" type="submit">卸下名字特效</button></form>
      </aside>
    </section>
    <section class="np-panel np-library">
      <div class="np-panel-head"><div><h2>特效商店</h2><p>免费领取、积分购买、任务或等级解锁，或由管理员授予。已获取的特效会移到“我的特效”。</p></div></div>
      <div class="np-libtop"><div class="np-tabs"><button class="np-libtab active" type="button" data-nptab="all">全部特效</button><button class="np-libtab" type="button" data-nptab="mine">我的特效</button></div><div class="np-libhint" id="npLibHint">展示所有可获取的名字特效，已获取的请到“我的特效”查看</div></div>
      <div class="np-grid" id="npGrid">
        <?php if (empty($npList)): ?>
          <div class="np-empty">暂无可用的名字特效</div>
        <?php else: foreach ($npList as $np):
            $id = (int)$np['id'];
            $owned = !empty($np['owned']);
            $equipped = $owned && !empty($np['is_equipped']);
            $obtain = (string)($np['obtain_method'] ?? 'grant');
            $prog = $npProgress[$id] ?? ['percent' => 0, 'label' => '', 'done' => false];
            $locked = !$owned && !in_array($obtain, ['free', 'shop'], true) && empty($prog['done']);
            $cur = strtoupper((string)($np['price_currency'] ?? ''));
            $amt = (float)($np['price_amount'] ?? 0);
            $amtLabel = rtrim(rtrim(number_format($amt, 6, '.', ''), '0'), '.');
            $canAfford = $cur !== '' && ($npBalances[$cur] ?? 0) >= $amt;
        ?>
        <div class="np-card <?= $equipped ? 'equipped' : '' ?> <?= $locked ? 'locked' : '' ?>" data-owned="<?= $owned ? '1' : '0' ?>">
          <div class="np-preview"><?= \App\Services\NameplateService::wrap(htmlspecialchars($npSelfName, ENT_QUOTES, 'UTF-8'), $np) ?></div>
          <div class="np-card-name"><?= htmlspecialchars((string)$np['name']) ?></div>
          <div class="np-card-desc"><?= htmlspecialchars((string)($np['description'] ?? '')) ?></div>
          <div class="np-meta">
            <span class="np-tag method"><?= htmlspecialchars($obtainLabels[$obtain] ?? $obtain) ?></span>
            <?php if ($obtain === 'shop' && $cur !== ''): ?><span class="np-tag"><?= htmlspecialchars($amtLabel . ' ' . $npCurLabel($cur)) ?></span><?php endif; ?>
          </div>
          <?php if (!$owned && in_array($obtain, ['task', 'level'], true)): ?>
            <div class="np-progress"><span style="width:<?= (int)($prog['percent'] ?? 0) ?>%"></span></div>
            <div class="np-rule"><?= htmlspecialchars((string)($prog['label'] ?? '')) ?></div>
          <?php endif; ?>
          <div class="np-actions">
            <?php if ($equipped): ?>
              <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="_action" value="equip_nameplate"><input type="hidden" name="nameplate_id" value="0"><button class="np-act equipped" type="submit">已装备 · 点击卸下</button></form>
            <?php elseif ($owned): ?>
              <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="_action" value="equip_nameplate"><input type="hidden" name="nameplate_id" value="<?= $id ?>"><button class="np-act equip" type="submit">装备</button></form>
            <?php elseif ($obtain === 'free'): ?>
              <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="_action" value="claim_nameplate"><input type="hidden" name="nameplate_id" value="<?= $id ?>"><button class="np-act free" type="submit">免费领取</button></form>
            <?php elseif ($obtain === 'shop'): ?>
              <form method="post" style="margin:0" onsubmit="return confirm('确认花费 <?= htmlspecialchars($amtLabel . ' ' . $npCurLabel($cur), ENT_QUOTES) ?> 购买该名字特效？')"><?= csrf_field() ?><input type="hidden" name="_action" value="buy_nameplate"><input type="hidden" name="nameplate_id" value="<?= $id ?>"><button class="np-act buy" type="submit" <?= $canAfford ? '' : 'disabled' ?>><?= $canAfford ? ('购买 ' . htmlspecialchars($amtLabel . ' ' . $npCurLabel($cur))) : '余额不足' ?></button></form>
            <?php elseif (!empty($prog['done'])): ?>
              <button class="np-act" type="button" disabled>条件已达成，刷新领取</button>
            <?php else: ?>
              <button class="np-act" type="button" disabled><?= $obtain === 'grant' ? '管理员授予' : '未解锁' ?></button>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </section>
  </div>
</main>
<script>
(function(){
  var grid=document.getElementById('npGrid');
  if(!grid)return;
  var cards=[].slice.call(grid.querySelectorAll('.np-card'));
  var tabs=[].slice.call(document.querySelectorAll('.np-libtab'));
  var hint=document.getElementById('npLibHint');
  var tab='all';
  function filter(){
    var shown=0;
    cards.forEach(function(c){
      var owned=c.getAttribute('data-owned')==='1';
      var show=(tab==='mine')?owned:!owned;
      c.style.setProperty('display',show?'grid':'none','important');
      if(show)shown++;
    });
    var empty=grid.querySelector('.np-empty-dyn');
    if(empty)empty.remove();
    if(!shown){
      var d=document.createElement('div');
      d.className='np-empty np-empty-dyn';
      d.textContent=(tab==='mine')?'还没有获取任何名字特效，去“全部特效”里获取吧':'太棒了，全部特效都已获取，去“我的特效”查看吧';
      grid.appendChild(d);
    }
    if(hint)hint.textContent=(tab==='mine')?'你已获取的名字特效，点击即可装备/卸下':'展示所有可获取的名字特效，已获取的请到“我的特效”查看';
  }
  tabs.forEach(function(b){b.addEventListener('click',function(){tabs.forEach(function(x){x.classList.remove('active');});b.classList.add('active');tab=b.getAttribute('data-nptab')||'all';filter();});});
  filter();
})();
</script>
