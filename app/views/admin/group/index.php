<?php
$pageTitle = '群聊管理';
require dirname(__DIR__) . '/layouts/main.php';
$tab = $tab ?? 'groups';
$groups = $groups ?? [];
$reports = $reports ?? [];
$stats = $stats ?? ['total' => 0, 'members' => 0, 'messages' => 0];
$reportStats = $reportStats ?? ['all' => 0, 'pending' => 0, 'processed' => 0, 'rejected' => 0];
$statusLabels = $statusLabels ?? ['pending' => '待处理', 'processed' => '已处理', 'rejected' => '已驳回'];
$statusClasses = ['pending' => 'status-pending', 'processed' => 'status-approved', 'rejected' => 'status-rejected'];
$currentQ = (string)($_GET['q'] ?? '');
$currentStatus = (string)($_GET['status'] ?? '');
?>

<div class="page-header">
  <div class="page-title">群聊管理</div>
</div>


<nav class="admin-tabs" style="margin-bottom:16px">
  <a class="<?= $tab === 'groups' ? 'is-active' : '' ?>" href="/admin.php?path=group-manage&tab=groups" style="display:inline-flex;align-items:center;gap:6px">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    群聊列表
    <span class="badge-pill" style="background:var(--cb-admin-surface-2);color:var(--cb-admin-muted);font-size:11px;padding:2px 7px;border-radius:999px"><?= (int)$stats['total'] ?></span>
  </a>
  <a class="<?= $tab === 'reports' ? 'is-active' : '' ?>" href="/admin.php?path=group-manage&tab=reports" style="display:inline-flex;align-items:center;gap:6px">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    群聊投诉
    <?php if ($reportStats['pending'] > 0): ?>
    <span class="badge-pill" style="background:var(--cb-admin-danger);color:#fff;font-size:11px;padding:2px 7px;border-radius:999px"><?= (int)$reportStats['pending'] ?></span>
    <?php endif; ?>
  </a>
</nav>

<?php if ($tab === 'groups'): ?>



<div class="group-stats-row" style="display:flex;gap:12px;margin-bottom:16px">
  <div style="flex:1;text-align:center;background:var(--cb-admin-surface);border:1px solid var(--cb-admin-line);border-radius:var(--cb-admin-radius);padding:16px;box-shadow:var(--cb-admin-shadow-sm)">
    <div style="font-size:28px;font-weight:950;color:var(--cb-admin-primary)"><?= (int)$stats['total'] ?></div>
    <div class="admin-muted" style="margin-top:4px">群聊总数</div>
  </div>
  <div style="flex:1;text-align:center;background:var(--cb-admin-surface);border:1px solid var(--cb-admin-line);border-radius:var(--cb-admin-radius);padding:16px;box-shadow:var(--cb-admin-shadow-sm)">
    <div style="font-size:28px;font-weight:950;color:var(--cb-admin-primary)"><?= (int)$stats['members'] ?></div>
    <div class="admin-muted" style="margin-top:4px">成员总数</div>
  </div>
  <div style="flex:1;text-align:center;background:var(--cb-admin-surface);border:1px solid var(--cb-admin-line);border-radius:var(--cb-admin-radius);padding:16px;box-shadow:var(--cb-admin-shadow-sm)">
    <div style="font-size:28px;font-weight:950;color:var(--cb-admin-primary)"><?= (int)$stats['messages'] ?></div>
    <div class="admin-muted" style="margin-top:4px">消息总数</div>
  </div>
</div>


<form method="get" action="/admin.php" class="admin-filter-bar">
  <input type="hidden" name="path" value="group-manage">
  <input type="hidden" name="tab" value="groups">
  <input type="text" class="input" name="q" value="<?= htmlspecialchars($currentQ) ?>" placeholder="搜索群名或群号...">
  <button class="btn">搜索</button>
</form>


<div class="table-responsive">
<table class="table">
  <thead>
    <tr>
      <th>ID</th>
      <th>群名</th>
      <th>群号</th>
      <th>群主</th>
      <th>成员数</th>
      <th>消息数</th>
      <th>创建时间</th>
      <th>操作</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($groups)): ?>
    <tr><td colspan="8" class="admin-muted" style="text-align:center;padding:32px">暂无群聊</td></tr>
    <?php else: foreach ($groups as $g): ?>
    <tr>
      <td><strong><?= (int)$g['id'] ?></strong></td>
      <td class="admin-table-title"><?= htmlspecialchars($g['name'] ?? '') ?></td>
      <td><code style="font-size:12px"><?= htmlspecialchars($g['public_id'] ?? '') ?></code></td>
      <td><?= htmlspecialchars($g['owner_nickname'] ?: ($g['owner_username'] ?? '')) ?></td>
      <td><?= (int)($g['member_count'] ?? 0) ?></td>
      <td><?= (int)($g['message_count'] ?? 0) ?></td>
      <td class="admin-muted"><?= htmlspecialchars($g['created_at'] ?? '') ?></td>
      <td>
        <a href="/admin.php?path=group-manage/view&id=<?= (int)$g['id'] ?>" class="btn">查看</a>
      </td>
    </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>
</div>

<?php else: ?>



<div class="admin-tabs" style="margin-bottom:16px">
  <a class="<?= $currentStatus === '' ? 'is-active' : '' ?>" href="/admin.php?path=group-manage&tab=reports">全部 <span class="badge-pill" style="background:var(--cb-admin-surface-2);color:var(--cb-admin-muted);font-size:11px;padding:1px 6px;border-radius:999px;margin-left:4px"><?= (int)$reportStats['all'] ?></span></a>
  <?php foreach ($statusLabels as $k => $v): ?>
  <a class="<?= $currentStatus === $k ? 'is-active' : '' ?>" href="/admin.php?path=group-manage&tab=reports&status=<?= $k ?>"><?= htmlspecialchars($v) ?> <span class="badge-pill" style="background:var(--cb-admin-surface-2);color:var(--cb-admin-muted);font-size:11px;padding:1px 6px;border-radius:999px;margin-left:4px"><?= (int)($reportStats[$k] ?? 0) ?></span></a>
  <?php endforeach; ?>
</div>


<div class="table-responsive">
<table class="table">
  <thead>
    <tr>
      <th>ID</th>
      <th>群聊</th>
      <th>投诉人</th>
      <th>涉及用户</th>
      <th>消息数</th>
      <th>状态</th>
      <th>投诉时间</th>
      <th>操作</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($reports)): ?>
    <tr><td colspan="8" class="admin-muted" style="text-align:center;padding:32px">暂无投诉</td></tr>
    <?php else: foreach ($reports as $r): ?>
    <tr>
      <td><strong><?= (int)$r['id'] ?></strong></td>
      <td class="admin-table-title">
        <?= htmlspecialchars($r['group_name'] ?? '') ?>
        <div class="admin-muted"><?= htmlspecialchars($r['group_public_id'] ?? '') ?></div>
      </td>
      <td><?= htmlspecialchars($r['reporter_nickname'] ?: ($r['reporter_username'] ?? '')) ?></td>
      <td><?= (int)($r['reported_user_count'] ?? 0) ?> 人</td>
      <td><?= (int)($r['message_count'] ?? 0) ?></td>
      <td><span class="<?= $statusClasses[$r['status']] ?? '' ?>"><?= $statusLabels[$r['status']] ?? $r['status'] ?></span></td>
      <td class="admin-muted"><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
      <td>
        <a href="/admin.php?path=group-manage/report-view&id=<?= (int)$r['id'] ?>" class="btn"><?= ($r['status'] ?? '') === 'pending' ? '处理' : '查看' ?></a>
      </td>
    </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>
</div>

<?php endif; ?>


<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
