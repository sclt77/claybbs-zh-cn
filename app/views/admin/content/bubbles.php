<?php

$pageTitle = '气泡管理';
require dirname(__DIR__) . '/layouts/main.php';
$effectTypes = $effectTypes ?? [];
foreach ($effectTypes as $k => $v) {
    if (is_string($v)) {
        $effectTypes[$k] = ['name' => $v, 'desc' => '', 'default_color' => '#7c3aed'];
    } elseif (is_array($v)) {
        $effectTypes[$k] = [
            'name' => (string)($v['name'] ?? $k),
            'desc' => (string)($v['desc'] ?? ''),
            'default_color' => (string)($v['default_color'] ?? ($k === 'cat' ? '#fb923c' : '#22d3ee')),
        ];
    } else {
        $effectTypes[$k] = ['name' => (string)$k, 'desc' => '', 'default_color' => '#7c3aed'];
    }
}
$bubbleRows = json_encode($bubbles, JSON_UNESCAPED_UNICODE);
$qualityRows = json_encode($qualities, JSON_UNESCAPED_UNICODE);
$userRows = json_encode($users, JSON_UNESCAPED_UNICODE);
$effectTypesJson = json_encode($effectTypes, JSON_UNESCAPED_UNICODE);
$currencies = $currencies ?? [];
$tasks = $tasks ?? [];
$levels = $levels ?? [];
$obtainLabels = ['free'=>'免费领取','shop'=>'商城购买','task'=>'任务解锁','level'=>'等级解锁','grant'=>'管理员授予'];
$curNames = [];
foreach ($currencies as $c) { $curNames[strtoupper((string)($c['code'] ?? ''))] = (string)($c['name'] ?? $c['code'] ?? ''); }
$curLabel = static function ($code) use ($curNames) { $code = strtoupper((string)$code); return $curNames[$code] ?? $code; };
?>

<div class="page-header"><div><div class="page-title">聊天气泡特效管理</div><div class="admin-muted admin-mt-4">完整聊天气泡特效体系：配置 Anime.js 粒子特效、品质筛选、手动授予、前台装饰中心展示和聊天消息实时渲染。</div></div><a class="btn btn-light" href="/index.php?path=decoration" target="_blank">查看前台</a></div>
<?php if ($error): ?><div class="card admin-alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($message): ?><div class="card admin-alert ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<div class="bubble-tabs-card" id="bubbleTabsCard">
    <div class="bubble-tabs">
      <button class="bubble-tab is-active" type="button" data-tab="list">气泡列表 <?= count($bubbles) ?></button>
      <button class="bubble-tab" type="button" data-tab="create">新增气泡</button>
      <button class="bubble-tab" type="button" data-tab="quality">品质配置 <?= count($qualities) ?></button>
      <button class="bubble-tab" type="button" data-tab="grant">授予用户</button>
      <button class="bubble-tab" type="button" data-tab="history">授予记录 <?= count($grants) ?></button>
      <button class="bubble-tab" type="button" data-tab="guide">教程</button>
    </div>

    
    <div class="bubble-panel is-active" data-panel="list">
      <h3>气泡列表</h3>
      <div class="bubble-help">查看和编辑当前所有聊天气泡特效。预览区域会实时渲染 Anime.js 粒子效果。</div>
      <div class="table-responsive">
        <table class="table">
          <thead><tr>
            <th>预览</th><th>编码</th><th>名称</th><th>特效</th><th>品质</th><th>获取方式</th><th>拥有/装备</th><th>状态</th><th>操作</th>
          </tr></thead>
          <tbody>
          <?php if (empty($bubbles)): ?>
            <tr><td colspan="9" class="text-center text-muted">暂无气泡</td></tr>
          <?php else: foreach ($bubbles as $b): ?>
            <tr>
              <td><div class="bubble-preview-mini" data-effect="<?= htmlspecialchars($b['effect_type'] ?? '') ?>" data-params="<?= htmlspecialchars($b['effect_params'] ?? '{}') ?>" style="width:116px;height:62px;border-radius:16px;border:1px solid var(--line-soft,#e2e8f0);overflow:visible;position:relative;background:#fff7fb;display:flex;align-items:center;justify-content:center"></div></td>
              <td><code class="bubble-code"><?= htmlspecialchars($b['code']) ?></code></td>
              <td><?= htmlspecialchars($b['name']) ?></td>
              <td><?php
                $et = $effectTypes[$b['effect_type'] ?? ''] ?? null;
                echo $et ? '<span style="color:'.$b['quality_color'].'">'.$et['name'].'</span>' : '<span class="text-muted">-</span>';
              ?></td>
              <td><span class="status-badge" style="background:<?= htmlspecialchars($b['quality_color']) ?>20;color:<?= htmlspecialchars($b['quality_color']) ?>"><?= htmlspecialchars($b['quality_name']) ?></span></td>
              <td><?php $om = (string)($b['obtain_method'] ?? 'grant'); ?><span class="status-badge" style="background:#eef2ff;color:#4338ca"><?= htmlspecialchars($obtainLabels[$om] ?? '管理员授予') ?></span><?php if ($om === 'shop' && !empty($b['price_currency'])): ?><br><small class="text-muted"><?= htmlspecialchars(rtrim(rtrim(number_format((float)($b['price_amount'] ?? 0),6,'.',''),'0'),'.') . ' ' . $curLabel((string)$b['price_currency'])) ?></small><?php endif; ?></td>
              <td><?= (int)$b['owner_count'] ?> / <?= (int)$b['equipped_count'] ?></td>
              <td><span class="status-<?= $b['status'] === 'active' ? 'approved' : 'rejected' ?>"><?= $b['status'] === 'active' ? '启用' : '停用' ?></span></td>
              <td>
                <button class="btn btn-light" onclick="editBubble(<?= (int)$b['id'] ?>)">编辑</button>
                <form method="post" action="/admin.php?path=bubbles" style="display:inline" onsubmit="return confirm('确认删除？')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="_action" value="delete_bubble">
                  <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                  <button class="btn btn-light admin-danger" type="submit">删除</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    
    <div class="bubble-panel" data-panel="create">
      <h3>新增气泡</h3>
      <div class="bubble-help">创建管理员配置的聊天气泡特效。用户不能自定义气泡，只能在装饰中心装备已获得的气泡。</div>
      <form method="post" action="/admin.php?path=bubbles" class="bubble-form admin-form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="save_bubble">
        <div class="admin-form-grid">
          <label>编码 <input name="code" required placeholder="如 galaxy-dream"></label>
          <label>名称 <input name="name" required placeholder="如 星河幻想"></label>
          <input type="hidden" name="effect_type" id="create_effect_type" value="">
          <label>特效参数 / CSS <textarea name="effect_params" id="create_effect_params" rows="5" placeholder='可以直接粘贴 CSS，例如：
.chat-msg.sakura{background:linear-gradient(135deg,#ff9a9e,#fad0c4);border-radius:20px;color:#831843}

也支持 JSON：{"type":"galaxy","color":"#22d3ee","count":14,"speed":0.8,"size":3}'></textarea></label>
          <label>品质
            <select name="quality" onchange="onQualityChange(this)">
              <?php foreach ($qualities as $q): ?>
                <option value="<?= htmlspecialchars($q['code']) ?>" data-name="<?= htmlspecialchars($q['name']) ?>" data-color="<?= htmlspecialchars($q['color']) ?>"><?= htmlspecialchars($q['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <input type="hidden" name="quality_name" value="标准">
          <input type="hidden" name="quality_color" value="#64748b">
          <label>获取方式
            <select name="obtain_method" id="bbObtainMethod"><option value="free">免费领取</option><option value="shop">商城购买</option><option value="task">任务解锁</option><option value="level">等级解锁</option><option value="grant" selected>管理员授予</option></select>
          </label>
          <label id="bbCurrencyField" style="display:none">购买货币<select name="price_currency" id="bbPriceCurrency"><option value="">（不需要）</option><?php foreach ($currencies as $c): ?><option value="<?= htmlspecialchars($c['code']) ?>"><?= htmlspecialchars($c['name'] . '（' . $c['code'] . '）') ?></option><?php endforeach; ?></select></label>
          <label id="bbPriceField" style="display:none">价格<input type="number" name="price_amount" id="bbPriceAmount" min="0" step="0.01" value="0"></label>
          <label id="bbTaskField" style="display:none">关联任务<select id="bbRuleTask"><option value="0">— 选择任务 —</option><?php foreach ($tasks as $t): ?><option value="<?= (int)$t['id'] ?>">#<?= (int)$t['id'] ?> <?= htmlspecialchars((string)$t['title']) ?><?= ($t['status'] ?? '') !== 'active' ? '（停用）' : '' ?></option><?php endforeach; ?></select></label>
          <label id="bbLevelField" style="display:none">达到等级<select id="bbRuleLevel"><option value="0">— 选择等级 —</option><?php foreach ($levels as $lv): ?><option value="<?= (int)$lv['level'] ?>">Lv.<?= (int)$lv['level'] ?> <?= htmlspecialchars((string)$lv['name']) ?></option><?php endforeach; ?></select></label>
          <input type="hidden" name="grant_type" value="manual">
          <input type="hidden" name="rule_type" value="manual">
          <input type="hidden" name="rule_value" value="0">
          <label>排序 <input name="sort_order" type="number" value="0"></label>
          <label>状态
            <select name="status"><option value="active">启用</option><option value="inactive">停用</option></select>
          </label>
        </div>
        <label>描述 <textarea name="description" rows="2" placeholder="气泡特效描述"></textarea></label>
        <div class="bubble-preview-area" id="createPreview" style="margin:16px 0">
          <div style="font-size:13px;color:var(--text-soft,#64748b);margin-bottom:8px">特效预览</div>
          <div id="createPreviewBox" style="width:100%;height:150px;border-radius:18px;border:1px solid var(--line-soft,#e2e8f0);overflow:visible;position:relative;background:linear-gradient(135deg,#fff7fb 0%,#eff6ff 100%);display:flex;align-items:center;justify-content:center"></div>
        </div>
        <button class="btn" type="submit">创建气泡</button>
      </form>
    </div>

    
    <div class="bubble-panel" data-panel="quality">
      <h3>品质配置</h3>
      <div class="bubble-help">配置前台气泡中心的品质筛选，可修改品质名称、颜色、排序和启用状态。</div>
      <form method="post" action="/admin.php?path=bubbles" class="bubble-form admin-form-grid" style="margin-bottom:20px">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="save_quality">
        <div class="admin-form-grid">
          <label>品质标识 <input name="code" required placeholder="如 legend"></label>
          <label>品质名称 <input name="name" required placeholder="如 传奇"></label>
          <label>颜色 <input name="color" type="color" value="#64748b"></label>
          <label>排序 <input name="sort_order" type="number" value="0"></label>
          <label>状态 <select name="status"><option value="active">启用</option><option value="inactive">停用</option></select></label>
        </div>
        <button class="btn" type="submit">添加品质</button>
      </form>
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>标识</th><th>名称</th><th>颜色</th><th>排序</th><th>气泡数</th><th>状态</th><th>操作</th></tr></thead>
          <tbody>
          <?php foreach ($qualities as $q): ?>
            <tr>
              <td><code class="bubble-code"><?= htmlspecialchars($q['code']) ?></code></td>
              <td><?= htmlspecialchars($q['name']) ?></td>
              <td><span style="display:inline-block;width:20px;height:20px;border-radius:4px;background:<?= htmlspecialchars($q['color']) ?>;vertical-align:middle"></span> <?= htmlspecialchars($q['color']) ?></td>
              <td><?= (int)$q['sort_order'] ?></td>
              <td><?= (int)($q['bubble_count'] ?? 0) ?></td>
              <td><span class="status-<?= $q['status']==='active'?'approved':'rejected' ?>"><?= $q['status']==='active'?'启用':'停用' ?></span></td>
              <td>
                <button class="btn btn-light" onclick="editQuality('<?= htmlspecialchars($q['code']) ?>')">编辑</button>
                <form method="post" action="/admin.php?path=bubbles" style="display:inline" onsubmit="return confirm('确认删除？')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="_action" value="delete_quality">
                  <input type="hidden" name="code" value="<?= htmlspecialchars($q['code']) ?>">
                  <button class="btn btn-light admin-danger" type="submit">删除</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    
    <div class="bubble-panel" data-panel="grant">
      <h3>授予用户</h3>
      <div class="bubble-help">授予成功后气泡进入用户仓库，是否装备由用户在装饰中心手动选择。</div>
      <form method="post" action="/admin.php?path=bubbles" class="bubble-form admin-form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="grant_bubble">
        <div class="admin-form-grid">
          <label>用户
            <div class="bubble-search-select" id="grantUserSelect">
              <input type="hidden" name="user_id" id="grantUserId">
              <input class="bubble-search-input input" placeholder="搜索用户名/昵称..." id="grantUserSearch">
              <div class="bubble-search-box" id="grantUserDropdown"></div>
            </div>
          </label>
          <label>气泡
            <div class="bubble-search-select" id="grantBubbleSelect">
              <input type="hidden" name="bubble_id" id="grantBubbleId">
              <input class="bubble-search-input input" placeholder="搜索气泡..." id="grantBubbleSearch">
              <div class="bubble-search-box" id="grantBubbleDropdown"></div>
            </div>
          </label>
        </div>
        <label>备注 <input name="note" placeholder="授予原因"></label>
        <button class="btn" type="submit">授予</button>
      </form>
    </div>

    
    <div class="bubble-panel" data-panel="history">
      <h3>授予记录</h3>
      <div class="bubble-help">查看已授予气泡记录，可对误授予的气泡执行收回操作。</div>
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>ID</th><th>用户</th><th>气泡</th><th>备注</th><th>来源</th><th>时间</th><th>操作</th></tr></thead>
          <tbody>
          <?php if (empty($grants)): ?>
            <tr><td colspan="7" class="text-center text-muted">暂无记录</td></tr>
          <?php else: foreach ($grants as $g): ?>
            <tr>
              <td><?= (int)$g['id'] ?></td>
              <td><?= htmlspecialchars($g['nickname'] ?: $g['username']) ?> <small class="text-muted">#<?= htmlspecialchars($g['public_id'] ?? '') ?></small></td>
              <td><?= htmlspecialchars($g['bubble_name']) ?></td>
              <td><?= htmlspecialchars($g['note'] ?? '') ?></td>
              <td><?= htmlspecialchars($g['grant_source']) ?></td>
              <td><?= htmlspecialchars($g['granted_at']) ?></td>
              <td>
                <form method="post" action="/admin.php?path=bubbles" style="display:inline" onsubmit="return confirm('确认收回？')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="_action" value="revoke_bubble">
                  <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                  <button class="btn btn-light admin-danger" type="submit">收回</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
      $decoGuideType = 'bubble';
      $decoGuideTitle = '气泡配置教程';
      $decoGuidePanelClass = 'bubble-panel';
      $decoGuidePanelAttr = 'data-panel';
      require dirname(__DIR__) . '/layouts/deco_guide.php';
    ?>
</div>


<div class="bubble-edit-modal" id="bubbleEditModal" aria-hidden="true">
  <div class="bubble-edit-modal__mask" onclick="closeEditModal()"></div>
  <div class="bubble-edit-modal__card">
    <div class="bubble-edit-modal__head"><strong id="editModalTitle">编辑</strong><button onclick="closeEditModal()">×</button></div>
    <div class="bubble-edit-modal__body">
      
      <form method="post" action="/admin.php?path=bubbles" id="bubbleEditForm" class="bubble-form admin-form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="save_bubble">
        <input type="hidden" name="id" id="edit_bubble_id">
        <div class="admin-form-grid">
          <label>编码 <input name="code" id="edit_bubble_code" required></label>
          <label>名称 <input name="name" id="edit_bubble_name" required></label>
          <input type="hidden" name="effect_type" id="edit_bubble_effect_type" value="">
          <label>特效参数 / CSS <textarea name="effect_params" id="edit_bubble_effect_params" rows="5"></textarea></label>
          <label>品质
            <select name="quality" id="edit_bubble_quality" onchange="onQualityChange(this)">
              <?php foreach ($qualities as $q): ?>
                <option value="<?= htmlspecialchars($q['code']) ?>" data-name="<?= htmlspecialchars($q['name']) ?>" data-color="<?= htmlspecialchars($q['color']) ?>"><?= htmlspecialchars($q['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <input type="hidden" name="quality_name" id="edit_bubble_quality_name">
          <input type="hidden" name="quality_color" id="edit_bubble_quality_color">
          <label>获取方式 <select name="obtain_method" id="edit_bubble_obtain_method"><option value="free">免费领取</option><option value="shop">商城购买</option><option value="task">任务解锁</option><option value="level">等级解锁</option><option value="grant">管理员授予</option></select></label>
          <label id="editBbCurrencyField" style="display:none">购买货币<select name="price_currency" id="edit_bubble_price_currency"><option value="">（不需要）</option><?php foreach ($currencies as $c): ?><option value="<?= htmlspecialchars($c['code']) ?>"><?= htmlspecialchars($c['name'] . '（' . $c['code'] . '）') ?></option><?php endforeach; ?></select></label>
          <label id="editBbPriceField" style="display:none">价格<input type="number" name="price_amount" id="edit_bubble_price_amount" min="0" step="0.01" value="0"></label>
          <label id="editBbTaskField" style="display:none">关联任务<select id="edit_bubble_rule_task"><option value="0">— 选择任务 —</option><?php foreach ($tasks as $t): ?><option value="<?= (int)$t['id'] ?>">#<?= (int)$t['id'] ?> <?= htmlspecialchars((string)$t['title']) ?><?= ($t['status'] ?? '') !== 'active' ? '（停用）' : '' ?></option><?php endforeach; ?></select></label>
          <label id="editBbLevelField" style="display:none">达到等级<select id="edit_bubble_rule_level"><option value="0">— 选择等级 —</option><?php foreach ($levels as $lv): ?><option value="<?= (int)$lv['level'] ?>">Lv.<?= (int)$lv['level'] ?> <?= htmlspecialchars((string)$lv['name']) ?></option><?php endforeach; ?></select></label>
          <input type="hidden" name="grant_type" value="manual"><input type="hidden" name="rule_type" value="manual"><input type="hidden" name="rule_value" value="0">
          <label>排序 <input name="sort_order" id="edit_bubble_sort_order" type="number"></label>
          <label>状态 <select name="status" id="edit_bubble_status"><option value="active">启用</option><option value="inactive">停用</option></select></label>
        </div>
        <label>描述 <textarea name="description" id="edit_bubble_description" rows="2"></textarea></label>
        <div style="margin:16px 0">
          <div style="font-size:13px;color:var(--text-soft,#64748b);margin-bottom:8px">特效预览</div>
          <div id="editPreviewBox" style="width:100%;height:150px;border-radius:18px;border:1px solid var(--line-soft,#e2e8f0);overflow:visible;position:relative;background:linear-gradient(135deg,#fff7fb 0%,#eff6ff 100%);display:flex;align-items:center;justify-content:center"></div>
        </div>
        <button class="btn" type="submit">保存</button>
      </form>
      
      <form method="post" action="/admin.php?path=bubbles" id="qualityEditForm" class="bubble-form" hidden>
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="save_quality">
        <input type="hidden" name="code" id="edit_quality_code">
        <div class="admin-form-grid">
          <label>品质名称 <input name="name" id="edit_quality_name" required></label>
          <label>颜色 <input name="color" id="edit_quality_color" type="color"></label>
          <label>排序 <input name="sort_order" id="edit_quality_sort" type="number"></label>
          <label>状态 <select name="status" id="edit_quality_status"><option value="active">启用</option><option value="inactive">停用</option></select></label>
        </div>
        <button class="btn" type="submit">保存</button>
      </form>
    </div>
  </div>
</div>

<script>
var ClayBubbleRows=<?= $bubbleRows ?>;
var ClayBubbleQualities=<?= $qualityRows ?>;
var ClayBubbleUsers=<?= $userRows ?>;
var ClayEffectTypes=<?= $effectTypesJson ?>;
</script>
<script src="/assets/js/bubble-effects.js?v=20260530-v10"></script>
<script>
(function(){
  /* Tab switching */
  var card=document.getElementById('bubbleTabsCard');
  if(!card)return;
  var tabs=card.querySelectorAll('.bubble-tab'),panels=card.querySelectorAll('.bubble-panel');
  var saved=localStorage.getItem('bubbleAdminTab')||'list';
  function switchTab(name){
    tabs.forEach(function(t){t.classList.toggle('is-active',t.dataset.tab===name)});
    panels.forEach(function(p){p.classList.toggle('is-active',p.dataset.panel===name)});
    localStorage.setItem('bubbleAdminTab',name);
  }
  tabs.forEach(function(t){t.addEventListener('click',function(){switchTab(t.dataset.tab)})});
  switchTab(saved);

  function parseParams(raw){try{return JSON.parse(raw||'{}')}catch(e){return {};}}
  function renderBubblePreview(box,et,params,text,mini){
    if(!box)return;
    box.innerHTML='';
    if(!et||!window.BubbleEffects){box.innerHTML='<span class="admin-bubble-preview-empty">未选择</span>';return;}
    var msg=document.createElement('div');
    msg.className='chat-msg mine has-bubble admin-bubble-preview-msg'+(mini?' is-mini':'');
    msg.setAttribute('data-effect',et);
    msg.setAttribute('data-effect-params',JSON.stringify(params||{}));
    msg.innerHTML='<span class="chat-msg-text">'+(text||'气泡预览')+'</span>';
    box.appendChild(msg);
    try{BubbleEffects.applyToChatMsg(msg,et,params||{})}catch(e){box.innerHTML='<span class="admin-bubble-preview-empty">预览失败</span>';}
  }

  /* Init mini previews */
  document.querySelectorAll('.bubble-preview-mini[data-effect]').forEach(function(el){
    renderBubblePreview(el,el.dataset.effect,parseParams(el.dataset.params),'预览',true);
  });

  /* Quality select auto-fill */
  window.onQualityChange=function(sel){
    var opt=sel.options[sel.selectedIndex];
    var form=sel.closest('form');
    var nameInput=form.querySelector('input[name="quality_name"]');
    var colorInput=form.querySelector('input[name="quality_color"]');
    if(nameInput)nameInput.value=opt.dataset.name||'';
    if(colorInput)colorInput.value=opt.dataset.color||'#64748b';
  };

  /* Sync type from effect_params JSON or raw CSS → hidden effect_type field, then preview */
  function inferCssType(raw){
    raw=String(raw||'');
    var m=raw.match(/\.chat-msg\.([a-zA-Z][a-zA-Z0-9_-]*)/);
    if(m)return m[1];
    m=raw.match(/\.([a-zA-Z][a-zA-Z0-9_-]*)\s*\{/);
    return m?m[1]:'';
  }
  function syncTypeFromParams(prefix){
    var paramsEl=document.getElementById(prefix==='create'?'create_effect_params':'edit_bubble_effect_params');
    var etInput=document.getElementById(prefix==='create'?'create_effect_type':'edit_bubble_effect_type');
    if(!paramsEl||!etInput)return;
    var raw=paramsEl.value||'';
    var et='';
    try{var obj=JSON.parse(raw);et=obj.type||'';}catch(e){
      /* Fallback: partial JSON or raw CSS */
      var m=raw.match(/"type"\s*:\s*"([^"]+)"/);
      et=m?m[1]:inferCssType(raw);
    }
    if(et)etInput.value=et;
    updatePreview(prefix);
  }

  /* Live JSON input triggers preview */
  var cp=document.getElementById('create_effect_params');
  if(cp)cp.addEventListener('input',function(){syncTypeFromParams('create')});
  var ep=document.getElementById('edit_bubble_effect_params');
  if(ep)ep.addEventListener('input',function(){syncTypeFromParams('edit')});

  /* Live preview */
  function parseEffectParams(raw,et){
    raw=String(raw||'').trim();
    if(!raw)return {};
    try{return JSON.parse(raw);}catch(e){
      /* If user pasted raw CSS, use it directly. */
      if(raw.indexOf('{')>=0 && raw.indexOf('}')>=0 && raw.indexOf(':')>=0 && raw.indexOf('"type"')<0){
        return {type:et||inferCssType(raw),css:raw};
      }
      /* Partial JSON fallback */
      var params={};
      var cm=raw.match(/"color"\s*:\s*"([^"]+)"/);if(cm)params.color=cm[1];
      var nm=raw.match(/"count"\s*:\s*(\d+)/);if(nm)params.count=parseInt(nm[1],10);
      var sm=raw.match(/"speed"\s*:\s*([0-9.]+)/);if(sm)params.speed=parseFloat(sm[1]);
      var zm=raw.match(/"size"\s*:\s*(\d+)/);if(zm)params.size=parseInt(zm[1],10);
      var cssm=raw.match(/"css"\s*:\s*"((?:[^"\\]|\\.)*)"/);if(cssm)params.css=cssm[1].replace(/\\"/g,'"');
      return params;
    }
  }
  function updatePreview(prefix){
    var boxId=prefix==='create'?'createPreviewBox':'editPreviewBox';
    var box=document.getElementById(boxId);
    if(!box)return;
    var etInput=document.getElementById(prefix==='create'?'create_effect_type':'edit_bubble_effect_type');
    var paramsInput=document.getElementById(prefix==='create'?'create_effect_params':'edit_bubble_effect_params');
    if(!etInput)return;
    var et=etInput.value||'';
    var raw=paramsInput?(paramsInput.value||''):'';
    var params=parseEffectParams(raw,et);
    if(!et && params.type){et=params.type;etInput.value=et;}
    renderBubblePreview(box,et,params,'后台特效预览',false);
  }

  /* Edit bubble */
  window.editBubble=function(id){
    var row=ClayBubbleRows.find(function(r){return parseInt(r.id)===id});
    if(!row)return;
    document.getElementById('editModalTitle').textContent='编辑气泡: '+row.name;
    document.getElementById('bubbleEditForm').hidden=false;
    document.getElementById('qualityEditForm').hidden=true;
    document.getElementById('edit_bubble_id').value=row.id;
    document.getElementById('edit_bubble_code').value=row.code;
    document.getElementById('edit_bubble_name').value=row.name;
    /* Ensure effect_params has type field; sync to hidden effect_type */
    var params=row.effect_params||'{}';
    var et=row.effect_type||'';
    try{var obj=JSON.parse(params);if(!obj.type&&et)obj.type=et;params=JSON.stringify(obj);}catch(e){}
    document.getElementById('edit_bubble_effect_params').value=params;
    document.getElementById('edit_bubble_effect_type').value=obj.type||et||'';
    document.getElementById('edit_bubble_quality').value=row.quality;
    document.getElementById('edit_bubble_quality_name').value=row.quality_name;
    document.getElementById('edit_bubble_quality_color').value=row.quality_color;
    var eom=String(row.obtain_method||'grant');var erv=parseInt(row.price_amount||0,10)||0;
    document.getElementById('edit_bubble_obtain_method').value=eom;
    document.getElementById('edit_bubble_price_currency').value=row.price_currency||'';
    document.getElementById('edit_bubble_price_amount').value=row.price_amount||0;
    document.getElementById('edit_bubble_rule_task').value=(eom==='task')?erv:0;
    document.getElementById('edit_bubble_rule_level').value=(eom==='level')?erv:0;
    editBbSync();
    document.getElementById('edit_bubble_sort_order').value=row.sort_order;
    document.getElementById('edit_bubble_status').value=row.status;
    document.getElementById('edit_bubble_description').value=row.description||'';
    openBubbleModal();
    setTimeout(function(){updatePreview('edit')},100);
  };
  function editBbSync(){var v=document.getElementById('edit_bubble_obtain_method').value;document.getElementById('editBbCurrencyField').style.display=(v==='shop')?'':'none';document.getElementById('editBbPriceField').style.display=(v==='shop')?'':'none';document.getElementById('editBbTaskField').style.display=(v==='task')?'':'none';document.getElementById('editBbLevelField').style.display=(v==='level')?'':'none';}
  (function(){var em=document.getElementById('edit_bubble_obtain_method');if(em)em.addEventListener('change',editBbSync);var et=document.getElementById('edit_bubble_rule_task');var el=document.getElementById('edit_bubble_rule_level');var pa=document.getElementById('edit_bubble_price_amount');if(et&&pa)et.addEventListener('change',function(){if(em.value==='task')pa.value=this.value||0;});if(el&&pa)el.addEventListener('change',function(){if(em.value==='level')pa.value=this.value||0;});})();
  /* Create form obtain method sync */
  (function(){var m=document.getElementById('bbObtainMethod');if(!m)return;function g(id){return document.getElementById(id);}function sync(){var v=m.value;g('bbCurrencyField').style.display=(v==='shop')?'':'none';g('bbPriceField').style.display=(v==='shop')?'':'none';g('bbTaskField').style.display=(v==='task')?'':'none';g('bbLevelField').style.display=(v==='level')?'':'none';if(v==='task')g('bbPriceAmount').value=g('bbRuleTask').value||0;else if(v==='level')g('bbPriceAmount').value=g('bbRuleLevel').value||0;else if(v!=='shop')g('bbPriceAmount').value=0;}m.addEventListener('change',sync);var rt=g('bbRuleTask'),rl=g('bbRuleLevel');if(rt)rt.addEventListener('change',function(){if(m.value==='task')g('bbPriceAmount').value=this.value||0;});if(rl)rl.addEventListener('change',function(){if(m.value==='level')g('bbPriceAmount').value=this.value||0;});sync();})();

  /* Edit quality */
  window.editQuality=function(code){
    var row=ClayBubbleQualities.find(function(r){return r.code===code});
    if(!row)return;
    document.getElementById('editModalTitle').textContent='编辑品质: '+row.name;
    document.getElementById('bubbleEditForm').hidden=true;
    document.getElementById('qualityEditForm').hidden=false;
    document.getElementById('edit_quality_code').value=row.code;
    document.getElementById('edit_quality_name').value=row.name;
    document.getElementById('edit_quality_color').value=row.color;
    document.getElementById('edit_quality_sort').value=row.sort_order;
    document.getElementById('edit_quality_status').value=row.status;
    openBubbleModal();
  };

  window.closeEditModal=function(){var m=document.getElementById('bubbleEditModal');m.classList.remove('is-open');m.setAttribute('aria-hidden','true');document.documentElement.style.overflow='';};
  function openBubbleModal(){var m=document.getElementById('bubbleEditModal');m.classList.add('is-open');m.setAttribute('aria-hidden','false');document.documentElement.style.overflow='hidden';}

  /* Search-select for grant */
  function setupSearchSelect(inputId,dropdownId,hiddenId,items,labelFn){
    var input=document.getElementById(inputId);
    var dd=document.getElementById(dropdownId);
    var hidden=document.getElementById(hiddenId);
    if(!input||!dd||!hidden)return;
    function render(items){
      dd.innerHTML='';
      items.forEach(function(item){
        var div=document.createElement('div');
        div.className='bubble-search-option';
        div.textContent=labelFn(item);
        div.addEventListener('click',function(){
          input.value=labelFn(item);
          hidden.value=item.id;
          dd.hidden=true;
        });
        dd.appendChild(div);
      });
      dd.hidden=items.length===0;
    }
    input.addEventListener('focus',function(){render(items)});
    input.addEventListener('input',function(){
      var q=input.value.toLowerCase();
      render(items.filter(function(i){return labelFn(i).toLowerCase().indexOf(q)>=0}));
    });
    document.addEventListener('click',function(e){
      if(!input.contains(e.target)&&!dd.contains(e.target))dd.hidden=true;
    });
  }
  setupSearchSelect('grantUserSearch','grantUserDropdown','grantUserId',ClayBubbleUsers,function(u){return(u.nickname||u.username)+' #'+u.public_id});
  setupSearchSelect('grantBubbleSearch','grantBubbleDropdown','grantBubbleId',ClayBubbleRows,function(b){return b.name+' ('+b.code+')'});

  /* Create form: init quality defaults */
  var createQualitySel=document.querySelector('.bubble-panel[data-panel="create"] select[name="quality"]');
  if(createQualitySel&&createQualitySel.options.length>0){
    var firstOpt=createQualitySel.options[0];
    var nf=createQualitySel.closest('form');
    var ni=nf.querySelector('input[name="quality_name"]');
    var ci=nf.querySelector('input[name="quality_color"]');
    if(ni)ni.value=firstOpt.dataset.name||'';
    if(ci)ci.value=firstOpt.dataset.color||'#64748b';
  }
})();
</script>

<style>
.bubble-tabs-card{background:#fff;border:1px solid #e2e8f0;border-radius:24px;box-shadow:0 22px 70px rgba(15,23,42,.08);overflow:hidden}.bubble-tabs{display:flex;align-items:center;gap:6px;padding:10px;border-bottom:1px solid #e2e8f0;background:linear-gradient(180deg,#fff,#f8fafc);overflow-x:auto}.bubble-tab{border:0;background:transparent;color:#64748b;padding:10px 14px;border-radius:999px;font-size:13px;font-weight:900;white-space:nowrap;cursor:pointer;transition:.18s ease}.bubble-tab:hover{background:#f1f5f9;color:#0f172a}.bubble-tab.is-active{background:#0f172a;color:#fff;box-shadow:0 10px 24px rgba(15,23,42,.18)}.bubble-panel{display:none;padding:22px}.bubble-panel.is-active{display:block}.bubble-panel h3{margin:0 0 8px;font-size:20px;letter-spacing:-.03em;color:#0f172a}.bubble-help{margin:0 0 18px;color:#64748b;font-size:13px;line-height:1.65}.bubble-form{display:block}.bubble-form.admin-form-grid,.bubble-form .admin-form-grid{display:grid!important;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px!important}.bubble-form label{display:block;margin:0;font-size:12px;font-weight:900;color:#475569}.bubble-form input,.bubble-form select,.bubble-form textarea,.bubble-search-input{width:100%;margin-top:6px;padding:10px 12px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;color:#0f172a;font-size:13px;outline:none;transition:.18s ease}.bubble-form input:focus,.bubble-form select:focus,.bubble-form textarea:focus,.bubble-search-input:focus{border-color:#0f172a;box-shadow:0 0 0 3px rgba(15,23,42,.08)}.bubble-form textarea{resize:vertical}.bubble-preview-mini{box-shadow:inset 0 0 0 1px rgba(255,255,255,.18),0 8px 20px rgba(15,23,42,.08)}.bubble-preview-area{margin:18px 0;padding:14px;border:1px dashed #cbd5e1;border-radius:18px;background:#f8fafc}.bubble-pill{display:inline-flex;align-items:center;gap:6px;max-width:220px;min-height:26px;padding:4px 9px;border-radius:999px;font-size:12px;font-weight:900;line-height:1.15;vertical-align:middle;overflow:hidden}.bubble-code{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:3px 7px;color:#334155}.bubble-edit-modal{position:fixed;inset:0;z-index:1600;display:none}.bubble-edit-modal.is-open{display:block}.bubble-edit-modal__mask{position:absolute;inset:0;background:rgba(15,23,42,.46);backdrop-filter:blur(6px)}.bubble-edit-modal__card{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:min(780px,calc(100vw - 28px));max-height:calc(100vh - 42px);overflow:auto;background:#fff;border:1px solid #e2e8f0;border-radius:24px;box-shadow:0 30px 90px rgba(15,23,42,.28);padding:22px}.bubble-edit-modal__head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px}.bubble-edit-modal__head strong{margin:0;font-size:20px;letter-spacing:-.03em;color:#0f172a}.bubble-edit-modal__head button{border:0!important;background:#f1f5f9!important;color:#475569!important;width:34px;height:34px;border-radius:999px;display:grid;place-items:center;cursor:pointer;padding:0;font-size:20px;line-height:1}.bubble-edit-modal__body{padding:0}.bubble-search-select{position:relative}.bubble-search-box{position:absolute;left:0;right:0;top:calc(100% + 8px);z-index:40;background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 18px 44px rgba(15,23,42,.16);max-height:260px;overflow:auto;padding:6px}.bubble-search-box[hidden]{display:none!important}.bubble-search-option{display:block;width:100%;text-align:left;border:0;background:transparent;border-radius:12px;padding:10px 12px;cursor:pointer;color:#0f172a;font-size:13px}.bubble-search-option:hover{background:#f1f5f9}.table td{vertical-align:middle}.admin-empty,.text-center{text-align:center}.text-muted{color:#94a3b8}.full-row{grid-column:1/-1}.admin-danger{color:#dc2626!important}.admin-spacer-top{margin-top:18px}@media(max-width:760px){.bubble-panel{padding:16px}.bubble-tabs{padding:8px}.bubble-form.admin-form-grid,.bubble-form .admin-form-grid{grid-template-columns:1fr!important}}

.admin-bubble-preview-empty{font-size:12px;font-weight:900;color:#94a3b8}.admin-bubble-preview-msg{max-width:72%!important;white-space:normal!important;word-break:break-word!important}.admin-bubble-preview-msg.is-mini{font-size:11px!important;line-height:1.25!important;padding:8px 12px!important;transform:scale(.78);transform-origin:center center}.bubble-preview-mini .cute-deco.face img{width:22px!important;height:22px!important}.bubble-preview-mini .cute-deco.ear-l img,.bubble-preview-mini .cute-deco.ear-r img{width:12px!important;height:22px!important}.bubble-preview-mini .cute-deco.cloud img{width:25px!important;height:17px!important}.bubble-preview-mini .cute-deco.star img,.bubble-preview-mini .cute-deco.heart img{width:10px!important;height:10px!important}
</style>

<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
