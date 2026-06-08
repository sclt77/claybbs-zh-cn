<?php
$pageTitle = '举报管理';
require dirname(__DIR__) . '/layouts/main.php';
$reports = $reports ?? [];
$stats = $stats ?? ['all'=>0,'pending'=>0,'processing'=>0,'resolved'=>0,'rejected'=>0];
$statusLabels = ['pending'=>'待处理','processing'=>'处理中','resolved'=>'已处理','rejected'=>'已驳回'];
$statusClasses = ['pending'=>'badge-warn','processing'=>'badge-warn','resolved'=>'badge-ok','rejected'=>'badge-err'];
$currentStatus = (string)($_GET['status'] ?? '');
function admin_report_preview(array $r): string {
    $content = (string)(($r['target_type'] ?? '') === 'thread' ? ($r['thread_content'] ?? '') : (($r['target_type'] ?? '') === 'private_message' ? ($r['private_content'] ?? '') : ($r['post_content'] ?? '')));
    $content = trim(strip_tags($content));
    return mb_strlen($content) > 180 ? mb_substr($content, 0, 180) . '...' : $content;
}
function admin_report_author(array $r): string {
    if (($r['target_type'] ?? '') === 'thread') return (string)($r['thread_author_name'] ?: $r['thread_author_username'] ?: '匿名');
    if (($r['target_type'] ?? '') === 'private_message') return (string)($r['private_sender_name'] ?: $r['private_sender_username'] ?: '匿名');
    return (string)($r['post_author_name'] ?: $r['post_author_username'] ?: '匿名');
}
function admin_report_target_url(array $r): string {
    if (($r['target_type'] ?? '') === 'thread') return '/index.php?path=thread&id=' . (int)$r['target_id'];
    if (($r['target_type'] ?? '') === 'private_message') return '#';
    return '/index.php?path=thread&id=' . (int)($r['post_thread_id'] ?? 0) . '#post-' . (int)$r['target_id'];
}
function admin_report_target_title(array $r): string {
    return (string)(($r['target_type'] ?? '') === 'thread' ? ($r['thread_title'] ?? '') : (($r['target_type'] ?? '') === 'private_message' ? '私聊消息 #' . (int)($r['target_id'] ?? 0) : ($r['post_thread_title'] ?? '')));
}
?>

<div class="page-header">
  <div class="page-title">举报管理</div>
  <form method="get" action="/admin.php" class="admin-filter-bar">
    <input type="hidden" name="path" value="reports">
    <select class="select" name="status">
      <option value="">全部状态</option>
      <?php foreach ($statusLabels as $k => $v): ?><option value="<?= $k ?>" <?= $currentStatus === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-light">筛选</button>
  </form>
</div>
<div class="report-stats" aria-label="举报状态统计">
  <a class="report-stat <?= $currentStatus === '' ? 'active' : '' ?>" href="/admin.php?path=reports"><strong><?= (int)$stats['all'] ?></strong><span>全部</span></a>
  <?php foreach ($statusLabels as $k => $v): ?>
    <a class="report-stat <?= $currentStatus === $k ? 'active' : '' ?>" href="/admin.php?path=reports&status=<?= $k ?>"><strong><?= (int)($stats[$k] ?? 0) ?></strong><span><?= htmlspecialchars($v) ?></span></a>
  <?php endforeach; ?>
</div>

<div class="report-grid">
<?php foreach ($reports as $r): ?>
  <?php
    $targetUrl = admin_report_target_url($r);
    $targetTitle = admin_report_target_title($r);
    $targetMissing = ($r['target_type'] === 'thread' && empty($r['thread_title'])) || ($r['target_type'] === 'post' && empty($r['post_content'])) || ($r['target_type'] === 'private_message' && empty($r['private_content']));
    $targetStatus = $r['target_type'] === 'thread' ? (string)($r['thread_status'] ?? '已删除') : ($r['target_type'] === 'private_message' ? (string)($r['private_status'] ?? '已删除') : (string)($r['post_status'] ?? '已删除')); 
    $handler = trim((string)($r['handler_nickname'] ?: $r['handler_username'] ?: ''));
    $isFinalReport = in_array((string)($r['status'] ?? ''), ['resolved','rejected'], true);
  ?>
  <section class="report-card">
    <div class="report-card-head">
      <div>
        <div class="report-title-line">
          <span class="report-id">#<?= (int)$r['id'] ?></span>
          <span class="badge <?= $statusClasses[$r['status']] ?? 'badge-warn' ?>"><?= $statusLabels[$r['status']] ?? htmlspecialchars($r['status']) ?></span>
          <?php if ((int)($r['same_target_count'] ?? 0) > 1): ?><span class="badge badge-warn">同对象 <?= (int)$r['same_target_count'] ?> 次</span><?php endif; ?>
          <?php if ($targetMissing): ?><span class="badge badge-err">对象已不存在</span><?php else: ?><span class="badge <?= $targetStatus === 'published' ? 'badge-ok' : 'badge-err' ?>"><?= htmlspecialchars($targetStatus) ?></span><?php endif; ?>
        </div>
        <div class="report-meta">
          <span>举报人：<?= htmlspecialchars($r['nickname'] ?: $r['username'] ?: ('#'.$r['user_id'])) ?></span>
          <span>对象作者：<?= htmlspecialchars(admin_report_author($r)) ?></span>
          <span><?= htmlspecialchars($r['created_at']) ?></span>
          <?php if ($handler !== ''): ?><span>处理人：<?= htmlspecialchars($handler) ?></span><?php endif; ?>
        </div>
      </div>
      <div class="admin-report-actions">
        <?php if (!$targetMissing && ($r['target_type'] ?? '') !== 'private_message'): ?><a class="btn btn-light" href="<?= htmlspecialchars($targetUrl) ?>" target="_blank">前台查看</a><?php endif; ?>
        <?php if (($r['target_type'] ?? '') === 'thread' && !$targetMissing): ?><a class="btn btn-light" href="/admin.php?path=threads/edit&id=<?= (int)$r['target_id'] ?>" >编辑帖子</a><?php endif; ?>
        <?php if (($r['target_type'] ?? '') === 'post' && !$targetMissing): ?><a class="btn btn-light" href="/admin.php?path=posts/edit&id=<?= (int)$r['target_id'] ?>" >编辑回复</a><?php endif; ?>
      </div>
    </div>

    <div class="report-content">
      <div class="report-box">
        <div class="report-box-title">举报原因</div>
        <div class="report-reason"><?= htmlspecialchars($r['reason']) ?></div>
        <?php if (!empty($r['admin_note'])): ?><div class="report-note">处理备注：<?= htmlspecialchars($r['admin_note']) ?></div><?php endif; ?>
      </div>
      <div class="report-box">
        <div class="report-box-title"><?= $r['target_type'] === 'thread' ? '被举报帖子' : (($r['target_type'] ?? '') === 'private_message' ? '被举报私聊消息' : '被举报回复') ?></div>
        <div class="report-reason"><?= htmlspecialchars($targetTitle ?: ('#' . (int)$r['target_id'])) ?></div>
        <?php $preview = admin_report_preview($r); if ($preview !== ''): ?><div class="report-preview"><?= htmlspecialchars($preview) ?></div><?php endif; ?>
      </div>
    </div>

    <div class="admin-grid-gap admin-mt-14">
      <?php if ($isFinalReport): ?>
        <div class="report-final-box"><strong>该举报已完成处理</strong><span><?= htmlspecialchars($statusLabels[$r['status']] ?? $r['status']) ?><?php if (!empty($r['admin_note'])): ?> · <?= htmlspecialchars($r['admin_note']) ?><?php endif; ?></span></div>
      <?php else: ?>
      <form class="report-inline-form" method="post" action="/admin.php?path=reports/handle">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <input type="hidden" name="return_status" value="<?= htmlspecialchars($currentStatus) ?>">
        <select class="select" name="status">
          <?php foreach ($statusLabels as $k => $v): ?><option value="<?= $k ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
        </select>
        <input class="input" name="admin_note" value="<?= htmlspecialchars($r['admin_note'] ?? '') ?>" placeholder="处理备注" class="admin-flex-1-min">
        <button class="btn btn-light">保存状态</button>
      </form>

      <?php if (!$targetMissing): ?>
      <div class="report-quick">
        <?php if (($r['target_type'] ?? '') === 'thread'): ?>
          <?php foreach ([['thread_hide','屏蔽帖子','确认屏蔽这个帖子并标记举报已处理？'],['thread_restore','恢复帖子','确认恢复这个帖子并标记举报已处理？'],['thread_delete','彻底删除帖子','确认彻底删除这个帖子？会删除帖子、回复、点赞、收藏、举报等关联记录，无法恢复。']] as $act): ?>
            <form method="post" action="/admin.php?path=reports/target-action" onsubmit="return confirm('<?= htmlspecialchars($act[2], ENT_QUOTES) ?>')"><?= csrf_field() ?><input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="target_type" value="thread"><input type="hidden" name="target_id" value="<?= (int)$r['target_id'] ?>"><input type="hidden" name="target_action" value="<?= $act[0] ?>"><input type="hidden" name="return_status" value="<?= htmlspecialchars($currentStatus) ?>"><button class="btn <?= $act[0] === 'thread_delete' ? 'report-danger' : 'btn-light' ?> admin-action-sm"><?= $act[1] ?></button></form>
          <?php endforeach; ?>
        <?php elseif (($r['target_type'] ?? '') === 'post'): ?>
          <?php foreach ([['post_hide','屏蔽回复','确认屏蔽这个回复并标记举报已处理？'],['post_restore','恢复回复','确认恢复这个回复并标记举报已处理？'],['post_delete','删除回复','确认删除这个回复？']] as $act): ?>
            <form method="post" action="/admin.php?path=reports/target-action" onsubmit="return confirm('<?= htmlspecialchars($act[2], ENT_QUOTES) ?>')"><?= csrf_field() ?><input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="target_type" value="post"><input type="hidden" name="target_id" value="<?= (int)$r['target_id'] ?>"><input type="hidden" name="target_action" value="<?= $act[0] ?>"><input type="hidden" name="return_status" value="<?= htmlspecialchars($currentStatus) ?>"><button class="btn <?= $act[0] === 'post_delete' ? 'report-danger' : 'btn-light' ?> admin-action-sm"><?= $act[1] ?></button></form>
          <?php endforeach; ?>
        <?php else: ?>
          <form method="post" action="/admin.php?path=reports/target-action" onsubmit="return confirm('确认隐藏这条被举报的私聊消息？')"><?= csrf_field() ?><input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="target_type" value="private_message"><input type="hidden" name="target_id" value="<?= (int)$r['target_id'] ?>"><input type="hidden" name="target_action" value="private_hide"><input type="hidden" name="return_status" value="<?= htmlspecialchars($currentStatus) ?>"><button class="btn report-danger admin-action-sm">隐藏私聊消息</button></form>
        <?php endif; ?>
        <form method="post" action="/admin.php?path=reports/target-action" onsubmit="return confirm('确认驳回该举报？')"><?= csrf_field() ?><input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="target_type" value="<?= htmlspecialchars($r['target_type']) ?>"><input type="hidden" name="target_id" value="<?= (int)$r['target_id'] ?>"><input type="hidden" name="target_action" value="reject"><input type="hidden" name="return_status" value="<?= htmlspecialchars($currentStatus) ?>"><button class="btn btn-light admin-action-sm">驳回举报</button></form>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>
<?php endforeach; ?>
<?php if (empty($reports)): ?><div class="report-empty">暂无举报</div><?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
