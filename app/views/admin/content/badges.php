<?php
$pageTitle = '勋章管理';
require dirname(__DIR__) . '/layouts/main.php';
$badges = $badges ?? [];
$grants = $grants ?? [];
$users = $users ?? [];
$currencies = $currencies ?? [];
$tasks = $tasks ?? [];
$levels = $levels ?? [];
$error = $error ?? '';
$message = $message ?? '';
$obtainLabels = ['free'=>'免费领取','shop'=>'商城购买','task'=>'任务解锁','level'=>'等级解锁','grant'=>'管理员授予'];
$curNames = [];
foreach ($currencies as $c) { $curNames[strtoupper((string)($c['code'] ?? ''))] = (string)($c['name'] ?? $c['code'] ?? ''); }
$curLabel = static function ($code) use ($curNames) { $code = strtoupper((string)$code); return $curNames[$code] ?? $code; };
function badge_icon_html(array $b, string $class = 'badge-icon-img'): string {
    $icon = (string)($b['icon'] ?? '');
    if ($icon !== '' && (str_starts_with($icon, '/uploads/') || str_starts_with($icon, '/assets/'))) {
        return '<img class="' . htmlspecialchars($class, ENT_QUOTES) . '" src="' . htmlspecialchars($icon, ENT_QUOTES) . '" alt="">';
    }
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:18px;height:18px;"><circle cx="12" cy="8" r="4"></circle><path d="M8.5 12.5 7 21l5-3 5 3-1.5-8.5"></path></svg>';
}
?>
<style>
.badge-pill{display:inline-flex;align-items:center;gap:6px;max-width:220px;min-height:26px;padding:4px 9px;border-radius:999px;font-size:12px;font-weight:900;line-height:1.15;vertical-align:middle;overflow:hidden}.badge-pill .badge-icon-img,.badge-current-icon .badge-icon-img{width:20px!important;height:20px!important;max-width:20px!important;max-height:20px!important;object-fit:contain;display:inline-block;flex:0 0 20px;border-radius:50%}.badge-current-icon{display:inline-flex;align-items:center;gap:6px;min-height:28px}.badge-tabs-card .table td img.badge-icon-img{width:20px!important;height:20px!important;max-width:20px!important;max-height:20px!important;object-fit:contain}.badge-edit-modal{position:fixed;inset:0;z-index:1600;display:none}.badge-edit-modal.is-open{display:block}.badge-edit-modal__mask{position:absolute;inset:0;background:rgba(15,23,42,.46);backdrop-filter:blur(6px)}.badge-edit-modal__card{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:min(760px,calc(100vw - 28px));max-height:calc(100vh - 42px);overflow:auto;background:#fff;border:1px solid #e2e8f0;border-radius:24px;box-shadow:0 30px 90px rgba(15,23,42,.28);padding:22px}.badge-edit-modal__head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px}.badge-edit-modal__head h3{margin:0;font-size:20px;letter-spacing:-.03em}.badge-edit-modal__head p{margin:4px 0 0;color:#64748b;font-size:13px}.badge-edit-modal__close{border:0!important;background:#f1f5f9!important;color:#475569!important;width:34px;height:34px;border-radius:999px;display:grid;place-items:center;cursor:pointer;padding:0}.badge-edit-modal__close svg{width:16px;height:16px}.badge-modal-delete{margin-top:12px;padding-top:12px;border-top:1px dashed #e2e8f0}.badge-modal-form{display:none}.badge-modal-form.is-active{display:block}.badge-grant-form{display:grid!important;grid-template-columns:minmax(280px,1.1fr) minmax(280px,1.1fr) minmax(220px,.8fr) auto!important;gap:18px!important;align-items:end!important}.badge-search-select{position:relative;display:flex;flex-direction:column;gap:9px;min-width:0}.badge-search-label{font-size:12px;font-weight:950;color:#334155;letter-spacing:.02em}.badge-search-wrap{position:relative}.badge-search-wrap .input{height:46px;border-radius:16px!important;padding-left:42px!important;border:1px solid #dbe4f0!important;background:linear-gradient(180deg,#fff,#f8fafc)!important;box-shadow:0 1px 2px rgba(15,23,42,.04)!important}.badge-search-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:#64748b;pointer-events:none}.badge-search-selected{min-height:36px;display:flex;align-items:center;gap:8px;padding:8px 11px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc;color:#64748b;font-size:12px}.badge-search-selected.is-filled{background:#eef6ff;border-color:#bfdbfe;color:#1d4ed8;font-weight:900}.badge-search-box{display:none;margin-top:8px;max-height:260px;overflow:auto;border:1px solid #dbe4f0;border-radius:18px;background:#fff;box-shadow:0 18px 45px rgba(15,23,42,.14);padding:7px}.badge-search-select.is-open .badge-search-box{display:grid;gap:5px}.badge-search-option{width:100%;border:0;background:#fff;text-align:left;border-radius:13px;padding:11px 12px;cursor:pointer;color:#0f172a;display:grid;gap:4px}.badge-search-option:hover,.badge-search-option.is-active{background:linear-gradient(135deg,#eff6ff,#f8fafc)}.badge-search-option strong{font-size:13px;font-weight:950}.badge-search-option span{font-size:12px;color:#64748b}.badge-search-empty{display:none;padding:12px;color:#94a3b8;font-size:13px}.badge-search-box.is-empty .badge-search-empty{display:block}@media(max-width:1100px){.badge-grant-form{grid-template-columns:1fr 1fr!important}.badge-grant-form .btn{width:max-content}}@media(max-width:760px){.badge-grant-form{grid-template-columns:1fr!important}.badge-grant-form .btn{width:100%}}
</style>
<div class="page-header"><div><div class="page-title">勋章管理</div><div class="admin-muted admin-mt-4">完整勋章体系：上传图标、默认手动勋章、手动发放、自动规则预留、获得通知、前台佩戴展示。</div></div><a class="btn btn-light" href="/index.php?path=medals" target="_blank">查看前台</a></div>
<?php if ($error): ?><div class="card admin-alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($message): ?><div class="card admin-alert ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<div class="badge-tabs-card">
  <nav class="badge-tabs" role="tablist" aria-label="勋章管理">
    <button class="badge-tab is-active" type="button" data-badge-tab="create">新增勋章</button>
    <button class="badge-tab" type="button" data-badge-tab="quality">品质配置 <?= count($qualities ?? []) ?></button>
    <button class="badge-tab" type="button" data-badge-tab="grant">授予用户</button>
    <button class="badge-tab" type="button" data-badge-tab="list">勋章列表 <?= count($badges) ?></button>
    <button class="badge-tab" type="button" data-badge-tab="grants">授予记录 <?= count($grants) ?></button>
    <button class="badge-tab" type="button" data-badge-tab="guide">教程</button>
  </nav>
  <section class="badge-panel is-active" data-badge-panel="create">
    <h3>新增勋章</h3>
    <div class="badge-help">这里可以上传或填写勋章图标；手动勋章由管理员发放，自动勋章按规则检测。</div>
    <form method="post" action="/admin.php?path=badges" class="admin-form-grid" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="_action" value="save_badge">
      <input class="input" name="name" placeholder="勋章名称" required>
      <input class="input" name="code" placeholder="唯一标识，可留空自动生成">
      <label class="badge-upload"><span class="admin-muted-strong">上传图标</span><input class="input" name="icon_file" type="file" accept="image/*"></label>
      <input class="input" type="color" name="color" value="#f59e0b" title="颜色">
      <select class="select" name="category"><option value="manual">手动荣誉</option><option value="auto">自动达成</option><option value="event">活动限定</option></select>
      <select class="select" name="level"><?php foreach(($qualities ?? []) as $q): ?><option value="<?= htmlspecialchars((string)$q['code']) ?>"><?= htmlspecialchars((string)$q['name']) ?></option><?php endforeach; ?></select>
      <select class="select" name="obtain_method" id="bdObtainMethod"><option value="free">免费领取</option><option value="shop">商城购买</option><option value="task">任务解锁</option><option value="level">等级解锁</option><option value="grant" selected>管理员授予</option></select>
      <select class="select" name="price_currency" id="bdPriceCurrency" style="display:none"><option value="">（购买货币）</option><?php foreach($currencies as $c): ?><option value="<?= htmlspecialchars((string)$c['code']) ?>"><?= htmlspecialchars((string)$c['name'] . '（' . (string)$c['code'] . '）') ?></option><?php endforeach; ?></select>
      <input class="input" type="number" name="price_amount" id="bdPriceAmount" min="0" step="0.01" value="0" placeholder="价格" style="display:none">
      <select class="select" id="bdRuleTask" style="display:none"><option value="0">— 选择任务 —</option><?php foreach($tasks as $t): ?><option value="<?= (int)$t['id'] ?>">#<?= (int)$t['id'] ?> <?= htmlspecialchars((string)$t['title']) ?><?= ($t['status'] ?? '') !== 'active' ? '（停用）' : '' ?></option><?php endforeach; ?></select>
      <select class="select" id="bdRuleLevel" style="display:none"><option value="0">— 选择等级 —</option><?php foreach($levels as $lv): ?><option value="<?= (int)$lv['level'] ?>">Lv.<?= (int)$lv['level'] ?> <?= htmlspecialchars((string)$lv['name']) ?></option><?php endforeach; ?></select>
      <input type="hidden" name="grant_type" value="manual"><input type="hidden" name="rule_type" value="manual"><input type="hidden" name="rule_value" value="0">
      <input class="input" name="sort_order" type="number" value="0" placeholder="排序">
      <select class="select" name="status"><option value="active">启用</option><option value="inactive">停用</option></select>
      <div class="full-row"><input class="input" name="description" placeholder="勋章说明"></div>
      <div class="full-row"><button class="btn">保存勋章</button></div>
    </form>
    <script>
    (function(){
      var m=document.getElementById('bdObtainMethod');if(!m)return;
      function g(id){return document.getElementById(id);}
      function sync(){
        var v=m.value;
        g('bdPriceCurrency').style.display=(v==='shop')?'':'none';
        g('bdPriceAmount').style.display=(v==='shop')?'':'none';
        g('bdRuleTask').style.display=(v==='task')?'':'none';
        g('bdRuleLevel').style.display=(v==='level')?'':'none';
        if(v==='task')g('bdPriceAmount').value=g('bdRuleTask').value||0;
        else if(v==='level')g('bdPriceAmount').value=g('bdRuleLevel').value||0;
        else if(v!=='shop')g('bdPriceAmount').value=0;
      }
      m.addEventListener('change',sync);
      g('bdRuleTask').addEventListener('change',function(){if(m.value==='task')g('bdPriceAmount').value=this.value||0;});
      g('bdRuleLevel').addEventListener('change',function(){if(m.value==='level')g('bdPriceAmount').value=this.value||0;});
      sync();
    })();
    </script>
  </section>
  <section class="badge-panel" data-badge-panel="quality">
    <h3>品质配置</h3>
    <div class="badge-help">这里配置前台“全部勋章”下方的品质筛选，可修改品质名称、颜色、排序和启用状态。品质标识用于绑定勋章，已有勋章使用中的品质不可删除。</div>
    <form method="post" action="/admin.php?path=badges" class="admin-form-grid">
      <?= csrf_field() ?><input type="hidden" name="_action" value="save_quality">
      <input class="input" name="code" placeholder="品质标识，如 mythic" required>
      <input class="input" name="name" placeholder="品质名称，如 神话" required>
      <input class="input" type="color" name="color" value="#2563eb" title="品质颜色">
      <input class="input" name="sort_order" type="number" value="50" placeholder="排序">
      <select class="select" name="status"><option value="active">启用</option><option value="inactive">停用</option></select>
      <button class="btn">保存品质</button>
    </form>
    <div class="table-responsive admin-spacer-top"><table class="table"><thead><tr><th>品质</th><th>标识</th><th>排序</th><th>勋章数</th><th>状态</th><th>操作</th></tr></thead><tbody>
    <?php foreach(($qualities ?? []) as $q): ?><tr><td><span class="badge-pill" style="background:<?= htmlspecialchars((string)$q['color']) ?>20;color:<?= htmlspecialchars((string)$q['color']) ?>;"><?= htmlspecialchars((string)$q['name']) ?></span></td><td><?= htmlspecialchars((string)$q['code']) ?></td><td><?= (int)$q['sort_order'] ?></td><td><?= (int)($q['badge_count'] ?? 0) ?></td><td><?= ($q['status'] ?? '')==='active'?'启用':'停用' ?></td><td><button class="btn btn-light" type="button" data-badge-edit-open="quality" data-quality-code="<?= htmlspecialchars((string)$q['code'], ENT_QUOTES) ?>">编辑</button></td></tr><?php endforeach; ?>
    <?php if(empty($qualities)): ?><tr><td colspan="6" class="admin-empty">暂无品质配置</td></tr><?php endif; ?></tbody></table></div>
  </section>
  <section class="badge-panel" data-badge-panel="grant">
    <h3>授予用户</h3>
    <div class="badge-help">授予成功后会写入系统消息，用户可在消息中心看到“获得新勋章”通知。</div>
    <form method="post" action="/admin.php?path=badges" class="admin-form-grid badge-grant-form" id="badgeGrantForm">
      <?= csrf_field() ?><input type="hidden" name="_action" value="grant_badge">
      <div class="badge-search-select" data-badge-search="user">
        <span class="badge-search-label">搜索用户</span>
        <div class="badge-search-wrap"><svg class="badge-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg><input class="input" type="search" data-badge-search-input placeholder="输入用户名、昵称或 CY 编号" autocomplete="off" required></div>
        <input type="hidden" name="user_id" data-badge-search-value required>
        <div class="badge-search-selected" data-badge-search-selected>尚未选择用户</div>
        <div class="badge-search-box" data-badge-search-box><div class="badge-search-empty">没有匹配的用户</div></div>
      </div>
      <div class="badge-search-select" data-badge-search="badge">
        <span class="badge-search-label">搜索勋章</span>
        <div class="badge-search-wrap"><svg class="badge-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg><input class="input" type="search" data-badge-search-input placeholder="输入勋章名称、标识或品质" autocomplete="off" required></div>
        <input type="hidden" name="badge_id" data-badge-search-value required>
        <div class="badge-search-selected" data-badge-search-selected>尚未选择勋章</div>
        <div class="badge-search-box" data-badge-search-box><div class="badge-search-empty">没有匹配的勋章</div></div>
      </div>
      <input class="input" name="note" placeholder="授予备注">
      <button class="btn">确认授予</button>
    </form>
    <form method="post" action="/admin.php?path=badges" class="admin-form-grid admin-spacer-top">
      <?= csrf_field() ?><input type="hidden" name="_action" value="check_auto">
      <select class="select" name="user_id" required><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars(($u['nickname'] ?: $u['username']) . (!empty($u['public_id']) ? ' · ' . $u['public_id'] : '')) ?></option><?php endforeach; ?></select>
      <button class="btn btn-light">检查自动勋章</button>
    </form>
  </section>
  <section class="badge-panel" data-badge-panel="list">
    <h3>勋章列表</h3>
    <div class="table-responsive"><table class="table"><thead><tr><th>ID</th><th>勋章</th><th>类型</th><th>规则</th><th>获得</th><th>状态</th><th>操作</th></tr></thead><tbody>
    <?php foreach($badges as $b): ?><tr><td><?= (int)$b['id'] ?></td><td><span class="badge-pill" style="background:<?= htmlspecialchars((string)$b['color']) ?>20;color:<?= htmlspecialchars((string)$b['color']) ?>;"><?= badge_icon_html($b) ?> <?= htmlspecialchars((string)$b['name']) ?></span><div class="admin-muted admin-mt-4"><?= htmlspecialchars((string)($b['description'] ?? '')) ?></div></td><td><?= (($b['grant_type'] ?? '')==='auto')?'自动':'手动' ?> · <?= htmlspecialchars((string)($b['level_name'] ?? $b['level'])) ?></td><td><?= htmlspecialchars((string)($obtainLabels[$b['obtain_method'] ?? 'grant'] ?? '管理员授予')) ?><?php if (($b['obtain_method'] ?? '') === 'shop' && !empty($b['price_currency'])): ?><br><small class="admin-muted"><?= htmlspecialchars(rtrim(rtrim(number_format((float)($b['price_amount'] ?? 0),6,'.',''),'0'),'.') . ' ' . $curLabel((string)$b['price_currency'])) ?></small><?php endif; ?></td><td><?= (int)($b['grant_count'] ?? 0) ?></td><td><?= $b['status']==='active'?'启用':'停用' ?></td><td><button class="btn btn-light" type="button" data-badge-edit-open="badge" data-badge-id="<?= (int)$b['id'] ?>">编辑</button></td></tr><?php endforeach; ?>
    <?php if(!$badges): ?><tr><td colspan="7" class="admin-empty">暂无勋章</td></tr><?php endif; ?></tbody></table></div>
  </section>
  <section class="badge-panel" data-badge-panel="grants">
    <h3>授予记录</h3>
    <div class="table-responsive"><table class="table"><thead><tr><th>用户</th><th>勋章</th><th>备注</th><th>佩戴</th><th>时间</th><th>操作</th></tr></thead><tbody><?php foreach($grants as $g): ?><tr><td><?= htmlspecialchars(($g['nickname'] ?: $g['username'] ?: ('用户#'.(int)$g['user_id'])) . (!empty($g['public_id']) ? ' · ' . $g['public_id'] : '')) ?></td><td><?= badge_icon_html(['icon'=>$g['icon'] ?? '']) ?> <?= htmlspecialchars((string)($g['badge_name'] ?? '勋章')) ?></td><td><?= htmlspecialchars((string)$g['note']) ?></td><td><?= !empty($g['is_equipped'])?'是':'否' ?></td><td><?= htmlspecialchars((string)$g['granted_at']) ?></td><td><form method="post" action="/admin.php?path=badges" onsubmit="return confirm('确认收回该勋章？')"><?= csrf_field() ?><input type="hidden" name="_action" value="revoke_badge"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="btn btn-light admin-danger">收回</button></form></td></tr><?php endforeach; ?><?php if(!$grants): ?><tr><td colspan="6" class="admin-empty">暂无授予记录</td></tr><?php endif; ?></tbody></table></div>
  </section>
  <?php
    $decoGuideType = 'badge';
    $decoGuideTitle = '勋章配置教程';
    $decoGuidePanelClass = 'badge-panel';
    $decoGuidePanelAttr = 'data-badge-panel';
    require dirname(__DIR__) . '/layouts/deco_guide.php';
  ?>
</div>
<div class="badge-edit-modal" id="badgeEditModal" aria-hidden="true">
  <div class="badge-edit-modal__mask" data-badge-edit-close="1"></div>
  <div class="badge-edit-modal__card" role="dialog" aria-modal="true" aria-labelledby="badgeEditModalTitle">
    <div class="badge-edit-modal__head"><div><h3 id="badgeEditModalTitle">编辑</h3><p id="badgeEditModalDesc"></p></div><button class="badge-edit-modal__close" type="button" data-badge-edit-close="1" aria-label="关闭"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>
    <div class="badge-modal-form" data-badge-modal-form="quality">
      <form method="post" action="/admin.php?path=badges" class="admin-form-grid" data-no-ajax>
        <?= csrf_field() ?><input type="hidden" name="_action" value="save_quality">
        <input class="input" name="code" data-field="code" readonly>
        <input class="input" name="name" data-field="name" required>
        <input class="input" type="color" name="color" data-field="color" value="#2563eb">
        <input class="input" type="number" name="sort_order" data-field="sort_order" value="50">
        <select class="select" name="status" data-field="status"><option value="active">启用</option><option value="inactive">停用</option></select>
        <button class="btn" type="submit">保存品质</button>
      </form>
      <form method="post" action="/admin.php?path=badges" class="badge-modal-delete" data-no-ajax onsubmit="return confirm('确认删除该品质？')">
        <?= csrf_field() ?><input type="hidden" name="_action" value="delete_quality"><input type="hidden" name="code" data-field="delete_code">
        <button class="btn btn-light admin-danger" type="submit">删除品质</button>
      </form>
    </div>
    <div class="badge-modal-form" data-badge-modal-form="badge">
      <form method="post" action="/admin.php?path=badges" class="admin-form-grid" enctype="multipart/form-data" data-no-ajax>
        <?= csrf_field() ?><input type="hidden" name="_action" value="save_badge"><input type="hidden" name="id" data-field="id"><input type="hidden" name="icon_existing" data-field="icon_existing">
        <input class="input" name="name" data-field="name" required>
        <input class="input" name="code" data-field="code">
        <label class="badge-upload"><span class="badge-current-icon" data-field="icon_preview">当前：无</span><input class="input" name="icon_file" type="file" accept="image/*"></label>
        <input class="input" type="color" name="color" data-field="color" value="#f59e0b">
        <select class="select" name="category" data-field="category"><option value="manual">手动荣誉</option><option value="auto">自动达成</option><option value="event">活动限定</option></select>
        <select class="select" name="level" data-field="level"><?php foreach(($qualities ?? []) as $q): ?><option value="<?= htmlspecialchars((string)$q['code']) ?>"><?= htmlspecialchars((string)$q['name']) ?></option><?php endforeach; ?></select>
        <select class="select" name="obtain_method" data-field="obtain_method" id="bdEditObtainMethod"><option value="free">免费领取</option><option value="shop">商城购买</option><option value="task">任务解锁</option><option value="level">等级解锁</option><option value="grant">管理员授予</option></select>
        <select class="select" name="price_currency" data-field="price_currency" id="bdEditPriceCurrency" style="display:none"><option value="">（购买货币）</option><?php foreach($currencies as $c): ?><option value="<?= htmlspecialchars((string)$c['code']) ?>"><?= htmlspecialchars((string)$c['name'] . '（' . (string)$c['code'] . '）') ?></option><?php endforeach; ?></select>
        <input class="input" type="number" name="price_amount" data-field="price_amount" id="bdEditPriceAmount" min="0" step="0.01" value="0" placeholder="价格" style="display:none">
        <select class="select" id="bdEditRuleTask" style="display:none"><option value="0">— 选择任务 —</option><?php foreach($tasks as $t): ?><option value="<?= (int)$t['id'] ?>">#<?= (int)$t['id'] ?> <?= htmlspecialchars((string)$t['title']) ?><?= ($t['status'] ?? '') !== 'active' ? '（停用）' : '' ?></option><?php endforeach; ?></select>
        <select class="select" id="bdEditRuleLevel" style="display:none"><option value="0">— 选择等级 —</option><?php foreach($levels as $lv): ?><option value="<?= (int)$lv['level'] ?>">Lv.<?= (int)$lv['level'] ?> <?= htmlspecialchars((string)$lv['name']) ?></option><?php endforeach; ?></select>
        <input type="hidden" name="grant_type" value="manual"><input type="hidden" name="rule_type" value="manual"><input type="hidden" name="rule_value" value="0">
        <input class="input" name="sort_order" type="number" data-field="sort_order" value="0">
        <select class="select" name="status" data-field="status"><option value="active">启用</option><option value="inactive">停用</option></select>
        <div class="full-row"><input class="input" name="description" data-field="description"></div>
        <div class="full-row"><button class="btn" type="submit">保存勋章</button></div>
      </form>
      <form method="post" action="/admin.php?path=badges" class="badge-modal-delete" data-no-ajax onsubmit="return confirm('确认删除该勋章？')">
        <?= csrf_field() ?><input type="hidden" name="_action" value="delete_badge"><input type="hidden" name="id" data-field="delete_id">
        <button class="btn btn-light admin-danger" type="submit">删除勋章</button>
      </form>
    </div>
  </div>
</div>
<script>
window.ClayBadgeQualities = <?= json_encode(array_values($qualities ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.ClayBadgeBadges = <?= json_encode(array_values($badges ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.ClayBadgeUsers = <?= json_encode(array_values($users ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
(function(){
  var tabs=document.querySelectorAll('[data-badge-tab]'),panels=document.querySelectorAll('[data-badge-panel]');
  tabs.forEach(function(tab){tab.addEventListener('click',function(e){e.preventDefault();var key=tab.getAttribute('data-badge-tab');tabs.forEach(function(t){t.classList.toggle('is-active',t===tab)});panels.forEach(function(p){p.classList.toggle('is-active',p.getAttribute('data-badge-panel')===key)});try{localStorage.setItem('clay_badge_admin_tab',key)}catch(_){}})});
  try{var saved=localStorage.getItem('clay_badge_admin_tab');var t=saved&&document.querySelector('[data-badge-tab="'+saved+'"]');if(t)t.click()}catch(_){}
  var modal=document.getElementById('badgeEditModal'),title=document.getElementById('badgeEditModalTitle'),desc=document.getElementById('badgeEditModalDesc');
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
  function norm(v){return String(v==null?'':v).toLowerCase();}
  function setupSearchSelect(root, items, toOption){if(!root)return;var input=root.querySelector('[data-badge-search-input]'),hidden=root.querySelector('[data-badge-search-value]'),box=root.querySelector('[data-badge-search-box]'),selected=root.querySelector('[data-badge-search-selected]');function close(){root.classList.remove('is-open');}function pick(item){var opt=toOption(item);hidden.value=String(item.id);input.value=opt.label;if(selected){selected.textContent='已选择：'+opt.label;selected.classList.add('is-filled');}close();}function render(){var q=norm(input.value).trim();hidden.value='';if(selected){selected.textContent=root.getAttribute('data-badge-search')==='user'?'尚未选择用户':'尚未选择勋章';selected.classList.remove('is-filled');}var filtered=(items||[]).filter(function(item){return !q || norm(toOption(item).search).indexOf(q)!==-1;}).slice(0,12);box.querySelectorAll('.badge-search-option').forEach(function(x){x.remove();});box.classList.toggle('is-empty',filtered.length===0);filtered.forEach(function(item){var opt=toOption(item),btn=document.createElement('button');btn.type='button';btn.className='badge-search-option';btn.innerHTML='<strong>'+esc(opt.label)+'</strong><span>'+esc(opt.meta||'')+'</span>';btn.addEventListener('mousedown',function(e){e.preventDefault();pick(item);});box.appendChild(btn);});root.classList.add('is-open');}input.addEventListener('focus',render);input.addEventListener('input',render);input.addEventListener('keydown',function(e){var first=box.querySelector('.badge-search-option');if(e.key==='Enter'&&first){e.preventDefault();first.dispatchEvent(new MouseEvent('mousedown',{bubbles:true,cancelable:true}));}if(e.key==='Escape')close();});document.addEventListener('mousedown',function(e){if(!root.contains(e.target))close();});}
  function setVal(scope,name,value){var el=scope.querySelector('[data-field="'+name+'"]');if(!el)return;if(el.tagName==='SELECT'){el.value=String(value==null?'':value);}else if(el.type==='color'){el.value=/^#[0-9a-fA-F]{6}$/.test(String(value||''))?String(value):'#64748b';}else{el.value=String(value==null?'':value);}}
  function hideForms(){document.querySelectorAll('[data-badge-modal-form]').forEach(function(f){f.classList.remove('is-active');});}
  function closeModal(e){if(e){e.preventDefault();e.stopPropagation();if(e.stopImmediatePropagation)e.stopImmediatePropagation();}hideForms();modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');document.documentElement.style.overflow='';return false;}
  function openQuality(code){var q=(window.ClayBadgeQualities||[]).find(function(x){return String(x.code)===String(code);});if(!q)return false;hideForms();title.textContent='编辑品质';desc.textContent='修改品质名称、颜色、排序和状态';var box=document.querySelector('[data-badge-modal-form="quality"]');setVal(box,'code',q.code);setVal(box,'name',q.name);setVal(box,'color',q.color);setVal(box,'sort_order',q.sort_order);setVal(box,'status',q.status||'active');setVal(box,'delete_code',q.code);box.classList.add('is-active');modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');document.documentElement.style.overflow='hidden';return false;}
  function bdEditSync(){var em=document.getElementById('bdEditObtainMethod');if(!em)return;var v=em.value;var g=function(id){return document.getElementById(id);};if(g('bdEditPriceCurrency'))g('bdEditPriceCurrency').style.display=(v==='shop')?'':'none';if(g('bdEditPriceAmount'))g('bdEditPriceAmount').style.display=(v==='shop')?'':'none';if(g('bdEditRuleTask'))g('bdEditRuleTask').style.display=(v==='task')?'':'none';if(g('bdEditRuleLevel'))g('bdEditRuleLevel').style.display=(v==='level')?'':'none';}
  function openBadge(id){var b=(window.ClayBadgeBadges||[]).find(function(x){return String(x.id)===String(id);});if(!b)return false;hideForms();title.textContent='编辑勋章';desc.textContent='修改勋章基础信息、图标、品质和获取方式';var box=document.querySelector('[data-badge-modal-form="badge"]');['id','name','code','color','category','level','obtain_method','price_currency','price_amount','sort_order','status','description'].forEach(function(k){setVal(box,k,b[k]);});setVal(box,'icon_existing',b.icon||'');setVal(box,'delete_id',b.id);var om=String(b.obtain_method||'grant');var rv=parseInt(b.price_amount||0,10)||0;var rt=document.getElementById('bdEditRuleTask');var rl=document.getElementById('bdEditRuleLevel');if(rt)rt.value=(om==='task')?rv:0;if(rl)rl.value=(om==='level')?rv:0;bdEditSync();var prev=box.querySelector('[data-field="icon_preview"]');prev.innerHTML='当前：'+(b.icon?'<img class="badge-icon-img" src="'+esc(b.icon)+'" alt="">':'无');box.classList.add('is-active');modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');document.documentElement.style.overflow='hidden';return false;}
  (function(){var em=document.getElementById('bdEditObtainMethod');if(em){em.addEventListener('change',bdEditSync);}var et=document.getElementById('bdEditRuleTask');var el=document.getElementById('bdEditRuleLevel');var pa=document.getElementById('bdEditPriceAmount');if(et&&pa)et.addEventListener('change',function(){if(em.value==='task')pa.value=this.value||0;});if(el&&pa)el.addEventListener('change',function(){if(em.value==='level')pa.value=this.value||0;});})();
  setupSearchSelect(document.querySelector('[data-badge-search="user"]'), window.ClayBadgeUsers||[], function(u){var name=u.nickname||u.username||('用户#'+u.id), pid=u.public_id||'', uname=u.username||'';return {label:name+(pid?' · '+pid:''),meta:uname?('用户名 '+uname):'用户 ID '+u.id,search:[name,pid,uname,u.id].join(' ')};});
  setupSearchSelect(document.querySelector('[data-badge-search="badge"]'), window.ClayBadgeBadges||[], function(b){return {label:b.name||('勋章#'+b.id),meta:(b.level_name||b.level||'')+' · '+(b.code||''),search:[b.name,b.code,b.level_name,b.level,b.id].join(' ')};});
  window.clayCloseBadgeModal=closeModal;
  document.addEventListener('click',function(e){var close=e.target.closest('[data-badge-edit-close]');if(close)return closeModal(e);var q=e.target.closest('[data-badge-edit-open="quality"]');if(q){e.preventDefault();e.stopPropagation();if(e.stopImmediatePropagation)e.stopImmediatePropagation();return openQuality(q.getAttribute('data-quality-code'));}var b=e.target.closest('[data-badge-edit-open="badge"]');if(b){e.preventDefault();e.stopPropagation();if(e.stopImmediatePropagation)e.stopImmediatePropagation();return openBadge(b.getAttribute('data-badge-id'));}},true);
  document.addEventListener('keydown',function(e){if(e.key==='Escape'&&modal.classList.contains('is-open'))closeModal(e);});
})();
</script>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
