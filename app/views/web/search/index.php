<?php
$keyword    = $keyword    ?? '';
$threads    = $threads    ?? [];
$total      = $total      ?? 0;
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$error      = $error      ?? '';
$type       = $type       ?? 'thread';
$sectionId  = $sectionId  ?? 0;
$sections   = $sections   ?? [];
$typeLabels = ['thread' => '帖子', 'user' => '作者', 'section' => '板块'];
$activeSectionName = '全部板块';
foreach ($sections as $sec) {
    if ((int)$sectionId === (int)$sec['id']) { $activeSectionName = (string)$sec['name']; break; }
}
function search_url(array $override = []): string {
    $params = array_merge([
        'path' => 'search',
        'q' => $GLOBALS['keyword'] ?? '',
        'type' => $GLOBALS['type'] ?? 'thread',
        'section_id' => (int)($GLOBALS['sectionId'] ?? 0),
    ], $override);
    return '/index.php?' . http_build_query($params);
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $keyword !== '' ? '搜索：' . htmlspecialchars($keyword) . ' - ' : '' ?>论坛搜索</title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
body{background:var(--bg-main,#f6f8fb)}
.search-page{padding:18px 0 108px}.search-panel{background:var(--card-bg,#fff);border:1px solid rgba(226,232,240,.86);border-radius:18px;box-shadow:0 10px 30px rgba(15,23,42,.045);overflow:hidden}.search-head{padding:22px 22px 18px;background:linear-gradient(135deg,rgba(2,132,199,.08),rgba(99,102,241,.045) 55%,rgba(255,255,255,.9));border-bottom:1px solid var(--line-soft,#e2e8f0)}.search-title{font-size:22px;font-weight:950;color:var(--text-main,#0f172a);letter-spacing:-.03em}.search-sub{margin-top:6px;color:var(--text-soft,#64748b);font-size:13px;line-height:1.6}.search-form{margin-top:16px;display:grid;gap:12px}.search-main-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center}.search-input-wrap{position:relative}.search-input-wrap:before{content:'⌕';position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94a3b8;font-weight:900}.search-input{width:100%;height:44px;border:1px solid var(--line-soft,#e2e8f0);border-radius:14px;background:var(--card-bg,#fff);color:var(--text-main,#0f172a);padding:0 14px 0 36px;font-size:15px;outline:none;box-shadow:inset 0 1px 0 rgba(255,255,255,.5)}.search-input:focus{border-color:rgba(2,132,199,.45);box-shadow:0 0 0 3px rgba(2,132,199,.10)}.search-submit{height:44px;border:0;border-radius:14px;background:var(--primary,#0284c7);color:white;padding:0 20px;font-size:14px;font-weight:950;cursor:pointer}.search-submit:hover{filter:brightness(.96)}.filter-bar{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.type-chip{display:inline-flex;align-items:center;height:32px;border-radius:999px;border:1px solid var(--line-soft,#e2e8f0);background:var(--card-bg,#fff);color:var(--text-soft,#64748b);padding:0 12px;font-size:12px;font-weight:900;text-decoration:none}.type-chip.active{background:rgba(2,132,199,.10);border-color:rgba(2,132,199,.22);color:var(--primary,#0284c7)}.filter-select{height:32px;border:1px solid var(--line-soft,#e2e8f0);border-radius:999px;background:var(--card-bg,#fff);color:var(--text-soft,#64748b);padding:0 30px 0 12px;font-size:12px;font-weight:850;max-width:180px}.active-filter{display:inline-flex;align-items:center;gap:5px;height:32px;border-radius:999px;background:var(--input-bg,#f8fafc);color:var(--text-soft,#64748b);padding:0 10px;font-size:12px;font-weight:850}.result-summary{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:14px 22px;border-bottom:1px solid var(--line-soft,#e2e8f0);color:var(--text-soft,#64748b);font-size:13px}.result-summary strong{color:var(--text-main,#0f172a)}.result-count{display:inline-flex;align-items:center;gap:6px}.result-count b{color:var(--primary,#0284c7)}.search-results{padding:16px 18px 20px}.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:10px 14px;border-radius:12px;font-size:13px;margin:14px 22px}.empty{margin:18px;min-height:180px;display:grid;place-items:center;text-align:center;color:#94a3b8;border:1px dashed var(--line-soft,#e2e8f0);border-radius:16px;background:rgba(255,255,255,.55)}.empty strong{display:block;color:var(--text-main,#0f172a);font-size:16px;margin-bottom:6px}.pagination{display:flex;justify-content:center;gap:8px;margin:22px 0 4px;flex-wrap:wrap}.pagination a,.pagination span{height:32px;min-width:34px;padding:0 12px;border-radius:999px;font-size:12px;font-weight:900;text-decoration:none;border:1px solid var(--line-soft,#e2e8f0);color:var(--text-soft,#64748b);display:inline-flex;align-items:center;justify-content:center}.pagination a:hover{background:rgba(2,132,199,.08);border-color:rgba(2,132,199,.2);color:var(--primary,#0284c7)}.pagination .current{background:var(--primary,#0284c7);color:#fff;border-color:var(--primary,#0284c7)}
@media(max-width:640px){.search-page{padding-top:10px}.search-head{padding:18px 14px 14px}.search-title{font-size:20px}.search-main-row{grid-template-columns:1fr}.search-submit{width:100%}.result-summary{padding:12px 14px}.search-results{padding:12px 10px}.filter-select{max-width:100%;flex:1 1 150px}.type-chip{padding:0 10px}}
</style>
</head>
<body>
<?php require __DIR__ . '/../layouts/topbar.php'; ?>
<div class="container search-page">
  <section class="search-panel">
    <div class="search-head">
      <div class="search-title">站内搜索</div>
      <div class="search-sub">聚合搜索帖子、作者和板块；需要更精确时可以限定板块范围。</div>
      <form method="GET" action="/index.php" class="search-form">
        <input type="hidden" name="path" value="search">
        <input type="hidden" name="type" id="searchTypeInput" value="<?= htmlspecialchars($type) ?>">
        <div class="search-main-row">
          <div class="search-input-wrap"><input class="search-input" type="text" name="q" value="<?= htmlspecialchars($keyword) ?>" placeholder="搜索帖子标题、内容、作者或板块" autofocus></div>
          <button class="search-submit" type="submit">搜索</button>
        </div>
        <div class="filter-bar">
          <?php foreach ($typeLabels as $key => $label): ?>
            <button class="type-chip <?= $type === $key ? 'active' : '' ?>" type="submit" name="type" value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></button>
          <?php endforeach; ?>
          <select class="filter-select" name="section_id" onchange="this.form.submit()">
            <option value="0">全部板块</option>
            <?php foreach($sections as $sec): ?><option value="<?= (int)$sec['id'] ?>" <?= (int)$sectionId===(int)$sec['id']?'selected':'' ?>><?= htmlspecialchars($sec['name']) ?></option><?php endforeach; ?>
          </select>
          <?php if ($keyword !== ''): ?><span class="active-filter">关键词：<?= htmlspecialchars($keyword) ?></span><?php endif; ?>
          <span class="active-filter">范围：<?= htmlspecialchars($activeSectionName) ?></span>
        </div>
      </form>
    </div>

    <?php if ($error): ?>
      <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($keyword !== ''): ?>
      <div class="result-summary">
        <span class="result-count"><?= $total > 0 ? '找到 <b>' . (int)$total . '</b> 条结果' : '没有找到匹配结果' ?></span>
        <span>类型：<strong><?= htmlspecialchars($typeLabels[$type] ?? '帖子') ?></strong> · 板块：<strong><?= htmlspecialchars($activeSectionName) ?></strong></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($threads)): ?>
      <div class="search-results">
        <?= thread_card_styles() ?>
        <div class="thread-card-grid">
          <?php foreach ($threads as $t): ?><?= render_thread_card($t) ?><?php endforeach; ?>
        </div>
        <?php if ($totalPages > 1): ?>
          <div class="pagination">
            <?php if ($page > 1): ?><a href="<?= htmlspecialchars(search_url(['page' => $page - 1])) ?>">上一页</a><?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
              <?= $i === $page ? '<span class="current">' . $i . '</span>' : '<a href="' . htmlspecialchars(search_url(['page' => $i])) . '">' . $i . '</a>' ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="<?= htmlspecialchars(search_url(['page' => $page + 1])) ?>">下一页</a><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php elseif ($keyword !== '' && !$error): ?>
      <div class="empty"><div><strong>没有找到相关内容</strong><span>换个关键词，或切换搜索类型/板块范围试试。</span></div></div>
    <?php elseif ($keyword === ''): ?>
      <div class="empty"><div><strong>输入关键词开始搜索</strong><span>可以搜帖子、作者，也可以限定到某个板块。</span></div></div>
    <?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/../../layouts/theme-toggle.php'; ?>
<?php require __DIR__ . '/../layouts/bottom-nav.php'; ?>
<script>
document.addEventListener('click',function(e){var card=e.target.closest('.thread-card-v2[data-href]');if(!card)return;if(e.target.closest('a,button,input,select,textarea'))return;window.location.href=card.dataset.href;});
</script>
</body>
</html>
