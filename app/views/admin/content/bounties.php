<?php $pageTitle='悬赏管理'; require dirname(__DIR__) . '/layouts/main.php'; ?>
<div class="page-header"><div class="page-title">悬赏管理</div></div>
<?php if (!empty($error)): ?><div class="badge badge-err admin-mb-12"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="card">
  <h3>悬赏设置</h3>
  <form method="post" action="/admin.php?path=bounties" class="admin-form-grid">
    <?= csrf_field() ?><input type="hidden" name="_action" value="settings">
    <div><label>采纳手续费</label><input class="input" type="number" step="0.000001" min="0" name="accept_fee" value="<?= htmlspecialchars((string)$settings['accept_fee']) ?>"></div>
    <div><label>关闭手续费</label><input class="input" type="number" step="0.000001" min="0" name="close_fee" value="<?= htmlspecialchars((string)$settings['close_fee']) ?>"></div>
    <div><label>高匹配阈值</label><input class="input" type="number" min="0" max="100" step="0.01" name="ai_threshold" value="<?= htmlspecialchars((string)$settings['ai_threshold']) ?>"></div>
    <label class="admin-inline-strong"><input type="checkbox" name="ai_enabled" value="1" <?= !empty($settings['ai_enabled'])?'checked':'' ?>> 启用 AI 匹配评分</label>
    <button class="btn full-row" type="submit">保存设置</button>
  </form>
</div>
<div class="card">
  <h3>关闭审核</h3>
  <div class="table-responsive"><table class="table"><thead><tr><th>帖子</th><th>悬赏</th><th>申请人</th><th>AI 高匹配</th><th>操作</th></tr></thead><tbody>
  <?php foreach($reviews as $r): $snapshot=json_decode((string)($r['ai_snapshot'] ?? '[]'), true) ?: []; ?>
    <tr><td><a href="/index.php?path=thread&id=<?= (int)$r['thread_id'] ?>" target="_blank"><?= htmlspecialchars((string)$r['title']) ?></a><div class="muted"><?= htmlspecialchars((string)$r['reason']) ?></div></td><td><?= htmlspecialchars((string)$r['bounty_currency']) ?> <?= htmlspecialchars((string)$r['bounty_amount']) ?></td><td><?= htmlspecialchars((string)($r['requester_name'] ?? '')) ?></td><td><?php foreach(array_slice($snapshot,0,3) as $s): ?><div class="admin-mb-12"><b><?= htmlspecialchars((string)$s['score']) ?>%</b> #<?= (int)$s['post_id'] ?><div class="muted"><?= htmlspecialchars((string)$s['reason']) ?></div></div><?php endforeach; ?></td><td><form method="post" action="/admin.php?path=bounties" class="admin-grid-min"><?= csrf_field() ?><input type="hidden" name="_action" value="review"><input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>"><input class="input" name="post_id" placeholder="采纳回复ID"><input class="input" name="note" placeholder="审核备注"><button class="btn" name="decision" value="accept">采纳该回复</button><button class="btn btn-light" name="decision" value="close">同意关闭</button><button class="btn btn-light" name="decision" value="reject">驳回关闭</button></form></td></tr>
  <?php endforeach; ?>
  <?php if(empty($reviews)): ?><tr><td colspan="5" class="admin-empty">暂无待审核悬赏</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
