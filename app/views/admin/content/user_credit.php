<?php
$pageTitle = '用户信用';
require dirname(__DIR__) . '/layouts/main.php';
$tab = $tab ?? 'users';
$settings = $settings ?? [];
$rows = $rows ?? [];
$logs = $logs ?? [];
function credit_badge_admin(int $score, array $settings): string {
  $low = (int)($settings['low_threshold'] ?? 60);
  $excellent = (int)($settings['excellent_threshold'] ?? 100);
  if ($score >= $excellent) return '<span class="badge badge-ok">优秀</span>';
  if ($score < $low) return '<span class="badge badge-err">较低</span>';
  return '<span class="badge badge-warn">正常</span>';
}
?>

<div class="page-header">
  <div class="page-title">用户信用</div>
  <form method="get" action="/admin.php" class="admin-filter-bar">
    <input type="hidden" name="path" value="user-credit">
    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
    <input class="input" name="kw" placeholder="搜索账号ID/用户名/昵称" value="<?= htmlspecialchars($_GET['kw'] ?? '') ?>" class="admin-w-220">
    <button class="btn" type="submit">筛选</button>
  </form>
</div>
<div class="admin-tabs" role="tablist" aria-label="用户信用">
  <a class="<?= $tab === 'users' ? 'active' : '' ?>" href="/admin.php?path=user-credit&tab=users">信用用户</a>
  <a class="<?= $tab === 'logs' ? 'active' : '' ?>" href="/admin.php?path=user-credit&tab=logs">信用流水</a>
  <a class="<?= $tab === 'settings' ? 'active' : '' ?>" href="/admin.php?path=user-credit&tab=settings">基础设置</a>
  <a class="<?= $tab === 'limits' ? 'active' : '' ?>" href="/admin.php?path=user-credit&tab=limits">低分限制</a>
  <a class="<?= $tab === 'recovery' ? 'active' : '' ?>" href="/admin.php?path=user-credit&tab=recovery">恢复规则</a>
  <a href="/admin.php?path=users">用户列表</a>
</div>

<?php if ($tab === 'settings'): ?>
  <div class="card card">
    <h3 class="admin-card-title">基础信用规则</h3>
    <p class="credit-mini-note">这里控制信用分开关、分值区间和举报核实后的加扣分。低分限制和恢复规则已拆到独立 Tab，避免设置堆在一起。</p>
    <form method="post" action="/admin.php?path=user-credit/settings" class="credit-setting-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="tab" value="settings">
      <label><input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : '' ?>> 启用信用系统</label>
      <label>默认分<input class="input" type="number" name="default_score" value="<?= (int)($settings['default_score'] ?? 100) ?>" min="0" max="1000"></label>
      <label>最低分<input class="input" type="number" name="min_score" value="<?= (int)($settings['min_score'] ?? 0) ?>" min="0" max="1000"></label>
      <label>最高分<input class="input" type="number" name="max_score" value="<?= (int)($settings['max_score'] ?? 120) ?>" min="1" max="1000"></label>
      <label>有效举报奖励<input class="input" type="number" name="valid_report_reward" value="<?= (int)($settings['valid_report_reward'] ?? 2) ?>" min="0" max="1000"></label>
      <label>被举报核实扣分<input class="input" type="number" name="valid_report_penalty" value="<?= (int)($settings['valid_report_penalty'] ?? 5) ?>" min="0" max="1000"></label>
      <label>无效举报扣分<input class="input" type="number" name="false_report_penalty" value="<?= (int)($settings['false_report_penalty'] ?? 0) ?>" min="0" max="1000"></label>
      <label>低信用阈值<input class="input" type="number" name="low_threshold" value="<?= (int)($settings['low_threshold'] ?? 60) ?>" min="0" max="1000"></label>
      <label>优秀阈值<input class="input" type="number" name="excellent_threshold" value="<?= (int)($settings['excellent_threshold'] ?? 100) ?>" min="0" max="1000"></label>
      <label>每日举报奖励上限<input class="input" type="number" name="daily_report_reward_limit" value="<?= (int)($settings['daily_report_reward_limit'] ?? 10) ?>" min="0" max="1000"></label>
      <div class="full-row"><button class="btn" type="submit">保存基础设置</button></div>
    </form>
  </div>
<?php elseif ($tab === 'limits'): ?>
  <div class="card card">
    <h3 class="admin-card-title">低分限制</h3>
    <p class="credit-mini-note">低于基础设置里的“低信用阈值”后生效。上限设为 0 表示低信用用户完全禁止该行为。</p>
    <form method="post" action="/admin.php?path=user-credit/settings" class="credit-setting-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="tab" value="limits">
      <label><input type="checkbox" name="restrict_enabled" value="1" <?= !empty($settings['restrict_enabled']) ? 'checked' : '' ?>> 启用低信用限制</label>
      <label>每日发帖上限<input class="input" type="number" name="low_daily_threads" value="<?= (int)($settings['low_daily_threads'] ?? 1) ?>" min="0" max="1000"></label>
      <label>每日回复上限<input class="input" type="number" name="low_daily_posts" value="<?= (int)($settings['low_daily_posts'] ?? 5) ?>" min="0" max="1000"></label>
      <label>每日私聊上限<input class="input" type="number" name="low_daily_private_messages" value="<?= (int)($settings['low_daily_private_messages'] ?? 10) ?>" min="0" max="1000"></label>
      <label>每日朋友圈上限<input class="input" type="number" name="low_daily_moments" value="<?= (int)($settings['low_daily_moments'] ?? 1) ?>" min="0" max="1000"></label>
      <label><input type="checkbox" name="low_disable_private_images" value="1" <?= !empty($settings['low_disable_private_images']) ? 'checked' : '' ?>> 低信用禁发私聊图片</label>
      <div class="full-row"><button class="btn" type="submit">保存低分限制</button></div>
    </form>
  </div>
<?php elseif ($tab === 'recovery'): ?>
  <div class="card card">
    <h3 class="admin-card-title">信用恢复规则</h3>
    <p class="credit-mini-note">自动恢复采用懒触发：用户打开信用页、发帖、回复、私聊或发朋友圈时都会先结算一次。这样不依赖定时任务，也不会额外扫全站用户。</p>
    <form method="post" action="/admin.php?path=user-credit/settings" class="credit-setting-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="tab" value="recovery">
      <label><input type="checkbox" name="recovery_enabled" value="1" <?= !empty($settings['recovery_enabled']) ? 'checked' : '' ?>> 启用自动恢复</label>
      <label>恢复间隔/小时<input class="input" type="number" name="recovery_interval_hours" value="<?= (int)($settings['recovery_interval_hours'] ?? 24) ?>" min="1" max="8760"></label>
      <label>每次恢复分数<input class="input" type="number" name="recovery_amount" value="<?= (int)($settings['recovery_amount'] ?? 2) ?>" min="0" max="1000"></label>
      <label>最高恢复到<input class="input" type="number" name="recovery_cap" value="<?= (int)($settings['recovery_cap'] ?? 100) ?>" min="0" max="1000"></label>
      <div class="full-row credit-mini-note">示例：间隔 24 小时、每次 2 分、最高恢复到 100 分，表示用户每满 24 小时自动恢复 2 分，直到回到 100 分为止。</div>
      <div class="full-row"><button class="btn" type="submit">保存恢复规则</button></div>
    </form>
  </div>
<?php elseif ($tab === 'logs'): ?>
  <div class="table-responsive"><table class="table">
    <thead><tr><th>ID</th><th>用户</th><th>动作</th><th>变化</th><th>前/后</th><th>原因</th><th>关联</th><th>操作人</th><th>时间</th></tr></thead>
    <tbody><?php foreach ($logs as $log): ?><tr>
      <td><?= (int)$log['id'] ?></td>
      <td><a href="/admin.php?path=users/edit&id=<?= (int)$log['user_id'] ?>"><?= htmlspecialchars(($log['nickname'] ?: $log['username']) ?? ('用户#'.(int)$log['user_id'])) ?></a></td>
      <td><?= htmlspecialchars($log['action'] ?? '') ?></td>
      <td class="<?= (int)$log['score_change'] >= 0 ? 'credit-change-pos' : 'credit-change-neg' ?>"><?= (int)$log['score_change'] >= 0 ? '+' : '' ?><?= (int)$log['score_change'] ?></td>
      <td><?= (int)$log['before_score'] ?> / <?= (int)$log['after_score'] ?></td>
      <td><?= htmlspecialchars($log['reason'] ?? '') ?></td>
      <td><?= htmlspecialchars(trim(($log['ref_type'] ?? '') . '#' . ($log['ref_id'] ?? ''), '#')) ?></td>
      <td><?= htmlspecialchars(($log['operator_nickname'] ?: $log['operator_username']) ?? '-') ?></td>
      <td><?= htmlspecialchars($log['created_at'] ?? '') ?></td>
    </tr><?php endforeach; ?><?php if (!$logs): ?><tr><td colspan="9" class="admin-empty">暂无信用流水</td></tr><?php endif; ?></tbody>
  </table></div>
<?php else: ?>
  <div class="table-responsive"><table class="table">
    <thead><tr><th>用户</th><th>信用分</th><th>等级</th><th>有效举报</th><th>无效举报</th><th>违规次数</th><th>状态</th><th>手动调整</th></tr></thead>
    <tbody><?php foreach ($rows as $u): $score=(int)($u['credit_score'] ?? 100); ?><tr>
      <td><div class="admin-bold"><?= htmlspecialchars(($u['nickname'] ?: $u['username']) ?? '') ?></div><div class="admin-muted">ID <?= htmlspecialchars($u['public_id'] ?? '') ?> · @<?= htmlspecialchars($u['username'] ?? '') ?></div></td>
      <td><span class="credit-score"><?= $score ?></span></td>
      <td><?= credit_badge_admin($score, $settings) ?></td>
      <td><?= (int)($u['valid_reports'] ?? 0) ?></td>
      <td><?= (int)($u['invalid_reports'] ?? 0) ?></td>
      <td><?= (int)($u['violations'] ?? 0) ?></td>
      <td><span class="badge <?= ($u['status'] ?? '') === 'active' ? 'badge-ok' : 'badge-err' ?>"><?= ($u['status'] ?? '') === 'active' ? '正常' : '封禁' ?></span></td>
      <td><form class="credit-adjust-form" method="post" action="/admin.php?path=user-credit/adjust">
        <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="return_to" value="/admin.php?path=user-credit&tab=users&kw=<?= urlencode((string)($_GET['kw'] ?? '')) ?>">
        <input class="input" type="number" name="score_change" placeholder="+/-" required>
        <input class="input" name="reason" placeholder="调整原因">
        <button class="btn btn-light" type="submit">调整</button>
      </form></td>
    </tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="8" class="admin-empty">暂无用户</td></tr><?php endif; ?></tbody>
  </table></div>
<?php endif; ?>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
