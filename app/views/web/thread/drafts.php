<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>草稿箱</title><link rel="stylesheet" href="/assets/css/style.css">
<style>
.draft-wrap{padding:18px 16px 96px}.draft-card{max-width:900px;margin:0 auto;overflow:hidden}.draft-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:16px}.draft-head h2{margin:0;font-size:28px;color:var(--text-main,#0f172a)}.draft-filter{display:grid;grid-template-columns:minmax(0,1fr) 180px auto;gap:8px;margin-bottom:12px}.draft-tabs{display:flex;gap:8px;padding:5px;margin-bottom:14px;border:1px solid var(--line-soft,#e2e8f0);border-radius:16px;background:var(--bg-soft,#f8fafc)}.draft-tab{flex:1;display:inline-flex;align-items:center;justify-content:center;gap:7px;height:42px;border:0;border-radius:12px;background:transparent;color:var(--text-muted,#94a3b8);font-size:14px;font-weight:950;cursor:pointer}.draft-tab.is-active{background:var(--card-bg,#fff);color:var(--primary,#0284c7);box-shadow:0 8px 22px rgba(15,23,42,.08)}.draft-count{display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 7px;border-radius:999px;background:rgba(2,132,199,.1);color:var(--primary,#0284c7);font-size:12px}.draft-tab:not(.is-active) .draft-count{background:rgba(148,163,184,.16);color:var(--text-muted,#94a3b8)}.draft-panel{display:none}.draft-panel.is-active{display:block}.draft-bulk{display:flex;justify-content:space-between;align-items:center;gap:10px;margin:4px 0 8px;padding:8px 0;border-bottom:1px solid var(--line-soft,#e2e8f0)}.draft-bulk label{font-size:12px;color:var(--text-muted,#94a3b8);font-weight:900}.draft-list-form{margin:0}.draft-item{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:12px;padding:14px 0;border-bottom:1px solid var(--line-soft,#e2e8f0)}.draft-item:last-child{border-bottom:0}.draft-check{padding-top:3px}.draft-title{font-weight:950;color:var(--text-main,#0f172a);line-height:1.45}.draft-meta{font-size:12px;color:var(--text-muted,#94a3b8);margin-top:5px;line-height:1.65}.draft-excerpt{max-width:680px}.draft-review{margin-top:8px;padding:9px 10px;border-radius:12px;background:#fff7ed;color:#9a3412;font-size:12px;line-height:1.65}.draft-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;flex:0 0 auto}.draft-empty{color:#94a3b8;padding:42px 0;text-align:center;font-size:15px}.draft-empty-small{color:#94a3b8;padding:24px 0;text-align:center}@media(max-width:768px){.draft-wrap{padding:12px 12px 96px}.draft-card{border-radius:20px}.draft-head h2{font-size:25px}.draft-head .btn{padding:11px 16px;min-width:auto}.draft-filter{grid-template-columns:1fr}.draft-tabs{position:sticky;top:0;z-index:3;margin-left:-2px;margin-right:-2px}.draft-tab{height:40px;font-size:13px}.draft-item{grid-template-columns:auto minmax(0,1fr);padding:14px 0}.draft-actions{grid-column:2;display:grid;grid-template-columns:1fr 1fr;margin-top:10px}.draft-actions .btn,.draft-actions button{width:100%;min-width:0}.draft-excerpt{max-width:none}.draft-bulk{display:grid}.draft-bulk .btn{width:100%}}</style>
</head><body><?php require __DIR__ . '/../layouts/topbar.php'; ?>
<?php $keyword=trim((string)($keyword ?? ($_GET['q'] ?? ''))); $sectionFilter=(int)($sectionFilter ?? ($_GET['section_id'] ?? 0)); $draftSections=$draftSections ?? []; $hasThreadDrafts=!empty($drafts); $hasReplyDrafts=!empty($replyDrafts); $threadCount=count($drafts ?? []); $replyCount=count($replyDrafts ?? []); $initialTab=$hasThreadDrafts || !$hasReplyDrafts ? 'thread' : 'reply'; ?>
<div class="container draft-wrap"><div class="card draft-card">
  <div class="draft-head"><h2>草稿箱</h2><div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;"><a class="btn btn-light" href="/index.php?path=me&tab=drafts" style="text-decoration:none;">个人中心草稿</a><a class="btn" href="/index.php?path=publish" style="text-decoration:none;">写新帖子</a></div></div>
  <form class="draft-filter" method="get" action="/index.php">
    <input type="hidden" name="path" value="drafts">
    <input class="input" name="q" value="<?= htmlspecialchars($keyword) ?>" placeholder="搜索标题、内容或帖子">
    <select class="select" name="section_id"><option value="0">全部板块</option><?php foreach($draftSections as $section): ?><option value="<?= (int)$section['id'] ?>" <?= $sectionFilter===(int)$section['id']?'selected':'' ?>><?= htmlspecialchars((string)$section['name']) ?></option><?php endforeach; ?></select>
    <button class="btn btn-light">筛选</button>
  </form>
  <div class="draft-tabs" role="tablist" aria-label="草稿分类">
    <button class="draft-tab <?= $initialTab === 'thread' ? 'is-active' : '' ?>" type="button" data-draft-tab="thread" role="tab" aria-selected="<?= $initialTab === 'thread' ? 'true' : 'false' ?>">帖子草稿 <span class="draft-count"><?= (int)$threadCount ?></span></button>
    <button class="draft-tab <?= $initialTab === 'reply' ? 'is-active' : '' ?>" type="button" data-draft-tab="reply" role="tab" aria-selected="<?= $initialTab === 'reply' ? 'true' : 'false' ?>">回复草稿 <span class="draft-count"><?= (int)$replyCount ?></span></button>
  </div>

  <section class="draft-panel <?= $initialTab === 'thread' ? 'is-active' : '' ?>" data-draft-panel="thread" role="tabpanel">
    <?php if ($hasThreadDrafts): ?>
      <form class="draft-list-form" method="post" action="/index.php?path=draft/batch-delete" onsubmit="return confirm('确认删除选中的帖子草稿？')"><?= csrf_field() ?><input type="hidden" name="type" value="thread">
      <div class="draft-bulk"><label><input type="checkbox" data-draft-check-all="thread"> 全选帖子草稿</label><button class="btn btn-light" style="color:#ef4444;">批量删除</button></div>
      <?php foreach ($drafts as $d): ?>
        <div class="draft-item"><div class="draft-check"><input type="checkbox" name="ids[]" value="<?= (int)$d['id'] ?>" data-draft-check="thread"></div><div><div class="draft-title"><?= htmlspecialchars($d['title'] ?: '未命名草稿') ?></div><div class="draft-meta"><?= htmlspecialchars($d['section_name'] ?? '未选板块') ?> · <?= htmlspecialchars($d['updated_at'] ?? '') ?></div><div class="draft-meta draft-excerpt"><?= htmlspecialchars(mb_substr(trim(strip_tags((string)$d['content'])),0,100)) ?></div><?php if (($d['review_status'] ?? '') === 'ai_rejected'): ?><div class="draft-review"><strong>AI 审核未通过</strong><br>原因：<?= htmlspecialchars($d['review_reason'] ?? '') ?><?php if (!empty($d['review_suggestion'])): ?><br>建议：<?= htmlspecialchars($d['review_suggestion']) ?><?php endif; ?><?php if (!empty($d['review_categories'])): ?><br>类型：<?= htmlspecialchars($d['review_categories']) ?><?php endif; ?></div><?php endif; ?></div><div class="draft-actions"><a class="btn btn-light" href="/index.php?path=publish&draft_id=<?= (int)$d['id'] ?>" style="text-decoration:none;">继续编辑</a><form method="post" action="/index.php?path=draft/delete" data-ajax-refresh onsubmit="return confirm('确认删除这个草稿？')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="type" value="thread"><button class="btn btn-light" style="color:#ef4444;">删除</button></form></div></div>
      <?php endforeach; ?>
      </form>
    <?php else: ?>
      <div class="draft-empty-small">暂无帖子草稿</div>
    <?php endif; ?>
  </section>

  <section class="draft-panel <?= $initialTab === 'reply' ? 'is-active' : '' ?>" data-draft-panel="reply" role="tabpanel">
    <?php if ($hasReplyDrafts): ?>
      <form class="draft-list-form" method="post" action="/index.php?path=draft/batch-delete" onsubmit="return confirm('确认删除选中的回复草稿？')"><?= csrf_field() ?><input type="hidden" name="type" value="reply">
      <div class="draft-bulk"><label><input type="checkbox" data-draft-check-all="reply"> 全选回复草稿</label><button class="btn btn-light" style="color:#ef4444;">批量删除</button></div>
      <?php foreach ($replyDrafts as $d): ?>
        <div class="draft-item"><div class="draft-check"><input type="checkbox" name="ids[]" value="<?= (int)$d['id'] ?>" data-draft-check="reply"></div><div><div class="draft-title">回复：<?= htmlspecialchars($d['thread_title'] ?? '帖子') ?></div><div class="draft-meta"><?= htmlspecialchars($d['updated_at'] ?? '') ?></div><div class="draft-meta draft-excerpt"><?= htmlspecialchars(mb_substr(trim(strip_tags((string)$d['content'])),0,120)) ?></div><?php if (($d['review_status'] ?? '') === 'ai_rejected'): ?><div class="draft-review"><strong>AI 审核未通过</strong><br>原因：<?= htmlspecialchars($d['review_reason'] ?? '') ?><?php if (!empty($d['review_suggestion'])): ?><br>建议：<?= htmlspecialchars($d['review_suggestion']) ?><?php endif; ?><?php if (!empty($d['review_categories'])): ?><br>类型：<?= htmlspecialchars($d['review_categories']) ?><?php endif; ?></div><?php endif; ?></div><div class="draft-actions"><a class="btn btn-light" href="/index.php?path=thread&id=<?= (int)$d['thread_id'] ?>&reply_draft_id=<?= (int)$d['id'] ?>#reply-box" style="text-decoration:none;">继续编辑</a><form method="post" action="/index.php?path=draft/delete" data-ajax-refresh onsubmit="return confirm('确认删除这个回复草稿？')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="type" value="reply"><button class="btn btn-light" style="color:#ef4444;">删除</button></form></div></div>
      <?php endforeach; ?>
      </form>
    <?php else: ?>
      <div class="draft-empty-small">暂无回复草稿</div>
    <?php endif; ?>
  </section>

  <?php if (!$hasThreadDrafts && !$hasReplyDrafts): ?><div class="draft-empty">暂无草稿</div><?php endif; ?>
</div></div>
<script>
(function(){
  var tabs=document.querySelectorAll('[data-draft-tab]');
  var panels=document.querySelectorAll('[data-draft-panel]');
  tabs.forEach(function(tab){tab.addEventListener('click',function(){var key=tab.getAttribute('data-draft-tab');tabs.forEach(function(item){var on=item===tab;item.classList.toggle('is-active',on);item.setAttribute('aria-selected',on?'true':'false');});panels.forEach(function(panel){panel.classList.toggle('is-active',panel.getAttribute('data-draft-panel')===key);});});});
  document.querySelectorAll('[data-draft-check-all]').forEach(function(all){all.addEventListener('change',function(){var key=all.getAttribute('data-draft-check-all');document.querySelectorAll('[data-draft-check="'+key+'"]').forEach(function(item){item.checked=all.checked;});});});
})();
</script>
<?php require __DIR__ . '/../../layouts/theme-toggle.php'; ?><?php require __DIR__ . '/../layouts/bottom-nav.php'; ?></body></html>
