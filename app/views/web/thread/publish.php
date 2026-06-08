<?php
$publishSelectedSectionId = (int)($selectedSectionId ?? ($_POST['section_id'] ?? 0));
$publishSelectedSectionName = '选择板块';
foreach (($sections ?? []) as $section) {
    if ((int)($section['id'] ?? 0) === $publishSelectedSectionId) {
        $publishSelectedSectionName = (string)($section['name'] ?? '选择板块');
        break;
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>发布帖子</title>
  <link rel="stylesheet" href="/assets/css/style.css?v=202605081639">
  <link href="/public/assets/css/quill.snow.css" rel="stylesheet">
  <link href="/assets/css/editor-enhance.css?v=202605261930" rel="stylesheet">
  <style>
    .publish-wrap{padding:16px 16px 96px}.question-bounty-box{display:none;padding:12px;border:1px solid rgba(2,132,199,.20);border-radius:14px;background:rgba(2,132,199,.06);gap:10px}.question-bounty-box.is-open{display:grid}.question-bounty-box h3{margin:0;font-size:15px;color:var(--text-main,#0f172a)}.question-bounty-row{display:grid;grid-template-columns:auto minmax(120px,1fr) minmax(120px,1fr);gap:10px;align-items:center}.question-bounty-row label{display:flex;gap:6px;align-items:center;font-size:13px;font-weight:900;color:var(--text-soft,#64748b)}@media(max-width:640px){.question-bounty-row{grid-template-columns:1fr}}.publish-card{max-width:860px;margin:0 auto;overflow:visible!important}.publish-title{margin:0 0 18px;font-size:22px;color:var(--text-main)}.clay-table-toolbar-btn,.clay-paid-toolbar-btn{width:28px!important;height:28px!important;border:1px solid rgba(2,132,199,.24)!important;border-radius:8px!important;background:rgba(2,132,199,.08)!important;color:var(--primary,#0284c7)!important;padding:0!important;display:inline-flex!important;align-items:center!important;justify-content:center!important}.clay-table-toolbar-btn svg,.clay-paid-toolbar-btn svg{width:16px;height:16px;display:block;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.paid-config-modal{position:fixed;inset:0;z-index:1600;display:none}.paid-config-modal.is-open{display:block}.paid-config-mask{position:absolute;inset:0;background:rgba(15,23,42,.46);backdrop-filter:blur(2px)}.paid-config-card{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:min(420px,calc(100vw - 32px));background:var(--card-bg,#fff);border:1px solid var(--line-soft,#e2e8f0);border-radius:18px;box-shadow:0 24px 80px rgba(15,23,42,.26);padding:16px}.paid-config-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}.paid-config-head strong{font-size:16px;color:var(--text-main,#0f172a)}.paid-config-close{border:0;background:transparent;color:#94a3b8;font-size:24px;line-height:1;cursor:pointer}.paid-config-grid{display:grid;gap:10px}.paid-config-grid label{display:grid;gap:6px;color:var(--text-soft,#64748b);font-size:12px;font-weight:900}.paid-config-grid input,.paid-config-grid select{height:38px;border:1px solid var(--line-soft,#e2e8f0);border-radius:12px;background:var(--input-bg,#f8fafc);color:var(--text-main,#0f172a);padding:0 11px;font-size:13px}.paid-config-tip{color:var(--text-muted,#94a3b8);font-size:12px;line-height:1.6}.paid-config-error{display:none;color:#ef4444;font-size:12px;font-weight:800}.paid-config-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:12px}.paid-config-actions button{height:34px;border-radius:999px;border:1px solid var(--line-soft,#e2e8f0);background:var(--card-bg,#fff);color:var(--text-soft,#64748b);padding:0 13px;font-size:12px;font-weight:950;cursor:pointer}.paid-config-actions button.primary{background:var(--primary,#0284c7);border-color:var(--primary,#0284c7);color:#fff}html[data-theme="dark"] .paid-config-card{background:#111827;border-color:#263244}html[data-theme="dark"] .paid-config-grid input,html[data-theme="dark"] .paid-config-grid select{background:#0f172a;border-color:#263244;color:#e5e7eb}.ql-editor p.clay-paid-block,.ql-editor p.clay-paid-block-block{margin:12px 0;padding:12px 14px;border:1px dashed rgba(2,132,199,.36);border-radius:12px;background:rgba(2,132,199,.06)}.ql-editor .clay-paid-block,.ql-editor .clay-paid-block-block{position:relative}.ql-editor .clay-paid-block.is-selected,.ql-editor .clay-paid-block-block.is-selected{outline:2px solid rgba(239,68,68,.35);outline-offset:2px}.ql-editor .clay-paid-block:after,.ql-editor .clay-paid-block-block:after{content:'Ctrl/Alt/⌘+退格删整块';display:none;margin-left:8px;color:#ef4444;font-size:11px;font-weight:900;opacity:.68}.ql-editor .clay-paid-delete{display:none;position:absolute;right:8px;top:8px;z-index:3;height:22px;padding:0 8px;border:1px solid rgba(239,68,68,.24);border-radius:999px;background:#fff1f2;color:#ef4444;font-size:11px;font-weight:900;line-height:20px;cursor:pointer}.ql-editor .clay-paid-block.is-selected .clay-paid-delete,.ql-editor .clay-paid-block-block.is-selected .clay-paid-delete{display:inline-flex;align-items:center}.ql-editor .clay-paid-block:before,.ql-editor .clay-paid-block-block:before{content:'付费查看';display:inline-flex;margin-right:8px;padding:2px 7px;border-radius:999px;background:rgba(2,132,199,.14);color:#0284c7;font-size:12px;font-weight:900}.paid-help{font-size:12px;color:var(--text-muted,#94a3b8);line-height:1.6}.autosave-restore{display:flex;justify-content:space-between;gap:10px;align-items:center;margin:0 0 14px;padding:10px 12px;border:1px solid #bae6fd;border-radius:14px;background:#f0f9ff;color:#075985;font-size:13px;line-height:1.6}.autosave-restore strong{display:block;color:#0c4a6e}.autosave-restore button{white-space:nowrap}@media(max-width:768px){.autosave-restore{display:grid}.autosave-restore button{width:100%}}.publish-form{display:flex;flex-direction:column;gap:14px}.publish-title-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;background:var(--card-bg,#fff);border:1px solid var(--line-soft,#e2e8f0);border-radius:16px;padding:0 10px 0 0}.publish-title-row .input{border:0!important;background:transparent!important;box-shadow:none!important}.section-picker-trigger{height:38px;max-width:150px;border:0;background:transparent;color:var(--text-main,#0f172a);font-size:14px;font-weight:900;cursor:pointer;display:inline-flex;align-items:center;justify-content:flex-end;gap:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.section-picker-trigger::after{content:'›';color:var(--text-muted,#94a3b8);font-size:18px;line-height:1}.section-picker-modal{position:fixed;inset:0;z-index:1550;display:none;background:var(--bg-main,#f6f8fb)}.section-picker-modal.is-open{display:block}.section-picker-page{min-height:100%;display:flex;flex-direction:column;background:var(--bg-main,#f6f8fb)}.section-picker-head{height:54px;display:grid;grid-template-columns:52px 1fr 52px;align-items:center;background:var(--card-bg,#fff);border-bottom:1px solid var(--line-soft,#e2e8f0)}.section-picker-head strong{text-align:center;font-size:17px;color:var(--text-main,#0f172a)}.section-picker-back{border:0;background:transparent;color:var(--text-main,#0f172a);font-size:30px;line-height:1;cursor:pointer}.section-picker-body{padding:14px 0 34px;overflow:auto}.section-picker-group{background:var(--card-bg,#fff);border-top:1px solid var(--line-soft,#e2e8f0);border-bottom:1px solid var(--line-soft,#e2e8f0);margin-bottom:12px}.section-picker-group-title{padding:13px 18px 7px;color:var(--text-soft,#64748b);font-size:15px;font-weight:950}.section-picker-item{width:100%;border:0;background:transparent;display:grid;grid-template-columns:46px minmax(0,1fr) auto;gap:12px;align-items:center;padding:12px 18px;text-align:left;cursor:pointer}.section-picker-item.is-active{background:rgba(2,132,199,.06)}.section-picker-icon{width:46px;height:46px;border-radius:14px;background:linear-gradient(135deg,rgba(2,132,199,.16),rgba(99,102,241,.16));display:grid;place-items:center;font-size:22px;overflow:hidden}.section-picker-icon img{width:100%;height:100%;object-fit:cover}.section-picker-name{font-size:15px;font-weight:900;color:var(--text-main,#0f172a);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.section-picker-count{margin-top:3px;color:var(--text-muted,#94a3b8);font-size:13px}.section-picker-choose{height:34px;border:0;border-radius:999px;background:#34c79b;color:#fff;padding:0 18px;font-size:14px;font-weight:950}.publish-actions{display:flex;gap:12px;flex-wrap:wrap}.publish-actions .btn{min-width:120px}.alert-error{margin:12px 0;padding:12px;border-radius:12px;background:#fef2f2;color:#b91c1c}.alert-success{margin:12px 0;padding:12px;border-radius:12px;background:#dcfce7;color:#166534}.select:disabled{opacity:.7}.draft-autosave-status{margin-top:-6px;color:var(--text-muted,#94a3b8);font-size:12px;line-height:1.6}.quality-hints{display:none;margin-top:-6px;padding:9px 11px;border-radius:12px;background:rgba(2,132,199,.06);color:var(--text-soft,#64748b);font-size:12px;line-height:1.65}.quality-hints.is-open{display:block}.quality-hints b{color:var(--text-main,#0f172a)}.draft-autosave-status.is-saving{color:#0284c7}.draft-autosave-status.is-saved{color:#16a34a}.draft-autosave-status.is-error{color:#ef4444}@media(max-width:768px){.publish-wrap{padding:12px 12px 96px}.publish-card{border-radius:14px}.publish-title{font-size:20px}.publish-title-row{border-radius:14px}.section-picker-trigger{max-width:112px;font-size:13px}.publish-actions{display:grid;grid-template-columns:1fr 1fr}.publish-actions .btn{width:100%;min-width:0}}
  </style>

<style>/* 20260508-section-picker-polish */
.section-picker-modal{z-index:3200!important;background:var(--bg-main,#f6f8fb)!important;}
.section-picker-page{background:var(--bg-main,#f6f8fb)!important;}
.section-picker-head{height:50px!important;grid-template-columns:48px 1fr 48px!important;background:var(--card-bg,#fff)!important;box-shadow:0 1px 0 rgba(226,232,240,.72)!important;position:sticky!important;top:0!important;z-index:2!important;}
.section-picker-head strong{font-size:17px!important;font-weight:950!important;letter-spacing:-.02em!important;}
.section-picker-back{font-size:28px!important;color:var(--text-main,#0f172a)!important;}
.section-picker-body{padding:10px 0 max(22px,env(safe-area-inset-bottom))!important;}
.section-picker-group{margin:0 0 10px!important;border-top:1px solid var(--line-soft,#e2e8f0)!important;border-bottom:1px solid var(--line-soft,#e2e8f0)!important;background:var(--card-bg,#fff)!important;}
.section-picker-group-title{padding:11px 18px 8px!important;font-size:14px!important;font-weight:950!important;color:var(--text-soft,#64748b)!important;background:var(--input-bg,#f8fafc)!important;}
.section-picker-item{grid-template-columns:42px minmax(0,1fr) auto!important;gap:11px!important;padding:11px 18px!important;min-height:66px!important;border-bottom:1px solid rgba(226,232,240,.58)!important;}
.section-picker-item:last-child{border-bottom:0!important;}
.section-picker-item.is-active{background:rgba(2,132,199,.045)!important;}
.section-picker-icon{width:42px!important;height:42px!important;border-radius:14px!important;font-size:20px!important;background:linear-gradient(135deg,rgba(2,132,199,.12),rgba(99,102,241,.12))!important;}
.section-picker-name{font-size:15px!important;font-weight:950!important;letter-spacing:-.01em!important;}
.section-picker-count{display:inline!important;margin-left:4px!important;color:var(--text-muted,#94a3b8)!important;font-size:13px!important;font-weight:800!important;}
.section-picker-choose{height:30px!important;min-width:58px!important;border-radius:999px!important;background:rgba(2,132,199,.08)!important;color:var(--primary,#0284c7)!important;border:1px solid rgba(2,132,199,.18)!important;padding:0 12px!important;font-size:13px!important;font-weight:950!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;}
.section-picker-item.is-active .section-picker-choose{background:var(--primary,#0284c7)!important;border-color:var(--primary,#0284c7)!important;color:#fff!important;}
.section-picker-item.is-active .section-picker-choose::before{content:'';display:inline-block;width:8px;height:14px;border:solid currentColor;border-width:0 2px 2px 0;transform:rotate(45deg);margin-right:6px;}
html[data-theme="dark"] .section-picker-head,html[data-theme="dark"] .section-picker-group{background:#111827!important;border-color:#263244!important;}html[data-theme="dark"] .section-picker-group-title{background:#0f172a!important;}html[data-theme="dark"] .section-picker-item{border-color:#263244!important;}html[data-theme="dark"] .section-picker-item.is-active{background:rgba(14,165,233,.10)!important;}
@media(max-width:768px){.section-picker-modal{inset:0!important;}.section-picker-head{height:48px!important;}.section-picker-body{padding-top:8px!important;}.section-picker-item{padding:10px 16px!important;min-height:62px!important;}.section-picker-icon{width:40px!important;height:40px!important}.section-picker-choose{height:28px!important;min-width:54px!important;font-size:12px!important;padding:0 10px!important;}}
</style>

</head>
<body>
<?php require __DIR__ . '/../layouts/topbar.php'; ?>

<div class="container publish-wrap">
  <div class="card publish-card">
    <h2 class="publish-title">发布帖子</h2>

    <?php if (!empty($error)): ?>
      <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
      <div class="alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($draft) && !empty($draft['is_autosave'])): ?>
      <div class="autosave-restore" id="autosave-restore"><div><strong>已恢复上次未完成内容</strong><span>这是自动保存的临时恢复点，不会显示在草稿箱。需要存档请点“保存到草稿箱”。</span></div><button class="btn btn-light" type="button" id="discard-autosave-btn">丢弃恢复</button></div>
    <?php endif; ?>

    <form method="post" action="/index.php?path=publish" id="publish-form" class="publish-form">
      <?= csrf_field() ?>
      <input type="hidden" name="draft_id" value="<?= (int)($draft['id'] ?? $draftId ?? 0) ?>">
      <input type="hidden" name="mode" value="manual">
      <input type="hidden" name="section_id" id="sectionIdInput" value="<?= $publishSelectedSectionId > 0 ? $publishSelectedSectionId : '' ?>">
      <div class="publish-title-row">
        <input class="input" name="title" placeholder="请输入帖子标题" value="<?= htmlspecialchars($_POST['title'] ?? $draft['title'] ?? '') ?>" required>
        <button class="section-picker-trigger" type="button" id="sectionPickerOpen" title="选择板块"><span id="sectionPickerLabel"><?= htmlspecialchars($publishSelectedSectionName) ?></span></button>
      </div>


      <div class="question-bounty-box" id="questionBountyBox">
        <h3>问答悬赏</h3>
        <div class="question-bounty-row">
          <label><input type="checkbox" name="bounty_enabled" value="1" <?= !empty($_POST['bounty_enabled']) ? 'checked' : '' ?>> 设置悬赏</label>
          <select class="select" name="bounty_currency"><option value="">选择货币</option><?php foreach($currencies as $c): ?><option value="<?= htmlspecialchars((string)$c['code']) ?>" <?= (string)($_POST['bounty_currency'] ?? '') === (string)$c['code'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$c['name']) ?></option><?php endforeach; ?></select>
          <input class="input" type="number" name="bounty_amount" min="0" step="0.000001" value="<?= htmlspecialchars((string)($_POST['bounty_amount'] ?? '')) ?>" placeholder="悬赏金额">
        </div>
        <div class="paid-help">楼主采纳最佳答案后，系统会从楼主钱包扣除悬赏并发给被采纳回复作者。</div>
      </div>

      <div class="editor-shell" id="editor-shell">
        <?= clay_editor_toolbar() ?>
        <div id="quill-editor"></div>
        <textarea class="textarea fallback-editor" id="fallback-editor" placeholder="请输入帖子内容..."><?= htmlspecialchars($_POST['content'] ?? $draft['content'] ?? '') ?></textarea>
        <input type="hidden" name="content" id="content-input">
        <input type="hidden" name="paid_visible_enabled" id="paidVisibleEnabled" value="<?= !empty($_POST['paid_visible_enabled']) ? '1' : '0' ?>">
        <input type="hidden" name="paid_visible_price" id="paidVisiblePrice" value="<?= htmlspecialchars((string)($_POST['paid_visible_price'] ?? '')) ?>">
        <input type="hidden" name="paid_visible_currency" id="paidVisibleCurrency" value="<?= htmlspecialchars((string)($_POST['paid_visible_currency'] ?? '')) ?>">
      </div>
      <div class="quality-hints" id="quality-hints" aria-live="polite"></div>


      <div class="draft-autosave-status" id="draft-autosave-status" aria-live="polite">自动保存已开启</div>

      <div class="publish-actions">
        <button type="submit" class="btn" id="submit-btn">立即发布</button>
        <button type="submit" class="btn btn-light" id="draft-btn" formaction="/index.php?path=draft/save">保存到草稿箱</button>
        <a href="/index.php?path=drafts" class="btn btn-light" style="text-decoration:none;">草稿箱</a>
        <a href="/index.php" class="btn btn-light" style="text-decoration:none;">返回首页</a>
      </div>
    </form>
  </div>
</div>


<div class="section-picker-modal" id="sectionPickerModal" aria-hidden="true">
  <div class="section-picker-page">
    <div class="section-picker-head"><button class="section-picker-back" type="button" data-section-close>‹</button><strong>选择板块</strong><span></span></div>
    <div class="section-picker-body">
      <?php $lastCategory = null; foreach (($sections ?? []) as $section): ?>
        <?php $cat = (string)($section['category_name'] ?? '默认分区'); if ($cat !== $lastCategory): if ($lastCategory !== null): ?></div><?php endif; ?><div class="section-picker-group"><div class="section-picker-group-title"><?= htmlspecialchars($cat) ?></div><?php $lastCategory = $cat; endif; ?>
        <?php $sid = (int)($section['id'] ?? 0); $icon = trim((string)($section['icon'] ?? '')); ?>
        <button class="section-picker-item <?= $publishSelectedSectionId === $sid ? 'is-active' : '' ?>" type="button" data-section-id="<?= $sid ?>" data-section-name="<?= htmlspecialchars((string)($section['name'] ?? ''), ENT_QUOTES) ?>" data-is-question="<?= !empty($section['is_question']) ? '1' : '0' ?>">
          <span class="section-picker-icon"><?php if ($icon !== '' && preg_match('#^https?://|^/#', $icon)): ?><img src="<?= htmlspecialchars($icon) ?>" alt=""><?php else: ?><?= htmlspecialchars($icon !== '' ? $icon : mb_substr((string)($section['name'] ?? '板'), 0, 1)) ?><?php endif; ?></span>
          <span><span class="section-picker-name"><?= htmlspecialchars((string)($section['name'] ?? '')) ?></span><span class="section-picker-count"><?= (int)($section['thread_count'] ?? 0) ?> 帖子</span></span>
          <span class="section-picker-choose"><?= $publishSelectedSectionId === $sid ? '已选' : '选择' ?></span>
        </button>
      <?php endforeach; if ($lastCategory !== null): ?></div><?php endif; ?>
    </div>
  </div>
</div>

<div class="paid-config-modal table-config-modal" id="tableConfigModal" aria-hidden="true">
  <div class="paid-config-mask" data-table-cancel></div>
  <div class="paid-config-card" role="dialog" aria-modal="true" aria-labelledby="tableConfigTitle">
    <div class="paid-config-head"><strong id="tableConfigTitle">插入表格</strong><button class="paid-config-close" type="button" data-table-cancel>×</button></div>
    <div class="paid-config-grid">
      <label>行数<input type="number" min="1" max="12" id="tableConfigRows" value="2"></label>
      <label>列数<input type="number" min="1" max="8" id="tableConfigCols" value="2"></label>
      <div class="paid-config-tip" id="tableConfigTip">设置后会在正文当前位置插入表格。</div>
      <div class="paid-config-error" id="tableConfigError"></div>
    </div>
    <div class="paid-config-actions"><button type="button" data-table-delete id="tableDeleteBtn" style="display:none;color:#ef4444;">删除表格</button><button type="button" data-table-cancel>取消</button><button class="primary" type="button" id="tableConfigConfirm">确定</button></div>
  </div>
</div>

<div class="paid-config-modal" id="paidConfigModal" aria-hidden="true">
  <div class="paid-config-mask" data-paid-cancel></div>
  <div class="paid-config-card" role="dialog" aria-modal="true" aria-labelledby="paidConfigTitle">
    <div class="paid-config-head"><strong id="paidConfigTitle">设置付费查看</strong><button class="paid-config-close" type="button" data-paid-cancel>×</button></div>
    <div class="paid-config-grid">
      <label>价格<input type="number" min="1" step="1" id="paidConfigPrice" placeholder="输入价格"></label>
      <label>货币<select id="paidConfigCurrency"><option value="">选择货币</option><?php foreach($currencies as $c): ?><?php $p=(int)($c['precision'] ?? 0); ?><option value="<?= htmlspecialchars((string)$c['code']) ?>" data-precision="<?= $p ?>"><?= htmlspecialchars((string)$c['name']) ?></option><?php endforeach; ?></select></label>
      <div class="paid-config-tip">确认后会在正文当前位置插入付费内容块，你可以直接在块里输入付费内容。</div>
      <div class="paid-config-error" id="paidConfigError"></div>
    </div>
    <div class="paid-config-actions"><button type="button" data-paid-cancel>取消</button><button class="primary" type="button" id="paidConfigConfirm">插入</button></div>
  </div>
</div>

<script src="/public/assets/js/quill.js"></script>
<script src="/assets/js/mention-picker.js"></script>
<script>
(function(){
  var form = document.getElementById('publish-form');
  var shell = document.getElementById('editor-shell');
  var tip = document.getElementById('editor-tip');
  var hidden = document.getElementById('content-input');
  var fallback = document.getElementById('fallback-editor');
  var qualityHints = document.getElementById('quality-hints');
  var titleInput = form ? form.querySelector('input[name=title]') : null;
  var oldContent = <?= json_encode((string)($_POST['content'] ?? $draft['content'] ?? '')) ?>;
  var quill = null;

  function bindSectionPicker(){
    var modal=document.getElementById('sectionPickerModal');
    var open=document.getElementById('sectionPickerOpen');
    var input=document.getElementById('sectionIdInput');
    var label=document.getElementById('sectionPickerLabel');
    var bountyBox=document.getElementById('questionBountyBox');
    if(!modal||!open||!input||!label) return;
    var active=modal.querySelector('.section-picker-item.is-active'); if(bountyBox&&active)bountyBox.classList.toggle('is-open', active.getAttribute('data-is-question')==='1');
    function close(){modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');}
    open.addEventListener('click',function(){modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');});
    modal.addEventListener('click',function(e){
      if(e.target.closest('[data-section-close]')){close();return;}
      var item=e.target.closest('[data-section-id]');
      if(!item)return;
      input.value=item.getAttribute('data-section-id')||'';
      label.textContent=item.getAttribute('data-section-name')||'选择板块';
      if(bountyBox)bountyBox.classList.toggle('is-open', item.getAttribute('data-is-question')==='1');
      modal.querySelectorAll('.section-picker-item').forEach(function(el){el.classList.toggle('is-active',el===item);var choose=el.querySelector('.section-picker-choose');if(choose)choose.textContent=el===item?'已选':'选择';});
      if (typeof scheduleAutosave === 'function') scheduleAutosave(300);
      close();
    });
    document.addEventListener('keydown',function(e){if(e.key==='Escape'&&modal.classList.contains('is-open'))close();});
  }
  bindSectionPicker();
  if(titleInput) titleInput.addEventListener('input', updateQualityHints);
  if(fallback) fallback.addEventListener('input', updateQualityHints);
  setTimeout(updateQualityHints, 200);


  function plainText(html){var div=document.createElement('div');div.innerHTML=html||'';return (div.textContent||div.innerText||'').replace(/\s+/g,' ').trim();}
  function updateQualityHints(){
    if(!qualityHints)return;
    var title=(titleInput&&titleInput.value||'').trim();
    var html=quill ? quill.root.innerHTML : (fallback&&fallback.value||'');
    var text=plainText(html);
    var tips=[];
    if(title.length>0 && title.length<6) tips.push('标题可以再具体一点');
    if(text.length>0 && text.length<20) tips.push('正文略短，可以补充背景或细节');
    if(!document.getElementById('sectionIdInput').value) tips.push('发布前记得选择板块');
    var imgCount=(html.match(/<img\b/gi)||[]).length;
    if(imgCount>6) tips.push('图片较多，建议保留最关键的几张');
    if(tips.length){qualityHints.classList.add('is-open');qualityHints.innerHTML='<b>内容提示：</b>'+tips.join(' · ');}else{qualityHints.classList.remove('is-open');qualityHints.innerHTML='';}
  }

  function enableFallback(message){
    shell.classList.add('editor-fallback');
    fallback.style.display = 'block';
    if (oldContent && !fallback.value) fallback.value = oldContent.replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]+>/g, '');
    if (tip) tip.textContent = message || '富文本编辑器加载失败，已切换为普通文本编辑。';
  }

  if (typeof window.Quill !== 'undefined') {
    try {
      var Parchment = Quill.import('parchment');
      var PaidClass = new Parchment.ClassAttributor('paidBlock', 'clay-paid', {scope: Parchment.Scope.BLOCK, whitelist:['block']});
      Quill.register(PaidClass, true);
    } catch(e) {}
  }

  if (typeof window.Quill === 'undefined') {
    enableFallback('富文本编辑器资源加载失败，已切换为普通文本编辑。');
  } else {
    try {
      quill = new Quill('#quill-editor', {
        theme: 'snow',
        placeholder: '请输入帖子内容...',
        modules: {
          toolbar: '#quill-toolbar'
        }
      });
      if (oldContent) quill.clipboard.dangerouslyPasteHTML(oldContent);
      bindImageUpload();
      bindTableBlock();
      bindPaidBlock();
      bindPaidDelete();
      quill.on('text-change', updateQualityHints);
      
if(window.ClayMentionPicker){ClayMentionPicker.bindQuill(quill, document.getElementById('editor-shell'));}
    } catch (e) {
      enableFallback('富文本编辑器初始化失败，已切换为普通文本编辑。');
    }
  }


  function uploadImage(file, cb){
    var fd=new FormData();
    fd.append('image', file);
    var csrfInput=document.querySelector('input[name="_csrf_token"]');
    if(csrfInput) fd.append('_csrf_token', csrfInput.value);
    fetch('/index.php?path=upload/image',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}})
      .then(function(r){return r.text().then(function(text){var data=null;try{data=JSON.parse(text);}catch(e){throw new Error('上传接口响应异常');} if(!r.ok || !data.ok) throw new Error((data&&data.error)||('HTTP '+r.status)); return data;});})
      .then(function(data){cb(data.url);})
      .catch(function(err){alert('图片上传失败：'+err.message);});
  }
  function bindImageUpload(){
    if(!quill) return;
    var toolbar=quill.getModule('toolbar');
    toolbar.addHandler('image', function(){
      var input=document.createElement('input'); input.type='file'; input.accept='image/jpeg,image/png,image/gif,image/webp';
      input.onchange=function(){var file=input.files&&input.files[0]; if(!file) return; uploadImage(file,function(url){var range=quill.getSelection(true); quill.insertEmbed(range.index,'image',url); quill.setSelection(range.index+1);});};
      input.click();
    });
  }
  var activeTable=null;
  var tableMode='insert';
  function currentTable(){
    var sel=window.getSelection&&window.getSelection();
    if(!sel || !sel.rangeCount) return null;
    var node=sel.anchorNode;
    if(node && node.nodeType===3) node=node.parentNode;
    return node && node.closest ? node.closest('#quill-editor table') : null;
  }
  function buildTableHtml(rows, cols){
    rows=Math.max(1,Math.min(12,parseInt(rows||2,10)||2));
    cols=Math.max(1,Math.min(8,parseInt(cols||2,10)||2));
    var html='<table><tbody>';
    for(var r=0;r<rows;r++){html+='<tr>';for(var c=0;c<cols;c++){html+='<td>'+(r===0?'表头':'内容')+'</td>';}html+='</tr>';}
    return html+'</tbody></table><p><br></p>';
  }
  function resizeTable(table, rows, cols){
    rows=Math.max(1,Math.min(12,parseInt(rows||2,10)||2));
    cols=Math.max(1,Math.min(8,parseInt(cols||2,10)||2));
    var tbody=table.tBodies[0] || table.appendChild(document.createElement('tbody'));
    while(tbody.rows.length<rows) tbody.appendChild(document.createElement('tr'));
    while(tbody.rows.length>rows) tbody.deleteRow(tbody.rows.length-1);
    Array.prototype.forEach.call(tbody.rows,function(row,r){
      while(row.cells.length<cols){var td=document.createElement('td');td.textContent=r===0?'表头':'内容';row.appendChild(td);}
      while(row.cells.length>cols) row.deleteCell(row.cells.length-1);
    });
  }
  function openTableConfig(mode, table){
    var modal=document.getElementById('tableConfigModal'), rows=document.getElementById('tableConfigRows'), cols=document.getElementById('tableConfigCols'), title=document.getElementById('tableConfigTitle'), del=document.getElementById('tableDeleteBtn'), err=document.getElementById('tableConfigError'), tip=document.getElementById('tableConfigTip');
    if(!modal||!rows||!cols) return;
    activeTable=table||null; tableMode=mode||'insert';
    if(activeTable){rows.value=activeTable.rows.length||2;cols.value=activeTable.rows[0]?activeTable.rows[0].cells.length:2;}
    else {rows.value=2;cols.value=2;}
    if(title) title.textContent=activeTable?'修改表格':'插入表格';
    if(tip) tip.textContent=activeTable?'调整行数/列数，或删除当前表格。':'设置后会在正文当前位置插入表格。';
    if(del) del.style.display=activeTable?'inline-flex':'none';
    if(err){err.style.display='none';err.textContent='';}
    modal.classList.add('is-open'); modal.setAttribute('aria-hidden','false'); setTimeout(function(){rows.focus();},30);
  }
  function closeTableConfig(){var modal=document.getElementById('tableConfigModal');if(modal){modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');}activeTable=null;tableMode='insert';}
  function confirmTableConfig(){
    var rows=document.getElementById('tableConfigRows'), cols=document.getElementById('tableConfigCols'), err=document.getElementById('tableConfigError');
    var r=parseInt(rows&&rows.value,10)||0,c=parseInt(cols&&cols.value,10)||0;
    if(r<1||c<1){if(err){err.textContent='请填写有效的行数和列数';err.style.display='block';}return;}
    if(activeTable){resizeTable(activeTable,r,c);}
    else {
      var html=buildTableHtml(r,c);
      if(!quill){var start=fallback.selectionStart||fallback.value.length;fallback.value=fallback.value.slice(0,start)+html+fallback.value.slice(start);fallback.focus();scheduleAutosave();closeTableConfig();return;}
      var range=quill.getSelection(true)||{index:quill.getLength(),length:0};quill.clipboard.dangerouslyPasteHTML(range.index,html);quill.setSelection(range.index+1,0);
    }
    scheduleAutosave();
    closeTableConfig();
  }
  document.addEventListener('click',function(e){
    if(e.target.closest('[data-table-cancel]')) closeTableConfig();
    if(e.target.closest('#tableConfigConfirm')) confirmTableConfig();
    if(e.target.closest('[data-table-delete]')){if(activeTable){activeTable.remove();scheduleAutosave();}closeTableConfig();}
  });
  document.addEventListener('keydown',function(e){var modal=document.getElementById('tableConfigModal');if(!modal||!modal.classList.contains('is-open'))return;if(e.key==='Escape')closeTableConfig();if(e.key==='Enter'&&(e.target.id==='tableConfigRows'||e.target.id==='tableConfigCols')){e.preventDefault();confirmTableConfig();}});
  function bindTableBlock(){
    var btn=document.getElementById('insertTableBlock');
    if(!btn || btn.dataset.tableBound==='1') return;
    btn.dataset.tableBound='1';
    btn.addEventListener('click', function(){openTableConfig('insert', currentTable());});
  }

  function paidDefaults(){
    return {
      enabled: document.getElementById('paidVisibleEnabled'),
      price: document.getElementById('paidVisiblePrice'),
      currency: document.getElementById('paidVisibleCurrency')
    };
  }
  var paidInsertCallback=null;
  function openPaidConfig(callback){
    var modal=document.getElementById('paidConfigModal');
    var priceInput=document.getElementById('paidConfigPrice');
    var currencyInput=document.getElementById('paidConfigCurrency');
    var errorBox=document.getElementById('paidConfigError');
    var fields=paidDefaults();
    if(!modal || !priceInput || !currencyInput) return;
    paidInsertCallback=callback;
    priceInput.value=fields.price ? fields.price.value : '';
    currencyInput.value=fields.currency ? fields.currency.value : '';
    if(errorBox){errorBox.style.display='none';errorBox.textContent='';}
    syncPaidPriceStep();
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden','false');
    setTimeout(function(){priceInput.focus();},30);
  }
  function closePaidConfig(){
    var modal=document.getElementById('paidConfigModal');
    if(modal){modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');}
    paidInsertCallback=null;
  }
  function currentPaidPrecision(){
    var currencyInput=document.getElementById('paidConfigCurrency');
    var opt=currencyInput && currencyInput.options ? currencyInput.options[currencyInput.selectedIndex] : null;
    var precision=parseInt(opt && opt.getAttribute('data-precision') || '0',10);
    return Math.max(0,Math.min(6,isNaN(precision)?0:precision));
  }
  function syncPaidPriceStep(){
    var priceInput=document.getElementById('paidConfigPrice');
    if(!priceInput) return;
    var precision=currentPaidPrecision();
    priceInput.step=precision>0 ? ('0.' + '0'.repeat(precision-1) + '1') : '1';
    priceInput.min=precision>0 ? priceInput.step : '1';
  }
  function confirmPaidConfig(){
    var priceInput=document.getElementById('paidConfigPrice');
    var currencyInput=document.getElementById('paidConfigCurrency');
    var errorBox=document.getElementById('paidConfigError');
    var price=priceInput ? String(priceInput.value||'').trim() : '';
    var currency=currencyInput ? String(currencyInput.value||'').trim().toUpperCase() : '';
    var precision=currentPaidPrecision();
    var value=Number(price);
    if(!price || value<=0 || !currency){
      if(errorBox){errorBox.textContent='请填写大于 0 的价格并选择货币';errorBox.style.display='block';}
      return;
    }
    if(precision===0 && Math.abs(value-Math.floor(value))>0.000001){
      if(errorBox){errorBox.textContent='该货币只支持整数价格';errorBox.style.display='block';}
      return;
    }
    var parts=price.split('.');
    if(parts[1] && parts[1].length>precision){
      if(errorBox){errorBox.textContent='价格最多支持 '+precision+' 位小数';errorBox.style.display='block';}
      return;
    }
    var fields=paidDefaults();
    if(fields.enabled) fields.enabled.value='1';
    if(fields.price) fields.price.value=price;
    if(fields.currency) fields.currency.value=currency;
    var cb=paidInsertCallback;
    closePaidConfig();
    if(cb) cb({price:price,currency:currency});
  }
  document.addEventListener('click', function(e){
    if(e.target.closest('[data-paid-cancel]')) closePaidConfig();
    if(e.target.closest('#paidConfigConfirm')) confirmPaidConfig();
  });
  var paidCurrencySelect=document.getElementById('paidConfigCurrency');
  if(paidCurrencySelect) paidCurrencySelect.addEventListener('change', syncPaidPriceStep);
  document.addEventListener('keydown', function(e){
    var modal=document.getElementById('paidConfigModal');
    if(!modal || !modal.classList.contains('is-open')) return;
    if(e.key==='Escape') closePaidConfig();
    if(e.key==='Enter' && (e.target.id==='paidConfigPrice' || e.target.id==='paidConfigCurrency')){e.preventDefault();confirmPaidConfig();}
  });


  function paidBlockAtSelection(){
    if(!quill) return null;
    var range=quill.getSelection(true);
    if(!range) return null;
    var leaf=quill.getLeaf(Math.max(0, range.index))[0];
    var node=leaf && leaf.domNode ? leaf.domNode : null;
    if(node && node.nodeType===3) node=node.parentNode;
    return node && node.closest ? node.closest('#quill-editor .clay-paid-block,#quill-editor .clay-paid-block-block') : null;
  }
  function deletePaidBlock(block){
    if(!block) return false;
    if(quill){
      var blot=Quill.find(block);
      var index=blot ? quill.getIndex(blot) : -1;
      if(index>=0){quill.deleteText(index, Math.max(1, (blot.length ? blot.length() : block.innerText.length+1)), 'user');quill.setSelection(Math.max(0,index-1),0,'silent');return true;}
    }
    block.remove();
    return true;
  }
  function insertPaidBlockAtSelection(){
    if(!quill) return;
    var range=quill.getSelection(true) || {index:quill.getLength(), length:0};
    if(range.length>0) quill.deleteText(range.index, range.length, 'user');
    var text='在这里直接填写需要付费后才能查看的内容';
    quill.insertText(range.index, text + '\n', 'user');
    quill.formatLine(range.index, text.length, 'paidBlock', 'block', 'user');
    quill.formatLine(range.index + text.length + 1, 1, 'paidBlock', false, 'user');
    quill.setSelection(range.index, text.length, 'user');
  }
  function ensurePaidDeleteButton(block){
    if(!block || block.querySelector('.clay-paid-delete')) return;
    var btn=document.createElement('button');
    btn.type='button';
    btn.className='clay-paid-delete';
    btn.setAttribute('contenteditable','false');
    btn.textContent='删除';
    block.appendChild(btn);
  }
  function bindPaidDelete(){
    if(!quill) return;
    var editor=document.querySelector('#quill-editor .ql-editor');
    if(!editor || editor.dataset.paidDeleteBound==='1') return;
    editor.dataset.paidDeleteBound='1';
    editor.addEventListener('keydown',function(e){
      if(e.key!=='Backspace' && e.key!=='Delete') return;
      var block=paidBlockAtSelection();
      if(!block) return;
      if(e.altKey || e.metaKey || e.ctrlKey){
        e.preventDefault();deletePaidBlock(block);
      }
    });
    editor.addEventListener('click',function(e){
      if(e.target.closest && e.target.closest('.clay-paid-delete')){e.preventDefault();var delBlock=e.target.closest('.clay-paid-block,.clay-paid-block-block');deletePaidBlock(delBlock);return;}
      var block=e.target.closest && e.target.closest('.clay-paid-block,.clay-paid-block-block');
      editor.querySelectorAll('.clay-paid-block.is-selected,.clay-paid-block-block.is-selected').forEach(function(el){el.classList.remove('is-selected');});
      if(block){block.classList.add('is-selected');ensurePaidDeleteButton(block);}
    });
  }

  function bindPaidBlock(){
    var btn=document.getElementById('insertPaidBlock');
    if(!btn || btn.dataset.paidBound==='1') return;
    btn.dataset.paidBound='1';
    btn.addEventListener('click', function(){
      openPaidConfig(function(cfg){
      if(!quill){
        var text='\n[付费查看 '+cfg.price+' '+cfg.currency+']在这里直接填写需要付费后才能查看的内容[/付费查看]\n';
        var start=fallback.selectionStart||fallback.value.length;
        fallback.value=fallback.value.slice(0,start)+text+fallback.value.slice(start);
        fallback.focus();
        scheduleAutosave();
        return;
      }
      insertPaidBlockAtSelection();
      scheduleAutosave();
      });
    });
  }

  if(window.ClayMentionPicker){ClayMentionPicker.bind(function(el){return (el && (el.id==='fallback-editor' || el.classList.contains('ql-editor'))) ? el : null;});}

  var autosaveStatus = document.getElementById('draft-autosave-status');
  var draftIdInput = form.querySelector('input[name="draft_id"]');
  var csrfInput = form.querySelector('input[name="_csrf_token"]');
  var titleInput = form.querySelector('input[name="title"]');
  var sectionInput = document.getElementById('sectionIdInput') || form.querySelector('[name="section_id"]');
  var autosaveTimer = null;
  var autosaveBusy = false;
  var autosaveQueued = false;
  var lastAutosavePayload = '';

  function setAutosaveStatus(text, state){
    if(!autosaveStatus) return;
    autosaveStatus.textContent = text;
    autosaveStatus.className = 'draft-autosave-status' + (state ? ' is-' + state : '');
  }
  function currentContentForSave(){
    if(quill) return quill.root.innerHTML;
    return fallback.value.trim().replace(/[&<>]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c]; }).replace(/\n/g, '<br>');
  }
  function hasMeaningfulDraft(title, content){
    var plain = String(content || '').replace(/<[^>]+>/g,'').replace(/&nbsp;/g,' ').trim();
    return String(title || '').trim() !== '' || plain !== '';
  }
  function scheduleAutosave(delay){
    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(runAutosave, delay || 1200);
    setAutosaveStatus('正在等待自动保存…', 'saving');
  }
  function runAutosave(){
    var title = titleInput ? titleInput.value : '';
    var content = currentContentForSave();
    if(!hasMeaningfulDraft(title, content)){
      setAutosaveStatus('自动保存已开启', '');
      return;
    }
    var payloadKey = [draftIdInput ? draftIdInput.value : '', sectionInput ? sectionInput.value : '', title, content].join('||');
    if(payloadKey === lastAutosavePayload){
      setAutosaveStatus('草稿已保存', 'saved');
      return;
    }
    if(autosaveBusy){
      autosaveQueued = true;
      return;
    }
    autosaveBusy = true;
    setAutosaveStatus('正在自动保存…', 'saving');
    var fd = new FormData();
    if(csrfInput) fd.append('_csrf_token', csrfInput.value);
    fd.append('draft_id', draftIdInput ? draftIdInput.value : '0');
    fd.append('mode', 'autosave');
    fd.append('section_id', sectionInput ? sectionInput.value : '0');
    fd.append('title', title);
    fd.append('content', content);
    fetch('/index.php?path=draft/save',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){return r.json().catch(function(){throw new Error('自动保存响应异常');});})
      .then(function(data){
        if(!data.ok) throw new Error(data.error || '自动保存失败');
        if(data.draft_id && draftIdInput) draftIdInput.value = data.draft_id;
        lastAutosavePayload = [draftIdInput ? draftIdInput.value : '', sectionInput ? sectionInput.value : '', title, content].join('||');
        setAutosaveStatus(data.skipped ? '空草稿未保存' : ('草稿已自动保存 ' + (data.saved_at || '')), data.skipped ? '' : 'saved');
      })
      .catch(function(err){setAutosaveStatus(err.message || '自动保存失败，稍后会重试', 'error');})
      .finally(function(){
        autosaveBusy = false;
        if(autosaveQueued){autosaveQueued=false;scheduleAutosave(800);}
      });
  }
  var discardAutosaveBtn = document.getElementById('discard-autosave-btn');
  if(discardAutosaveBtn){
    discardAutosaveBtn.addEventListener('click', function(){
      if(!confirm('确认丢弃这份自动恢复内容？')) return;
      var fd = new FormData();
      if(csrfInput) fd.append('_csrf_token', csrfInput.value);
      fetch('/index.php?path=draft/discard-autosave',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){return r.json();})
        .then(function(data){if(!data.ok) throw new Error(data.error||'丢弃失败'); location.href='/index.php?path=publish';})
        .catch(function(err){alert(err.message||'丢弃失败');});
    });
  }

  if(titleInput) titleInput.addEventListener('input', function(){scheduleAutosave();});

  if(fallback) fallback.addEventListener('input', function(){scheduleAutosave();});
  if(quill) quill.on('text-change', function(){scheduleAutosave();});
  window.addEventListener('beforeunload', function(){
    if(autosaveTimer){clearTimeout(autosaveTimer); runAutosave();}
  });


  form.addEventListener('submit', function(e){
    if (!document.getElementById('sectionIdInput').value) { e.preventDefault(); alert('请选择板块'); return; }
    var content = '';
    if (quill) {
      if (quill.getText().trim().length === 0) {
        e.preventDefault(); alert('请输入帖子内容'); return;
      }
      content = quill.root.innerHTML;
    } else {
      content = fallback.value.trim();
      if (content.length === 0) {
        e.preventDefault(); alert('请输入帖子内容'); return;
      }
      content = content.replace(/[&<>]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c]; }).replace(/\n/g, '<br>');
    }
    hidden.value = content;
  });
})();
</script>
<?php require __DIR__ . '/../../layouts/theme-toggle.php'; ?>
<?php require __DIR__ . '/../layouts/bottom-nav.php'; ?>
</body>
</html>
