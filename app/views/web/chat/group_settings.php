<?php
$siteCfg = (new \App\Models\SettingModel())->getSiteConfig();
$groupId = (int)($group['id'] ?? ($_GET['id'] ?? 0));
$groupName = trim((string)($group['name'] ?? '群聊'));
$groupNotice = trim((string)($group['notice'] ?? ''));
$memberCount = (int)($group['member_count'] ?? count($members ?? []));
$myRole = (string)($group['role'] ?? 'member');
$roleText = $myRole === 'owner' ? '群主' : ($myRole === 'admin' ? '管理员' : '成员');
$canManage = in_array($myRole, ['owner', 'admin'], true);
$isMuted = !empty($group['muted_until']) && strtotime((string)$group['muted_until']) > time();
$isPinned = !empty($group['is_pinned']);
$joinMode = (string)($group['join_mode'] ?? 'direct');
$csrf = csrf_token();
$view = (string)($_GET['view'] ?? 'index');
$allowedViews = ['index','name','notice','members','search','manage','join-requests'];
if (!in_array($view, $allowedViews, true)) { $view = 'index'; }
if (!$canManage && in_array($view, ['manage', 'join-requests'], true)) { $view = 'index'; }
$baseUrl = '/index.php?path=group-chat/settings&id=' . $groupId;
$pageTitleMap = [
    'index' => '聊天信息',
    'name' => '群聊名称',
    'notice' => '群公告',
    'members' => '群成员',
    'search' => '查找聊天记录',
    'manage' => '群管理',
    'join-requests' => '加群申请',
];
$pageTitle = htmlspecialchars($pageTitleMap[$view] . ' - ' . (string)($siteCfg['site_name'] ?? 'ClayBBS'));
function gs_initial(string $name): string { return htmlspecialchars(mb_substr($name !== '' ? $name : '群', 0, 1)); }
function gs_avatar(array $m, int $size = 48): string {
    $name = (string)($m['nickname'] ?: $m['username'] ?: '成员');
    $avatar = trim((string)($m['avatar'] ?? ''));
    $s = (int)$size;
    if ($avatar !== '') {
        return '<span class="gs-avatar" style="width:' . $s . 'px;height:' . $s . 'px"><img src="' . htmlspecialchars($avatar) . '" alt=""></span>';
    }
    return '<span class="gs-avatar" style="width:' . $s . 'px;height:' . $s . 'px">' . gs_initial($name) . '</span>';
}
function gs_role_text(string $role): string { return $role === 'owner' ? '群主' : ($role === 'admin' ? '管理员' : '成员'); }
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<title><?= $pageTitle ?></title>
<style>
:root{--bg:#ededed;--bar:#f7f7f7;--card:#fff;--line:#e6e6e6;--text:#111;--sub:#8a8a8a;--green:#07c160;--red:#fa5151;--blue:#576b95}*{box-sizing:border-box}html,body{margin:0;min-height:100%;background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",Arial,sans-serif}.gs-phone{min-height:100vh;background:var(--bg);max-width:430px;margin:0 auto;position:relative;box-shadow:0 0 0 1px rgba(0,0,0,.035)}@media(max-width:520px){.gs-phone{max-width:none;box-shadow:none}}.gs-top{height:52px;padding-top:env(safe-area-inset-top);display:grid;grid-template-columns:68px minmax(0,1fr)68px;align-items:center;background:var(--bar);border-bottom:1px solid rgba(0,0,0,.06);position:sticky;top:0;z-index:10}.gs-top a,.gs-top button{height:52px;border:0;background:transparent;color:#111;text-decoration:none;display:flex;align-items:center;justify-content:center;font-size:15px;cursor:pointer}.gs-back{gap:2px;justify-content:flex-start!important;padding-left:12px!important}.gs-back svg,.gs-more svg,.gs-cell-arrow svg,.gs-add svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.gs-title{text-align:center;font-size:17px;font-weight:650;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.gs-done{color:var(--green)!important;font-weight:650}.gs-done[disabled]{color:#b6b6b6!important}.gs-body{padding-bottom:34px}.gs-members{background:var(--card);padding:18px 14px 12px;margin-bottom:8px}.gs-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:16px 10px}.gs-member{text-align:center;min-width:0;color:inherit;text-decoration:none;border:0;background:transparent;padding:0}.gs-avatar{border-radius:8px;overflow:hidden;margin:0 auto 7px;display:grid;place-items:center;color:#fff;background:linear-gradient(135deg,#5b8cff,#7c3aed);font-weight:700;font-size:18px}.gs-avatar img{width:100%;height:100%;object-fit:cover;display:block}.gs-name{font-size:12px;line-height:1.25;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.gs-add{width:48px;height:48px;border-radius:8px;border:1px dashed #cfcfcf;background:#f7f7f7;color:#777;display:grid;place-items:center;margin:0 auto 7px}.gs-more-row{margin-top:15px;width:100%;height:32px;border:0;background:transparent;color:#666;font-size:14px}.gs-section{background:var(--card);margin:8px 0}.gs-cell{min-height:52px;padding:0 15px;display:grid;grid-template-columns:auto minmax(0,1fr)22px;align-items:center;gap:12px;border:0;border-bottom:1px solid var(--line);background:var(--card);width:100%;text-decoration:none;color:inherit;font-size:16px;text-align:left}.gs-cell:last-child{border-bottom:0}.gs-cell.no-arrow{grid-template-columns:auto minmax(0,1fr)}.gs-cell[disabled]{opacity:.55}.gs-value{min-width:0;text-align:right;color:var(--sub);font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.gs-subvalue{max-width:220px}.gs-cell-arrow{color:#c2c2c2;font-size:24px;display:flex;align-items:center;justify-content:center;font-weight:300;line-height:1}.gs-switch{justify-self:end;width:51px;height:31px;border-radius:999px;background:#d8d8d8;position:relative;transition:.18s}.gs-switch:before{content:"";position:absolute;width:27px;height:27px;left:2px;top:2px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:.18s}.gs-switch.on{background:var(--green)}.gs-switch.on:before{transform:translateX(20px)}.gs-danger,.gs-safe{display:block;width:100%;height:52px;margin:8px 0 0;border:0;background:#fff;font-size:16px}.gs-danger{color:var(--red)}.gs-safe{color:#111}.gs-note{padding:10px 16px 18px;color:#999;font-size:13px;line-height:1.6}.gs-edit{padding:16px}.gs-input,.gs-textarea,.gs-search-input{width:100%;border:0;background:#fff;border-radius:0;font:inherit;color:#111;outline:none}.gs-input{height:52px;padding:0 15px;font-size:17px}.gs-textarea{min-height:180px;resize:none;padding:14px 15px;line-height:1.6;font-size:16px}.gs-help{padding:10px 2px;color:#999;font-size:13px;line-height:1.6}.gs-list{background:#fff}.gs-user-row{min-height:64px;padding:8px 15px;display:grid;grid-template-columns:44px minmax(0,1fr)auto;gap:12px;align-items:center;border-bottom:1px solid var(--line)}.gs-user-row:last-child{border-bottom:0}.gs-user-row .gs-avatar{margin:0;border-radius:7px;font-size:16px}.gs-user-main{min-width:0}.gs-user-name{font-size:16px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.gs-user-id{font-size:12px;color:#999;margin-top:4px}.gs-role{font-size:13px;color:#777}.gs-role.owner{color:#d59b00}.gs-search-wrap{padding:10px;background:var(--bar);position:sticky;top:52px;z-index:6}.gs-search-box{height:38px;background:#fff;border-radius:6px;display:grid;grid-template-columns:1fr 52px;overflow:hidden}.gs-search-input{height:38px;padding:0 12px;font-size:15px}.gs-search-btn{border:0;background:#fff;color:var(--green);font-weight:650}.gs-result-empty{padding:46px 16px;text-align:center;color:#999;font-size:14px}.gs-message{padding:12px 15px;border-bottom:1px solid var(--line);background:#fff}.gs-message-meta{font-size:12px;color:#999;margin-bottom:6px}.gs-message-text{font-size:15px;line-height:1.55;color:#222;word-break:break-word}.gs-action-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:30;display:none}.gs-action-overlay.show{display:block}.gs-sheet{position:absolute;left:50%;bottom:0;width:min(430px,100vw);transform:translateX(-50%);background:#f5f5f5;border-radius:14px 14px 0 0;overflow:hidden;padding-bottom:env(safe-area-inset-bottom)}.gs-sheet-title{background:#fff;padding:18px 18px 8px;text-align:center;font-weight:650}.gs-sheet-desc{background:#fff;padding:0 24px 16px;text-align:center;color:#888;font-size:13px;line-height:1.6}.gs-sheet button{width:100%;height:54px;border:0;background:#fff;border-top:1px solid var(--line);font-size:16px}.gs-sheet .danger{color:var(--red);font-weight:650}.gs-sheet .cancel{margin-top:8px;color:#111}.gs-toast{position:fixed;left:50%;bottom:88px;transform:translateX(-50%);max-width:260px;padding:9px 14px;border-radius:6px;background:rgba(0,0,0,.72);color:#fff;font-size:14px;opacity:0;pointer-events:none;transition:.18s;z-index:50}.gs-toast.show{opacity:1}.gs-manage-card{background:#fff;margin:8px 0;padding:18px 15px}.gs-manage-title{font-weight:650;margin-bottom:8px}.gs-manage-text{color:#777;font-size:14px;line-height:1.7}.gs-kv{display:grid;grid-template-columns:90px 1fr;gap:8px;padding:10px 0;border-bottom:1px solid var(--line);font-size:15px}.gs-kv:last-child{border-bottom:0}.gs-kv span:first-child{color:#999}.gs-kv span:last-child{text-align:right;color:#333}.gs-status{padding:10px 16px;color:#999;font-size:13px;min-height:34px}.gs-hidden{display:none!important}.gs-approve-btn,.gs-reject-btn{padding:6px 12px;border:0;border-radius:4px;font-size:13px;cursor:pointer;transition:opacity .18s}.gs-approve-btn{background:#07c160;color:#fff}.gs-reject-btn{background:#fa5151;color:#fff}.gs-approve-btn:hover,.gs-reject-btn:hover{opacity:.85}.gs-approve-btn:active,.gs-reject-btn:active{opacity:.7}
</style>
</head>
<body>
<div class="gs-phone" data-group-settings data-group-id="<?= $groupId ?>" data-csrf="<?= htmlspecialchars($csrf) ?>" data-base-url="<?= htmlspecialchars($baseUrl) ?>" data-group-name="<?= htmlspecialchars($groupName) ?>" data-group-notice="<?= htmlspecialchars($groupNotice) ?>">
<?php if ($view === 'index'): ?>
  <header class="gs-top">
    <button class="gs-back" type="button" data-back-chat><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg><span>返回</span></button>
    <div class="gs-title">聊天信息</div>
    <button class="gs-more" type="button" data-refresh aria-label="刷新"><svg viewBox="0 0 24 24"><path d="M20 12a8 8 0 1 1-2.35-5.65"/><path d="M20 4v6h-6"/></svg></button>
  </header>
  <main class="gs-body">
    <section class="gs-members">
      <div class="gs-grid">
        <?php foreach (array_slice($members ?? [], 0, 9) as $m): $name=(string)($m['nickname'] ?: $m['username'] ?: '成员'); ?>
          <a class="gs-member" href="<?= htmlspecialchars($baseUrl . '&view=members') ?>">
            <?= gs_avatar($m, 48) ?>
            <div class="gs-name"><?= htmlspecialchars($name) ?></div>
          </a>
        <?php endforeach; ?>
        <?php if ($canManage): ?>
          <a class="gs-member" href="/index.php?path=me" data-add-member>
            <span class="gs-add"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></span>
            <div class="gs-name">添加</div>
          </a>
        <?php endif; ?>
      </div>
      <a class="gs-more-row" href="<?= htmlspecialchars($baseUrl . '&view=members') ?>" style="display:block;text-align:center;text-decoration:none;line-height:32px;">查看更多群成员 <?= $memberCount ?></a>
    </section>

    <section class="gs-section">
      <?php if ($canManage): ?>
        <a class="gs-cell" href="<?= htmlspecialchars($baseUrl . '&view=name') ?>"><span>群聊名称</span><span class="gs-value gs-subvalue" data-name-text><?= htmlspecialchars($groupName) ?></span><span class="gs-cell-arrow">›</span></a>
      <?php else: ?>
        <div class="gs-cell no-arrow"><span>群聊名称</span><span class="gs-value gs-subvalue" data-name-text><?= htmlspecialchars($groupName) ?></span></div>
      <?php endif; ?>
      <a class="gs-cell" href="<?= htmlspecialchars($baseUrl . '&view=notice') ?>"><span>群公告</span><span class="gs-value gs-subvalue" data-notice-text><?= htmlspecialchars($groupNotice !== '' ? $groupNotice : '未设置') ?></span><span class="gs-cell-arrow">›</span></a>
      <?php if ($canManage): ?>
        <a class="gs-cell" href="<?= htmlspecialchars($baseUrl . '&view=manage') ?>"><span>群管理</span><span class="gs-value"><?= htmlspecialchars($roleText) ?></span><span class="gs-cell-arrow">›</span></a>
      <?php else: ?>
        <div class="gs-cell no-arrow"><span>我的身份</span><span class="gs-value"><?= htmlspecialchars($roleText) ?></span></div>
      <?php endif; ?>
      <button class="gs-cell no-arrow" type="button" data-copy="<?= htmlspecialchars((string)($group['public_id'] ?? '')) ?>"><span>群号</span><span class="gs-value"><?= htmlspecialchars((string)($group['public_id'] ?? '')) ?></span></button>
    </section>

    <?php if ($canManage): ?>
    <section class="gs-section">
      <div class="gs-cell no-arrow"><span>加群方式</span><span class="gs-switch <?= $joinMode === 'approval' ? 'on' : '' ?>" data-switch="join_mode"></span></div>
      <a class="gs-cell" href="<?= htmlspecialchars($baseUrl . '&view=join-requests') ?>"><span>加群申请</span><span class="gs-value" data-join-request-count></span><span class="gs-cell-arrow">›</span></a>
    </section>
    <?php endif; ?>

    <section class="gs-section">
      <button class="gs-cell no-arrow" type="button" data-toggle-setting="muted"><span>消息免打扰</span><span class="gs-switch <?= $isMuted ? 'on' : '' ?>" data-switch="muted"></span></button>
      <button class="gs-cell no-arrow" type="button" data-toggle-setting="pinned"><span>置顶聊天</span><span class="gs-switch <?= $isPinned ? 'on' : '' ?>" data-switch="pinned"></span></button>
      <a class="gs-cell" href="<?= htmlspecialchars($baseUrl . '&view=search') ?>"><span>查找聊天记录</span><span class="gs-value"></span><span class="gs-cell-arrow">›</span></a>
    </section>

    <?php if ($canManage): ?>
    <section class="gs-section">
      <a class="gs-cell" href="<?= htmlspecialchars($baseUrl . '&view=manage') ?>"><span>群成员权限</span><span class="gs-value">可管理</span><span class="gs-cell-arrow">›</span></a>
      <a class="gs-cell" href="/index.php?path=report&type=group&id=<?= $groupId ?>"><span>投诉</span><span class="gs-value"></span><span class="gs-cell-arrow">›</span></a>
    </section>
    <?php else: ?>
    <section class="gs-section">
      <a class="gs-cell" href="/index.php?path=report&type=group&id=<?= $groupId ?>"><span>投诉</span><span class="gs-value"></span><span class="gs-cell-arrow">›</span></a>
    </section>
    <?php endif; ?>

    <button class="gs-safe" type="button" data-open-action="clear">清空聊天记录</button>
    <button class="gs-danger" type="button" data-open-action="leave"><?= $myRole === 'owner' ? '解散群聊' : '退出群聊' ?></button>
    <div class="gs-note">群设置只影响当前群聊。消息免打扰、置顶聊天和清空聊天记录均为当前账号独立设置。</div>
  </main>
<?php elseif ($view === 'name'): ?>
  <header class="gs-top"><a class="gs-back" href="<?= htmlspecialchars($baseUrl) ?>"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg><span>返回</span></a><div class="gs-title">群聊名称</div><button class="gs-done" type="button" data-save-name <?= $canManage ? '' : 'disabled' ?>>完成</button></header>
  <main class="gs-edit"><input class="gs-input" data-name-input maxlength="40" value="<?= htmlspecialchars($groupName) ?>" <?= $canManage ? '' : 'disabled' ?>><div class="gs-help"><?= $canManage ? '修改后群成员都可以看到新的群聊名称。' : '仅群主或管理员可以修改群聊名称。' ?></div><div class="gs-status" data-status></div></main>
<?php elseif ($view === 'notice'): ?>
  <header class="gs-top"><a class="gs-back" href="<?= htmlspecialchars($baseUrl) ?>"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg><span>返回</span></a><div class="gs-title">群公告</div><button class="gs-done" type="button" data-save-notice <?= $canManage ? '' : 'disabled' ?>>完成</button></header>
  <main class="gs-edit"><textarea class="gs-textarea" data-notice-input maxlength="500" placeholder="填写群公告" <?= $canManage ? '' : 'disabled' ?>><?= htmlspecialchars($groupNotice) ?></textarea><div class="gs-help"><?= $canManage ? '群公告最多 500 个字，保存后所有成员可见。' : '仅群主或管理员可以编辑群公告。' ?></div><div class="gs-status" data-status></div></main>
<?php elseif ($view === 'members'): ?>
  <header class="gs-top"><a class="gs-back" href="<?= htmlspecialchars($baseUrl) ?>"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg><span>返回</span></a><div class="gs-title">群成员</div><span></span></header>
  <main class="gs-body"><section class="gs-list"><?php foreach ($members ?? [] as $m): $name=(string)($m['nickname'] ?: $m['username'] ?: '成员'); $role=(string)($m['role'] ?? 'member'); ?><div class="gs-user-row"><?= gs_avatar($m, 44) ?><div class="gs-user-main"><div class="gs-user-name"><?= htmlspecialchars($name) ?></div><div class="gs-user-id"><?= htmlspecialchars((string)($m['public_id'] ?? '')) ?></div></div><div class="gs-role <?= $role === 'owner' ? 'owner' : '' ?>"><?= htmlspecialchars(gs_role_text($role)) ?></div></div><?php endforeach; ?></section></main>
<?php elseif ($view === 'search'): ?>
  <header class="gs-top"><a class="gs-back" href="<?= htmlspecialchars($baseUrl) ?>"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg><span>返回</span></a><div class="gs-title">查找聊天记录</div><span></span></header>
  <main class="gs-body"><div class="gs-search-wrap"><div class="gs-search-box"><input class="gs-search-input" data-search-input placeholder="搜索聊天记录"><button class="gs-search-btn" type="button" data-search-run>搜索</button></div></div><div data-search-results><div class="gs-result-empty">输入关键词搜索当前群聊消息</div></div></main>
<?php elseif ($view === 'join-requests'): ?>
  <header class="gs-top"><a class="gs-back" href="<?= htmlspecialchars($baseUrl) ?>"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg><span>返回</span></a><div class="gs-title">加群申请</div><span></span></header>
  <main class="gs-body"><div data-join-requests><div class="gs-result-empty">加载中</div></div></main>
<?php else: ?>
  <header class="gs-top"><a class="gs-back" href="<?= htmlspecialchars($baseUrl) ?>"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg><span>返回</span></a><div class="gs-title">群管理</div><span></span></header>
  <main class="gs-body"><section class="gs-manage-card"><div class="gs-manage-title">当前身份</div><div class="gs-manage-text">你在本群的身份是：<?= htmlspecialchars($roleText) ?>。</div></section><section class="gs-section"><div class="gs-kv"><span>修改群名称</span><span><?= $canManage ? '可用' : '不可用' ?></span></div><div class="gs-kv"><span>编辑群公告</span><span><?= $canManage ? '可用' : '不可用' ?></span></div><div class="gs-kv"><span>成员数量</span><span><?= $memberCount ?></span></div><div class="gs-kv"><span>群号</span><span><?= htmlspecialchars((string)($group['public_id'] ?? '')) ?></span></div></section><div class="gs-note">成员管理能力会根据群主、管理员、普通成员身份展示。没有权限的操作不会显示为可执行按钮。</div></main>
<?php endif; ?>
  <div class="gs-action-overlay" data-action-overlay>
    <div class="gs-sheet" role="dialog" aria-modal="true">
      <div class="gs-sheet-title" data-sheet-title></div>
      <div class="gs-sheet-desc" data-sheet-desc></div>
      <button class="danger" type="button" data-sheet-confirm></button>
      <button class="cancel" type="button" data-sheet-cancel>取消</button>
    </div>
  </div>
  <div class="gs-toast" data-toast></div>
</div>
<script>
(function(){var root=document.querySelector('[data-group-settings]');if(!root)return;var gid=root.dataset.groupId,csrf=root.dataset.csrf,base=root.dataset.baseUrl;function $(s){return root.querySelector(s)}function toast(t){var el=$('[data-toast]');if(!el)return;el.textContent=t;el.classList.add('show');setTimeout(function(){el.classList.remove('show')},1800)}function status(t){var el=$('[data-status]');if(el)el.textContent=t}function fd(){var f=new FormData();f.append('_csrf_token',csrf);f.append('group_id',gid);return f}function post(url,form){return fetch(url,{method:'POST',body:form,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json()})}var back=$('[data-back-chat]');if(back){back.onclick=function(){if(history.length>1){history.back()}else{location.href='/index.php?path=me'}}}var refresh=$('[data-refresh]');if(refresh){refresh.onclick=function(){location.reload()}}root.addEventListener('click',function(e){var copy=e.target.closest('[data-copy]');if(copy){navigator.clipboard&&navigator.clipboard.writeText(copy.dataset.copy||'');toast('群号已复制')}var add=e.target.closest('[data-add-member]');if(add){e.preventDefault();toast('请从好友聊天页邀请成员')}});root.querySelectorAll('[data-toggle-setting]').forEach(function(btn){btn.onclick=function(){var type=btn.dataset.toggleSetting,sw=root.querySelector('[data-switch="'+type+'"]'),next=!sw.classList.contains('on'),form=fd();form.append('type',type);form.append('enabled',next?'1':'0');post('/api/group-chat/member-settings',form).then(function(d){if(!d.ok){toast(d.error||'保存失败');return}sw.classList.toggle('on',next);toast('已保存')}).catch(function(){toast('网络异常')})}});var joinModeSwitch=root.querySelector('[data-switch="join_mode"]');if(joinModeSwitch){joinModeSwitch.parentElement.onclick=function(){var next=!joinModeSwitch.classList.contains('on'),form=fd();form.append('mode',next?'approval':'direct');post('/api/group-chat/join-mode',form).then(function(d){if(!d.ok){toast(d.error||'保存失败');return}joinModeSwitch.classList.toggle('on',next);toast(next?'已开启审批加群':'已开放直接加群')}).catch(function(){toast('网络异常')})}}function loadJoinRequests(){var box=$('[data-join-requests]');if(!box)return;fetch('/api/group-chat/pending-requests?group_id='+encodeURIComponent(gid),{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){if(!d.ok){box.innerHTML='<div class="gs-result-empty">'+(d.error||'加载失败')+'</div>';return}var arr=d.requests||[];if(!arr.length){box.innerHTML='<div class="gs-result-empty">暂无加群申请</div>';return}box.innerHTML=arr.map(function(r){return '<div class="gs-user-row">'+(r.avatar?'<span class="gs-avatar" style="width:44px;height:44px"><img src="'+esc(r.avatar)+'" alt=""></span>':'<span class="gs-avatar" style="width:44px;height:44px">'+esc((r.nickname||r.username||'用').charAt(0))+'</span>')+'<div class="gs-user-main"><div class="gs-user-name">'+esc(r.nickname||r.username||'用户')+'</div><div class="gs-user-id">'+esc(r.message||'申请加入群聊')+'</div></div><div><button class="gs-approve-btn" data-request-id="'+r.id+'" style="background:#07c160;color:#fff;border:0;padding:6px 12px;border-radius:4px;font-size:13px;cursor:pointer">同意</button> <button class="gs-reject-btn" data-request-id="'+r.id+'" style="background:#fa5151;color:#fff;border:0;padding:6px 12px;border-radius:4px;font-size:13px;cursor:pointer">拒绝</button></div></div>'}).join('');var countEl=$('[data-join-request-count]');if(countEl)countEl.textContent=arr.length+'条待处理'}).catch(function(){box.innerHTML='<div class="gs-result-empty">网络异常</div>'})}if($('[data-join-requests]'))loadJoinRequests();root.addEventListener('click',function(e){var approve=e.target.closest('.gs-approve-btn'),reject=e.target.closest('.gs-reject-btn');if(approve){var id=approve.dataset.requestId,form=fd();form.append('request_id',id);form.append('decision','approve');post('/api/group-chat/review-join',form).then(function(d){if(!d.ok){toast(d.error||'操作失败');return}toast('已同意');loadJoinRequests()}).catch(function(){toast('网络异常')})}if(reject){var id2=reject.dataset.requestId,form2=fd();form2.append('request_id',id2);form2.append('decision','reject');post('/api/group-chat/review-join',form2).then(function(d){if(!d.ok){toast(d.error||'操作失败');return}toast('已拒绝');loadJoinRequests()}).catch(function(){toast('网络异常')})}});function saveInfo(kind){var nameEl=$('[data-name-input]'),noticeEl=$('[data-notice-input]'),form=fd();form.append('name',nameEl?(nameEl.value||'').trim():root.dataset.groupName);form.append('notice',noticeEl?(noticeEl.value||'').trim():root.dataset.groupNotice);status('保存中');post('/api/group-chat/update',form).then(function(d){if(!d.ok){status(d.error||'保存失败');return}status('已保存');setTimeout(function(){location.href=base},450)}).catch(function(){status('网络异常')})}var sn=$('[data-save-name]');if(sn)sn.onclick=function(){saveInfo('name')};var st=$('[data-save-notice]');if(st)st.onclick=function(){saveInfo('notice')};var searchBtn=$('[data-search-run]');if(searchBtn){var run=function(){var q=($('[data-search-input]').value||'').trim(),box=$('[data-search-results]');if(!q){box.innerHTML='<div class="gs-result-empty">请输入关键词</div>';return}box.innerHTML='<div class="gs-result-empty">搜索中</div>';fetch('/api/group-chat/search?group_id='+encodeURIComponent(gid)+'&q='+encodeURIComponent(q),{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){if(!d.ok){box.innerHTML='<div class="gs-result-empty">'+(d.error||'搜索失败')+'</div>';return}var arr=d.messages||[];if(!arr.length){box.innerHTML='<div class="gs-result-empty">没有找到相关记录</div>';return}box.innerHTML=arr.map(function(m){return '<div class="gs-message"><div class="gs-message-meta">'+esc(m.sender_name||'成员')+' · '+esc(m.created_at||'')+'</div><div class="gs-message-text">'+esc(m.content||'')+'</div></div>'}).join('')}).catch(function(){box.innerHTML='<div class="gs-result-empty">网络异常</div>'})};searchBtn.onclick=run;var si=$('[data-search-input]');if(si)si.addEventListener('keydown',function(e){if(e.key==='Enter')run()})}function esc(s){return String(s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}var overlay=$('[data-action-overlay]'),action=null;function openSheet(type){action=type;var owner='<?= $myRole === 'owner' ? '1' : '0' ?>'==='1';$('[data-sheet-title]').textContent=type==='clear'?'清空聊天记录':(owner?'解散群聊':'退出群聊');$('[data-sheet-desc]').textContent=type==='clear'?'只清空你当前账号看到的本群记录，不影响其他成员。':(owner?'解散后所有成员将不能继续使用该群聊。':'退出后你将不再接收本群消息。');$('[data-sheet-confirm]').textContent=type==='clear'?'确认清空':(owner?'确认解散':'确认退出');overlay.classList.add('show')}root.querySelectorAll('[data-open-action]').forEach(function(b){b.onclick=function(){openSheet(b.dataset.openAction)}});var cancel=$('[data-sheet-cancel]');if(cancel)cancel.onclick=function(){overlay.classList.remove('show')};if(overlay)overlay.addEventListener('click',function(e){if(e.target===overlay)overlay.classList.remove('show')});var confirm=$('[data-sheet-confirm]');if(confirm)confirm.onclick=function(){if(!action)return;var form=fd(),url=action==='clear'?'/api/group-chat/clear-history':'/api/group-chat/leave';post(url,form).then(function(d){if(!d.ok){toast(d.error||'操作失败');return}overlay.classList.remove('show');if(action==='leave'){location.href='/index.php?path=me'}else{toast('已清空')}}).catch(function(){toast('网络异常')})};})();
</script>
</body>
</html>
