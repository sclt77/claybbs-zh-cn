<?php
$_authUser = $_SESSION['auth_user'] ?? null;
function message_quick_link(array $msg, string $type): string {
    if (!empty($msg['link_url'])) return (string)$msg['link_url'];
    if ($type === 'finance') return '/index.php?path=wallet';
    $content = (string)($msg['content'] ?? '');
    if (preg_match('/帖子《([^》]+)》/u', $content, $m)) {
        try {
            $db = \App\Core\Database::connection();
            $stmt = $db->prepare("SELECT id FROM threads WHERE title = :title ORDER BY id DESC LIMIT 1");
            $stmt->execute([':title' => $m[1]]);
            $id = (int)$stmt->fetchColumn();
            if ($id > 0) {
                preg_match('/#post-(\d+)/', $content, $pm);
                return '/index.php?path=thread&id=' . $id . (!empty($pm[1]) ? '#post-' . (int)$pm[1] : '');
            }
        } catch (\Throwable $e) {}
    }
    return '';
}
$type = $type ?? ($_GET['type'] ?? 'all');
$box = in_array((string)($box ?? ($_GET['box'] ?? 'unread')), ['unread','history'], true) ? (string)($box ?? ($_GET['box'] ?? 'unread')) : 'unread';
$page = max(1, (int)($page ?? ($_GET['page'] ?? 1)));
$totalPages = max(1, (int)($totalPages ?? 1));
$total = (int)($total ?? 0);
$keyword = '';


$inviteStatuses = [];
try {
    $allMsgArr = $messages ?? [];
    $inviteIds = [];
    foreach ($allMsgArr as $m) {
        if (($m['ref_type'] ?? '') === 'group_invite' && !empty($m['ref_id'])) {
            $inviteIds[] = (int)$m['ref_id'];
        }
    }
    if (!empty($inviteIds)) {
        $placeholders = implode(',', array_fill(0, count($inviteIds), '?'));
        $invStmt = \App\Core\Database::connection()->prepare("SELECT id, status FROM chat_group_invites WHERE id IN ($placeholders)");
        $invStmt->execute($inviteIds);
        foreach ($invStmt->fetchAll(PDO::FETCH_ASSOC) as $inv) {
            $inviteStatuses[(int)$inv['id']] = $inv['status'];
        }
    }
} catch (\Throwable $e) {}

function invite_status_label(int $refId, array $inviteStatuses): ?string {
    $status = $inviteStatuses[$refId] ?? null;
    if ($status === 'accepted') return '已加入';
    if ($status === 'rejected') return '已拒绝';
    return null; 
}
$pageUrl = function (int $pageNo) use ($type, $box): string { return '/index.php?path=messages&type=' . urlencode($type) . '&box=' . urlencode($box) . '&page=' . $pageNo; };
$siteCfg = (new \App\Models\SettingModel())->getSiteConfig();
$tabs = [
  'all' => ['label'=>'全部','color'=>'#0284c7','icon'=>'<svg viewBox="0 0 24 24"><path d="M4 6h16"></path><path d="M4 12h16"></path><path d="M4 18h16"></path></svg>','empty'=>'暂无消息'],
  'fans' => ['label'=>'粉丝','color'=>'#f05265','icon'=>'<svg viewBox="0 0 24 24"><path d="M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0Z"></path><path d="M4.5 20c1.35-3.05 3.9-4.6 7.5-4.6s6.15 1.55 7.5 4.6"></path><path d="M18.5 8.5h2.8"></path><path d="M19.9 7.1v2.8"></path></svg>','empty'=>'暂无粉丝消息'],
  'reply' => ['label'=>'回复','color'=>'#6288f5','icon'=>'<svg viewBox="0 0 24 24"><path d="M5 6.5h14a2 2 0 0 1 2 2v6.2a2 2 0 0 1-2 2H10l-5 3v-3H5a2 2 0 0 1-2-2V8.5a2 2 0 0 1 2-2Z"></path><path d="M7.5 10.5h9"></path><path d="M7.5 13.5h5.5"></path></svg>','empty'=>'暂无回复消息'],
  'like' => ['label'=>'点赞','color'=>'#fb7185','icon'=>'<svg viewBox="0 0 24 24"><path d="M12 21s-7-4.35-9.2-8.55C1 9 3.2 5.5 6.9 5.5c2 0 3.4 1.05 4.1 2.1.7-1.05 2.1-2.1 4.1-2.1 3.7 0 5.9 3.5 4.1 6.95C19 16.65 12 21 12 21Z"></path></svg>','empty'=>'暂无点赞消息'],
  'favorite' => ['label'=>'收藏','color'=>'#f59e0b','icon'=>'<svg viewBox="0 0 24 24"><path d="M6 3.8h12a1 1 0 0 1 1 1v16l-7-4-7 4v-16a1 1 0 0 1 1-1Z"></path></svg>','empty'=>'暂无收藏消息'],
  'private' => ['label'=>'私聊','color'=>'#8b5cf6','icon'=>'<svg viewBox="0 0 24 24"><path d="M4 6.5h16v10H8l-4 3v-13Z"></path><path d="M8 10h8"></path><path d="M8 13h5"></path></svg>','empty'=>'暂无私聊通知'],
  'review' => ['label'=>'审核','color'=>'#0ea5e9','icon'=>'<svg viewBox="0 0 24 24"><path d="M12 3.5l7 3v5.6c0 4-2.7 6.9-7 7.9-4.3-1-7-3.9-7-7.9V6.5l7-3Z"></path><path d="M8.5 12l2.2 2.2 4.8-5"></path></svg>','empty'=>'暂无审核通知'],
  'finance' => ['label'=>'财务','color'=>'#25b83a','icon'=>'<svg viewBox="0 0 24 24"><path d="M6.5 8.5h11"></path><path d="M8 4.5h8l2.5 4v10a2 2 0 0 1-2 2h-9a2 2 0 0 1-2-2v-10l2.5-4Z"></path><path d="M12 9v7"></path><path d="M9.5 11.5h5"></path><path d="M9.5 14.5h5"></path></svg>','empty'=>'暂无财务记录'],
  'system' => ['label'=>'系统','color'=>'#ff6a3d','icon'=>'<svg viewBox="0 0 24 24"><path d="M12 3.5l7.5 3.2v5.8c0 4.25-2.9 7.25-7.5 8-4.6-.75-7.5-3.75-7.5-8V6.7L12 3.5Z"></path><path d="M9 12l2 2 4-4"></path></svg>','empty'=>'暂无系统消息'],
];
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>消息中心 - <?= htmlspecialchars($siteCfg['site_name'] ?? 'ClayBBS') ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
<?= user_avatar_verify_styles() ?>
<?= user_level_badge_styles() ?>
<style>
:root{--msg-bg:#f5f7fb;--msg-card:#ffffff;--msg-ink:#0f172a;--msg-muted:#64748b;--msg-soft:#94a3b8;--msg-line:#e2e8f0;--msg-primary:#2563eb;--msg-shadow:0 18px 45px rgba(15,23,42,.08)}
html[data-theme="dark"]{--msg-bg:#0b1120;--msg-card:#111827;--msg-ink:#e5e7eb;--msg-muted:#a7b0c0;--msg-soft:#7b8798;--msg-line:#243044;--msg-primary:#60a5fa;--msg-shadow:0 18px 45px rgba(0,0,0,.32)}
.msg-page{min-height:calc(100vh - 64px);padding:22px 16px 126px;background:radial-gradient(circle at 18% -8%,rgba(37,99,235,.16),transparent 32%),radial-gradient(circle at 92% 6%,rgba(14,165,233,.13),transparent 28%),var(--msg-bg);color:var(--msg-ink);position:relative;z-index:1}.msg-shell{max-width:1120px;margin:0 auto}.msg-hero{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:18px;align-items:end;margin:0 0 18px;padding:28px 30px;border:1px solid rgba(226,232,240,.78);border-radius:30px;background:linear-gradient(135deg,rgba(255,255,255,.94),rgba(248,250,252,.82));box-shadow:var(--msg-shadow);overflow:hidden;position:relative}.msg-hero::after{content:"";position:absolute;right:-70px;top:-90px;width:220px;height:220px;border-radius:999px;background:linear-gradient(135deg,rgba(37,99,235,.18),rgba(14,165,233,.06));pointer-events:none}.msg-kicker{display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:var(--msg-primary);margin:0 0 10px}.msg-kicker::before{content:"";width:8px;height:8px;border-radius:999px;background:var(--msg-primary);box-shadow:0 0 0 6px rgba(37,99,235,.1)}.msg-title{margin:0;font-size:clamp(32px,5vw,56px);font-weight:950;line-height:1;letter-spacing:-.06em;color:var(--msg-ink)}.msg-subtitle{margin:12px 0 0;color:var(--msg-muted);font-size:15px;line-height:1.7}.msg-header-actions{position:relative;z-index:1;display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end}.msg-header-actions a,.msg-header-actions button,.msg-clear-btn{height:38px;border:1px solid var(--msg-line);background:rgba(255,255,255,.74);color:var(--msg-ink);font-size:13px;font-weight:900;text-decoration:none;cursor:pointer;padding:0 15px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 8px 20px rgba(15,23,42,.045)}.msg-header-actions button{color:#fff;background:linear-gradient(135deg,#2563eb,#0ea5e9);border-color:transparent}.msg-clear-btn{color:#dc2626;background:rgba(255,255,255,.88)}.msg-tabs{display:grid;grid-template-columns:repeat(9,minmax(0,1fr));gap:10px;margin:0 0 14px}.msg-tab{position:relative;display:grid;grid-template-columns:auto minmax(0,1fr);align-items:center;gap:9px;text-decoration:none;color:var(--msg-ink);background:rgba(255,255,255,.82);border:1px solid rgba(226,232,240,.86);border-radius:20px;padding:12px 11px;box-shadow:0 10px 24px rgba(15,23,42,.045);transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}.msg-tab:hover,.msg-tab.active{transform:translateY(-2px);border-color:rgba(37,99,235,.35);box-shadow:0 16px 34px rgba(37,99,235,.12)}.msg-tab.active::after{content:"";position:absolute;left:16px;right:16px;bottom:-1px;height:3px;border-radius:999px;background:linear-gradient(90deg,#2563eb,#0ea5e9)}.msg-tab-icon{width:36px;height:36px;border-radius:14px;display:grid;place-items:center;color:#fff;box-shadow:0 10px 20px rgba(15,23,42,.14)}.msg-tab-icon svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.msg-tab-label{font-size:13px;font-weight:950;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.msg-tab-badge{position:absolute;right:8px;top:7px;min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:#ef4444;color:#fff;border:2px solid #fff;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:950;line-height:1;box-shadow:0 6px 14px rgba(239,68,68,.28)}.msg-filter{display:flex;justify-content:flex-end;margin:0 0 12px}.msg-box-tabs{display:inline-flex;gap:6px;margin:2px 0 14px;padding:6px;background:rgba(255,255,255,.72);border:1px solid rgba(226,232,240,.86);border-radius:999px;box-shadow:0 10px 26px rgba(15,23,42,.045)}.msg-box-tab{height:34px;display:inline-flex;align-items:center;justify-content:center;padding:0 18px;border-radius:999px;text-decoration:none;color:var(--msg-muted);font-size:13px;font-weight:950}.msg-box-tab.active{background:var(--msg-ink);color:#fff;box-shadow:0 10px 20px rgba(15,23,42,.14)}.msg-panel{background:rgba(255,255,255,.88);border:1px solid rgba(226,232,240,.86);border-radius:28px;padding:10px;box-shadow:var(--msg-shadow);overflow:hidden}.msg-card,.fan-row{position:relative;display:block;text-decoration:none;color:var(--msg-ink);background:transparent;border:0;border-bottom:1px dashed var(--msg-line);padding:18px 18px 18px 20px;transition:background .16s ease,transform .16s ease}.msg-card:last-child,.fan-row:last-child{border-bottom:0}.msg-card:hover,.fan-row:hover{background:rgba(37,99,235,.045);transform:translateX(2px)}.msg-card.unread{background:linear-gradient(90deg,rgba(37,99,235,.08),rgba(255,255,255,0) 58%)}.msg-card.unread::before{content:"";position:absolute;left:0;top:22px;bottom:22px;width:4px;border-radius:999px;background:linear-gradient(180deg,#2563eb,#0ea5e9)}.msg-card-title{font-size:16px;font-weight:950;letter-spacing:-.02em;color:var(--msg-ink);margin:0 0 8px;overflow-wrap:anywhere}.msg-card-content{font-size:14px;line-height:1.8;color:var(--msg-muted);overflow-wrap:anywhere}.msg-card-meta{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-top:12px;color:var(--msg-soft);font-size:12px}.msg-read-btn{border:0;background:transparent;color:var(--msg-primary);font-size:12px;font-weight:950;padding:0;text-decoration:none;cursor:pointer}.fan-row{display:grid;grid-template-columns:auto minmax(0,1fr);gap:12px;align-items:center}.fan-name{font-size:15px;font-weight:950;color:var(--msg-ink)}.msg-empty{min-height:240px;display:grid;place-items:center;text-align:center;color:var(--msg-muted);font-weight:900;font-size:15px}.msg-empty::before{content:"";width:72px;height:72px;border-radius:26px;background:linear-gradient(135deg,rgba(37,99,235,.12),rgba(14,165,233,.08));box-shadow:inset 0 0 0 1px rgba(37,99,235,.12);display:block;margin:0 auto 14px}.msg-pagination{display:flex;gap:8px;align-items:center;justify-content:center;flex-wrap:wrap;padding:18px}.msg-pagination a,.msg-pagination span{height:34px;min-width:34px;padding:0 12px;border-radius:999px;border:1px solid var(--msg-line);display:inline-flex;align-items:center;justify-content:center;text-decoration:none;color:var(--msg-muted);font-size:12px;font-weight:900}.msg-pagination a.active{background:var(--msg-ink);color:#fff;border-color:var(--msg-ink)}
html[data-theme="dark"] .msg-hero,html[data-theme="dark"] .msg-tab,html[data-theme="dark"] .msg-box-tabs,html[data-theme="dark"] .msg-panel{background:rgba(17,24,39,.86);border-color:#243044}html[data-theme="dark"] .msg-header-actions a,html[data-theme="dark"] .msg-clear-btn{background:rgba(17,24,39,.74);border-color:#243044;color:#e5e7eb}html[data-theme="dark"] .msg-card.unread{background:linear-gradient(90deg,rgba(96,165,250,.14),rgba(17,24,39,0) 58%)}
@media(max-width:1100px){.msg-tabs{grid-template-columns:repeat(5,minmax(0,1fr))}.msg-shell{max-width:920px}}@media(max-width:720px){.msg-page{padding:14px 10px 112px}.msg-hero{grid-template-columns:1fr;padding:22px 18px;border-radius:24px}.msg-title{font-size:34px}.msg-header-actions{justify-content:flex-start}.msg-tabs{display:flex;overflow-x:auto;gap:9px;padding:0 2px 8px;scroll-snap-type:x mandatory}.msg-tab{min-width:86px;grid-template-columns:1fr;justify-items:center;text-align:center;scroll-snap-align:start;padding:12px 10px}.msg-tab-icon{width:42px;height:42px}.msg-panel{border-radius:22px;padding:4px}.msg-card,.fan-row{padding:16px 14px}.msg-card-meta{align-items:flex-start;flex-direction:column}.msg-box-tabs{width:100%;display:grid;grid-template-columns:1fr 1fr;border-radius:18px}.msg-box-tab{border-radius:14px}.msg-header-actions a,.msg-header-actions button,.msg-clear-btn{height:36px;padding:0 12px}.msg-empty{min-height:190px}}

/* 2026-05-26 消息中心精简：去除顶部大卡片，操作移动到列表工具栏 */
.msg-page{padding-top:14px}.msg-tabs{margin-top:0}.msg-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:12px 0 12px;padding:12px 14px;border:1px solid var(--msg-line);border-radius:18px;background:rgba(255,255,255,.82);box-shadow:0 10px 24px rgba(15,23,42,.045);backdrop-filter:blur(14px)}.msg-toolbar-title{font-size:14px;font-weight:950;color:var(--msg-ink)}.msg-toolbar-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.msg-toolbar-actions a,.msg-toolbar-actions button{height:34px;border:1px solid var(--msg-line);border-radius:999px;background:rgba(255,255,255,.92);color:var(--msg-ink);padding:0 13px;font-size:12px;font-weight:900;text-decoration:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}.msg-toolbar-actions button{background:linear-gradient(135deg,#2563eb,#0ea5e9);border-color:transparent;color:#fff}.msg-pagination span{white-space:nowrap}html[data-theme="dark"] .msg-toolbar{background:rgba(17,24,39,.86);border-color:#243044}html[data-theme="dark"] .msg-toolbar-actions a{background:rgba(17,24,39,.74);border-color:#243044;color:#e5e7eb}@media(max-width:720px){.msg-page{padding-top:10px}.msg-toolbar{align-items:flex-start;flex-direction:column;border-radius:16px;padding:11px 12px}.msg-toolbar-actions{width:100%;justify-content:flex-start}.msg-toolbar-actions a,.msg-toolbar-actions button{height:32px;padding:0 11px}.msg-tabs{margin-bottom:4px}}
.msg-invite-card{border-left:3px solid #07c160}.msg-invite-actions{display:flex;gap:8px;padding:8px 0 0}.msg-invite-accept{height:32px;border:0;border-radius:6px;background:#07c160;color:#fff;font-size:13px;font-weight:600;padding:0 16px;cursor:pointer}.msg-invite-reject{height:32px;border:0;border-radius:6px;background:#f3f4f6;color:#374151;font-size:13px;padding:0 14px;cursor:pointer}
</style>
</head>
<body>
<?php require dirname(__DIR__) . '/layouts/topbar.php'; ?>
<div class="msg-page"><main class="msg-shell">
  <nav class="msg-tabs" aria-label="消息分类">
    <?php foreach ($tabs as $key => $tab): ?>
      <?php $unread = (int)($categoryUnreadCounts[$key] ?? 0); ?>
      <a class="msg-tab <?= $type === $key ? 'active' : '' ?>" data-tab-key="<?= $key ?>" href="/index.php?path=messages&type=<?= $key ?>">
        <?php if ($unread > 0): ?><span class="msg-tab-badge" data-tab-badge="<?= $key ?>" data-count="<?= $unread ?>"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?>
        <span class="msg-tab-icon" style="background:<?= $tab['color'] ?>"><?= $tab['icon'] ?></span>
        <span class="msg-tab-label"><?= $tab['label'] ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="msg-box-tabs">
    <a class="msg-box-tab <?= $box === 'unread' ? 'active' : '' ?>" href="/index.php?path=messages&type=<?= urlencode($type) ?>&box=unread">未读消息</a>
    <a class="msg-box-tab <?= $box === 'history' ? 'active' : '' ?>" href="/index.php?path=messages&type=<?= urlencode($type) ?>&box=history">历史消息</a>
  </div>

  <div class="msg-toolbar">
    <div class="msg-toolbar-title"><?= htmlspecialchars($tabs[$type]['label'] ?? '全部') ?> · <?= $box === 'history' ? '历史消息' : '未读消息' ?></div>
    <div class="msg-toolbar-actions">
      <a href="/index.php?path=notification-settings">通知设置</a>
      <?php if ($box === 'unread' && $type !== 'fans'): ?><button onclick="markAllRead()" type="button">全部已读</button><?php endif; ?>
    </div>
  </div>

  <section class="msg-panel">
  <?php if ($type === 'fans'): ?>
    <?php if (!empty($followers)): ?>
      <?php foreach ($followers as $f): ?>
        <a class="fan-row" href="/index.php?path=user&id=<?= (int)$f['follower_id'] ?>">
          <?= user_avatar_html($f, 'user-avatar', 42) ?>
          <div><div class="fan-name"><span class="name-level-inline"><?= htmlspecialchars($f['nickname'] ?: $f['username']) ?><?= user_level_badge_html($f, 'level-badge small') ?></span></div><div class="msg-card-meta">关注了你 · <?= htmlspecialchars($f['created_at'] ?? '') ?></div></div>
        </a>
      <?php endforeach; ?>
    <?php else: ?><div class="msg-empty"><?= $tabs[$type]['empty'] ?></div><?php endif; ?>
  <?php elseif ($type === 'finance'): ?>
    <?php if (!empty($messages)): ?>
      <?php foreach ($messages as $msg): ?>
        <?php $quickLink = message_quick_link($msg, $type); $isInvite = ($msg['ref_type'] ?? '') === 'group_invite'; ?><div class="msg-card <?= empty($msg['read_at']) ? 'unread' : '' ?><?= $isInvite ? ' msg-invite-card' : '' ?>" id="msg-<?= (int)$msg['id'] ?>"><div class="msg-card-title"><?= htmlspecialchars($msg['title']) ?></div><div class="msg-card-content"><?= htmlspecialchars(currency_localize_text((string)$msg['content'])) ?></div><?php if ($isInvite): ?><?php $invLabel = invite_status_label((int)($msg['ref_id'] ?? 0), $inviteStatuses); ?><?php if ($invLabel): ?><div class="msg-invite-actions"><span style="color:#999;font-size:13px;font-weight:600"><?= $invLabel ?></span></div><?php else: ?><div class="msg-invite-actions"><button type="button" class="msg-invite-accept" onclick="handleMsgInvite(<?= (int)($msg['ref_id'] ?? 0) ?>,'accept',this)">加入群聊</button><button type="button" class="msg-invite-reject" onclick="handleMsgInvite(<?= (int)($msg['ref_id'] ?? 0) ?>,'reject',this)">拒绝</button></div><?php endif; ?><?php endif; ?><div class="msg-card-meta"><span><?= $msg['sent_at'] ? date('Y-m-d H:i', strtotime($msg['sent_at'])) : '' ?></span><span><?php if ($quickLink && !$isInvite): ?><a class="msg-read-btn" href="<?= htmlspecialchars($quickLink) ?>">查看</a> · <?php endif; ?><?php if (empty($msg['read_at']) && !$isInvite): ?><button class="msg-read-btn" onclick="markRead(<?= (int)$msg['id'] ?>, this)">标为已读</button><?php elseif (!$isInvite): ?><span>历史</span><?php endif; ?></span></div></div>
      <?php endforeach; ?>
    <?php else: ?><div class="msg-empty"><?= $tabs[$type]['empty'] ?></div><?php endif; ?>
  <?php else: ?>
    <?php if (!empty($messages)): ?>
      <?php foreach ($messages as $msg): ?>
        <?php $quickLink = message_quick_link($msg, $type); $isInvite = ($msg['ref_type'] ?? '') === 'group_invite'; ?><div class="msg-card <?= empty($msg['read_at']) ? 'unread' : '' ?><?= $isInvite ? ' msg-invite-card' : '' ?>" id="msg-<?= (int)$msg['id'] ?>"><div class="msg-card-title"><?= htmlspecialchars($msg['title']) ?></div><div class="msg-card-content"><?= htmlspecialchars(currency_localize_text((string)$msg['content'])) ?></div><?php if ($isInvite): ?><?php $invLabel = invite_status_label((int)($msg['ref_id'] ?? 0), $inviteStatuses); ?><?php if ($invLabel): ?><div class="msg-invite-actions"><span style="color:#999;font-size:13px;font-weight:600"><?= $invLabel ?></span></div><?php else: ?><div class="msg-invite-actions"><button type="button" class="msg-invite-accept" onclick="handleMsgInvite(<?= (int)($msg['ref_id'] ?? 0) ?>,'accept',this)">加入群聊</button><button type="button" class="msg-invite-reject" onclick="handleMsgInvite(<?= (int)($msg['ref_id'] ?? 0) ?>,'reject',this)">拒绝</button></div><?php endif; ?><?php endif; ?><div class="msg-card-meta"><span><?= $msg['sent_at'] ? date('Y-m-d H:i', strtotime($msg['sent_at'])) : '' ?></span><span><?php if ($quickLink && !$isInvite): ?><a class="msg-read-btn" href="<?= htmlspecialchars($quickLink) ?>">查看</a> · <?php endif; ?><?php if (empty($msg['read_at']) && !$isInvite): ?><button class="msg-read-btn" onclick="markRead(<?= (int)$msg['id'] ?>, this)">标为已读</button><?php elseif (!$isInvite): ?><span>历史</span><?php endif; ?></span></div></div>
      <?php endforeach; ?>
    <?php else: ?><div class="msg-empty"><?= $tabs[$type]['empty'] ?></div><?php endif; ?>
  <?php endif; ?>
    <?php if ($type !== 'fans' && $totalPages > 1): ?>
      <div class="msg-pagination">
        <?php if ($page > 1): ?><a href="<?= htmlspecialchars($pageUrl($page - 1)) ?>">上一页</a><?php endif; ?>
        <?php for ($pp = max(1, $page - 2); $pp <= min($totalPages, $page + 2); $pp++): ?><a class="<?= $pp === $page ? 'active' : '' ?>" href="<?= htmlspecialchars($pageUrl($pp)) ?>"><?= $pp ?></a><?php endfor; ?>
        <?php if ($page < $totalPages): ?><a href="<?= htmlspecialchars($pageUrl($page + 1)) ?>">下一页</a><?php endif; ?>
        <span>共 <?= (int)$total ?> 条 · 每页 <?= (int)$pageSize ?> 条</span>
      </div>
    <?php endif; ?>
  </section>
</main></div>
<?php require dirname(__DIR__, 2) . '/layouts/theme-toggle.php'; ?>
<?php require dirname(__DIR__) . '/layouts/bottom-nav.php'; ?>
<script>
var csrfToken = <?= json_encode(csrf_token()) ?>;
var currentMsgType = <?= json_encode((string)$type) ?>;
function updateTabBadge(type, delta){var badge=document.querySelector('[data-tab-badge="'+type+'"]');if(!badge)return;var count=parseInt(badge.dataset.count||badge.textContent||'0',10)||0;count=Math.max(0,count+delta);if(count<=0){badge.remove();return;}badge.dataset.count=String(count);badge.textContent=count>99?'99+':String(count);}
function clearTabBadges(){document.querySelectorAll('[data-tab-badge]').forEach(function(b){b.remove();});}
function markRead(id, btn){var card=document.getElementById('msg-'+id);var wasUnread=card&&card.classList.contains('unread');var fd=new FormData();fd.append('message_id',id);fd.append('_csrf_token',csrfToken);fetch('/api.php?path=messages/read',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json()}).then(function(d){if(d.ok){if(wasUnread)updateTabBadge(currentMsgType,-1);if(card&&currentMsgType!=='fans'){card.remove();}else if(card){card.classList.remove('unread');}if(btn&&document.body.contains(btn)){var span=document.createElement('span');span.textContent='历史';btn.parentNode.replaceChild(span,btn);}if(!document.querySelector('.msg-card')&&currentMsgType!=='fans'){location.reload();}}}).catch(function(){});}
function markAllRead(){var fd=new FormData();fd.append('all','1');fd.append('category',currentMsgType==='all'?'':currentMsgType);fd.append('_csrf_token',csrfToken);fetch('/api.php?path=messages/read',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json()}).then(function(d){if(d.ok){document.querySelectorAll('.msg-card.unread').forEach(function(card){card.remove();});if(currentMsgType==='all'){clearTabBadges();}else{updateTabBadge(currentMsgType,-999999);updateTabBadge('all',-999999);}setTimeout(function(){location.href='/index.php?path=messages&type='+encodeURIComponent(currentMsgType)+'&box=history';},250);}}).catch(function(){});}
function handleMsgInvite(inviteId,decision,btn){if(!inviteId||!btn)return;btn.disabled=true;btn.textContent=decision==='accept'?'处理中':'拒绝中';var fd=new FormData();fd.append('_csrf_token',csrfToken);fd.append('invite_id',inviteId);fd.append('decision',decision);fetch('/api.php?path=group-chat/invite-handle',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json()}).then(function(d){var card=btn.closest('.msg-card');if(!d.ok){btn.disabled=false;btn.textContent=d.error||'失败';return;}if(card){var actions=card.querySelector('.msg-invite-actions');if(actions)actions.innerHTML='<span style="color:#999;font-size:13px;font-weight:600">'+(decision==='accept'?'已加入':'已拒绝')+'</span>';var wasUnread=card.classList.contains('unread');card.classList.remove('unread');if(wasUnread)updateTabBadge(currentMsgType,-1);var msgId=parseInt((card.id||'').replace('msg-',''),10);if(msgId){var mfd=new FormData();mfd.append('message_id',msgId);mfd.append('_csrf_token',csrfToken);fetch('/api.php?path=messages/read',{method:'POST',body:mfd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(){return r.json()}).catch(function(){});}}}).catch(function(){btn.disabled=false;});}
</script>
</body></html>
