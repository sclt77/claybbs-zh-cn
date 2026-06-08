<?php
$fullUser = $fullUser ?? [];
$error    = $error ?? '';
$success  = $success ?? '';
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>编辑资料</title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
.edit-card{max-width:520px;margin:24px auto 96px;padding:28px;}
.form-group{margin-bottom:18px;}
.form-group label{display:block;font-size:13px;color:var(--text-soft);margin-bottom:6px;font-weight:700;}
.form-group input,.form-group textarea{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid var(--line-soft);border-radius:12px;font-size:14px;outline:none;transition:border .2s;background:var(--input-bg);color:var(--text-main);}
.form-group input:focus,.form-group textarea:focus{border-color:var(--primary);}
.form-group textarea{resize:vertical;min-height:90px;}
.hint{font-size:12px;color:var(--text-muted);margin-top:4px;}
.alert-error{background:#fff0f0;border:1px solid #fca5a5;color:#b91c1c;padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;}
.alert-success{background:#f0fdf4;border:1px solid #86efac;color:#166534;padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;}
.file-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.file-box{border:1px dashed var(--line-soft);border-radius:14px;padding:12px;background:var(--input-bg)}.file-box input{border:0;padding:0;border-radius:0;background:transparent;font-size:12px}body[data-theme="dark"] .alert-error,html[data-theme="dark"] .alert-error{background:#450a0a;border-color:#7f1d1d;color:#fecaca;}body[data-theme="dark"] .alert-success,html[data-theme="dark"] .alert-success{background:#052e16;border-color:#166534;color:#bbf7d0;}@media(max-width:768px){.edit-card{margin:16px 12px 96px;padding:20px}.file-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php require __DIR__ . '/../layouts/topbar.php'; ?>

<div class="card edit-card">
  <h2 style="margin:0 0 22px;font-size:20px;color:var(--text-main);">编辑资料</h2>

  <?php if ($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <form method="POST" action="/index.php?path=me/edit" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-group">
      <label>昵称 <span style="color:#e53935">*</span></label>
      <input type="text" name="nickname" value="<?= htmlspecialchars($fullUser['nickname'] ?? '') ?>" required>
    </div>
    <div class="form-group">
      <label>个人简介</label>
      <textarea name="bio"><?= htmlspecialchars($fullUser['bio'] ?? '') ?></textarea>
    </div>
    <div class="file-grid">
      <div class="form-group file-box">
        <label>头像图片</label>
        <input type="file" name="avatar" accept="image/*">
      </div>
      <div class="form-group file-box">
        <label>背景图片</label>
        <input type="file" name="cover" accept="image/*">
      </div>
    </div>
    <div class="form-group">
      <label>新密码</label>
      <input type="password" name="password" placeholder="留空则不修改">
      <p class="hint">至少 6 个字符，留空则保持原密码不变</p>
    </div>
    <div class="form-group">
      <label>确认新密码</label>
      <input type="password" name="confirm" placeholder="再次输入新密码">
    </div>
    <div style="display:flex;gap:12px;margin-top:24px;">
      <button type="submit" class="btn btn-primary" style="flex:1;">保存修改</button>
      <a href="/index.php?path=me" class="btn btn-light" style="flex:1;text-align:center;text-decoration:none;">取消</a>
    </div>
  </form>
</div>

</body>
</html>
