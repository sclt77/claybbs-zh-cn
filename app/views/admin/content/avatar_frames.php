<?php
$pageTitle = '头像框管理';
require dirname(__DIR__) . '/layouts/main.php';
$frames = $frames ?? [];
$grants = $grants ?? [];
$users = $users ?? [];
$qualities = $qualities ?? [];
$error = $error ?? '';
$message = $message ?? '';
$ruleLabels = ['manual'=>'手动授予','register_days'=>'注册天数','thread_count'=>'主题数量','post_count'=>'回复数量','like_count'=>'获赞数量','level'=>'用户等级'];
$obtainLabels = ['free'=>'免费领取','shop'=>'商城购买','task'=>'任务解锁','level'=>'等级解锁','grant'=>'管理员授予'];
$curNames = [];
foreach (($currencies ?? []) as $c) { $curNames[strtoupper((string)($c['code'] ?? ''))] = (string)($c['name'] ?? $c['code'] ?? ''); }
$curLabel = static function ($code) use ($curNames) { $code = strtoupper((string)$code); return $curNames[$code] ?? $code; };

$afJson = [];
foreach ($frames as $f) {
    $afJson[] = [
        'id'=>(int)$f['id'], 'code'=>(string)$f['code'], 'name'=>(string)$f['name'], 'description'=>(string)($f['description'] ?? ''),
        'image'=>(string)($f['image'] ?? ''), 'quality'=>(string)($f['quality'] ?? 'standard'),
        'obtain_method'=>(string)($f['obtain_method'] ?? 'grant'), 'price_currency'=>(string)($f['price_currency'] ?? ''),
        'price_amount'=>rtrim(rtrim(number_format((float)($f['price_amount'] ?? 0), 6, '.', ''), '0'), '.'),
        'sort_order'=>(int)($f['sort_order'] ?? 0), 'status'=>(string)($f['status'] ?? 'active'),
    ];
}
?>
<style>
.avatar-frame-admin{display:grid;gap:16px}
.af-tabs-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;box-shadow:0 4px 16px rgba(15,23,42,.04)}
.af-tabs{display:flex;gap:4px;flex-wrap:wrap;padding:10px 14px;border-bottom:1px solid #e2e8f0;background:#f8fafc}
.af-tab{border:0;border-radius:999px;background:transparent;color:#64748b;padding:8px 14px;font-weight:900;font-size:13px;cursor:pointer;transition:.15s}
.af-tab:hover{background:#e2e8f0}
.af-tab.is-active{background:#0284c7;color:#fff}
.af-panel{display:none;padding:18px 20px}
.af-panel.is-active{display:block}
.af-panel h3{margin:0 0 14px;font-size:16px;letter-spacing:-.02em}
.af-help{color:#64748b;font-size:13px;line-height:1.6;margin-bottom:16px;padding:10px 14px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0}
.af-preview{width:48px;height:48px;border-radius:12px;background:#f8fafc;display:grid;place-items:center;border:1px solid #e2e8f0}
.af-preview img{width:44px;height:44px;object-fit:contain;border-radius:10px}
.af-img-mini{width:34px;height:34px;object-fit:contain}
.af-edit-modal{position:fixed;inset:0;z-index:1600;display:none}
.af-edit-modal.is-open{display:block}
.af-edit-modal__mask{position:absolute;inset:0;background:rgba(15,23,42,.46);backdrop-filter:blur(6px)}
.af-edit-modal__card{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:min(720px,calc(100vw - 28px));max-height:calc(100vh - 42px);overflow:auto;background:#fff;border:1px solid #e2e8f0;border-radius:24px;box-shadow:0 30px 90px rgba(15,23,42,.28);padding:22px}
.af-edit-modal__head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px}
.af-edit-modal__head h3{margin:0;font-size:20px;letter-spacing:-.03em}
.af-edit-modal__close{border:0!important;background:#f1f5f9!important;color:#475569!important;width:34px;height:34px;border-radius:999px;display:grid;place-items:center;cursor:pointer;padding:0}
.af-edit-modal__close svg{width:16px;height:16px}
.af-search-select{position:relative;display:grid;gap:7px}
.af-search-label{font-size:13px;font-weight:900;color:#475569}
.af-search-wrap{position:relative}
.af-search-wrap .input{padding-left:36px}
.af-search-icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:#94a3b8;pointer-events:none}
.af-search-box{display:none;max-height:220px;overflow:auto;border:1px solid #e2e8f0;border-radius:14px;background:#fff;padding:6px;box-shadow:0 18px 45px rgba(15,23,42,.12);position:absolute;left:0;right:0;top:100%;z-index:5}
.af-search-select.is-open .af-search-box{display:block}
.af-search-option{border:0;background:#f8fafc;border-radius:11px;padding:9px;text-align:left;cursor:pointer;font-weight:850;color:#0f172a;display:block;width:100%}
.af-search-option:hover{background:#e0f2fe}
.af-search-option small{display:block;color:#64748b;margin-top:3px}
.af-search-selected{font-size:12px;color:#64748b;font-weight:800;min-height:20px}
.af-search-selected.is-filled{color:#0284c7}
.af-search-empty{color:#94a3b8;font-size:13px;text-align:center;padding:14px}
.af-modal-delete{margin-top:12px;padding-top:12px;border-top:1px dashed #e2e8f0}
</style>
<div class="page-header"><div><div class="page-title">头像框管理</div><div class="admin-muted admin-mt-4">管理头像框、品质配置、自动授予规则、手动发放和用户装备状态。</div></div><a class="btn btn-light" href="/index.php?path=decoration&tab=frames" target="_blank">查看前台</a></div>
<?php if ($error): ?><div class="card admin-alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($message): ?><div class="card admin-alert ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<div class="af-tabs-card">
  <nav class="af-tabs" role="tablist" aria-label="头像框管理">
    <button class="af-tab is-active" type="button" data-af-tab="frames">头像框 <?= count($frames) ?></button>
    <button class="af-tab" type="button" data-af-tab="create">新增头像框</button>
    <button class="af-tab" type="button" data-af-tab="quality">品质配置 <?= count($qualities) ?></button>
    <button class="af-tab" type="button" data-af-tab="grant">授予用户</button>
    <button class="af-tab" type="button" data-af-tab="grants">授予记录 <?= count($grants) ?></button>
    <button class="af-tab" type="button" data-af-tab="guide">教程</button>
  </nav>

  
  <section class="af-panel is-active" data-af-panel="frames">
    <h3>头像框列表</h3>
    <div class="table-responsive"><table class="table"><thead><tr><th>预览</th><th>名称</th><th>品质</th><th>获取方式</th><th>拥有/装备</th><th>状态</th><th>操作</th></tr></thead><tbody>
    <?php foreach ($frames as $f): ?>
    <tr>
      <td><div class="af-preview"><?php if (!empty($f['image'])): ?><img src="<?= htmlspecialchars((string)$f['image']) ?>" alt=""><?php endif; ?></div></td>
      <td><b><?= htmlspecialchars((string)$f['name']) ?></b><br><small class="admin-muted"><?= htmlspecialchars((string)$f['code']) ?></small></td>
      <td><span style="color:<?= htmlspecialchars((string)($f['quality_color'] ?? '#64748b')) ?>;font-weight:950"><?= htmlspecialchars((string)($f['quality_name'] ?? '标准')) ?></span></td>
      <td><span class="af-obtain-tag"><?= htmlspecialchars((string)($obtainLabels[$f['obtain_method'] ?? 'grant'] ?? '管理员授予')) ?></span><?php if (($f['obtain_method'] ?? '') === 'shop' && !empty($f['price_currency'])): ?><br><small class="admin-muted"><?= htmlspecialchars(rtrim(rtrim(number_format((float)($f['price_amount'] ?? 0),6,'.',''),'0'),'.') . ' ' . $curLabel((string)$f['price_currency'])) ?></small><?php endif; ?></td>
      <td><?= (int)$f['owner_count'] ?> / <?= (int)$f['equipped_count'] ?></td>
      <td><?= ($f['status'] ?? '') === 'active' ? '启用' : '停用' ?></td>
      <td><div class="af-actions"><button class="btn" type="button" data-af-edit="<?= (int)$f['id'] ?>">编辑</button><form method="post" onsubmit="return confirm('确认删除？已有用户获得时会被阻止');"><?= csrf_field() ?><input type="hidden" name="_action" value="delete_frame"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="btn danger" type="submit">删除</button></form></div></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$frames): ?><tr><td colspan="7" class="admin-empty">暂无头像框</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>

  
  <section class="af-panel" data-af-panel="create">
    <h3>新增头像框</h3>
    <div class="af-help">上传头像框图片，配置品质和获取方式。头像框会在用户头像周围显示装饰边框。</div>
    <form method="post" action="/admin.php?path=avatar-frames" class="admin-form-grid" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="_action" value="save_frame">
      <label>标识<input class="input" name="code" required placeholder="founder_gold"></label>
      <label>名称<input class="input" name="name" required></label>
      <label>品质<select class="select" name="quality" id="afCreateQuality"><?php foreach ($qualities as $q): ?><option value="<?= htmlspecialchars($q['code']) ?>" data-name="<?= htmlspecialchars($q['name']) ?>" data-color="<?= htmlspecialchars($q['color']) ?>"><?= htmlspecialchars($q['name']) ?></option><?php endforeach; ?></select></label>
      <input type="hidden" name="quality_name" id="afCreateQualityName" value="标准"><input type="hidden" name="quality_color" id="afCreateQualityColor" value="#64748b">
      <label>获取方式<select class="select" name="obtain_method" id="afObtainMethod"><option value="free">免费领取</option><option value="shop">商城购买</option><option value="task">任务解锁</option><option value="level">等级解锁</option><option value="grant" selected>管理员授予</option></select></label>
      <label id="afCurrencyField" style="display:none">购买货币<select class="select" name="price_currency" id="afPriceCurrency"><option value="">（不需要）</option><?php foreach ($currencies as $c): ?><option value="<?= htmlspecialchars($c['code']) ?>"><?= htmlspecialchars($c['name'] . '（' . $c['code'] . '）') ?></option><?php endforeach; ?></select></label>
      <label id="afPriceField" style="display:none">价格<input class="input" type="number" name="price_amount" id="afPriceAmount" min="0" step="0.01" value="0"></label>
      <label id="afTaskField" style="display:none">关联任务<select class="select" id="afRuleTask"><option value="0">— 选择任务 —</option><?php foreach ($tasks as $t): ?><option value="<?= (int)$t['id'] ?>">#<?= (int)$t['id'] ?> <?= htmlspecialchars((string)$t['title']) ?><?= ($t['status'] ?? '') !== 'active' ? '（停用）' : '' ?></option><?php endforeach; ?></select></label>
      <label id="afLevelField" style="display:none">达到等级<select class="select" id="afRuleLevel"><option value="0">— 选择等级 —</option><?php foreach ($levels as $lv): ?><option value="<?= (int)$lv['level'] ?>">Lv.<?= (int)$lv['level'] ?> <?= htmlspecialchars((string)$lv['name']) ?></option><?php endforeach; ?></select></label>
      <input type="hidden" name="grant_type" value="manual"><input type="hidden" name="rule_type" value="manual"><input type="hidden" name="rule_value" value="0">
      <label>排序<input class="input" type="number" name="sort_order" value="0"></label>
      <label>状态<select class="select" name="status"><option value="active">启用</option><option value="disabled">禁用</option></select></label>
      <label>上传图片<input class="input" type="file" name="image_file" accept="image/png,image/webp,image/jpeg"></label>
      <label>图片路径<input class="input" name="image" placeholder="/assets/images/avatar-frames/demo.png"></label>
      <label style="grid-column:1/-1">介绍<textarea class="input" name="description" rows="2"></textarea></label>
      <button class="btn" type="submit">保存头像框</button>
    </form>
    <script>
    (function(){
      var m=document.getElementById('afObtainMethod');if(!m)return;
      function g(id){return document.getElementById(id);}
      function sync(){
        var v=m.value;
        g('afCurrencyField').style.display=(v==='shop')?'':'none';
        g('afPriceField').style.display=(v==='shop')?'':'none';
        g('afTaskField').style.display=(v==='task')?'':'none';
        g('afLevelField').style.display=(v==='level')?'':'none';
        if(v==='task')g('afPriceAmount').value=g('afRuleTask').value||0;
        else if(v==='level')g('afPriceAmount').value=g('afRuleLevel').value||0;
        else if(v!=='shop')g('afPriceAmount').value=0;
      }
      m.addEventListener('change',sync);
      g('afRuleTask').addEventListener('change',function(){if(m.value==='task')g('afPriceAmount').value=this.value||0;});
      g('afRuleLevel').addEventListener('change',function(){if(m.value==='level')g('afPriceAmount').value=this.value||0;});
      sync();
    })();
    </script>
  </section>

  
  <section class="af-panel" data-af-panel="quality">
    <h3>品质配置</h3>
    <div class="af-help">这里配置前台"全部头像框"下方的品质筛选，可修改品质名称、颜色、排序和启用状态。品质标识用于绑定头像框，已有头像框使用中的品质不可删除。</div>
    <form method="post" action="/admin.php?path=avatar-frames" class="admin-form-grid">
      <?= csrf_field() ?><input type="hidden" name="_action" value="save_quality">
      <label>品质标识<input class="input" name="code" placeholder="如 mythic" required></label>
      <label>品质名称<input class="input" name="name" placeholder="如 神话" required></label>
      <label>品质颜色<input class="input" type="color" name="color" value="#2563eb"></label>
      <label>排序<input class="input" type="number" name="sort_order" value="50"></label>
      <label>状态<select class="select" name="status"><option value="active">启用</option><option value="inactive">停用</option></select></label>
      <button class="btn" type="submit">保存品质</button>
    </form>
    <div class="table-responsive admin-spacer-top"><table class="table"><thead><tr><th>品质</th><th>标识</th><th>排序</th><th>头像框数</th><th>状态</th><th>操作</th></tr></thead><tbody>
    <?php foreach ($qualities as $q): ?>
    <tr>
      <td><span style="display:inline-flex;align-items:center;gap:6px;padding:4px 9px;border-radius:999px;background:<?= htmlspecialchars($q['color']) ?>20;color:<?= htmlspecialchars($q['color']) ?>;font-size:12px;font-weight:900"><?= htmlspecialchars($q['name']) ?></span></td>
      <td><?= htmlspecialchars($q['code']) ?></td>
      <td><?= (int)$q['sort_order'] ?></td>
      <td><?= (int)($q['frame_count'] ?? 0) ?></td>
      <td><?= ($q['status'] ?? '') === 'active' ? '启用' : '停用' ?></td>
      <td><button class="btn btn-light" type="button" data-af-edit-quality="<?= htmlspecialchars($q['code']) ?>">编辑</button></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($qualities)): ?><tr><td colspan="6" class="admin-empty">暂无品质配置</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>

  
  <section class="af-panel" data-af-panel="grant">
    <h3>授予用户</h3>
    <div class="af-help">授予成功后会写入系统消息，用户可在消息中心看到"获得新头像框"通知。</div>
    <form method="post" action="/admin.php?path=avatar-frames" class="admin-form-grid">
      <?= csrf_field() ?><input type="hidden" name="_action" value="grant_frame">
      <div class="af-search-select" data-af-search="user">
        <span class="af-search-label">搜索用户</span>
        <div class="af-search-wrap"><svg class="af-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg><input class="input" type="search" data-af-search-input placeholder="输入用户名、昵称或 CY 编号" autocomplete="off" required></div>
        <input type="hidden" name="user_id" data-af-search-value required>
        <div class="af-search-selected" data-af-search-selected>尚未选择用户</div>
        <div class="af-search-box" data-af-search-box><div class="af-search-empty">没有匹配的用户</div></div>
      </div>
      <div class="af-search-select" data-af-search="frame">
        <span class="af-search-label">搜索头像框</span>
        <div class="af-search-wrap"><svg class="af-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg><input class="input" type="search" data-af-search-input placeholder="输入头像框名称或编码" autocomplete="off" required></div>
        <input type="hidden" name="frame_id" data-af-search-value required>
        <div class="af-search-selected" data-af-search-selected>尚未选择头像框</div>
        <div class="af-search-box" data-af-search-box><div class="af-search-empty">没有匹配的头像框</div></div>
      </div>
      <label>过期时间<input class="input" type="datetime-local" name="expires_at"></label>
      <label>备注<input class="input" name="note" placeholder="授予备注"></label>
      <button class="btn" type="submit">确认授予</button>
    </form>
    <form method="post" action="/admin.php?path=avatar-frames" class="admin-form-grid" style="margin-top:14px">
      <?= csrf_field() ?><input type="hidden" name="_action" value="check_auto">
      <div class="af-search-select" data-af-search="auto-user">
        <span class="af-search-label">搜索用户自动检查</span>
        <div class="af-search-wrap"><svg class="af-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg><input class="input" type="search" data-af-search-input placeholder="输入用户名、昵称或 CY 编号" autocomplete="off" required></div>
        <input type="hidden" name="user_id" data-af-search-value required>
        <div class="af-search-selected" data-af-search-selected>尚未选择用户</div>
        <div class="af-search-box" data-af-search-box><div class="af-search-empty">没有匹配的用户</div></div>
      </div>
      <button class="btn" type="submit">检查自动授予</button>
    </form>
  </section>

  
  <section class="af-panel" data-af-panel="grants">
    <h3>授予记录</h3>
    <div class="table-responsive"><table class="table"><thead><tr><th>用户</th><th>头像框</th><th>来源</th><th>状态</th><th>时间</th><th>操作</th></tr></thead><tbody>
    <?php foreach ($grants as $g): ?>
    <tr>
      <td><?= htmlspecialchars(($g['nickname'] ?? '') ?: ($g['username'] ?? ('用户#'.(int)$g['user_id']))) ?><br><small class="admin-muted"><?= htmlspecialchars((string)($g['public_id'] ?? '')) ?></small></td>
      <td><?php if (!empty($g['frame_image'])): ?><img class="af-img-mini" src="<?= htmlspecialchars($g['frame_image']) ?>" alt=""><?php endif; ?> <?= htmlspecialchars((string)($g['frame_name'] ?? '')) ?></td>
      <td><?= htmlspecialchars((string)($g['grant_source'] ?? '')) ?></td>
      <td><?= !empty($g['is_equipped']) ? '已装备' : '未装备' ?></td>
      <td><?= htmlspecialchars((string)($g['granted_at'] ?? '')) ?></td>
      <td><form method="post" onsubmit="return confirm('确认撤销？')"><?= csrf_field() ?><input type="hidden" name="_action" value="revoke_frame"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="btn admin-danger" type="submit">撤销</button></form></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$grants): ?><tr><td colspan="6" class="admin-empty">暂无授予记录</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>
  <?php
    $decoGuideType = 'avatar_frame';
    $decoGuideTitle = '头像框配置教程';
    $decoGuidePanelClass = 'af-panel';
    $decoGuidePanelAttr = 'data-af-panel';
    require dirname(__DIR__) . '/layouts/deco_guide.php';
  ?>
</div>


<div class="af-edit-modal" id="afEditModal" aria-hidden="true">
  <div class="af-edit-modal__mask" data-af-edit-close="1"></div>
  <div class="af-edit-modal__card" role="dialog" aria-modal="true">
    <div class="af-edit-modal__head"><div><h3 id="afEditTitle">编辑头像框</h3></div><button class="af-edit-modal__close" type="button" data-af-edit-close="1" aria-label="关闭"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>
    <div id="afEditFrameForm">
      <form method="post" action="/admin.php?path=avatar-frames" class="admin-form-grid" enctype="multipart/form-data" data-no-ajax>
        <?= csrf_field() ?><input type="hidden" name="_action" value="save_frame"><input type="hidden" name="id" data-field="id"><input type="hidden" name="image_existing" data-field="image_existing">
        <label>标识<input class="input" name="code" data-field="code" required></label>
        <label>名称<input class="input" name="name" data-field="name" required></label>
        <label>品质<select class="select" name="quality" data-field="quality" id="afEditQuality"><?php foreach ($qualities as $q): ?><option value="<?= htmlspecialchars($q['code']) ?>" data-name="<?= htmlspecialchars($q['name']) ?>" data-color="<?= htmlspecialchars($q['color']) ?>"><?= htmlspecialchars($q['name']) ?></option><?php endforeach; ?></select></label>
        <input type="hidden" name="quality_name" data-field="quality_name" id="afEditQualityName"><input type="hidden" name="quality_color" data-field="quality_color" id="afEditQualityColor">
        <label>获取方式<select class="select" name="obtain_method" data-field="obtain_method" id="afEditObtainMethod"><option value="free">免费领取</option><option value="shop">商城购买</option><option value="task">任务解锁</option><option value="level">等级解锁</option><option value="grant">管理员授予</option></select></label>
        <label id="afEditCurrencyField" style="display:none">购买货币<select class="select" name="price_currency" data-field="price_currency" id="afEditPriceCurrency"><option value="">（不需要）</option><?php foreach ($currencies as $c): ?><option value="<?= htmlspecialchars($c['code']) ?>"><?= htmlspecialchars($c['name'] . '（' . $c['code'] . '）') ?></option><?php endforeach; ?></select></label>
        <label id="afEditPriceField" style="display:none">价格<input class="input" type="number" name="price_amount" data-field="price_amount" id="afEditPriceAmount" min="0" step="0.01" value="0"></label>
        <label id="afEditTaskField" style="display:none">关联任务<select class="select" id="afEditRuleTask"><option value="0">— 选择任务 —</option><?php foreach ($tasks as $t): ?><option value="<?= (int)$t['id'] ?>">#<?= (int)$t['id'] ?> <?= htmlspecialchars((string)$t['title']) ?><?= ($t['status'] ?? '') !== 'active' ? '（停用）' : '' ?></option><?php endforeach; ?></select></label>
        <label id="afEditLevelField" style="display:none">达到等级<select class="select" id="afEditRuleLevel"><option value="0">— 选择等级 —</option><?php foreach ($levels as $lv): ?><option value="<?= (int)$lv['level'] ?>">Lv.<?= (int)$lv['level'] ?> <?= htmlspecialchars((string)$lv['name']) ?></option><?php endforeach; ?></select></label>
        <input type="hidden" name="grant_type" value="manual"><input type="hidden" name="rule_type" value="manual"><input type="hidden" name="rule_value" value="0">
        <label>排序<input class="input" type="number" name="sort_order" data-field="sort_order"></label>
        <label>状态<select class="select" name="status" data-field="status"><option value="active">启用</option><option value="disabled">禁用</option></select></label>
        <label>替换图片<input class="input" type="file" name="image_file" accept="image/png,image/webp,image/jpeg"></label>
        <label>图片路径<input class="input" name="image" data-field="image"></label>
        <label style="grid-column:1/-1">介绍<textarea class="input" name="description" data-field="description" rows="2"></textarea></label>
        <button class="btn" type="submit">保存修改</button>
      </form>
      <div class="af-modal-delete">
        <form method="post" action="/admin.php?path=avatar-frames" data-no-ajax onsubmit="return confirm('确认删除该头像框？已有用户获得时会被阻止');">
          <?= csrf_field() ?><input type="hidden" name="_action" value="delete_frame"><input type="hidden" name="id" data-field="delete_id">
          <button class="btn btn-light admin-danger" type="submit">删除头像框</button>
        </form>
      </div>
    </div>
    <div id="afEditQualityForm" style="display:none">
      <form method="post" action="/admin.php?path=avatar-frames" class="admin-form-grid" data-no-ajax>
        <?= csrf_field() ?><input type="hidden" name="_action" value="save_quality">
        <label>品质标识<input class="input" name="code" data-field="q_code" readonly></label>
        <label>品质名称<input class="input" name="name" data-field="q_name" required></label>
        <label>品质颜色<input class="input" type="color" name="color" data-field="q_color"></label>
        <label>排序<input class="input" type="number" name="sort_order" data-field="q_sort_order"></label>
        <label>状态<select class="select" name="status" data-field="q_status"><option value="active">启用</option><option value="inactive">停用</option></select></label>
        <button class="btn" type="submit">保存品质</button>
      </form>
      <div class="af-modal-delete">
        <form method="post" action="/admin.php?path=avatar-frames" data-no-ajax onsubmit="return confirm('确认删除该品质？')">
          <?= csrf_field() ?><input type="hidden" name="_action" value="delete_quality"><input type="hidden" name="code" data-field="q_delete_code">
          <button class="btn btn-light admin-danger" type="submit">删除品质</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
window.AfFrames=<?= json_encode(array_values($afJson), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
window.AfQualities=<?= json_encode(array_values($qualities), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
window.AfUsers=<?= json_encode(array_values($users), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
(function(){
  // Tab switching
  document.querySelectorAll('.af-tab').forEach(function(b){b.onclick=function(){document.querySelectorAll('.af-tab,.af-panel').forEach(function(x){x.classList.remove('is-active')});b.classList.add('is-active');document.querySelector('[data-af-panel="'+b.dataset.afTab+'"]').classList.add('is-active');try{localStorage.setItem('clay_af_admin_tab',b.dataset.afTab)}catch(_){}}});
  try{var saved=localStorage.getItem('clay_af_admin_tab');var t=saved&&document.querySelector('[data-af-tab="'+saved+'"]');if(t)t.click()}catch(_){}

  // Modal
  var modal=document.getElementById('afEditModal'),title=document.getElementById('afEditTitle'),frameForm=document.getElementById('afEditFrameForm'),qualityForm=document.getElementById('afEditQualityForm');
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
  function setVal(scope,name,value){var el=scope.querySelector('[data-field="'+name+'"]');if(!el)return;if(el.tagName==='SELECT'){el.value=String(value==null?'':value);}else if(el.type==='color'){el.value=/^#[0-9a-fA-F]{6}$/.test(String(value||''))?String(value):'#64748b';}else{el.value=String(value==null?'':value);}}
  function syncQuality(selectEl,nameEl,colorEl){if(!selectEl||!nameEl||!colorEl)return;var opt=selectEl.options[selectEl.selectedIndex];nameEl.value=(opt&&opt.dataset.name)||'标准';colorEl.value=(opt&&opt.dataset.color)||'#64748b';}
  function syncCreateQuality(){syncQuality(document.getElementById('afCreateQuality'),document.getElementById('afCreateQualityName'),document.getElementById('afCreateQualityColor'));}
  function syncEditQuality(){syncQuality(document.getElementById('afEditQuality'),document.getElementById('afEditQualityName'),document.getElementById('afEditQualityColor'));}
  var afCreateQuality=document.getElementById('afCreateQuality');if(afCreateQuality){afCreateQuality.addEventListener('change',syncCreateQuality);syncCreateQuality();}
  function closeModal(e){if(e){e.preventDefault();e.stopPropagation();if(e.stopImmediatePropagation)e.stopImmediatePropagation();}modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');document.documentElement.style.overflow='';return false;}
  function afEditSync(){var v=(document.getElementById('afEditObtainMethod')||{}).value;if(v===undefined)return;document.getElementById('afEditCurrencyField').style.display=(v==='shop')?'':'none';document.getElementById('afEditPriceField').style.display=(v==='shop')?'':'none';document.getElementById('afEditTaskField').style.display=(v==='task')?'':'none';document.getElementById('afEditLevelField').style.display=(v==='level')?'':'none';}
  function openFrame(id){var f=(window.AfFrames||[]).find(function(x){return String(x.id)===String(id);});if(!f)return false;title.textContent='编辑头像框';frameForm.style.display='block';qualityForm.style.display='none';['id','code','name','description','image','quality','quality_name','quality_color','obtain_method','price_currency','price_amount','sort_order','status'].forEach(function(k){setVal(frameForm,k,f[k]);});syncEditQuality();setVal(frameForm,'image_existing',f.image||'');setVal(frameForm,'delete_id',f.id);var om=String(f.obtain_method||'grant');var rv=parseInt(f.price_amount||0,10)||0;var rt=document.getElementById('afEditRuleTask');var rl=document.getElementById('afEditRuleLevel');if(rt)rt.value=(om==='task')?rv:0;if(rl)rl.value=(om==='level')?rv:0;afEditSync();modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');document.documentElement.style.overflow='hidden';return false;}
  function openQuality(code){var q=(window.AfQualities||[]).find(function(x){return String(x.code)===String(code);});if(!q)return false;title.textContent='编辑品质';frameForm.style.display='none';qualityForm.style.display='block';setVal(qualityForm,'q_code',q.code);setVal(qualityForm,'q_name',q.name);setVal(qualityForm,'q_color',q.color);setVal(qualityForm,'q_sort_order',q.sort_order);setVal(qualityForm,'q_status',q.status||'active');setVal(qualityForm,'q_delete_code',q.code);modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');document.documentElement.style.overflow='hidden';return false;}

  document.addEventListener('click',function(e){var close=e.target.closest('[data-af-edit-close]');if(close)return closeModal(e);var f=e.target.closest('[data-af-edit]');if(f){e.preventDefault();e.stopPropagation();if(e.stopImmediatePropagation)e.stopImmediatePropagation();return openFrame(f.getAttribute('data-af-edit'));}var q=e.target.closest('[data-af-edit-quality]');if(q){e.preventDefault();e.stopPropagation();if(e.stopImmediatePropagation)e.stopImmediatePropagation();return openQuality(q.getAttribute('data-af-edit-quality'));}},true);
  document.addEventListener('keydown',function(e){if(e.key==='Escape'&&modal.classList.contains('is-open'))closeModal(e);});
  (function(){var em=document.getElementById('afEditObtainMethod');if(em){em.addEventListener('change',afEditSync);}var eq=document.getElementById('afEditQuality');if(eq){eq.addEventListener('change',syncEditQuality);}var et=document.getElementById('afEditRuleTask');var el=document.getElementById('afEditRuleLevel');var pa=document.getElementById('afEditPriceAmount');if(et&&pa)et.addEventListener('change',function(){if(em.value==='task')pa.value=this.value||0;});if(el&&pa)el.addEventListener('change',function(){if(em.value==='level')pa.value=this.value||0;});})();

  // Search select
  function norm(v){return String(v==null?'':v).toLowerCase();}
  function setupSearchSelect(root,items,toOption){if(!root)return;var input=root.querySelector('[data-af-search-input]'),hidden=root.querySelector('[data-af-search-value]'),box=root.querySelector('[data-af-search-box]'),selected=root.querySelector('[data-af-search-selected]');function close(){root.classList.remove('is-open');}function pick(item){var opt=toOption(item);hidden.value=String(item.id);input.value=opt.label;if(selected){selected.textContent='已选择：'+opt.label;selected.classList.add('is-filled');}close();}function render(){var q=norm(input.value).trim();hidden.value='';if(selected){selected.textContent=root.getAttribute('data-af-search')==='user'?'尚未选择用户':'尚未选择头像框';selected.classList.remove('is-filled');}var filtered=(items||[]).filter(function(item){return !q||norm(toOption(item).search).indexOf(q)!==-1;}).slice(0,12);box.querySelectorAll('.af-search-option').forEach(function(x){x.remove();});if(filtered.length===0){box.innerHTML='<div class="af-search-empty">没有匹配结果</div>';}else{filtered.forEach(function(item){var opt=toOption(item),btn=document.createElement('button');btn.type='button';btn.className='af-search-option';btn.innerHTML='<strong>'+esc(opt.label)+'</strong><small>'+esc(opt.meta||'')+'</small>';btn.addEventListener('mousedown',function(e){e.preventDefault();pick(item);});box.appendChild(btn);});}root.classList.add('is-open');}input.addEventListener('focus',render);input.addEventListener('input',render);input.addEventListener('keydown',function(e){var first=box.querySelector('.af-search-option');if(e.key==='Enter'&&first){e.preventDefault();first.dispatchEvent(new MouseEvent('mousedown',{bubbles:true,cancelable:true}));}if(e.key==='Escape')close();});document.addEventListener('mousedown',function(e){if(!root.contains(e.target))close();});}

  setupSearchSelect(document.querySelector('[data-af-search="user"]'),window.AfUsers||[],function(u){var name=u.nickname||u.username||('用户#'+u.id),pid=u.public_id||'',uname=u.username||'';return{label:name+(pid?' · '+pid:''),meta:uname?'用户名 '+uname:'用户 ID '+u.id,search:[name,pid,uname,u.id].join(' ')};});
  setupSearchSelect(document.querySelector('[data-af-search="frame"]'),window.AfFrames||[],function(f){return{label:f.name||('头像框#'+f.id),meta:(f.quality_name||f.quality||'')+' · '+(f.code||''),search:[f.name,f.code,f.quality_name,f.quality,f.id].join(' ')};});
  setupSearchSelect(document.querySelector('[data-af-search="auto-user"]'),window.AfUsers||[],function(u){var name=u.nickname||u.username||('用户#'+u.id),pid=u.public_id||'',uname=u.username||'';return{label:name+(pid?' · '+pid:''),meta:uname?'用户名 '+uname:'用户 ID '+u.id,search:[name,pid,uname,u.id].join(' ')};});
})();
</script>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
