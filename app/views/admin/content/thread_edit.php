<?php
$pageTitle = '编辑帖子';
require dirname(__DIR__) . '/layouts/main.php';
$thread = $thread ?? [];
$sections = $sections ?? [];
$currencies = $currencies ?? [];
$currentContent = (string)($_POST['content'] ?? $thread['content'] ?? '');
$paidEnabledValue = !empty($_POST) ? !empty($_POST['paid_visible_enabled']) : !empty($thread['paid_visible_enabled']);
$paidPriceValue = (string)($_POST['paid_visible_price'] ?? $thread['paid_visible_price'] ?? '');
$paidCurrencyValue = (string)($_POST['paid_visible_currency'] ?? $thread['paid_visible_currency'] ?? '');
$adminSelectedSectionId = (int)($_POST['section_id'] ?? $thread['section_id'] ?? 0);
$adminSelectedSectionName = '选择板块';
foreach (($sections ?? []) as $section) {
    if ((int)($section['id'] ?? 0) === $adminSelectedSectionId) {
        $adminSelectedSectionName = (string)(($section['category_name'] ?? '') ? $section['category_name'] . ' / ' . $section['name'] : $section['name']);
        break;
    }
}
?>
<link href="/public/assets/css/quill.snow.css" rel="stylesheet">
<link href="/assets/css/editor-enhance.css?v=202605261930" rel="stylesheet">




<div class="page-header">
  <div class="page-title">编辑帖子</div>
  <a class="btn btn-light admin-link-clean" href="/admin.php?path=threads">返回帖子管理</a>
</div>
<div class="card thread-edit-card">
  <?php if (!empty($error)): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" action="/admin.php?path=threads/edit" id="admin-thread-edit-form" class="thread-edit-form" data-no-ajax>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)($thread['id'] ?? 0) ?>">
    <input type="hidden" name="section_id" id="sectionIdInput" value="<?= $adminSelectedSectionId > 0 ? $adminSelectedSectionId : '' ?>">
    <div class="thread-edit-grid">
      <div class="thread-title-row">
        <input class="input" name="title" placeholder="帖子标题" value="<?= htmlspecialchars((string)($_POST['title'] ?? $thread['title'] ?? '')) ?>" required>
        <button class="section-picker-trigger" type="button" id="sectionPickerOpen" title="选择板块"><span id="sectionPickerLabel"><?= htmlspecialchars($adminSelectedSectionName) ?></span></button>
      </div>
      <select class="select" name="status" required>
        <?php foreach (['published'=>'已发布','pending'=>'待审核','hidden'=>'已屏蔽','deleted'=>'已删除'] as $value => $label): ?>
          <option value="<?= $value ?>" <?= ((string)($_POST['status'] ?? $thread['status'] ?? 'published') === $value) ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="editor-shell" id="editor-shell">
      <?= clay_editor_toolbar() ?>
      <div id="quill-editor"></div>
      <textarea class="textarea fallback-editor" id="fallback-editor"><?= htmlspecialchars($currentContent) ?></textarea>
      <input type="hidden" name="content" id="content-input">
      <input type="hidden" name="paid_visible_enabled" id="paidVisibleEnabled" value="<?= $paidEnabledValue ? '1' : '0' ?>">
      <input type="hidden" name="paid_visible_price" id="paidVisiblePrice" value="<?= htmlspecialchars($paidPriceValue) ?>">
      <input type="hidden" name="paid_visible_currency" id="paidVisibleCurrency" value="<?= htmlspecialchars($paidCurrencyValue) ?>">
    </div>
    <div class="editor-tip" id="editor-tip">支持标题、加粗、列表、引用、居中、表格、链接、图片和付费查看内容块。</div>
    <div class="thread-edit-actions">
      <button class="btn" type="submit">保存修改</button>
      <a class="btn btn-light" href="/index.php?path=thread&id=<?= (int)($thread['id'] ?? 0) ?>" target="_blank">查看帖子</a>
      <a class="btn btn-light" href="/admin.php?path=threads">取消</a>
    </div>
  </form>
</div>

<div class="section-picker-modal" id="sectionPickerModal" aria-hidden="true"><div class="section-picker-page"><div class="section-picker-head"><button class="section-picker-back" type="button" data-section-close>‹</button><strong>选择板块</strong><span></span></div><div class="section-picker-body"><?php $lastCategory = null; foreach (($sections ?? []) as $section): ?><?php $cat = (string)($section['category_name'] ?? '默认分区'); if ($cat !== $lastCategory): if ($lastCategory !== null): ?></div><?php endif; ?><div class="section-picker-group"><div class="section-picker-group-title"><?= htmlspecialchars($cat) ?></div><?php $lastCategory = $cat; endif; ?><?php $sid = (int)($section['id'] ?? 0); $icon = trim((string)($section['icon'] ?? '')); ?><button class="section-picker-item <?= $adminSelectedSectionId === $sid ? 'is-active' : '' ?>" type="button" data-section-id="<?= $sid ?>" data-section-name="<?= htmlspecialchars((string)(($section['category_name'] ?? '') ? $section['category_name'] . ' / ' . $section['name'] : $section['name']), ENT_QUOTES) ?>"><span class="section-picker-icon"><?php if ($icon !== '' && preg_match('#^https?://|^/#', $icon)): ?><img src="<?= htmlspecialchars($icon) ?>" alt=""><?php else: ?><?= htmlspecialchars($icon !== '' ? $icon : mb_substr((string)($section['name'] ?? '板'), 0, 1)) ?><?php endif; ?></span><span><span class="section-picker-name"><?= htmlspecialchars((string)($section['name'] ?? '')) ?></span><span class="section-picker-count"><?= (int)($section['thread_count'] ?? 0) ?> 帖子</span></span><span class="section-picker-choose"><?= $adminSelectedSectionId === $sid ? '已选' : '选择' ?></span></button><?php endforeach; if ($lastCategory !== null): ?></div><?php endif; ?></div></div></div>
<div class="paid-config-modal table-config-modal" id="tableConfigModal" aria-hidden="true"><div class="paid-config-mask" data-table-cancel></div><div class="paid-config-card" role="dialog" aria-modal="true" aria-labelledby="tableConfigTitle"><div class="paid-config-head"><strong id="tableConfigTitle">插入表格</strong><button class="paid-config-close" type="button" data-table-cancel>×</button></div><div class="paid-config-grid"><label>行数<input type="number" min="1" max="12" id="tableConfigRows" value="2"></label><label>列数<input type="number" min="1" max="8" id="tableConfigCols" value="2"></label><div class="paid-config-tip" id="tableConfigTip">设置后会在正文当前位置插入表格。</div><div class="paid-config-error" id="tableConfigError"></div></div><div class="paid-config-actions"><button type="button" data-table-delete id="tableDeleteBtn" class="admin-modal-error">删除表格</button><button type="button" data-table-cancel>取消</button><button class="primary" type="button" id="tableConfigConfirm">确定</button></div></div></div>
<div class="paid-config-modal" id="paidConfigModal" aria-hidden="true"><div class="paid-config-mask" data-paid-cancel></div><div class="paid-config-card" role="dialog" aria-modal="true" aria-labelledby="paidConfigTitle"><div class="paid-config-head"><strong id="paidConfigTitle">设置付费查看</strong><button class="paid-config-close" type="button" data-paid-cancel>×</button></div><div class="paid-config-grid"><label>价格<input type="number" min="0" step="0.000001" id="paidConfigPrice" placeholder="输入价格"></label><label>货币<select id="paidConfigCurrency"><option value="">选择货币</option><?php foreach($currencies as $c): ?><option value="<?= htmlspecialchars((string)$c['code']) ?>"><?= htmlspecialchars((string)$c['name']) ?></option><?php endforeach; ?></select></label><div class="paid-config-tip">确认后会在正文当前位置插入付费内容块，你可以直接在块里输入付费内容。</div><div class="paid-config-error" id="paidConfigError"></div></div><div class="paid-config-actions"><button type="button" data-paid-cancel>取消</button><button class="primary" type="button" id="paidConfigConfirm">插入</button></div></div></div>
<script src="/public/assets/js/quill.js"></script>
<script>
(function(){
  var form=document.getElementById('admin-thread-edit-form'), shell=document.getElementById('editor-shell'), hidden=document.getElementById('content-input'), fallback=document.getElementById('fallback-editor'), tip=document.getElementById('editor-tip');
  var oldContent=<?= json_encode($currentContent) ?>, quill=null;

  function bindSectionPicker(){var modal=document.getElementById('sectionPickerModal'),open=document.getElementById('sectionPickerOpen'),input=document.getElementById('sectionIdInput'),label=document.getElementById('sectionPickerLabel');if(!modal||!open||!input||!label)return;function close(){modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');}open.addEventListener('click',function(){modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');});modal.addEventListener('click',function(e){if(e.target.closest('[data-section-close]')){close();return;}var item=e.target.closest('[data-section-id]');if(!item)return;input.value=item.getAttribute('data-section-id')||'';label.textContent=item.getAttribute('data-section-name')||'选择板块';modal.querySelectorAll('.section-picker-item').forEach(function(el){el.classList.toggle('is-active',el===item);var choose=el.querySelector('.section-picker-choose');if(choose)choose.textContent=el===item?'已选':'选择';});close();});document.addEventListener('keydown',function(e){if(e.key==='Escape'&&modal.classList.contains('is-open'))close();});}
  bindSectionPicker();
  function plain(html){return (html||'').replace(/<br\s*\/?>/gi,'\n').replace(/<[^>]+>/g,'');}
  function enableFallback(message){shell.classList.add('editor-fallback');fallback.style.display='block';if(oldContent&&!fallback.value)fallback.value=plain(oldContent);if(tip)tip.textContent=message||'富文本编辑器加载失败，已切换为普通文本编辑。';}
  if (typeof window.Quill !== 'undefined') {try{var Parchment=Quill.import('parchment');var PaidClass=new Parchment.ClassAttributor('paidBlock','clay-paid',{scope:Parchment.Scope.BLOCK,whitelist:['block']});Quill.register(PaidClass,true);}catch(e){}}
  function uploadImage(file, cb){var fd=new FormData();fd.append('image',file);var csrfInput=document.querySelector('input[name="_csrf_token"]');if(csrfInput)fd.append('_csrf_token',csrfInput.value);fetch('/index.php?path=upload/image',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}}).then(function(r){return r.text().then(function(text){var data=null;try{data=JSON.parse(text);}catch(e){throw new Error('上传接口响应异常');}if(!r.ok||!data.ok)throw new Error((data&&data.error)||('HTTP '+r.status));return data;});}).then(function(data){cb(data.url);}).catch(function(err){alert('图片上传失败：'+err.message);});}
  function bindImageUpload(){if(!quill)return;quill.getModule('toolbar').addHandler('image',function(){var input=document.createElement('input');input.type='file';input.accept='image/jpeg,image/png,image/gif,image/webp';input.onchange=function(){var file=input.files&&input.files[0];if(!file)return;uploadImage(file,function(url){var range=quill.getSelection(true);quill.insertEmbed(range.index,'image',url);quill.setSelection(range.index+1);});};input.click();});}
  var activeTable=null;
  function currentTable(){var sel=window.getSelection&&window.getSelection();if(!sel||!sel.rangeCount)return null;var node=sel.anchorNode;if(node&&node.nodeType===3)node=node.parentNode;return node&&node.closest?node.closest('#quill-editor table'):null;}
  function buildTableHtml(rows,cols){rows=Math.max(1,Math.min(12,parseInt(rows||2,10)||2));cols=Math.max(1,Math.min(8,parseInt(cols||2,10)||2));var html='<table><tbody>';for(var r=0;r<rows;r++){html+='<tr>';for(var c=0;c<cols;c++){html+='<td>'+(r===0?'表头':'内容')+'</td>';}html+='</tr>';}return html+'</tbody></table><p><br></p>';}
  function resizeTable(table,rows,cols){rows=Math.max(1,Math.min(12,parseInt(rows||2,10)||2));cols=Math.max(1,Math.min(8,parseInt(cols||2,10)||2));var tbody=table.tBodies[0]||table.appendChild(document.createElement('tbody'));while(tbody.rows.length<rows)tbody.appendChild(document.createElement('tr'));while(tbody.rows.length>rows)tbody.deleteRow(tbody.rows.length-1);Array.prototype.forEach.call(tbody.rows,function(row,r){while(row.cells.length<cols){var td=document.createElement('td');td.textContent=r===0?'表头':'内容';row.appendChild(td);}while(row.cells.length>cols)row.deleteCell(row.cells.length-1);});}
  function openTableConfig(table){var modal=document.getElementById('tableConfigModal'), rows=document.getElementById('tableConfigRows'), cols=document.getElementById('tableConfigCols'), title=document.getElementById('tableConfigTitle'), del=document.getElementById('tableDeleteBtn'), err=document.getElementById('tableConfigError'), tipBox=document.getElementById('tableConfigTip');if(!modal||!rows||!cols)return;activeTable=table||null;if(activeTable){rows.value=activeTable.rows.length||2;cols.value=activeTable.rows[0]?activeTable.rows[0].cells.length:2;}else{rows.value=2;cols.value=2;}if(title)title.textContent=activeTable?'修改表格':'插入表格';if(tipBox)tipBox.textContent=activeTable?'调整行数/列数，或删除当前表格。':'设置后会在正文当前位置插入表格。';if(del)del.style.display=activeTable?'inline-flex':'none';if(err){err.style.display='none';err.textContent='';}modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');setTimeout(function(){rows.focus();},30);}
  function closeTableConfig(){var modal=document.getElementById('tableConfigModal');if(modal){modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');}activeTable=null;}
  function confirmTableConfig(){var rows=document.getElementById('tableConfigRows'), cols=document.getElementById('tableConfigCols'), err=document.getElementById('tableConfigError');var r=parseInt(rows&&rows.value,10)||0,c=parseInt(cols&&cols.value,10)||0;if(r<1||c<1){if(err){err.textContent='请填写有效的行数和列数';err.style.display='block';}return;}if(activeTable){resizeTable(activeTable,r,c);}else{var html=buildTableHtml(r,c);if(!quill){var start=fallback.selectionStart||fallback.value.length;fallback.value=fallback.value.slice(0,start)+html+fallback.value.slice(start);fallback.focus();closeTableConfig();return;}var range=quill.getSelection(true)||{index:quill.getLength(),length:0};quill.clipboard.dangerouslyPasteHTML(range.index,html);quill.setSelection(range.index+1,0);}closeTableConfig();}
  document.addEventListener('click',function(e){if(e.target.closest('[data-table-cancel]'))closeTableConfig();if(e.target.closest('#tableConfigConfirm'))confirmTableConfig();if(e.target.closest('[data-table-delete]')){if(activeTable)activeTable.remove();closeTableConfig();}});
  document.addEventListener('keydown',function(e){var modal=document.getElementById('tableConfigModal');if(!modal||!modal.classList.contains('is-open'))return;if(e.key==='Escape')closeTableConfig();if(e.key==='Enter'&&(e.target.id==='tableConfigRows'||e.target.id==='tableConfigCols')){e.preventDefault();confirmTableConfig();}});
  function bindTableBlock(){var btn=document.getElementById('insertTableBlock');if(btn)btn.addEventListener('click',function(){openTableConfig(currentTable());});}
  function paidDefaults(){return{enabled:document.getElementById('paidVisibleEnabled'),price:document.getElementById('paidVisiblePrice'),currency:document.getElementById('paidVisibleCurrency')}}
  var paidInsertCallback=null;
  function openPaidConfig(callback){var modal=document.getElementById('paidConfigModal'), priceInput=document.getElementById('paidConfigPrice'), currencyInput=document.getElementById('paidConfigCurrency'), errorBox=document.getElementById('paidConfigError'), fields=paidDefaults();if(!modal||!priceInput||!currencyInput)return;paidInsertCallback=callback;priceInput.value=fields.price?fields.price.value:'';currencyInput.value=fields.currency?fields.currency.value:'';if(errorBox){errorBox.style.display='none';errorBox.textContent='';}modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');setTimeout(function(){priceInput.focus();},30);}
  function closePaidConfig(){var modal=document.getElementById('paidConfigModal');if(modal){modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');}paidInsertCallback=null;}
  function confirmPaidConfig(){var priceInput=document.getElementById('paidConfigPrice'), currencyInput=document.getElementById('paidConfigCurrency'), errorBox=document.getElementById('paidConfigError');var price=priceInput?String(priceInput.value||'').trim():'', currency=currencyInput?String(currencyInput.value||'').trim().toUpperCase():'';if(!price||Number(price)<=0||!currency){if(errorBox){errorBox.textContent='请填写大于 0 的价格并选择货币';errorBox.style.display='block';}return;}var fields=paidDefaults();if(fields.enabled)fields.enabled.value='1';if(fields.price)fields.price.value=price;if(fields.currency)fields.currency.value=currency;var cb=paidInsertCallback;closePaidConfig();if(cb)cb({price:price,currency:currency});}
  document.addEventListener('click',function(e){if(e.target.closest('[data-paid-cancel]'))closePaidConfig();if(e.target.closest('#paidConfigConfirm'))confirmPaidConfig();});
  document.addEventListener('keydown',function(e){var modal=document.getElementById('paidConfigModal');if(!modal||!modal.classList.contains('is-open'))return;if(e.key==='Escape')closePaidConfig();if(e.key==='Enter'&&(e.target.id==='paidConfigPrice'||e.target.id==='paidConfigCurrency')){e.preventDefault();confirmPaidConfig();}});

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

  function bindPaidBlock(){var btn=document.getElementById('insertPaidBlock');if(!btn||btn.dataset.paidBound==='1')return;btn.dataset.paidBound='1';btn.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();openPaidConfig(function(cfg){if(!quill){var text='\n[付费查看 '+cfg.price+' '+cfg.currency+']在这里直接填写需要付费后才能查看的内容[/付费查看]\n';var start=fallback.selectionStart||fallback.value.length;fallback.value=fallback.value.slice(0,start)+text+fallback.value.slice(start);fallback.focus();return;}insertPaidBlockAtSelection();});});}
  if(typeof window.Quill==='undefined'){enableFallback('富文本编辑器资源加载失败，已切换为普通文本编辑。');}else{try{quill=new Quill('#quill-editor',{theme:'snow',placeholder:'请输入帖子内容...',modules:{toolbar:'#quill-toolbar'}});if(oldContent)quill.clipboard.dangerouslyPasteHTML(oldContent);bindImageUpload();bindTableBlock();bindPaidBlock();bindPaidDelete();}catch(e){enableFallback('富文本编辑器初始化失败，已切换为普通文本编辑。');quill=null;}}
  form.addEventListener('submit',function(e){if(!document.getElementById('sectionIdInput').value){e.preventDefault();alert('请选择板块');return;}var content='';if(quill){if(quill.getText().trim()===''){e.preventDefault();alert('请输入帖子内容');return;}content=quill.root.innerHTML;}else{content=fallback.value.trim();if(!content){e.preventDefault();alert('请输入帖子内容');return;}content=content.replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c];}).replace(/\n/g,'<br>');}hidden.value=content;});
})();
</script>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
