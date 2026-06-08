<?php
$pageTitle = '帖子管理';
require dirname(__DIR__) . '/layouts/main.php';
$threads = $threads ?? [];
$sections = $sections ?? [];
$total = $total ?? 0;
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$statusLabels = ['published'=>'已发布','pending'=>'待审核','hidden'=>'已屏蔽','deleted'=>'已删除'];
function admin_thread_can(string $perm, array $thread): bool {
    return \App\Services\ThreadPermission::can($perm, $thread, true);
}
?>


<div class="page-header">
  <div class="page-title">帖子管理</div>
  <form method="get" action="/admin.php" class="admin-filter-bar">
    <input type="hidden" name="path" value="threads">
    <input class="input" name="kw" placeholder="搜索标题" value="<?= htmlspecialchars($_GET['kw'] ?? '') ?>" class="admin-w-180">
    <select class="select admin-w-130" name="status"><option value="">全部状态</option><?php foreach ($statusLabels as $value=>$label): ?><option value="<?= $value ?>" <?= (($_GET['status'] ?? '') === $value) ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select>
    <select class="select admin-w-160" name="section_id"><option value="0">全部板块</option><?php foreach ($sections as $s): ?><option value="<?= (int)$s['id'] ?>" <?= ((int)($_GET['section_id'] ?? 0) === (int)$s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?></select>
    <button class="btn" type="submit">筛选</button>
  </form>
</div>
<div class="admin-muted">共 <?= (int)$total ?> 个帖子</div>
<form id="bulkThreadForm" method="post" action="/admin.php?path=threads/bulk" data-refresh-on-success onsubmit="return confirm('确认执行批量操作？')">
  <?= csrf_field() ?>
</form>
<div class="admin-filter-bar">
    <select class="select admin-w-150" name="bulk_action" form="bulkThreadForm" required><option value="">批量操作</option><option value="published">恢复发布</option><option value="hidden">批量屏蔽</option><option value="deleted">批量彻底删除</option><option value="move">批量移动板块</option></select>
    <select class="select admin-w-180" name="target_section_id" form="bulkThreadForm"><option value="0">移动到板块...</option><?php foreach ($sections as $s): ?><option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?></select>
    <button class="btn btn-light" type="submit" form="bulkThreadForm">执行</button>
  </div>
  <div class="table-responsive"><table class="table">
    <thead><tr><th class="admin-thread-check-col"><input type="checkbox" onclick="document.querySelectorAll('.thread-check').forEach(c=>c.checked=this.checked)"></th><th>标题</th><th>板块</th><th>作者</th><th>状态</th><th>属性</th><th>时间</th><th>操作</th></tr></thead>
    <tbody>
    <?php if (!empty($threads)): ?>
      <?php foreach ($threads as $t): ?>
      <tr>
        <td><input class="thread-check" type="checkbox" name="ids[]" value="<?= (int)$t['id'] ?>" form="bulkThreadForm"></td>
        <td><?= htmlspecialchars($t['title']) ?></td>
        <td><?= htmlspecialchars($t['section_name'] ?? '') ?></td>
        <td><?= htmlspecialchars($t['author_name'] ?? '') ?></td>
        <td><span class="badge <?= $t['status']==='published'?'badge-ok':($t['status']==='pending'?'badge-warn':'badge-err') ?>"><?= $statusLabels[$t['status']] ?? htmlspecialchars($t['status']) ?></span></td>
        <td class="admin-muted"><?= !empty($t['is_top']) ? (((string)($t['top_scope'] ?? 'section') === 'global') ? '全局置顶 ' : '板块置顶 ') : '' ?><?= !empty($t['is_featured']) ? '精华 ':'' ?><?= !empty($t['is_recommended']) ? '推荐 ':'' ?><?= !empty($t['is_locked']) ? '锁定':'' ?><?= empty($t['is_top']) && empty($t['is_featured']) && empty($t['is_recommended']) && empty($t['is_locked']) ? '普通' : '' ?></td>
        <td class="admin-muted"><?= date('m-d H:i', strtotime($t['created_at'])) ?></td>
        <td><div class="admin-actions">
          <a class="btn btn-light" href="/index.php?path=thread&id=<?= (int)$t['id'] ?>" target="_blank" class="admin-action-sm">查看</a>
          <?php if (admin_thread_can('thread.edit_any', $t)): ?><a class="btn btn-light" href="/admin.php?path=threads/edit&id=<?= (int)$t['id'] ?>" class="admin-action-sm">编辑</a><?php endif; ?>
          <?php if (admin_thread_can('thread.edit_any', $t) || admin_thread_can('thread.hide', $t) || admin_thread_can('thread.pin', $t) || admin_thread_can('thread.feature', $t) || admin_thread_can('thread.recommend', $t) || admin_thread_can('thread.lock', $t) || admin_thread_can('thread.delete_any', $t)): ?>
            <button class="btn btn-light" type="button" data-admin-manage-open="<?= (int)$t['id'] ?>">管理</button>
            <div class="reason-modal admin-manage-modal" id="adminManageModal-<?= (int)$t['id'] ?>" aria-hidden="true"><div class="reason-modal-mask" data-admin-manage-close></div><div class="reason-modal-card" role="dialog" aria-modal="true"><div class="reason-modal-title">管理帖子</div><div class="reason-modal-desc"><?= htmlspecialchars((string)$t['title']) ?></div><div class="admin-actions"><a class="btn btn-light" href="/admin.php?path=posts&thread_id=<?= (int)$t['id'] ?>" class="admin-action-sm">回复管理</a><form class="admin-manage-action-form admin-filter-bar" method="post" action="/admin.php?path=threads/action">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <?php if (admin_thread_can('thread.edit_any', $t)): ?><button class="btn btn-light" type="button" data-admin-move-open data-current-section="<?= (int)($t['section_id'] ?? 0) ?>">移动</button><?php endif; ?>
              <?php if (admin_thread_can('thread.hide', $t) && $t['status'] !== 'published'): ?><button class="btn" name="status" value="published">恢复</button><?php endif; ?>
              <?php if (admin_thread_can('thread.hide', $t) && $t['status'] !== 'hidden'): ?><button class="btn btn-light" name="status" value="hidden">屏蔽</button><?php endif; ?>
              <?php if (admin_thread_can('thread.pin', $t)): ?><button class="btn btn-light" type="button" data-admin-pin-open><?= !empty($t['is_top']) ? (((string)($t['top_scope'] ?? '') === 'global') ? '已置顶·全局' : '已置顶·板块') : '置顶' ?></button><?php endif; ?>
              <?php if (admin_thread_can('thread.feature', $t)): ?><input type="hidden" name="featured_reason" value="<?= htmlspecialchars((string)($t['featured_reason'] ?? '')) ?>"><button class="btn btn-light" type="button" data-reason-action="featured" data-reason-field="featured_reason" data-reason-current="<?= htmlspecialchars((string)($t['featured_reason'] ?? ''), ENT_QUOTES) ?>" data-reason-prompt="精华理由，可留空"><?= !empty($t['is_featured']) ? '取消精华' : '精华' ?></button><?php endif; ?>
              <?php if (admin_thread_can('thread.recommend', $t)): ?><input type="hidden" name="recommended_reason" value="<?= htmlspecialchars((string)($t['recommended_reason'] ?? '')) ?>"><button class="btn btn-light" type="button" data-reason-action="recommended" data-reason-field="recommended_reason" data-reason-current="<?= htmlspecialchars((string)($t['recommended_reason'] ?? ''), ENT_QUOTES) ?>" data-reason-prompt="推荐理由，可留空"><?= !empty($t['is_recommended']) ? '取消推荐' : '推荐' ?></button><button class="btn btn-light" name="moderation_action" value="broadcast_section">推送板块转播</button><?php endif; ?>
              <?php if (admin_thread_can('thread.lock', $t)): ?><button class="btn btn-light" name="moderation_action" value="locked"><?= !empty($t['is_locked']) ? '解锁' : '锁定' ?></button><?php endif; ?>
              <?php if (admin_thread_can('thread.delete_any', $t)): ?><button class="btn admin-danger-bg" name="status" value="deleted" onclick="return confirm('确认彻底删除该帖子？此操作会删除帖子、回复、点赞、收藏、举报等关联记录，无法恢复。')">删除</button><?php endif; ?>
            </form></div><div class="reason-modal-actions"><button class="btn btn-light" type="button" data-admin-manage-close>关闭</button></div></div></div>
          <?php endif; ?>
        </div></td>
      </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr><td colspan="8" class="admin-empty">暂无帖子</td></tr>
    <?php endif; ?>
    </tbody>
  </table></div>
<div class="reason-modal" id="adminMoveSelectModal" aria-hidden="true"><div class="reason-modal-mask" data-admin-move-cancel></div><div class="reason-modal-card" role="dialog" aria-modal="true" aria-labelledby="adminMoveSelectTitle"><div class="reason-modal-title" id="adminMoveSelectTitle">移动帖子</div><div class="reason-modal-desc">选择目标板块，确认后帖子会移动过去。</div><select class="move-modal-select" id="adminMoveSelectSection" aria-label="目标板块"><option value="0">请选择目标板块</option><?php foreach ($sections as $s): ?><option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?></select><div class="reason-modal-actions"><button class="btn btn-light" type="button" data-admin-move-cancel>取消</button><button class="btn" type="button" data-admin-move-confirm>确认移动</button></div></div></div>
<div class="reason-modal" id="adminPinModal" aria-hidden="true"><div class="reason-modal-mask" data-admin-pin-cancel></div><div class="reason-modal-card" role="dialog" aria-modal="true" aria-labelledby="adminPinTitle"><div class="reason-modal-title" id="adminPinTitle">设置置顶方式</div><div class="reason-modal-desc">选择帖子全局置顶，或只在所属板块置顶。</div><div class="reason-modal-actions admin-inline-actions"><button class="btn btn-light" type="button" data-admin-pin-action="top_global">全局置顶</button><button class="btn btn-light" type="button" data-admin-pin-action="top_section">板块置顶</button><button class="btn btn-light admin-danger" type="button" data-admin-pin-action="top_cancel">取消置顶</button><button class="btn btn-light" type="button" data-admin-pin-cancel>关闭</button></div></div></div>
<div class="reason-modal" id="adminReasonModal" aria-hidden="true"><div class="reason-modal-mask" data-reason-cancel></div><div class="reason-modal-card" role="dialog" aria-modal="true" aria-labelledby="adminReasonTitle"><div class="reason-modal-title" id="adminReasonTitle">填写理由</div><div class="reason-modal-desc">这段理由会展示在帖子详情里，留空也可以。</div><textarea class="reason-modal-input" id="adminReasonInput" maxlength="255" placeholder="请输入理由，可留空"></textarea><div class="reason-modal-actions"><button class="btn btn-light" type="button" data-reason-cancel>取消</button><button class="btn" type="button" data-reason-confirm>确定</button></div></div></div>
<?php if ($totalPages > 1): ?>
  <div class="admin-actions admin-mt-14">
    <?php $base = $_GET; $base['path'] = 'threads'; ?>
    <?php if ($page > 1): $base['page']=$page-1; ?><a class="btn btn-light admin-link-clean" href="/admin.php?<?= http_build_query($base) ?>">上一页</a><?php endif; ?>
    <span class="admin-muted">第 <?= (int)$page ?> / <?= (int)$totalPages ?> 页</span>
    <?php if ($page < $totalPages): $base['page']=$page+1; ?><a class="btn btn-light admin-link-clean" href="/admin.php?<?= http_build_query($base) ?>">下一页</a><?php endif; ?>
  </div>
<?php endif; ?>

<script>
(function(){
  var modal=document.getElementById('adminReasonModal');
  var input=document.getElementById('adminReasonInput');
  var title=document.getElementById('adminReasonTitle');
  var pending=null;
  function submitReason(ctx, reason){
    var form=ctx.form, action=ctx.action, fieldName=ctx.fieldName;
    var field=form.querySelector('input[name="'+fieldName+'"]');
    if(field) field.value=reason||'';
    var actionInput=form.querySelector('input[name="moderation_action"][data-reason-submit]');
    if(!actionInput){actionInput=document.createElement('input');actionInput.type='hidden';actionInput.name='moderation_action';actionInput.setAttribute('data-reason-submit','1');form.appendChild(actionInput);}
    actionInput.value=action;
    if(form.requestSubmit) form.requestSubmit(); else form.submit();
  }
  function closeReason(){if(modal){modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');} pending=null;}
  document.querySelectorAll('[data-reason-action]').forEach(function(btn){
    btn.addEventListener('click',function(){
      var form=btn.closest('form'); if(!form) return;
      var ctx={form:form,action:btn.getAttribute('data-reason-action')||'',fieldName:btn.getAttribute('data-reason-field')||''};
      var isCancel=(btn.textContent||'').indexOf('取消')!==-1;
      if(isCancel){submitReason(ctx,'');return;}
      pending=ctx;
      if(title) title.textContent=(ctx.action==='featured'?'精华理由':'推荐理由');
      if(input){input.value=btn.getAttribute('data-reason-current')||'';input.placeholder=btn.getAttribute('data-reason-prompt')||'请输入理由，可留空';}
      if(modal){modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');setTimeout(function(){input&&input.focus();},30);} else {submitReason(ctx,'');}
    });
  });
  document.querySelectorAll('[data-reason-cancel]').forEach(function(el){el.addEventListener('click',closeReason);});
  var ok=document.querySelector('[data-reason-confirm]');
  if(ok) ok.addEventListener('click',function(){if(!pending)return;submitReason(pending,input?input.value:'');closeReason();});
  document.addEventListener('keydown',function(e){if(e.key==='Escape'&&modal&&modal.classList.contains('is-open'))closeReason();});
})();
(function(){
  function closeModal(modal){if(modal){modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');}}
  var pinModal=document.getElementById('adminPinModal'), pinForm=null, moveModal=document.getElementById('adminMoveSelectModal'), moveForm=null;
  function closePin(){if(pinModal){pinModal.classList.remove('is-open');pinModal.setAttribute('aria-hidden','true');}pinForm=null;}
  function closeMove(){if(moveModal){moveModal.classList.remove('is-open');moveModal.setAttribute('aria-hidden','true');}moveForm=null;}
  function submitPin(action){if(!pinForm)return;var input=pinForm.querySelector('input[name="moderation_action"][data-pin-submit]');if(!input){input=document.createElement('input');input.type='hidden';input.name='moderation_action';input.setAttribute('data-pin-submit','1');pinForm.appendChild(input);}input.value=action;if(pinForm.requestSubmit)pinForm.requestSubmit();else pinForm.submit();}
  function submitMove(){if(!moveForm)return;var sel=document.getElementById('adminMoveSelectSection');if(!sel||parseInt(sel.value||'0',10)<=0){alert('请选择目标板块');return;}var target=moveForm.querySelector('input[name="target_section_id"][data-move-submit]');if(!target){target=document.createElement('input');target.type='hidden';target.name='target_section_id';target.setAttribute('data-move-submit','1');moveForm.appendChild(target);}target.value=sel.value;var action=moveForm.querySelector('input[name="moderation_action"][data-move-submit]');if(!action){action=document.createElement('input');action.type='hidden';action.name='moderation_action';action.setAttribute('data-move-submit','1');moveForm.appendChild(action);}action.value='move';if(moveForm.requestSubmit)moveForm.requestSubmit();else moveForm.submit();}
  document.addEventListener('click',function(e){
    var moveOpen=e.target.closest('[data-admin-move-open]');
    if(moveOpen){moveForm=moveOpen.closest('form');var sel=document.getElementById('adminMoveSelectSection');if(sel)sel.value=moveOpen.getAttribute('data-current-section')||'0';if(moveModal){if(moveModal.parentNode!==document.body)document.body.appendChild(moveModal);moveModal.classList.add('is-open');moveModal.setAttribute('aria-hidden','false');}return;}
    if(e.target.closest('[data-admin-move-confirm]')){submitMove();closeMove();return;}
    if(e.target.closest('[data-admin-move-cancel]')){closeMove();return;}
    var pinOpen=e.target.closest('[data-admin-pin-open]');
    if(pinOpen){pinForm=pinOpen.closest('form');if(pinModal){if(pinModal.parentNode!==document.body)document.body.appendChild(pinModal);pinModal.classList.add('is-open');pinModal.setAttribute('aria-hidden','false');}return;}
    var pinAction=e.target.closest('[data-admin-pin-action]');
    if(pinAction){submitPin(pinAction.getAttribute('data-admin-pin-action')||'');closePin();return;}
    if(e.target.closest('[data-admin-pin-cancel]')){closePin();return;}
    var opener=e.target.closest('[data-admin-manage-open]');
    if(opener){var modal=document.getElementById('adminManageModal-'+opener.getAttribute('data-admin-manage-open'));if(modal){if(modal.parentNode!==document.body)document.body.appendChild(modal);modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');}return;}
    if(e.target.closest('[data-admin-manage-close]')){closeModal(e.target.closest('.admin-manage-modal'));}
  });
  document.addEventListener('keydown',function(e){if(e.key==='Escape'){document.querySelectorAll('.admin-manage-modal.is-open').forEach(closeModal);closePin();closeMove();}});

})();
</script>
<?php require dirname(__DIR__) . '/layouts/main_footer.php'; ?>
