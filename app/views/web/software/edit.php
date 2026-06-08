<?php
$__pageTitle = '编辑投稿';
require dirname(__DIR__) . '/layouts/main.php';

$platforms = ['android' => 'Android', 'ios' => 'iOS', 'windows' => 'Windows', 'macos' => 'macOS'];
$typeOptions = ['' => '无'];
foreach (($softwareTypes ?? []) as $t) { $typeOptions[(string)$t['slug']] = (string)$t['name']; }
?>

<style>
.edit-page{padding:0 0 80px;background:var(--bg-main,#f5f5f5);min-height:100vh}
.edit-header{background:var(--card-bg,#fff);padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--line-soft,#f0f0f0)}
.edit-header h1{font-size:18px;font-weight:900;flex:1;color:var(--text-main,#0f172a)}
.edit-header a{color:var(--text-muted,#94a3b8);text-decoration:none;font-size:14px}

.status-note{margin:12px;padding:12px 16px;border-radius:12px;font-size:14px;background:var(--bg-soft,#f8fafc);border-left:4px solid #f59e0b}
.status-note.rejected{border-left-color:#ef4444}

.edit-form{background:var(--card-bg,#fff);margin:12px;border-radius:16px;padding:20px}
.edit-form h2{font-size:16px;font-weight:800;margin:0 0 16px;color:var(--text-main,#0f172a)}
.form-group{margin-bottom:14px}
.form-group label{display:block;font-size:13px;font-weight:700;margin-bottom:5px;color:var(--text-soft,#64748b)}
.form-group input,.form-group textarea,.form-group select{width:100%;padding:10px 14px;border:1px solid var(--line-soft,#e2e8f0);border-radius:12px;font-size:14px;background:var(--card-bg,#fff);color:var(--text-main,#0f172a);box-sizing:border-box}
.form-group input[type="file"]{padding:9px 12px;background:var(--bg-soft,#f8fafc)}
.form-help{font-size:12px;color:var(--text-muted,#94a3b8);margin-top:6px;line-height:1.5}
.form-group textarea{min-height:100px;resize:vertical}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.btn-submit{width:100%;padding:12px;background:#3cc9a4;color:#fff;border:none;border-radius:99px;font-size:15px;font-weight:800;cursor:pointer;margin-top:8px}.upload-card-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.upload-card{position:relative;min-height:118px;border:1.5px dashed var(--line-soft,#cbd5e1);border-radius:16px;background:var(--bg-soft,#f8fafc);display:grid;place-items:center;overflow:hidden;cursor:pointer;transition:.18s}.upload-card:hover{border-color:#3cc9a4;box-shadow:0 10px 24px rgba(60,201,164,.12)}.upload-card input{position:absolute;inset:0;opacity:0;cursor:pointer}.upload-card img{width:100%;height:100%;object-fit:cover;position:absolute;inset:0}.upload-card .upload-empty{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;color:var(--text-muted,#94a3b8);font-size:12px;font-weight:850;gap:8px;z-index:1;line-height:1.25;transform:none}.upload-card .upload-empty svg{width:24px;height:24px;display:block;flex:0 0 auto;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.upload-card .upload-empty span{display:block}.logo-upload-card{width:116px;height:116px;border-radius:24px}.logo-upload-card img{border-radius:24px}.screenshot-card{aspect-ratio:16/10;min-height:auto}.upload-card.has-image{border-style:solid;border-color:rgba(60,201,164,.7)}.upload-card.has-image .upload-empty{opacity:0}.upload-remove{position:absolute;right:8px;top:8px;width:26px;height:26px;border:0;border-radius:999px;background:rgba(15,23,42,.72);color:#fff;z-index:3;display:grid;place-items:center;cursor:pointer}.upload-card:not(.has-image) .upload-remove{display:none}.upload-tip{font-size:12px;color:var(--text-muted,#94a3b8);margin-top:8px;line-height:1.5}@media(max-width:720px){.upload-card-grid{grid-template-columns:repeat(2,1fr)}}

html[data-theme="dark"] .edit-header{background:#1e293b;border-color:#263244}
html[data-theme="dark"] .edit-form{background:#1e293b}
html[data-theme="dark"] .edit-form h2{color:#e5e7eb}
html[data-theme="dark"] .form-group input,html[data-theme="dark"] .form-group textarea,html[data-theme="dark"] .form-group select{background:#0f172a;border-color:#334155;color:#e5e7eb}
html[data-theme="dark"] .status-note{background:#0f172a}
html[data-theme="dark"] .edit-page{background:#0f172a}html[data-theme="dark"] .upload-card{background:#0f172a;border-color:#334155}
</style>

<div class="edit-page">
  <div class="edit-header">
    <h1>编辑投稿</h1>
    <a href="/index.php?path=software/submission">← 返回</a>
  </div>

  <?php if (!empty($software['admin_note'])): ?>
  <div class="status-note <?= $software['status'] ?>">
    <strong>管理员备注：</strong><?= htmlspecialchars($software['admin_note']) ?>
  </div>
  <?php endif; ?>

  <div class="edit-form">
    <form id="editForm" action="/index.php?path=software/submission/update" method="post" enctype="multipart/form-data" onsubmit="return handleUpdate(this)">
      <input type="hidden" name="id" value="<?= (int)$software['id'] ?>">
      <div class="form-row">
        <div class="form-group"><label>软件名称 *</label><input type="text" name="name" value="<?= htmlspecialchars($software['name']) ?>" required></div>
        <div class="form-group"><label>标识 *</label><input type="text" name="slug" value="<?= htmlspecialchars($software['slug']) ?>" required pattern="[a-zA-Z0-9_-]+"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>平台 *</label><select name="platform" required><?php foreach ($platforms as $k => $v): ?><option value="<?= $k ?>" <?= $software['platform'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>分类</label><select name="category_id"><option value="">选择分类</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ($software['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>类型</label><select name="type"><?php foreach ($typeOptions as $k => $v): ?><option value="<?= htmlspecialchars($k) ?>" <?= ($software['type'] ?? '') === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Logo</label><label class="upload-card logo-upload-card<?= !empty($software['icon']) ? ' has-image' : '' ?>" data-preview-card><?php if (!empty($software['icon'])): ?><img class="preview-img" src="<?= htmlspecialchars($software['icon']) ?>" alt="当前 Logo"><?php endif; ?><input type="file" name="icon" accept="image/jpeg,image/png,image/webp,image/gif" data-preview-input><span class="upload-empty"><svg viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg><span><?= !empty($software['icon']) ? '更换 Logo' : '上传 Logo' ?></span></span><button type="button" class="upload-remove" data-remove-preview>×</button></label><div class="form-help">当前投稿必须有 Logo。重新上传会替换原 Logo；支持 JPG / PNG / WEBP / GIF，最大 2MB。</div></div>
        <div class="form-group"><label>版本</label><input type="text" name="version" value="<?= htmlspecialchars($software['version']) ?>"></div>
      </div>
      <div class="form-group"><label>开发者</label><input type="text" name="developer" value="<?= htmlspecialchars($software['developer'] ?? '') ?>"></div>
      <div class="form-group"><label>应用大小</label><input type="text" name="size" value="<?= htmlspecialchars($software['size'] ?? '') ?>" placeholder="例如：18MB、1.2GB、245KB，由用户自行填写"></div>
      <div class="form-group"><label>下载链接 *</label><input type="url" name="download_url" value="<?= htmlspecialchars($software['download_url']) ?>" required></div>
      <div class="form-group"><label>简介</label><input type="text" name="description" value="<?= htmlspecialchars($software['description'] ?? '') ?>" maxlength="200"></div>
      <div class="form-group"><label>详细介绍</label><textarea name="detail"><?= htmlspecialchars($software['detail'] ?? '') ?></textarea></div>
      <div class="form-group"><label>应用展示图</label><div class="upload-card-grid" data-screenshot-grid>
        <?php for ($i=0;$i<6;$i++): $shot=$screenshots[$i] ?? null; ?>
        <label class="upload-card screenshot-card<?= !empty($shot['image_path']) ? ' has-image' : '' ?>" data-preview-card><?php if (!empty($shot['image_path'])): ?><img class="preview-img" src="<?= htmlspecialchars($shot['image_path']) ?>" alt="展示图 <?= $i+1 ?>"><?php endif; ?><input type="file" name="screenshots[]" accept="image/*" data-preview-input><span class="upload-empty"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M8 13l2.5-2.5L14 14l2-2 3 3"/></svg><span>展示图 <?= $i+1 ?></span></span><button type="button" class="upload-remove" data-remove-preview>×</button></label>
        <?php endfor; ?>
      </div><div class="upload-tip">最多 6 张。上传新图片后提交，会整体替换展示图；不修改则保留当前展示图。</div></div>
      <button type="submit" class="btn-submit">重新提交审核</button>
    </form>
  </div>
</div>

<script>

function initUploadPreviews(root){
  root=root||document;
  root.querySelectorAll('[data-preview-card]').forEach(function(card){
    var input=card.querySelector('[data-preview-input]');
    var remove=card.querySelector('[data-remove-preview]');
    if(!input) return;
    input.addEventListener('change',function(){
      var file=input.files&&input.files[0];
      if(!file) return;
      if(!file.type || file.type.indexOf('image/')!==0){alert('请选择图片文件'); input.value=''; return;}
      var old=card.querySelector('img.preview-img'); if(old) old.remove();
      var img=document.createElement('img'); img.className='preview-img'; img.alt='预览图'; img.src=URL.createObjectURL(file); card.appendChild(img); card.classList.add('has-image');
    });
    if(remove){remove.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();input.value='';var img=card.querySelector('img.preview-img');if(img)img.remove();card.classList.remove('has-image');});}
  });
}
initUploadPreviews(document);

function handleUpdate(form){
  var fd=new FormData(form);
  var btn=form.querySelector('.btn-submit');
  if(btn){btn.disabled=true;btn.textContent='提交中...';}
  fetch(form.action,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
    if(d.ok){alert('更新成功，等待审核');location.href='/index.php?path=software/submission';}
    else alert(d.error||'更新失败');
  }).catch(function(e){alert('请求失败：'+e.message);}).finally(function(){
    if(btn){btn.disabled=false;btn.textContent='重新提交审核';}
  });
  return false;
}
</script>

<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>