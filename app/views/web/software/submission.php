<?php
$__pageTitle = '我的投稿';
require dirname(__DIR__) . '/layouts/main.php';

$platforms = ['android' => 'Android', 'ios' => 'iOS', 'windows' => 'Windows', 'macos' => 'macOS'];
$statusLabels = ['draft' => '草稿', 'pending' => '审核中', 'published' => '已通过', 'rejected' => '已拒绝', 'removed' => '已下架'];
$statusColors = ['draft' => '#94a3b8', 'pending' => '#f59e0b', 'published' => '#10b981', 'rejected' => '#ef4444', 'removed' => '#ef4444'];
$typeOptions = ['' => '无'];
foreach (($softwareTypes ?? []) as $t) { $typeOptions[(string)$t['slug']] = (string)$t['name']; }
?>

<style>
/* 软件投稿中心层级修复：顶部用户头像下拉菜单必须高于本页 sticky 投稿头部 */
body:has(.submit-page) .topbar{z-index:3000!important;overflow:visible!important}
body:has(.submit-page) .topbar-actions,body:has(.submit-page) .user-dropdown{position:relative;z-index:3010!important;overflow:visible!important}
body:has(.submit-page) .dropdown-menu{z-index:3020!important}
.submit-page{padding:0 0 88px;background:radial-gradient(circle at 12% 0%,rgba(60,201,164,.14),transparent 30%),linear-gradient(180deg,var(--bg-main,#f8fafc),var(--bg-main,#f5f5f5));min-height:100vh;position:relative;z-index:1}
.submit-shell{max-width:1120px;margin:0 auto;padding:14px 12px 0}
.submit-header{position:sticky;top:58px;z-index:70;background:rgba(255,255,255,.88);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);padding:14px;border:1px solid rgba(226,232,240,.86);border-radius:22px;box-shadow:0 14px 38px rgba(15,23,42,.06);display:grid;gap:14px;margin-bottom:12px}
.submit-header-main{display:flex;align-items:center;gap:12px}.submit-header-copy{flex:1;min-width:0}.submit-header h1{font-size:21px;line-height:1.15;font-weight:950;margin:0;color:var(--text-main,#0f172a);letter-spacing:-.5px}.submit-header p{margin:5px 0 0;font-size:13px;line-height:1.55;color:var(--text-muted,#94a3b8)}
.submit-tabs{display:grid;grid-template-columns:1fr 1fr;gap:8px;background:var(--bg-soft,#f8fafc);border-radius:18px;padding:6px}.submit-tab{border:0;border-radius:14px;min-height:54px;padding:8px 10px;background:transparent;color:var(--text-soft,#64748b);font:inherit;text-align:left;cursor:pointer;display:flex;align-items:center;gap:10px;transition:background .18s ease,color .18s ease,box-shadow .18s ease,transform .18s ease}.submit-tab:active{transform:scale(.985)}.submit-tab.is-active{background:var(--card-bg,#fff);color:var(--text-main,#0f172a);box-shadow:0 10px 26px rgba(15,23,42,.08)}.submit-tab-icon{width:34px;height:34px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(135deg,#e0f2fe,#d1fae5);color:#0f766e;flex:none}.submit-tab-icon svg{width:19px;height:19px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.submit-tab-text{min-width:0}.submit-tab strong{display:block;font-size:14px;font-weight:950;line-height:1.1}.submit-tab-desc{display:block;margin-top:3px;font-size:11px;color:var(--text-muted,#94a3b8);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.submit-panel{display:none}.submit-panel.is-active{display:block;animation:submitFade .2s ease both}@keyframes submitFade{from{opacity:.4;transform:translateY(6px)}to{opacity:1;transform:none}}
.submit-form{background:var(--card-bg,#fff);border:1px solid rgba(226,232,240,.82);border-radius:22px;padding:18px;box-shadow:0 14px 38px rgba(15,23,42,.055)}.submit-form h2,.my-apps h2{font-size:17px;font-weight:950;margin:0 0 4px;color:var(--text-main,#0f172a)}.section-subtitle{font-size:13px;color:var(--text-muted,#94a3b8);line-height:1.6;margin:0 0 16px}.form-group{margin-bottom:14px}.form-group label{display:block;font-size:13px;font-weight:850;margin-bottom:6px;color:var(--text-soft,#64748b)}.form-group input,.form-group textarea,.form-group select{width:100%;padding:11px 14px;border:1px solid var(--line-soft,#e2e8f0);border-radius:13px;font-size:14px;background:var(--card-bg,#fff);color:var(--text-main,#0f172a);box-sizing:border-box;outline:none;transition:border-color .15s,box-shadow .15s}.form-group input:focus,.form-group textarea:focus,.form-group select:focus{border-color:#3cc9a4;box-shadow:0 0 0 4px rgba(60,201,164,.12)}.form-group input[type="file"]{padding:10px 12px;background:var(--bg-soft,#f8fafc)}.form-help{font-size:12px;color:var(--text-muted,#94a3b8);margin-top:6px;line-height:1.5}.form-group textarea{min-height:112px;resize:vertical}.form-row{display:grid;grid-template-columns:1fr;gap:0}.btn-submit{width:100%;min-height:46px;padding:12px;background:linear-gradient(135deg,#3cc9a4,#0ea5e9);color:#fff;border:none;border-radius:14px;font-size:15px;font-weight:950;cursor:pointer;margin-top:8px;box-shadow:0 14px 28px rgba(14,165,233,.18)}.btn-submit:active{opacity:.88}.btn-submit:disabled{opacity:.65;cursor:not-allowed}.upload-card-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.upload-card{position:relative;min-height:118px;border:1.5px dashed var(--line-soft,#cbd5e1);border-radius:16px;background:var(--bg-soft,#f8fafc);display:grid;place-items:center;overflow:hidden;cursor:pointer;transition:.18s}.upload-card:hover{border-color:#3cc9a4;box-shadow:0 10px 24px rgba(60,201,164,.12)}.upload-card input{position:absolute;inset:0;opacity:0;cursor:pointer}.upload-card img{width:100%;height:100%;object-fit:cover;position:absolute;inset:0}.upload-card .upload-empty{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;color:var(--text-muted,#94a3b8);font-size:12px;font-weight:850;gap:8px;z-index:1;line-height:1.25;transform:none}.upload-card .upload-empty svg{width:24px;height:24px;display:block;flex:0 0 auto;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.upload-card .upload-empty span{display:block}.logo-upload-card{width:116px;height:116px;border-radius:24px}.logo-upload-card img{border-radius:24px}.screenshot-card{aspect-ratio:16/10;min-height:auto}.upload-card.has-image{border-style:solid;border-color:rgba(60,201,164,.7)}.upload-card.has-image .upload-empty{opacity:0}.upload-remove{position:absolute;right:8px;top:8px;width:26px;height:26px;border:0;border-radius:999px;background:rgba(15,23,42,.72);color:#fff;z-index:3;display:none;cursor:pointer}.upload-card.has-image .upload-remove{display:grid;place-items:center}.upload-tip{font-size:12px;color:var(--text-muted,#94a3b8);margin-top:8px;line-height:1.5}
.my-apps{background:var(--card-bg,#fff);border:1px solid rgba(226,232,240,.82);border-radius:22px;padding:18px;box-shadow:0 14px 38px rgba(15,23,42,.055)}.my-app-list{display:grid;gap:10px}.my-app-item{display:flex;align-items:center;padding:12px;background:var(--bg-soft,#f8fafc);border-radius:16px;gap:12px;border:1px solid rgba(226,232,240,.72)}.my-app-item .icon{width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#f0f1f3,#e2e8f0);display:grid;place-items:center;flex-shrink:0;overflow:hidden;color:#64748b}.my-app-item .icon img{width:100%;height:100%;object-fit:cover}.my-app-item .icon svg{width:24px;height:24px;stroke:currentColor;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}.my-app-item .info{flex:1;min-width:0}.my-app-item .info h3{font-size:15px;font-weight:900;margin:0 0 4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text-main,#0f172a)}.my-app-item .info .meta{font-size:12px;color:var(--text-muted,#94a3b8)}.my-app-item .status{padding:4px 10px;border-radius:999px;font-size:11px;font-weight:850;color:#fff;white-space:nowrap}.my-app-item .actions{display:flex;gap:7px;flex-wrap:wrap;justify-content:flex-end}.my-app-item .actions a,.my-app-item .actions button{font-size:12px;color:#0ea5e9;text-decoration:none;font-weight:850;background:rgba(14,165,233,.1);border-radius:999px;padding:5px 8px;border:0;white-space:nowrap}.my-app-item .actions .version-submit-btn{background:#0f172a;color:#fff}.empty-tip{text-align:center;padding:46px 20px;font-size:14px;color:var(--text-muted,#94a3b8);background:var(--bg-soft,#f8fafc);border-radius:18px}.empty-tip strong{display:block;color:var(--text-main,#0f172a);font-size:16px;margin-bottom:6px}.version-submit-btn{cursor:pointer}
@media (min-width: 900px){.submit-shell{padding:24px clamp(22px,4vw,42px) 0}.submit-header{top:70px;padding:18px 20px;border-radius:26px;grid-template-columns:minmax(0,1fr) minmax(420px,520px);align-items:center}.submit-header h1{font-size:30px}.submit-header p{font-size:14px}.submit-tabs{border-radius:20px}.submit-tab{min-height:62px;padding:10px 12px}.submit-tab-icon{width:40px;height:40px}.submit-tab strong{font-size:15px}.submit-tab span{font-size:12px}.submit-form,.my-apps{padding:24px;border-radius:26px}.form-row{grid-template-columns:1fr 1fr;gap:14px}.form-row .form-group{margin-bottom:14px}.submit-form h2,.my-apps h2{font-size:20px}.my-app-list{grid-template-columns:1fr}.my-app-item{padding:14px}.my-app-item .icon{width:54px;height:54px;border-radius:16px}}
@media (max-width: 720px){.upload-card-grid{grid-template-columns:repeat(2,1fr)}}
@media (max-width: 520px){.upload-card-grid{grid-template-columns:1fr 1fr}.submit-shell{padding-left:10px;padding-right:10px}.submit-header{top:58px;border-radius:18px}.submit-tab{gap:8px}.submit-tab-icon{width:30px;height:30px;border-radius:10px}.submit-tab-desc{display:none}.my-app-item{align-items:flex-start;flex-wrap:wrap}.my-app-item .info{min-width:calc(100% - 64px)}.my-app-item .actions{width:100%;justify-content:flex-end}}
html[data-theme="dark"] .submit-page{background:#0f172a}html[data-theme="dark"] .submit-header,html[data-theme="dark"] .submit-form,html[data-theme="dark"] .my-apps{background:rgba(30,41,59,.9);border-color:#263244}html[data-theme="dark"] .submit-tabs,html[data-theme="dark"] .my-app-item,html[data-theme="dark"] .empty-tip,html[data-theme="dark"] .form-group input[type="file"]{background:#0f172a}html[data-theme="dark"] .submit-tab.is-active{background:#1e293b}html[data-theme="dark"] .submit-header h1,html[data-theme="dark"] .submit-form h2,html[data-theme="dark"] .my-apps h2,html[data-theme="dark"] .my-app-item .info h3,html[data-theme="dark"] .empty-tip strong{color:#e5e7eb}html[data-theme="dark"] .form-group input,html[data-theme="dark"] .form-group textarea,html[data-theme="dark"] .form-group select{background:#0f172a;border-color:#334155;color:#e5e7eb}html[data-theme="dark"] .my-app-item{border-color:#263244}html[data-theme="dark"] .upload-card{background:#0f172a;border-color:#334155}
</style>

<div class="submit-page">
  <div class="submit-shell">
    <div class="submit-header">
      <div class="submit-header-main">
        <div class="submit-header-copy">
          <h1>软件投稿中心</h1>
          <p>提交新应用并跟踪审核状态，Logo、详情和下载链接会进入软件库审核流程。</p>
        </div>
      </div>
      <div class="submit-tabs" role="tablist" aria-label="软件投稿切换">
        <button type="button" class="submit-tab is-active" id="tab-new" role="tab" aria-selected="true" aria-controls="panel-new" data-target="new">
          <span class="submit-tab-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg></span>
          <span class="submit-tab-text"><strong>新应用投稿</strong><span class="submit-tab-desc">填写资料并提交审核</span></span>
        </button>
        <button type="button" class="submit-tab" id="tab-mine" role="tab" aria-selected="false" aria-controls="panel-mine" data-target="mine">
          <span class="submit-tab-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="14" rx="3"/><path d="M8 9h8"/><path d="M8 13h5"/></svg></span>
          <span class="submit-tab-text"><strong>我的投稿</strong><span class="submit-tab-desc"><?= count($mySoftwares ?? []) ?> 个投稿记录</span></span>
        </button>
      </div>
    </div>

    <section class="submit-panel is-active" id="panel-new" role="tabpanel" aria-labelledby="tab-new">
      <div class="submit-form">
        <h2>投稿新软件</h2>
        <p class="section-subtitle">请尽量填写完整信息，应用 Logo 为必填项，提交后将进入后台审核。</p>
        <form id="submitForm" action="/index.php?path=software/submission/create" method="post" enctype="multipart/form-data" onsubmit="return handleSubmit(this)">
          <div class="form-row">
            <div class="form-group"><label>软件名称 *</label><input type="text" name="name" required></div>
            <div class="form-group"><label>标识（英文）*</label><input type="text" name="slug" required pattern="[a-zA-Z0-9_-]+"></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label>平台 *</label><select name="platform" required><?php foreach ($platforms as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>分类</label><select name="category_id"><option value="">选择分类</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label>类型</label><select name="type"><?php foreach ($typeOptions as $k => $v): ?><option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>版本</label><input type="text" name="version" value="1.0.0"></div>
          </div>
          <div class="form-group"><label>开发者</label><input type="text" name="developer" placeholder="填写开发者名称"></div>
          <div class="form-group"><label>应用大小</label><input type="text" name="size" placeholder="例如：18MB、1.2GB、245KB，由投稿用户自行填写"></div>
          <div class="form-group"><label>下载链接 *</label><input type="url" name="download_url" required placeholder="https://example.com/app.apk"></div>
          <div class="form-group"><label>简介</label><input type="text" name="description" maxlength="200" placeholder="一句话介绍"></div>
          <div class="form-group"><label>详细介绍</label><textarea name="detail" placeholder="详细介绍软件功能..."></textarea></div>
          <div class="form-group"><label>应用 Logo *</label><label class="upload-card logo-upload-card" data-preview-card><input type="file" name="icon" accept="image/jpeg,image/png,image/webp,image/gif" required data-preview-input><span class="upload-empty"><svg viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg><span>上传 Logo</span></span><button type="button" class="upload-remove" data-remove-preview>×</button></label><div class="form-help">选择后会立即预览。支持 JPG / PNG / WEBP / GIF，最大 2MB。</div></div>
          <div class="form-group"><label>应用展示图</label><div class="upload-card-grid" data-screenshot-grid>
            <?php for ($i=0;$i<6;$i++): ?><label class="upload-card screenshot-card" data-preview-card><input type="file" name="screenshots[]" accept="image/*" data-preview-input><span class="upload-empty"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M8 13l2.5-2.5L14 14l2-2 3 3"/></svg><span>展示图 <?= $i+1 ?></span></span><button type="button" class="upload-remove" data-remove-preview>×</button></label><?php endfor; ?>
          </div><div class="upload-tip">最多上传 6 张，上传后直接显示预览；编辑时也可以逐张更改。</div></div>
          <button type="submit" class="btn-submit">提交审核</button>
        </form>
      </div>
    </section>

    <section class="submit-panel" id="panel-mine" role="tabpanel" aria-labelledby="tab-mine" hidden>
      <div class="my-apps">
        <h2>我的投稿</h2>
        <p class="section-subtitle">查看审核状态，草稿、被拒绝或已下架的软件可以继续编辑后重新提交。</p>
        <?php if (empty($mySoftwares)): ?>
        <div class="empty-tip"><strong>暂无投稿</strong>切换到“新应用投稿”提交你的第一个应用。</div>
        <?php else: ?>
        <div class="my-app-list">
          <?php foreach ($mySoftwares as $s): ?>
          <div class="my-app-item">
            <div class="icon">
              <?php if (!empty($s['icon'])): ?>
                <img src="<?= htmlspecialchars($s['icon']) ?>" alt="<?= htmlspecialchars($s['name']) ?> Logo">
              <?php else: ?>
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="2.5" width="10" height="19" rx="2.4"/><path d="M10 18h4"/><path d="M9 5.5h6"/></svg>
              <?php endif; ?>
            </div>
            <div class="info">
              <h3><?= htmlspecialchars($s['name']) ?></h3>
              <div class="meta"><?= $platforms[$s['platform']] ?? $s['platform'] ?> · v<?= htmlspecialchars($s['version']) ?></div>
            </div>
            <span class="status" style="background:<?= $statusColors[$s['status']] ?? '#94a3b8' ?>"><?= $statusLabels[$s['status']] ?? $s['status'] ?></span>
            <div class="actions">
              <a href="/index.php?path=software/show&slug=<?= htmlspecialchars($s['slug']) ?>">查看</a>
              <a href="/index.php?path=software/submission/edit&id=<?= (int)$s['id'] ?>">编辑</a>
              <?php if (($s['status'] ?? '') === 'published'): ?>
              <a class="version-submit-btn" href="/index.php?path=software/submission/version&id=<?= (int)$s['id'] ?>">提交新版本</a>
              <a href="/index.php?path=software/submission/versions&id=<?= (int)$s['id'] ?>">版本历史</a>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>

<script>
(function(){
  var tabs=[].slice.call(document.querySelectorAll('.submit-tab'));
  var panels={new:document.getElementById('panel-new'),mine:document.getElementById('panel-mine')};
  function activate(name){
    tabs.forEach(function(tab){
      var active=tab.dataset.target===name;
      tab.classList.toggle('is-active',active);
      tab.setAttribute('aria-selected',active?'true':'false');
    });
    Object.keys(panels).forEach(function(key){
      if(!panels[key]) return;
      var active=key===name;
      panels[key].classList.toggle('is-active',active);
      panels[key].hidden=!active;
    });
    try{history.replaceState(null,'','#'+(name==='mine'?'my-submissions':'new-submission'));}catch(e){}
  }
  tabs.forEach(function(tab){tab.addEventListener('click',function(){activate(tab.dataset.target||'new');});});
  if(location.hash==='#my-submissions' && !location.search.includes('_new=1')) activate('mine');
})();

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
      var img=document.createElement('img'); img.className='preview-img'; img.alt='预览图';
      img.src=URL.createObjectURL(file); card.appendChild(img); card.classList.add('has-image');
    });
    if(remove){remove.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();input.value='';var img=card.querySelector('img.preview-img');if(img)img.remove();card.classList.remove('has-image');});}
  });
}
initUploadPreviews(document);

function handleSubmit(form){
  var fd=new FormData(form);
  var btn=form.querySelector('.btn-submit');
  if(btn){btn.disabled=true;btn.textContent='提交中...';}
  fetch(form.action,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
    if(d.ok){alert('投稿成功，等待审核');location.reload();}
    else alert(d.error||'投稿失败');
  }).catch(function(e){alert('请求失败：'+e.message);}).finally(function(){
    if(btn){btn.disabled=false;btn.textContent='提交审核';}
  });
  return false;
}
</script>

<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>