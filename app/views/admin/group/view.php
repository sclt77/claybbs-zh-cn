<?php
$pageTitle = '群聊详情';
require dirname(__DIR__) . '/layouts/main.php';
$group = $group ?? [];
$members = $members ?? [];
$messages = $messages ?? [];
$roleLabels = ['owner' => '群主', 'admin' => '管理员', 'member' => '成员'];
$roleColors = ['owner' => 'status-approved', 'admin' => 'status-pending', 'member' => ''];
?>

<div class="page-header">
  <div class="page-title"><?= htmlspecialchars($group['name'] ?? '群聊详情') ?></div>
  <a href="/admin.php?path=group-manage" class="btn">← 返回列表</a>
</div>


<div class="admin-section-stack">
  <div class="admin-panel">
    <div class="admin-panel-head">
      <strong>基本信息</strong>
      <code style="font-size:12px"><?= htmlspecialchars($group['public_id'] ?? '') ?></code>
    </div>
    <div class="admin-panel-body" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
      <div>
        <div class="admin-muted">群 ID</div>
        <div style="font-weight:800;margin-top:4px"><?= (int)$group['id'] ?></div>
      </div>
      <div>
        <div class="admin-muted">群主</div>
        <div style="font-weight:800;margin-top:4px"><?= htmlspecialchars($group['owner_nickname'] ?: ($group['owner_username'] ?? '')) ?> <span class="admin-muted">(<?= (int)$group['owner_user_id'] ?>)</span></div>
      </div>
      <div>
        <div class="admin-muted">加群方式</div>
        <div style="font-weight:800;margin-top:4px"><?= ($group['join_mode'] ?? '') === 'approval' ? '需要审批' : '直接加入' ?></div>
      </div>
      <div>
        <div class="admin-muted">可见性</div>
        <div style="font-weight:800;margin-top:4px"><?= ($group['visibility'] ?? '') === 'public' ? '公开' : '私密' ?></div>
      </div>
      <div>
        <div class="admin-muted">成员数</div>
        <div style="font-weight:800;margin-top:4px"><?= count($members) ?></div>
      </div>
      <div>
        <div class="admin-muted">创建时间</div>
        <div style="font-weight:800;margin-top:4px"><?= htmlspecialchars($group['created_at'] ?? '') ?></div>
      </div>
    </div>
    <?php if (!empty($group['notice'])): ?>
    <div style="border-top:1px solid var(--cb-admin-line);padding-top:12px;margin-top:4px">
      <div class="admin-muted" style="margin-bottom:6px">群公告</div>
      <div style="background:var(--cb-admin-surface-2);border-radius:10px;padding:12px;white-space:pre-wrap;line-height:1.7;font-size:13px"><?= nl2br(htmlspecialchars($group['notice'])) ?></div>
    </div>
    <?php endif; ?>
    <?php if (!empty($group['notice_title'])): ?>
    <div style="margin-top:8px">
      <div class="admin-muted" style="margin-bottom:4px">公告标题</div>
      <div style="font-weight:800"><?= htmlspecialchars($group['notice_title']) ?></div>
    </div>
    <?php endif; ?>
  </div>

  
  <div class="admin-panel">
    <div class="admin-panel-head">
      <strong>管理操作</strong>
    </div>
    <div class="admin-panel-body">
      <form method="post" action="/admin.php?path=group-manage/action" data-ajax style="display:flex;gap:8px;align-items:center">
        <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <button type="submit" name="action" value="disband" class="btn admin-danger-bg" onclick="return confirm('确定解散该群聊？此操作不可撤销！')">解散群聊</button>
        <span class="admin-muted">解散后所有成员将不能继续使用该群聊</span>
      </form>
    </div>
  </div>
</div>


<div class="admin-panel" style="margin-top:16px">
  <div class="admin-panel-head">
    <strong>成员列表</strong>
    <span class="admin-muted"><?= count($members) ?> 人</span>
  </div>
  <div class="table-responsive">
  <table class="table">
    <thead>
      <tr>
        <th>用户</th>
        <th>角色</th>
        <th>状态</th>
        <th>封禁信息</th>
        <th>操作</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($members)): ?>
      <tr><td colspan="5" class="admin-muted" style="text-align:center;padding:32px">暂无成员</td></tr>
      <?php else: foreach ($members as $m): ?>
      <tr>
        <td>
          <strong><?= htmlspecialchars($m['nickname'] ?: $m['username']) ?></strong>
          <div class="admin-muted"><?= (int)$m['user_id'] ?></div>
        </td>
        <td><span class="<?= $roleColors[$m['role'] ?? ''] ?? '' ?>"><?= $roleLabels[$m['role'] ?? ''] ?? $m['role'] ?></span></td>
        <td>
          <?php if (!empty($m['banned_until']) && strtotime($m['banned_until']) > time()): ?>
            <span class="status-rejected">封禁中</span>
          <?php else: ?>
            <span class="status-approved">正常</span>
          <?php endif; ?>
        </td>
        <td class="admin-muted">
          <?php if (!empty($m['banned_until'])): ?>
            <?= htmlspecialchars($m['banned_until']) ?>
            <?php if (!empty($m['ban_reason'])): ?> · <?= htmlspecialchars($m['ban_reason']) ?><?php endif; ?>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td>
          <?php if (($m['role'] ?? '') !== 'owner'): ?>
          <form method="post" action="/admin.php?path=group-manage/action" style="display:inline-flex;gap:6px" data-ajax>
            <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
            <input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <?php if (!empty($m['banned_until']) && strtotime($m['banned_until']) > time()): ?>
              <button type="submit" name="action" value="unban" class="btn">解封</button>
            <?php else: ?>
              <button type="submit" name="action" value="ban" class="btn" style="background:var(--cb-admin-warn);color:#fff" onclick="var d=prompt('封禁天数（0=永久）:','3');if(d===null)return false;this.form.insertAdjacentHTML('beforeend','<input type=hidden name=days value='+d+'>');var r=prompt('封禁原因:','');if(r===null)return false;this.form.insertAdjacentHTML('beforeend','<input type=hidden name=reason value='+encodeURIComponent(r)+'>');">封禁</button>
            <?php endif; ?>
            <button type="submit" name="action" value="kick" class="btn admin-danger-bg" onclick="return confirm('确定踢出？')">踢出</button>
          </form>
          <?php else: ?>
            <span class="admin-muted">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
</div>


<div class="admin-panel" style="margin-top:16px">
  <div class="admin-panel-head">
    <strong>最近消息</strong>
    <span class="admin-muted"><?= count($messages) ?> 条</span>
  </div>
  <div class="table-responsive">
  <table class="table">
    <thead>
      <tr>
        <th>ID</th>
        <th>发送者</th>
        <th>内容</th>
        <th>类型</th>
        <th>状态</th>
        <th>时间</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($messages)): ?>
      <tr><td colspan="6" class="admin-muted" style="text-align:center;padding:32px">暂无消息</td></tr>
      <?php else: foreach (array_reverse($messages) as $msg): ?>
      <tr>
        <td><strong><?= (int)$msg['id'] ?></strong></td>
        <td>
          <?= htmlspecialchars($msg['nickname'] ?: $msg['username']) ?>
          <div class="admin-muted"><?= (int)$msg['sender_user_id'] ?></div>
        </td>
        <td class="admin-text-ellipsis"><?= htmlspecialchars(mb_substr($msg['content'] ?? '', 0, 100)) ?></td>
        <td><code style="font-size:11px"><?= htmlspecialchars($msg['message_type'] ?? 'text') ?></code></td>
        <td>
          <?php
          $st = $msg['status'] ?? '';
          $stClass = $st === 'sent' ? 'status-approved' : ($st === 'pending_review' ? 'status-pending' : ($st === 'rejected' ? 'status-rejected' : ''));
          ?>
          <span class="<?= $stClass ?>"><?= htmlspecialchars($st) ?></span>
        </td>
        <td class="admin-muted"><?= htmlspecialchars($msg['created_at'] ?? '') ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
</div>

<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
