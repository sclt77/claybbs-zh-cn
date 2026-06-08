<?php
$pageTitle = '名字特效管理';
require dirname(__DIR__) . '/layouts/main.php';
$nameplates = $nameplates ?? [];
$grants = $grants ?? [];
$users = $users ?? [];
$styleKeys = $styleKeys ?? [];
$currencies = $currencies ?? [];
$tasks = $tasks ?? [];
$levels = $levels ?? [];
$error = $error ?? '';
$message = $message ?? '';
$obtainLabels = ['free' => '免费领取', 'shop' => '商城购买', 'task' => '任务解锁', 'level' => '等级解锁', 'grant' => '管理员授予'];
$styleLabels = [
    'aurora' => '极光流光', 'neon' => '霓虹脉冲', 'rainbow' => '彩虹渐变', 'gold' => '流金质感',
    'fire' => '烈焰燃烧', 'ice' => '冰晶', 'glitch' => '故障风', 'starlight' => '星光点缀',
    'gradient-flow' => '渐变流边', 'shadow-pulse' => '呼吸光晕',
    'calligraphy' => '翰墨书法', 'handwrite' => '灵动手写', 'pixel' => '像素霓虹', 'elegant-serif' => '典雅衬线',
];

$curNames = [];
foreach ($currencies as $c) { $curNames[strtoupper((string)($c['code'] ?? ''))] = (string)($c['name'] ?? $c['code'] ?? ''); }
$curLabel = static function ($code) use ($curNames) { $code = strtoupper((string)$code); return $curNames[$code] ?? $code; };
$npJson = [];
foreach ($nameplates as $n) {
    $npJson[] = [
        'id' => (int)$n['id'], 'name' => (string)$n['name'], 'description' => (string)($n['description'] ?? ''),
        'style_key' => (string)$n['style_key'], 'frame_color' => (string)$n['frame_color'],
        'accent_color' => (string)$n['accent_color'], 'text_color' => (string)$n['text_color'],
        'custom_css' => (string)($n['custom_css'] ?? ''), 'obtain_method' => (string)$n['obtain_method'],
        'price_currency' => (string)($n['price_currency'] ?? ''), 'price_amount' => rtrim(rtrim(number_format((float)$n['price_amount'], 6, '.', ''), '0'), '.'),
        'status' => (string)$n['status'], 'sort_order' => (int)$n['sort_order'],
    ];
}
?>
<link rel="stylesheet" href="/assets/css/name-effects.css?v=20260530-font-v2">
<style>
.np-tabs-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;box-shadow:0 4px 16px rgba(15,23,42,.04);max-width:100%}
.np-tabs{display:flex;gap:4px;flex-wrap:wrap;padding:10px 14px;border-bottom:1px solid #e2e8f0;background:#f8fafc}
.np-tab{border:0;border-radius:999px;background:transparent;color:#64748b;padding:8px 14px;font-weight:900;font-size:13px;cursor:pointer;transition:.15s;white-space:nowrap}
.np-tab:hover{background:#e2e8f0}
.np-tab.is-active{background:#7c3aed;color:#fff}
.np-panel{display:none;padding:18px 20px}
.np-panel.is-active{display:block}
.np-panel h3{margin:0 0 14px;font-size:16px;letter-spacing:-.02em}
.np-help{color:#64748b;font-size:13px;line-height:1.6;margin-bottom:14px;padding:10px 14px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0}
.np-live{margin:0 0 16px;padding:16px;border-radius:14px;background:linear-gradient(135deg,#0f172a,#1e293b);display:grid;place-items:center;overflow:hidden}
.np-live .np-fx{font-size:22px;font-weight:900}
.np-form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px}
.np-field{display:grid;gap:6px;min-width:0}
.np-field.full{grid-column:1/-1}
.np-field label{font-size:12px;font-weight:900;color:#475569}
.np-field input,.np-field select,.np-field textarea{width:100%;box-sizing:border-box;height:38px;border:1px solid #e2e8f0;border-radius:10px;padding:0 11px;font-size:13px;background:#fff;color:#0f172a}
.np-field textarea{height:auto;min-height:60px;padding:8px 11px;resize:vertical}
.np-field input[type=color]{padding:2px;height:38px;cursor:pointer}
.np-prev-box{display:inline-grid;place-items:center;padding:6px 12px;border-radius:10px;background:linear-gradient(135deg,#0f172a,#1e293b)}
.np-prev-box .np-fx{font-size:15px;font-weight:900}
.np-badge{display:inline-flex;font-size:11px;font-weight:900;border-radius:999px;padding:3px 8px;background:#f1f5f9;color:#475569}
.np-badge.method{background:#f5f3ff;color:#7c3aed}
.np-badge.off{background:#fee2e2;color:#b91c1c}
html[data-theme="dark"] body .np-tabs-card{background:#111827;border-color:#263244}
html[data-theme="dark"] body .np-tabs{background:#0f172a;border-color:#263244}
html[data-theme="dark"] body .np-help{background:#0f172a;border-color:#263244;color:#94a3b8}
html[data-theme="dark"] body .np-field input,html[data-theme="dark"] body .np-field select,html[data-theme="dark"] body .np-field textarea{background:#0f172a;border-color:#263244;color:#e5e7eb}
html[data-theme="dark"] body .np-badge{background:#0f172a;color:#94a3b8}
@media(max-width:640px){.np-panel{padding:14px}.np-form-grid{grid-template-columns:1fr 1fr}}
</style>
<div class="page-header"><div><div class="page-title">名字特效管理</div><div class="admin-muted admin-mt-4">管理名字特效、获取方式（免费/付费/任务/等级/授予）、给用户授予和解锁检查。</div></div><a class="btn btn-light" href="/index.php?path=decoration&tab=nameplates" target="_blank">查看前台</a></div>
<?php if ($error): ?><div class="card admin-alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($message): ?><div class="card admin-alert ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<div class="np-tabs-card">
  <nav class="np-tabs" role="tablist" aria-label="名字特效管理">
    <button class="np-tab is-active" type="button" data-np-tab="list">特效列表 <?= count($nameplates) ?></button>
    <button class="np-tab" type="button" data-np-tab="create">新建特效</button>
    <button class="np-tab" type="button" data-np-tab="grant">授予用户</button>
    <button class="np-tab" type="button" data-np-tab="grants">授予记录 <?= count($grants) ?></button>
    <button class="np-tab" type="button" data-np-tab="guide">教程</button>
  </nav>

  
  <section class="np-panel is-active" data-np-panel="list">
    <h3>全部名字特效</h3>
    <div class="table-responsive"><table class="table"><thead><tr><th>预览</th><th>名称</th><th>样式</th><th>获取方式</th><th>价格</th><th>拥有/装备</th><th>状态</th><th>操作</th></tr></thead><tbody>
      <?php if (empty($nameplates)): ?><tr><td colspan="8" class="admin-empty">暂无名字特效</td></tr><?php endif; ?>
      <?php foreach ($nameplates as $n): $id=(int)$n['id']; ?>
      <tr>
        <td><span class="np-prev-box"><?= \App\Services\NameplateService::wrap('示例', $n) ?></span></td>
        <td><b><?= htmlspecialchars((string)$n['name']) ?></b></td>
        <td><?= htmlspecialchars($styleLabels[(string)$n['style_key']] ?? (string)$n['style_key']) ?></td>
        <td><span class="np-badge method"><?= htmlspecialchars($obtainLabels[(string)$n['obtain_method']] ?? (string)$n['obtain_method']) ?></span></td>
        <td><?= (string)$n['obtain_method'] === 'shop' ? htmlspecialchars(rtrim(rtrim(number_format((float)$n['price_amount'],6,'.',''),'0'),'.') . ' ' . $curLabel((string)$n['price_currency'])) : '—' ?></td>
        <td><?= (int)($n['owner_count'] ?? 0) ?> / <?= (int)($n['equipped_count'] ?? 0) ?></td>
        <td><?= (string)$n['status'] === 'active' ? '启用' : '<span class="np-badge off">停用</span>' ?></td>
        <td><div style="display:flex;gap:6px;flex-wrap:wrap"><button class="btn" type="button" data-np-edit="<?= $id ?>">编辑</button><form method="post" onsubmit="return confirm('确认删除？已有用户获得时会被阻止');" style="margin:0"><?= csrf_field() ?><input type="hidden" name="_action" value="delete_nameplate"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn danger" type="submit">删除</button></form></div></td>
      </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </section>

  
  <section class="np-panel" data-np-panel="create">
    <h3 id="npFormTitle">新建名字特效</h3>
    <div class="np-help">名字特效会包裹在用户昵称外，全站展示。获取方式：免费领取、商城购买（钱包货币）、任务解锁、等级解锁、管理员授予。任务/等级解锁请在“解锁阈值”里填任务ID或等级数。</div>
    <div class="np-live"><span id="npLivePreview"><span class="np-fx np-fx--aurora" data-np-style="aurora" style="--np-frame:#38bdf8;--np-accent:#a78bfa;--np-text:#0f172a"><span class="np-fx-text">示例昵称</span></span></span></div>
    <form method="post" id="npForm" action="/admin.php?path=nameplates">
      <?= csrf_field() ?>
      <input type="hidden" name="_action" value="save_nameplate">
      <input type="hidden" name="id" id="np_id" value="0">
      <div class="np-form-grid">
        <div class="np-field"><label>名称</label><input type="text" name="name" id="np_name" required maxlength="80"></div>
        <input type="hidden" name="style_key" id="np_style_key" value="aurora">
        <div class="np-field"><label>主色 --np-frame</label><input type="color" name="frame_color" id="np_frame_color" value="#38bdf8"></div>
        <div class="np-field"><label>强调色 --np-accent</label><input type="color" name="accent_color" id="np_accent_color" value="#a78bfa"></div>
        <div class="np-field"><label>文字色 --np-text</label><input type="color" name="text_color" id="np_text_color" value="#0f172a"></div>
        <div class="np-field"><label>获取方式</label><select name="obtain_method" id="np_obtain_method"><?php foreach ($obtainLabels as $k => $v): ?><option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option><?php endforeach; ?></select></div>
        <div class="np-field" id="np_currency_field"><label>购买货币</label><select name="price_currency" id="np_price_currency"><option value="">（不需要）</option><?php foreach ($currencies as $c): ?><option value="<?= htmlspecialchars($c['code']) ?>"><?= htmlspecialchars($c['name'] . '（' . $c['code'] . '）') ?></option><?php endforeach; ?></select></div>
        <div class="np-field" id="np_price_field"><label>价格</label><input type="number" name="price_amount" id="np_price_amount" min="0" step="0.01" value="0"></div>
        <input type="hidden" name="rule_value" id="np_rule_value" value="0">
        <div class="np-field" id="np_task_field"><label>关联任务（完成后解锁）</label><select id="np_rule_task"><option value="0">— 选择任务 —</option><?php foreach ($tasks as $t): ?><option value="<?= (int)$t['id'] ?>">#<?= (int)$t['id'] ?> <?= htmlspecialchars((string)$t['title']) ?><?= ($t['status'] ?? '') !== 'active' ? '（停用）' : '' ?></option><?php endforeach; ?></select></div>
        <div class="np-field" id="np_level_field"><label>达到等级后解锁</label><select id="np_rule_level"><option value="0">— 选择等级 —</option><?php foreach ($levels as $lv): ?><option value="<?= (int)$lv['level'] ?>">Lv.<?= (int)$lv['level'] ?> <?= htmlspecialchars((string)$lv['name']) ?></option><?php endforeach; ?></select></div>
        <div class="np-field"><label>排序</label><input type="number" name="sort_order" id="np_sort_order" value="0"></div>
        <div class="np-field"><label>状态</label><select name="status" id="np_status"><option value="active">启用</option><option value="disabled">禁用</option></select></div>
        <div class="np-field full"><label>描述</label><input type="text" name="description" id="np_description" maxlength="255"></div>
        <div class="np-field full"><label>特效参数 / CSS（可用主色 var(--np-frame)、强调色 var(--np-accent)、文字色 var(--np-text)）</label><textarea name="custom_css" id="np_custom_css" rows="6" placeholder='可以直接粘贴 CSS，例如：
.np-fx.aqua-name .np-fx-text{color:var(--np-text);background:linear-gradient(90deg,var(--np-frame),var(--np-accent));-webkit-background-clip:text;-webkit-text-fill-color:transparent;text-shadow:0 0 12px var(--np-accent)}

也支持 JSON：{"type":"aqua-name","css":".np-fx.aqua-name .np-fx-text{color:var(--np-text);text-shadow:0 0 12px var(--np-accent)}"}'></textarea></div>
      </div>
      <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
        <button type="submit" class="btn">保存</button>
        <button type="button" class="btn btn-light" onclick="npResetForm()">重置为新建</button>
      </div>
    </form>
  </section>

  
  <section class="np-panel" data-np-panel="grant">
    <h3>授予用户 / 解锁检查</h3>
    <form method="post" action="/admin.php?path=nameplates" class="np-form-grid" style="align-items:end">
      <?= csrf_field() ?>
      <input type="hidden" name="_action" value="grant_nameplate">
      <div class="np-field"><label>用户ID</label><input type="number" name="user_id" min="1" placeholder="目标用户 ID" required></div>
      <div class="np-field"><label>名字特效</label><select name="nameplate_id" required><?php foreach ($nameplates as $n): ?><option value="<?= (int)$n['id'] ?>"><?= htmlspecialchars((string)$n['name']) ?></option><?php endforeach; ?></select></div>
      <div class="np-field"><label>备注</label><input type="text" name="note" maxlength="255" placeholder="可选"></div>
      <div class="np-field"><label>&nbsp;</label><button type="submit" class="btn">授予</button></div>
    </form>
    <div style="height:1px;background:#e2e8f0;margin:18px 0"></div>
    <form method="post" action="/admin.php?path=nameplates" class="np-form-grid" style="align-items:end">
      <?= csrf_field() ?>
      <input type="hidden" name="_action" value="check_auto">
      <div class="np-field"><label>对用户ID执行解锁检查</label><input type="number" name="user_id" min="1" placeholder="用户 ID"></div>
      <div class="np-field"><label>&nbsp;</label><button type="submit" class="btn btn-light">触发任务/等级解锁检查</button></div>
    </form>
  </section>

  
  <section class="np-panel" data-np-panel="grants">
    <h3>授予记录（最近 200 条）</h3>
    <div class="table-responsive"><table class="table"><thead><tr><th>用户</th><th>名字特效</th><th>来源</th><th>时间</th><th>操作</th></tr></thead><tbody>
      <?php if (empty($grants)): ?><tr><td colspan="5" class="admin-empty">暂无记录</td></tr><?php endif; ?>
      <?php foreach ($grants as $g): ?>
      <tr>
        <td><?= htmlspecialchars((string)($g['nickname'] ?: $g['username'])) ?> <span class="admin-muted">#<?= (int)$g['user_id'] ?></span></td>
        <td><?= htmlspecialchars((string)($g['plate_name'] ?? '')) ?></td>
        <td><span class="np-badge"><?= htmlspecialchars((string)($g['source'] ?? '')) ?></span><?= !empty($g['is_equipped']) ? ' <span class="np-badge method">已装备</span>' : '' ?></td>
        <td><?= htmlspecialchars((string)($g['obtained_at'] ?? '')) ?></td>
        <td><form method="post" onsubmit="return confirm('确认撤销？')" style="margin:0"><?= csrf_field() ?><input type="hidden" name="_action" value="revoke_nameplate"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button type="submit" class="btn danger">撤销</button></form></td>
      </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </section>
  <?php
    $decoGuideType = 'nameplate';
    $decoGuideTitle = '名字特效配置教程';
    $decoGuidePanelClass = 'np-panel';
    $decoGuidePanelAttr = 'data-np-panel';
    require dirname(__DIR__) . '/layouts/deco_guide.php';
  ?>
</div>

<script>
window.NpData = <?= json_encode(array_values($npJson), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
(function(){
  // Tab 切换
  document.querySelectorAll('.np-tab').forEach(function(b){b.onclick=function(){document.querySelectorAll('.np-tab,.np-panel').forEach(function(x){x.classList.remove('is-active')});b.classList.add('is-active');var p=document.querySelector('[data-np-panel="'+b.dataset.npTab+'"]');if(p)p.classList.add('is-active');try{localStorage.setItem('clay_np_admin_tab',b.dataset.npTab)}catch(_){}}});
  try{var saved=localStorage.getItem('clay_np_admin_tab');var t=saved&&document.querySelector('[data-np-tab="'+saved+'"]');if(t)t.click()}catch(_){}

  function g(id){return document.getElementById(id);}
  function inferNpType(raw){raw=String(raw||'');var m=raw.match(/\.np-fx\.([a-zA-Z][a-zA-Z0-9_-]*)/);if(m)return m[1];m=raw.match(/\.np-fx--([a-zA-Z][a-zA-Z0-9_-]*)/);if(m)return m[1];m=raw.match(/\.([a-zA-Z][a-zA-Z0-9_-]*)\s*\{/);return m?m[1]:'';}
  function parseNpCss(raw){raw=String(raw||'').trim();if(!raw)return {type:g('np_style_key').value||'aurora',css:''};try{var o=JSON.parse(raw);return {type:o.type||g('np_style_key').value||'aurora',css:o.css||''};}catch(e){return {type:inferNpType(raw)||g('np_style_key').value||'aurora',css:raw};}}
  function npLive(){
    var f=g('np_frame_color').value,a=g('np_accent_color').value,t=g('np_text_color').value,raw=g('np_custom_css').value||'';
    var parsed=parseNpCss(raw),s=parsed.type||'aurora';g('np_style_key').value=s;
    var name=(g('np_name').value||'示例昵称').replace(/[<>&]/g,'');
    var styleId='np-live-custom-style',old=document.getElementById(styleId);if(old)old.remove();
    if(parsed.css&&parsed.css.indexOf('{')>=0){var st=document.createElement('style');st.id=styleId;st.textContent=parsed.css;(document.body||document.head).appendChild(st);}
    var inline='--np-frame:'+f+';--np-accent:'+a+';--np-text:'+t+';';
    if(parsed.css&&parsed.css.indexOf('{')<0)inline+=parsed.css;
    g('npLivePreview').innerHTML='<span class="np-fx np-fx--'+s+' '+s+'" data-np-style="'+s+'" style="'+inline.replace(/"/g,'&quot;')+'"><span class="np-fx-text">'+name+'</span></span>';
  }
  ['np_frame_color','np_accent_color','np_text_color','np_name','np_custom_css'].forEach(function(id){g(id).addEventListener('input',npLive);});
  function npToggle(){
    var m=g('np_obtain_method').value;
    g('np_currency_field').style.display=(m==='shop')?'grid':'none';
    g('np_price_field').style.display=(m==='shop')?'grid':'none';
    g('np_task_field').style.display=(m==='task')?'grid':'none';
    g('np_level_field').style.display=(m==='level')?'grid':'none';
  }
  // 任务/等级下拉 -> 同步到隐藏的 rule_value
  function npSyncRule(){
    var m=g('np_obtain_method').value;
    if(m==='task'){g('np_rule_value').value=g('np_rule_task').value||0;}
    else if(m==='level'){g('np_rule_value').value=g('np_rule_level').value||0;}
    else{g('np_rule_value').value=0;}
  }
  g('np_rule_task').addEventListener('change',npSyncRule);
  g('np_rule_level').addEventListener('change',npSyncRule);
  g('np_obtain_method').addEventListener('change',function(){npToggle();npSyncRule();});
  window.npResetForm=function(){g('npForm').reset();g('np_id').value=0;g('np_rule_task').value=0;g('np_rule_level').value=0;g('np_rule_value').value=0;g('npFormTitle').textContent='新建名字特效';npToggle();npLive();};
  document.addEventListener('click',function(e){
    var btn=e.target.closest('[data-np-edit]'); if(!btn)return;
    var id=btn.getAttribute('data-np-edit');
    var d=(window.NpData||[]).find(function(x){return String(x.id)===String(id);}); if(!d)return;
    g('npFormTitle').textContent='编辑：'+d.name;
    g('np_id').value=d.id;g('np_name').value=d.name;g('np_description').value=d.description;
    g('np_style_key').value=d.style_key;g('np_frame_color').value=d.frame_color;g('np_accent_color').value=d.accent_color;g('np_text_color').value=d.text_color;
    g('np_custom_css').value=d.custom_css;g('np_obtain_method').value=d.obtain_method;g('np_price_currency').value=d.price_currency;g('np_price_amount').value=d.price_amount;
    // task/level 的阈值复用 price_amount 列存储
    var rv=parseInt(d.price_amount||0,10)||0;
    g('np_rule_value').value=(d.obtain_method==='task'||d.obtain_method==='level')?rv:0;
    g('np_rule_task').value=(d.obtain_method==='task')?rv:0;
    g('np_rule_level').value=(d.obtain_method==='level')?rv:0;
    g('np_sort_order').value=d.sort_order;g('np_status').value=d.status;
    npToggle();npLive();
    var tab=document.querySelector('[data-np-tab="create"]'); if(tab)tab.click();
    g('npForm').scrollIntoView({behavior:'smooth'});
  });
  npToggle();npLive();
})();
</script>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
