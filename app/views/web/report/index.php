<?php
$typeLabel = ($target['type'] ?? '') === 'post' ? '回复' : '帖子';
$preview = trim((string)($target['content'] ?? ''));
if (function_exists('mb_strlen') && mb_strlen($preview) > 220) {
    $preview = mb_substr($preview, 0, 220) . '...';
}
$targetUrl = (string)($target['url'] ?? '/index.php');
$selectedReason = (string)($_POST['reason_type'] ?? '');
$detailValue = (string)($_POST['detail'] ?? '');
$reasonOptions = [
    'spam' => '垃圾广告',
    'illegal' => '违规内容',
    'attack' => '人身攻击',
    'copyright' => '涉嫌侵权',
    'privacy' => '隐私泄露',
    'other' => '其他',
];
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>举报<?= htmlspecialchars($typeLabel) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
.report-page{min-height:100vh;padding:18px 16px 110px;background:var(--bg-main,#f6f8fb)}
.report-shell{max-width:820px;margin:0 auto;display:grid;gap:14px}
.report-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
.report-title{margin:0;font-size:26px;line-height:1.25;font-weight:950;letter-spacing:-.035em;color:var(--text-main,#0f172a)}
.report-sub{margin:7px 0 0;color:var(--text-soft,#64748b);font-size:14px;line-height:1.7}
.report-card{background:var(--card-bg,#fff);border:1px solid rgba(226,232,240,.88);border-radius:24px;padding:22px;box-shadow:0 16px 42px rgba(15,23,42,.065)}
.target-box{border:1px solid var(--line-soft,#e2e8f0);border-radius:18px;background:linear-gradient(180deg,var(--input-bg,#f8fafc),rgba(248,250,252,.65));padding:16px;margin-bottom:18px}
.target-meta{display:flex;gap:8px;flex-wrap:wrap;color:var(--text-muted,#94a3b8);font-size:12px;font-weight:850}
.target-meta span{display:inline-flex;align-items:center;border-radius:999px;background:var(--card-bg,#fff);border:1px solid rgba(226,232,240,.72);padding:5px 9px}
.target-title{margin-top:12px;color:var(--text-main,#0f172a);font-size:18px;font-weight:950;line-height:1.5}
.target-preview{margin-top:9px;color:var(--text-soft,#64748b);font-size:14px;line-height:1.78;white-space:pre-wrap;word-break:break-word}
.target-link{display:inline-flex;margin-top:12px;color:var(--primary,#0284c7);text-decoration:none;font-size:13px;font-weight:950}
.target-link:hover{text-decoration:underline}
.form-label{font-weight:950;color:var(--text-main,#0f172a);margin-bottom:10px}
.report-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:12px 0 14px}
.report-option{display:flex;align-items:center;gap:10px;border:1px solid var(--line-soft,#e2e8f0);background:var(--card-bg,#fff);border-radius:15px;padding:12px 13px;color:var(--text-main,#0f172a);font-size:14px;font-weight:900;cursor:pointer;transition:.15s ease}
.report-option:hover{border-color:rgba(2,132,199,.32);background:rgba(2,132,199,.04)}
.report-option input{margin:0;accent-color:var(--primary,#0284c7)}
.report-tip{margin-top:8px;color:var(--text-muted,#94a3b8);font-size:12px;line-height:1.6}
.report-actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;margin-top:16px}
.alert-error,.alert-success{padding:12px 14px;border-radius:15px;margin-bottom:14px;font-size:14px;line-height:1.65;font-weight:850}
.alert-error{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
.alert-success{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
html[data-theme="dark"] .report-card{background:#111827;border-color:#263244;box-shadow:0 16px 42px rgba(0,0,0,.25)}
html[data-theme="dark"] .target-box{background:#0f172a;border-color:#263244}
html[data-theme="dark"] .target-meta span,html[data-theme="dark"] .report-option{background:#111827;border-color:#263244}
html[data-theme="dark"] .report-option:hover{background:rgba(14,165,233,.08);border-color:#0ea5e9}
@media(max-width:640px){.report-page{padding:12px 12px 104px}.report-card{border-radius:20px;padding:16px}.report-title{font-size:22px}.report-options{grid-template-columns:1fr}.report-actions .btn{flex:1}}
</style>
</head>
<body>
<?php require __DIR__ . '/../layouts/topbar.php'; ?>
<main class="report-page">
  <div class="report-shell">
    <div class="report-head">
      <div>
        <h1 class="report-title">举报<?= htmlspecialchars($typeLabel) ?></h1>
        <p class="report-sub">请确认举报对象并选择原因。提交后平台会尽快核实处理。</p>
      </div>
      <a class="btn btn-light" href="<?= htmlspecialchars($targetUrl) ?>" style="text-decoration:none;">返回原内容</a>
    </div>

    <section class="report-card">
      <?php if (!empty($error)): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if (!empty($success)): ?><div class="alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

      <div class="target-box">
        <div class="target-meta">
          <span>举报对象：<?= htmlspecialchars($typeLabel) ?> #<?= (int)($target['id'] ?? 0) ?></span>
          <span>作者：<?= htmlspecialchars($target['author'] ?? '匿名') ?></span>
        </div>
        <div class="target-title"><?= htmlspecialchars($target['title'] ?? '') ?></div>
        <?php if ($preview !== ''): ?><div class="target-preview"><?= htmlspecialchars($preview) ?></div><?php endif; ?>
        <a class="target-link" href="<?= htmlspecialchars($targetUrl) ?>">查看原内容</a>
      </div>

      <?php if (empty($success) && empty($alreadyReported)): ?>
        <form method="post" action="/index.php?path=report" class="report-form">
          <?= csrf_field() ?>
          <input type="hidden" name="target_type" value="<?= htmlspecialchars($target['type']) ?>">
          <input type="hidden" name="target_id" value="<?= (int)$target['id'] ?>">
          <div class="form-label">请选择举报原因</div>
          <div class="report-options">
            <?php foreach ($reasonOptions as $value => $label): ?>
              <label class="report-option"><input type="radio" name="reason_type" value="<?= htmlspecialchars($value) ?>" <?= $selectedReason === $value ? 'checked' : '' ?> required> <?= htmlspecialchars($label) ?></label>
            <?php endforeach; ?>
          </div>
          <textarea class="textarea" name="detail" placeholder="补充说明，可选；选择其他时请填写" style="min-height:128px;"><?= htmlspecialchars($detailValue) ?></textarea>
          <div class="report-tip">为了方便处理，请尽量说明具体问题。恶意或重复举报可能会被驳回。</div>
          <div class="report-actions">
            <a class="btn btn-light" href="<?= htmlspecialchars($targetUrl) ?>" style="text-decoration:none;">取消</a>
            <button class="btn" type="submit">提交举报</button>
          </div>
        </form>
      <?php else: ?>
        <div class="report-actions">
          <a class="btn" href="<?= htmlspecialchars($targetUrl) ?>" style="text-decoration:none;">返回原内容</a>
          <a class="btn btn-light" href="/index.php" style="text-decoration:none;">返回首页</a>
        </div>
      <?php endif; ?>
    </section>
  </div>
</main>
<?php require __DIR__ . '/../../layouts/theme-toggle.php'; ?>
<?php require __DIR__ . '/../layouts/bottom-nav.php'; ?>
</body>
</html>
