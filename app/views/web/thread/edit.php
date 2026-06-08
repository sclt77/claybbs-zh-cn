<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>编辑帖子</title>
  <link rel="stylesheet" href="/assets/css/style.css?v=202605081639">
  <link href="/public/assets/css/quill.snow.css" rel="stylesheet">
  <link href="/assets/css/editor-enhance.css?v=202605261930" rel="stylesheet">
  <style>
    .edit-thread-wrap{padding:16px 16px 96px}.edit-thread-card{max-width:860px;margin:0 auto;overflow:visible!important}.edit-form{display:flex;flex-direction:column;gap:14px}.clay-table-toolbar-btn,.clay-paid-toolbar-btn{width:28px!important;height:28px!important;border:1px solid rgba(2,132,199,.24)!important;border-radius:8px!important;background:rgba(2,132,199,.08)!important;color:var(--primary,#0284c7)!important;padding:0!important;display:inline-flex!important;align-items:center!important;justify-content:center!important}.clay-table-toolbar-btn svg,.clay-paid-toolbar-btn svg{width:16px;height:16px;display:block;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.paid-config-modal{position:fixed;inset:0;z-index:1600;display:none}.paid-config-modal.is-open{display:block}.paid-config-mask{position:absolute;inset:0;background:rgba(15,23,42,.46);backdrop-filter:blur(2px)}.paid-config-card{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:min(420px,calc(100vw - 32px));background:var(--card-bg,#fff);border:1px solid var(--line-soft,#e2e8f0);border-radius:18px;box-shadow:0 24px 80px rgba(15,23,42,.26);padding:16px}.paid-config-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}.paid-config-head strong{font-size:16px;color:var(--text-main,#0f172a)}.paid-config-close{border:0;background:transparent;color:#94a3b8;font-size:24px;line-height:1;cursor:pointer}.paid-config-grid{display:grid;gap:10px}.paid-config-grid label{display:grid;gap:6px;color:var(--text-soft,#64748b);font-size:12px;font-weight:900}.paid-config-grid input,.paid-config-grid select{height:38px;border:1px solid var(--line-soft,#e2e8f0);border-radius:12px;background:var(--input-bg,#f8fafc);color:var(--text-main,#0f172a);padding:0 11px;font-size:13px}.paid-config-tip{color:var(--text-muted,#94a3b8);font-size:12px;line-height:1.6}.paid-config-error{display:none;color:#ef4444;font-size:12px;font-weight:800}.paid-config-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:12px}.paid-config-actions button{height:34px;border-radius:999px;border:1px solid var(--line-soft,#e2e8f0);background:var(--card-bg,#fff);color:var(--text-soft,#64748b);padding:0 13px;font-size:12px;font-weight:950;cursor:pointer}.paid-config-actions button.primary{background:var(--primary,#0284c7);border-color:var(--primary,#0284c7);color:#fff}html[data-theme="dark"] .paid-config-card{background:#111827;border-color:#263244}html[data-theme="dark"] .paid-config-grid input,html[data-theme="dark"] .paid-config-grid select{background:#0f172a;border-color:#263244;color:#e5e7eb}.ql-editor p.clay-paid-block,.ql-editor p.clay-paid-block-block{margin:12px 0;padding:12px 14px;border:1px dashed rgba(2,132,199,.36);border-radius:12px;background:rgba(2,132,199,.06)}.ql-editor .clay-paid-block,.ql-editor .clay-paid-block-block{position:relative}.ql-editor .clay-paid-block.is-selected,.ql-editor .clay-paid-block-block.is-selected{outline:2px solid rgba(239,68,68,.35);outline-offset:2px}.ql-editor .clay-paid-block:after,.ql-editor .clay-paid-block-block:after{content:'Ctrl/Alt/⌘+退格删整块';display:none;margin-left:8px;color:#ef4444;font-size:11px;font-weight:900;opacity:.68}.ql-editor .clay-paid-delete{display:none;position:absolute;right:8px;top:8px;z-index:3;height:22px;padding:0 8px;border:1px solid rgba(239,68,68,.24);border-radius:999px;background:#fff1f2;color:#ef4444;font-size:11px;font-weight:900;line-height:20px;cursor:pointer}.ql-editor .clay-paid-block.is-selected .clay-paid-delete,.ql-editor .clay-paid-block-block.is-selected .clay-paid-delete{display:inline-flex;align-items:center}.ql-editor .clay-paid-block:before,.ql-editor .clay-paid-block-block:before{content:'付费查看';display:inline-flex;margin-right:8px;padding:2px 7px;border-radius:999px;background:rgba(2,132,199,.14);color:#0284c7;font-size:12px;font-weight:900}.edit-thread-title{margin:0 0 18px;font-size:22px;color:var(--text-main)}.alert-error{padding:12px;border-radius:12px;background:#fef2f2;color:#b91c1c}.review-note{margin:-4px 0 10px;padding:11px 12px;border-left:4px solid #f59e0b;background:#fffbeb;color:#92400e;font-size:13px;line-height:1.7;font-weight:700}.edit-actions{display:flex;gap:12px;flex-wrap:wrap}.edit-actions .btn{min-width:120px;text-decoration:none}@media(max-width:768px){.edit-thread-wrap{padding:12px 12px 96px}.edit-thread-card{border-radius:14px}.edit-thread-title{font-size:20px}.edit-actions{display:grid;grid-template-columns:1fr 1fr}.edit-actions .btn{width:100%;min-width:0}}
  </style>
</head>
<body>
<?php require __DIR__ . '/../layouts/topbar.php'; ?>
<div class="container edit-thread-wrap">
  <div class="card edit-thread-card">
    <h2 class="edit-thread-title">编辑帖子</h2>
    <div class="review-note">重新编辑会先审核修订版本；审核通过后才更新原帖，审核前原帖内容保持不变。</div>
    <?php if (!empty($error)): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" action="/index.php?path=thread/edit" id="edit-thread-form" class="edit-form">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$thread['id'] ?>">
      <input class="input" name="title" value="<?= htmlspecialchars((string)($_POST['title'] ?? $thread['title'] ?? '')) ?>" required>
      <div class="editor-shell" id="editor-shell">
        <?= clay_editor_toolbar() ?>
        <div id="quill-editor"></div>
        <textarea class="textarea fallback-editor" id="fallback-editor"><?= htmlspecialchars((string)($_POST['content'] ?? $thread['content'] ?? '')) ?></textarea>
        <input type="hidden" name="content" id="content-input">
        <input type="hidden" name="paid_visible_enabled" id="paidVisibleEnabled" value="<?= !empty($_POST['paid_visible_enabled']) || !empty($thread['paid_visible_enabled']) ? '1' : '0' ?>">
        <input type="hidden" name="paid_visible_price" id="paidVisiblePrice" value="<?= htmlspecialchars((string)($_POST['paid_visible_price'] ?? $thread['paid_visible_price'] ?? '')) ?>">
        <input type="hidden" name="paid_visible_currency" id="paidVisibleCurrency" value="<?= htmlspecialchars((string)($_POST['paid_visible_currency'] ?? $thread['paid_visible_currency'] ?? '')) ?>">
      </div>
      

      <div class="edit-actions">
        <button class="btn" type="submit">保存修改</button>
        <a class="btn btn-light" href="/index.php?path=thread&id=<?= (int)$thread['id'] ?>">取消</a>
      </div>
    </form>
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
      <label>货币<select id="paidConfigCurrency"><option value="">选择货币</option><?php foreach(($currencies ?? []) as $c): ?><?php $p=(int)($c['precision'] ?? 0); ?><option value="<?= htmlspecialchars((string)$c['code']) ?>" data-precision="<?= $p ?>"><?= htmlspecialchars((string)$c['name']) ?></option><?php endforeach; ?></select></label>
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
  var form=document.getElementById('edit-thread-form');
  var shell=document.getElementById('editor-shell');
  var hidden=document.getElementById('content-input');
  var fallback=document.getElementById('fallback-editor');
  var old=<?= json_encode((string)($_POST['content'] ?? $thread['content'] ?? '')) ?>;
  var quill=null;
  function plain(html){return (html||'').replace(/<br\s*\/?>/gi,'\n').replace(/<[^>]+>/g,'');}
  function fallbackOn(){shell.classList.add('editor-fallback');fallback.style.display='block';if(old&&!fallback.value)fallback.value=plain(old);}
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
      if(!quill){var start=fallback.selectionStart||fallback.value.length;fallback.value=fallback.value.slice(0,start)+html+fallback.value.slice(start);fallback.focus();closeTableConfig();return;}
      var range=quill.getSelection(true)||{index:quill.getLength(),length:0};quill.clipboard.dangerouslyPasteHTML(range.index,html);quill.setSelection(range.index+1,0);
    }
    
    closeTableConfig();
  }
  document.addEventListener('click',function(e){
    if(e.target.closest('[data-table-cancel]')) closeTableConfig();
    if(e.target.closest('#tableConfigConfirm')) confirmTableConfig();
    if(e.target.closest('[data-table-delete]')){if(activeTable){activeTable.remove();}closeTableConfig();}
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
          return;
        }
        insertPaidBlockAtSelection();
      });
    });
  }

  if (typeof window.Quill !== 'undefined') {
    try {
      var Parchment = Quill.import('parchment');
      var PaidClass = new Parchment.ClassAttributor('paidBlock', 'clay-paid', {scope: Parchment.Scope.BLOCK, whitelist:['block']});
      Quill.register(PaidClass, true);
    } catch(e) {}
  }

  if(typeof window.Quill==='undefined'){
    fallbackOn();
  }else{
    try{
      quill=new Quill('#quill-editor',{theme:'snow',placeholder:'请输入帖子内容...',modules:{toolbar: '#quill-toolbar'}});
      if(old)quill.clipboard.dangerouslyPasteHTML(old);
      bindImageUpload();
      bindTableBlock();
      bindPaidBlock();
      bindPaidDelete();
      
if(window.ClayMentionPicker){ClayMentionPicker.bindQuill(quill, shell);}
    }catch(e){fallbackOn();quill=null;}
  }
  if(window.ClayMentionPicker){ClayMentionPicker.bind(function(el){return (el && (el.id==='fallback-editor' || el.classList.contains('ql-editor'))) ? el : null;});}
  form.addEventListener('submit',function(e){
    var c='';
    if(quill){
      if(quill.getText().trim()===''){e.preventDefault();alert('请输入内容');return;}
      c=quill.root.innerHTML;
    }else{
      c=fallback.value.trim();
      if(!c){e.preventDefault();alert('请输入内容');return;}
      c=c.replace(/[&<>]/g,function(x){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[x]}).replace(/\n/g,'<br>');
    }
    hidden.value=c;
  });
})();
</script>
<?php require __DIR__ . '/../../layouts/theme-toggle.php'; ?>
<?php require __DIR__ . '/../layouts/bottom-nav.php'; ?>
</body>
</html>
