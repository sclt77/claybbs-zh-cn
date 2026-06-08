<?php
$__pageTitle = '提交新版本';
require dirname(__DIR__) . '/layouts/main.php';
?>
<style>
.version-page{padding:0 0 88px;background:var(--bg-main,#f5f5f5);min-height:100vh}.version-shell{max-width:760px;margin:0 auto;padding:14px 12px}.version-card{background:var(--card-bg,#fff);border-radius:22px;padding:18px;box-shadow:0 14px 38px rgba(15,23,42,.06)}.version-head{display:flex;align-items:center;gap:12px;margin-bottom:16px}.version-logo{width:54px;height:54px;border-radius:16px;overflow:hidden;background:var(--bg-soft,#f8fafc);display:grid;place-items:center}.version-logo img{width:100%;height:100%;object-fit:cover}.version-title{flex:1}.version-title h1{font-size:22px;margin:0;color:var(--text-main,#0f172a);font-weight:950}.version-title p{margin:5px 0 0;color:var(--text-muted,#94a3b8);font-size:13px}.form-group{margin-bottom:14px}.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}.form-group label{display:block;font-size:13px;font-weight:850;margin-bottom:6px;color:var(--text-soft,#64748b)}.form-group input,.form-group textarea{width:100%;box-sizing:border-box;border:1px solid var(--line-soft,#e2e8f0);border-radius:13px;padding:11px 14px;background:var(--card-bg,#fff);color:var(--text-main,#0f172a);font-size:14px}.form-group textarea{min-height:150px;resize:vertical}.btn-submit{width:100%;height:46px;border:0;border-radius:14px;background:#0f172a;color:#fff;font-weight:950}.back-link{display:inline-flex;margin-bottom:12px;color:var(--text-muted,#94a3b8);text-decoration:none;font-weight:850}html[data-theme="dark"] .version-page{background:#0f172a}html[data-theme="dark"] .version-card{background:#1e293b}html[data-theme="dark"] .form-group input,html[data-theme="dark"] .form-group textarea{background:#0f172a;border-color:#334155;color:#e5e7eb}@media(max-width:640px){.form-row{grid-template-columns:1fr}}
</style>
<div class="version-page"><div class="version-shell">
  <a class="back-link" href="/index.php?path=software/submission#my-submissions">返回我的投稿</a>
  <div class="version-card">
    <div class="version-head"><div class="version-logo"><?php if(!empty($software['icon'])): ?><img src="<?= htmlspecialchars($software['icon']) ?>" alt=""><?php else: ?><span>APP</span><?php endif; ?></div><div class="version-title"><h1>提交新版本</h1><p><?= htmlspecialchars($software['name']) ?> · 当前 v<?= htmlspecialchars($software['version']) ?></p></div></div>
    <form action="/index.php?path=software/submission/version" method="post" enctype="multipart/form-data" onsubmit="return handleVersionSubmit(this)">
      <input type="hidden" name="software_id" value="<?= (int)$software['id'] ?>">
      <div class="form-row"><div class="form-group"><label>新版本号 *</label><input type="text" name="version" required placeholder="例如：1.1.0"></div><div class="form-group"><label>应用大小</label><input type="text" name="size" placeholder="例如：25MB"></div></div>
      <div class="form-group"><label>下载链接 *</label><input type="url" name="download_url" required placeholder="https://example.com/app-new.apk"></div>
      <div class="form-group"><label>更新日志 *</label><textarea name="changelog" required placeholder="说明本次新增、修复和优化内容..."></textarea></div>
      <div class="form-group"><label>新 Logo（可选）</label><input type="file" name="icon" accept="image/jpeg,image/png,image/webp,image/gif"></div>
      <button type="submit" class="btn-submit">提交新版本审核</button>
    </form>
  </div>
</div></div>
<script>
function handleVersionSubmit(form){var fd=new FormData(form);var btn=form.querySelector('.btn-submit');if(btn){btn.disabled=true;btn.textContent='提交中...';}fetch(form.action,{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(d=>{if(d.ok){alert('新版本已提交，等待审核');location.href='/index.php?path=software/submission/versions&id=<?= (int)$software['id'] ?>';}else alert(d.error||'提交失败');}).catch(e=>alert('请求失败：'+e.message)).finally(()=>{if(btn){btn.disabled=false;btn.textContent='提交新版本审核';}});return false;}
</script>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
