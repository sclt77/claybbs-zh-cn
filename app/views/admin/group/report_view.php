<?php
$pageTitle = '投诉详情 #' . ((int)($report['id'] ?? 0));
require dirname(__DIR__) . '/layouts/main.php';
$report = $report ?? [];
$messages = $messages ?? [];
$reportedUsers = $reportedUsers ?? [];
$actions = $actions ?? [];
$isPending = ($report['status'] ?? '') === 'pending';
$statusMap = ['pending' => ['待处理', 'status-pending'], 'processed' => ['已处理', 'status-approved'], 'rejected' => ['已驳回', 'status-rejected']];
$stInfo = $statusMap[$report['status']] ?? ['', ''];
?>

<div class="page-header">
  <div class="page-title">投诉 #<?= (int)$report['id'] ?></div>
  <a href="/admin.php?path=group-manage&tab=reports" class="btn">← 返回列表</a>
</div>

<div class="admin-section-stack">
  
  <div class="admin-panel">
    <div class="admin-panel-head">
      <strong>投诉信息</strong>
      <span class="<?= $stInfo[1] ?>"><?= $stInfo[0] ?></span>
    </div>
    <div class="admin-panel-body" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
      <div>
        <div class="admin-muted">投诉人</div>
        <div style="font-weight:800;margin-top:4px"><?= htmlspecialchars($report['reporter_nickname'] ?: ($report['reporter_username'] ?? '')) ?> <span class="admin-muted">(<?= (int)$report['reporter_id'] ?>)</span></div>
      </div>
      <div>
        <div class="admin-muted">群聊</div>
        <div style="font-weight:800;margin-top:4px"><?= htmlspecialchars($report['group_name'] ?? '') ?> <span class="admin-muted"><?= htmlspecialchars($report['group_public_id'] ?? '') ?></span></div>
      </div>
      <div>
        <div class="admin-muted">投诉时间</div>
        <div style="font-weight:800;margin-top:4px"><?= htmlspecialchars($report['created_at'] ?? '') ?></div>
      </div>
    </div>
    <?php if (!empty($report['reason'])): ?>
    <div style="border-top:1px solid var(--cb-admin-line);padding-top:12px;margin-top:4px">
      <div class="admin-muted" style="margin-bottom:6px">投诉原因</div>
      <div style="background:var(--cb-admin-surface-2);border-radius:10px;padding:12px;white-space:pre-wrap;line-height:1.7;font-size:13px"><?= nl2br(htmlspecialchars($report['reason'])) ?></div>
    </div>
    <?php endif; ?>
  </div>

  
  <div class="admin-panel">
    <div class="admin-panel-head">
      <strong>涉及用户</strong>
      <span class="admin-muted"><?= count($reportedUsers) ?> 人</span>
    </div>
    <div class="admin-panel-body">
      <?php foreach ($reportedUsers as $u): ?>
      <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--cb-admin-line)">
        <div style="width:36px;height:36px;border-radius:10px;background:var(--cb-admin-surface-2);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:var(--cb-admin-muted);flex-shrink:0">
          <?= mb_substr($u['nickname'] ?: $u['username'], 0, 1) ?>
        </div>
        <div>
          <strong><?= htmlspecialchars($u['nickname'] ?: $u['username']) ?></strong>
          <span class="admin-muted" style="margin-left:6px">ID <?= (int)$u['user_id'] ?> · <?= (int)$u['message_count'] ?> 条消息</span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  
  <div class="admin-panel">
    <div class="admin-panel-head">
      <strong>被投诉消息</strong>
      <span class="admin-muted"><?= count($messages) ?> 条</span>
    </div>
    <div class="admin-panel-body" style="gap:8px">
      <?php foreach ($messages as $m): ?>
      <div style="background:var(--cb-admin-surface-2);border-radius:10px;padding:12px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
          <strong style="font-size:13px"><?= htmlspecialchars($m['nickname'] ?: $m['username']) ?></strong>
          <span class="admin-muted">ID <?= (int)$m['user_id'] ?> · <?= htmlspecialchars($m['created_at'] ?? '') ?></span>
        </div>
        <?php if (($m['message_type'] ?? 'text') === 'image'): ?>
          <img src="<?= htmlspecialchars($m['message_text'] ?? '') ?>" style="max-width:300px;max-height:200px;border-radius:8px" alt="图片消息">
        <?php else: ?>
          <div style="line-height:1.7;font-size:13px;white-space:pre-wrap"><?= nl2br(htmlspecialchars($m['message_text'] ?? '')) ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($isPending): ?>
  
  <div class="admin-panel">
    <div class="admin-panel-head">
      <strong>处理投诉</strong>
    </div>
    <div class="admin-panel-body">
      <form method="post" action="/admin.php?path=group-manage/process-report" id="reportProcessForm">
        <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

        <label style="display:grid;gap:5px;margin-bottom:12px">
          <span style="font-size:12px;font-weight:700;color:var(--cb-admin-muted)">处理方式</span>
          <div style="display:flex;flex-wrap:wrap;gap:12px">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px"><input type="radio" name="action" value="ban" checked style="accent-color:var(--cb-admin-primary)"> 封禁用户</label>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px"><input type="radio" name="action" value="warn" style="accent-color:var(--cb-admin-primary)"> 发送警告</label>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px"><input type="radio" name="action" value="reject" style="accent-color:var(--cb-admin-primary)"> 驳回投诉</label>
          </div>
        </label>

        <div id="banDaysGroup" style="margin-bottom:12px">
          <span style="font-size:12px;font-weight:700;color:var(--cb-admin-muted);display:block;margin-bottom:6px">封禁时长</span>
          <div style="display:flex;flex-wrap:wrap;gap:10px">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px"><input type="radio" name="ban_days" value="1" style="accent-color:var(--cb-admin-primary)"> 1天</label>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px"><input type="radio" name="ban_days" value="3" checked style="accent-color:var(--cb-admin-primary)"> 3天</label>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px"><input type="radio" name="ban_days" value="7" style="accent-color:var(--cb-admin-primary)"> 7天</label>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px"><input type="radio" name="ban_days" value="30" style="accent-color:var(--cb-admin-primary)"> 30天</label>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px"><input type="radio" name="ban_days" value="0" style="accent-color:var(--cb-admin-primary)"> 永久</label>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px"><input type="radio" name="ban_days" value="custom" style="accent-color:var(--cb-admin-primary)"> 自定义</label>
          </div>
          <div id="customDaysWrap" style="display:none;margin-top:8px">
            <input type="number" name="custom_days" min="1" max="3650" class="input" style="width:120px" placeholder="天数">
          </div>
        </div>

        <label style="display:grid;gap:5px;margin-bottom:16px">
          <span style="font-size:12px;font-weight:700;color:var(--cb-admin-muted)">管理员备注</span>
          <textarea name="admin_note" class="input" rows="3" placeholder="处理原因或备注（选填）"><?= htmlspecialchars($report['admin_note'] ?? '') ?></textarea>
        </label>

        <button type="submit" class="btn admin-danger-bg" onclick="return confirm('确认处理？')">确认处理</button>
      </form>
    </div>
  </div>

  <?php else: ?>
  
  <div class="admin-panel">
    <div class="admin-panel-head">
      <strong>处理结果</strong>
    </div>
    <div class="admin-panel-body" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
      <div>
        <div class="admin-muted">处理人</div>
        <div style="font-weight:800;margin-top:4px"><?= htmlspecialchars($report['admin_nickname'] ?: ($report['admin_username'] ?? '')) ?></div>
      </div>
      <div>
        <div class="admin-muted">处理时间</div>
        <div style="font-weight:800;margin-top:4px"><?= htmlspecialchars($report['processed_at'] ?? '') ?></div>
      </div>
      <?php if (!empty($report['admin_note'])): ?>
      <div style="grid-column:1/-1">
        <div class="admin-muted">处理备注</div>
        <div style="font-weight:800;margin-top:4px"><?= htmlspecialchars($report['admin_note']) ?></div>
      </div>
      <?php endif; ?>
    </div>
    <?php if (!empty($actions)): ?>
    <div style="border-top:1px solid var(--cb-admin-line);padding-top:12px;margin-top:4px">
      <div class="admin-muted" style="margin-bottom:8px">处理记录</div>
      <?php foreach ($actions as $a): ?>
      <div style="display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid var(--cb-admin-line)">
        <?php
        $actionLabels = ['ban' => '封禁', 'warn' => '警告', 'reject' => '驳回'];
        $actionColors = ['ban' => 'status-rejected', 'warn' => 'status-pending', 'reject' => ''];
        ?>
        <span class="<?= $actionColors[$a['action_type']] ?? '' ?>"><?= $actionLabels[$a['action_type']] ?? '' ?></span>
        <span>用户: <?= htmlspecialchars($a['nickname'] ?: ($a['username'] ?? '')) ?></span>
        <?php if (($a['action_type'] ?? '') === 'ban' && $a['ban_duration'] !== null): ?>
          <span class="admin-muted">· <?= (int)$a['ban_duration'] > 0 ? (int)$a['ban_duration'] . '天' : '永久' ?></span>
        <?php endif; ?>
        <?php if (!empty($a['ban_reason'])): ?>
          <span class="admin-muted">· <?= htmlspecialchars($a['ban_reason']) ?></span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var form = document.getElementById('reportProcessForm');
  if (!form) return;

  var actionRadios = form.querySelectorAll('input[name="action"]');
  var banDaysGroup = document.getElementById('banDaysGroup');

  function toggleBanDays() {
    var val = form.querySelector('input[name="action"]:checked').value;
    banDaysGroup.style.display = val === 'ban' ? '' : 'none';
  }

  actionRadios.forEach(function(r) { r.addEventListener('change', toggleBanDays); });
  toggleBanDays();

  var customRadio = form.querySelector('input[name="ban_days"][value="custom"]');
  var customWrap = document.getElementById('customDaysWrap');
  form.querySelectorAll('input[name="ban_days"]').forEach(function(r) {
    r.addEventListener('change', function() {
      customWrap.style.display = customRadio.checked ? '' : 'none';
    });
  });

  form.addEventListener('submit', function(e) {
    var action = form.querySelector('input[name="action"]:checked').value;
    if (action === 'ban') {
      var daysRadio = form.querySelector('input[name="ban_days"]:checked');
      if (daysRadio && daysRadio.value === 'custom') {
        var customDays = form.querySelector('input[name="custom_days"]').value;
        if (!customDays || parseInt(customDays) < 1) { e.preventDefault(); alert('请输入封禁天数'); return; }
        daysRadio.value = customDays;
      }
    }
  });
});
</script>

<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
