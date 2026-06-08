<?php
$tab = $tab ?? 'threads';
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$fullUser = $fullUser ?? $authUser;
$displayName = trim((string)($fullUser['nickname'] ?? '')) ?: (string)($fullUser['username'] ?? '用户');
$initial = mb_substr($displayName, 0, 1);
$cover = trim((string)($fullUser['cover'] ?? ''));
$roleLabel = (string)($fullUser['role_label'] ?? '普通用户');
$username = (string)($fullUser['username'] ?? ('user' . (int)($fullUser['id'] ?? 0)));
$flashSuccess = (string)($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>个人中心 - <?= htmlspecialchars($displayName) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
<?= user_avatar_verify_styles() ?>
<?= user_level_badge_styles() ?>
<?= \App\Core\Hook::filter('view.styles', '') ?>
<style>
.flash-success{max-width:1080px;margin:0 auto 12px;padding:11px 13px;border-left:4px solid #16a34a;background:#f0fdf4;color:#166534;font-size:13px;font-weight:800}.me-page{padding:18px 0 104px;background:var(--bg-main,#f6f8fb)}.me-shell{max-width:1080px}.author-card{position:relative;overflow:visible;border:1px solid rgba(226,232,240,.9);border-radius:24px;background:var(--card-bg,#fff);box-shadow:0 18px 48px rgba(15,23,42,.08)}.author-cover{position:relative;height:220px;background:radial-gradient(circle at 16% 20%,rgba(56,189,248,.26),transparent 28%),linear-gradient(135deg,#dff3ff,#eef4ff 48%,#f2efff);overflow:visible}.author-cover-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center;display:block;background:#f8fafc}.author-cover::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(15,23,42,.02),rgba(15,23,42,.18));pointer-events:none}.author-top-actions{position:absolute;right:16px;top:16px;z-index:4;display:flex;gap:8px}.me-settings,.cover-upload-btn{height:40px;border-radius:999px;border:1px solid rgba(148,163,184,.32);background:rgba(255,255,255,.78);backdrop-filter:blur(12px);display:inline-flex;align-items:center;justify-content:center;gap:7px;color:#334155;text-decoration:none;box-shadow:0 10px 24px rgba(15,23,42,.10);font-size:12px;font-weight:900;cursor:pointer}.me-settings{width:40px}.me-settings svg{width:20px;height:20px}.cover-upload-btn{padding:0 12px}.cover-upload-btn svg{width:15px;height:15px}.author-avatar-layer{position:absolute;left:24px;bottom:-54px;z-index:5}.me-avatar-wrap{position:relative}.me-avatar-button{border:0;background:transparent;padding:0;cursor:pointer;display:block;overflow:visible}.me-avatar{width:112px!important;height:112px!important;min-width:112px!important;min-height:112px!important;aspect-ratio:1/1!important;border-radius:50%;border:4px solid rgba(255,255,255,.96);background:linear-gradient(135deg,#0284c7,#6366f1);display:grid;place-items:center;color:#fff;font-size:42px;font-weight:950;overflow:hidden;box-shadow:0 20px 50px rgba(15,23,42,.24)}.me-avatar-button .avatar-verify-wrap{--avatar-size:112px!important}.me-avatar-button .avatar-verify-badge{right:4px!important;bottom:4px!important;width:26px!important;height:26px!important;max-width:26px!important;max-height:26px!important;font-size:13px!important;border-width:3px!important}.avatar-upload-pop{display:none;position:absolute;left:0;top:calc(100% + 10px);z-index:20;width:226px;border-radius:16px;background:rgba(255,255,255,.98);border:1px solid #e2e8f0;box-shadow:0 20px 52px rgba(15,23,42,.18);padding:12px}.avatar-upload-pop.is-open{display:block}.avatar-upload-pop strong{display:block;font-size:13px;color:#0f172a;margin-bottom:8px}.avatar-upload-pop input[type=file]{display:block;width:100%;font-size:12px;color:#64748b}.avatar-upload-pop button{width:100%;margin-top:10px}.author-info{padding:64px 24px 18px}.author-title-line{display:flex;gap:10px;align-items:baseline;flex-wrap:wrap}.author-name{margin:0;font-size:32px;line-height:1.1;letter-spacing:-.04em;color:var(--text-main,#0f172a)}.author-username{color:var(--text-soft,#64748b);font-size:15px;font-weight:800}.author-meta{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:9px}.author-badge{display:inline-flex;align-items:center;border-radius:999px;background:rgba(2,132,199,.12);color:#0284c7;padding:5px 9px;font-size:12px;font-weight:900}.author-bio{margin:12px 0 0;color:var(--text-soft,#64748b);font-size:14px;line-height:1.75}.me-social-stats{position:absolute;right:24px;bottom:22px;z-index:6;display:flex;gap:10px}.me-social-stat{min-width:82px;border-radius:18px;background:rgba(255,255,255,.86);border:1px solid rgba(226,232,240,.9);box-shadow:0 12px 28px rgba(15,23,42,.10);padding:10px 12px;text-align:center;backdrop-filter:blur(12px);text-decoration:none;display:block}.me-social-stat strong{display:block;font-size:20px;color:var(--text-main,#0f172a);line-height:1}.me-social-stat span{display:block;margin-top:5px;font-size:12px;color:var(--text-soft,#64748b);font-weight:900}.me-quick-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.me-quick-action{display:inline-flex;align-items:center;gap:7px;height:36px;padding:0 12px;border-radius:999px;background:var(--input-bg,#f8fafc);color:var(--text-soft,#64748b);text-decoration:none;font-size:13px;font-weight:900}.me-quick-action:hover{color:var(--primary,#0284c7)}.my-badge-panel{display:none;margin-top:12px;border:1px solid var(--line-soft,#e2e8f0);border-radius:16px;background:var(--input-bg,#f8fafc);padding:12px;max-width:560px}.my-badge-panel.is-open{display:block}.my-badge-head{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:10px}.my-badge-head strong{color:var(--text-main,#0f172a);font-size:14px}.my-badge-grid{display:flex;gap:8px;flex-wrap:wrap}.my-badge-empty{font-size:13px;color:var(--text-muted,#94a3b8);line-height:1.7}.me-quick-action.badges{background:rgba(245,158,11,.10);color:#d97706}.verify-mini{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:0 11px;height:36px;background:rgba(37,99,235,.09);color:#2563eb;text-decoration:none;font-size:13px;font-weight:950}.verify-mini .user-verify-v{position:static!important;width:18px!important;height:18px!important;min-width:18px!important;min-height:18px!important;font-size:10px!important;border-color:#fff}.me-tabs-card{margin-top:14px;padding:0;overflow:hidden;border:1px solid rgba(226,232,240,.9);border-radius:22px;background:var(--card-bg,#fff);box-shadow:0 10px 28px rgba(15,23,42,.045)}.me-tabs{display:flex;gap:6px;padding:12px 14px;border-bottom:1px solid var(--line-soft,#e2e8f0);overflow-x:auto}.me-tabs a{flex:0 0 auto;text-align:center;padding:9px 14px;border-radius:999px;text-decoration:none;color:var(--text-soft,#64748b);background:var(--input-bg,#f8fafc);font-weight:950;font-size:13px}.me-tabs a.active,.me-tabs a:hover{background:var(--primary,#0284c7);color:#fff}.me-list{padding:16px 18px 18px}.reply-item{padding:15px 0;border-bottom:1px solid var(--line-soft,#e2e8f0)}.reply-item:last-child{border-bottom:none}.reply-item .meta{font-size:12px;color:var(--text-muted,#94a3b8);margin-top:6px}.reply-item .content{font-size:14px;color:var(--text-main,#0f172a);line-height:1.65;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.reply-item .meta a{color:var(--primary,#0284c7);text-decoration:none}.empty{text-align:center;padding:70px 16px;color:var(--text-muted,#94a3b8);font-size:15px}.pagination{display:flex;justify-content:center;gap:8px;margin:18px 0 4px;flex-wrap:wrap}.pagination a,.pagination span{padding:7px 12px;border-radius:999px;font-size:13px;text-decoration:none;border:1px solid var(--line-soft,#e2e8f0);color:var(--text-soft,#64748b)}.pagination a:hover,.pagination .current{background:var(--primary,#0284c7);border-color:var(--primary,#0284c7);color:#fff}.hidden-file{display:none}html[data-theme="dark"] .author-card,html[data-theme="dark"] .me-tabs-card{background:#111827;border-color:#263244;box-shadow:0 10px 30px rgba(0,0,0,.25)}html[data-theme="dark"] .author-cover{background:linear-gradient(135deg,#13233a,#111827 52%,#1e1b4b)}html[data-theme="dark"] .author-cover-img{background:#0f172a}html[data-theme="dark"] .me-settings,html[data-theme="dark"] .cover-upload-btn,html[data-theme="dark"] .me-social-stat{background:rgba(15,23,42,.72);border-color:#263244;color:#e5e7eb}html[data-theme="dark"] .avatar-upload-pop{background:#111827;border-color:#263244}html[data-theme="dark"] .avatar-upload-pop strong{color:#e5e7eb}@media(max-width:820px){.me-page{padding-top:0}.container.me-shell{padding:0}.author-card,.me-tabs-card{border-radius:0;border-left:0;border-right:0}.author-cover{height:170px}.author-avatar-layer{left:18px;bottom:-44px}.me-avatar{width:92px!important;height:92px!important;min-width:92px!important;min-height:92px!important;font-size:34px}.me-avatar-button .avatar-verify-wrap{--avatar-size:92px!important}.me-avatar-button .avatar-verify-badge{width:23px!important;height:23px!important;max-width:23px!important;max-height:23px!important;font-size:12px!important;right:3px!important;bottom:3px!important}.me-social-stats{right:16px;bottom:-50px}.me-social-stat{min-width:66px;padding:8px 10px;border-radius:16px}.me-social-stat strong{font-size:18px}.author-info{padding:64px 18px 16px}.author-name{font-size:28px}.cover-upload-btn span{display:none}.author-top-actions{right:12px;top:12px}.me-list{padding:14px 16px 18px}.empty{padding:78px 16px 120px}}
.me-subtabs{display:flex;gap:8px;overflow-x:auto;margin:0 0 14px;padding:2px;scrollbar-width:none}.me-subtabs::-webkit-scrollbar{display:none}.me-subtab{min-height:34px;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:var(--input-bg,#f8fafc);color:var(--text-soft,#64748b);padding:0 12px;text-decoration:none;font-size:12px;font-weight:950;white-space:nowrap}.me-subtab.active,.me-subtab:hover{background:var(--primary,#0284c7);color:#fff}@media(max-width:820px){.me-quick-actions{display:flex;flex-wrap:wrap;gap:7px;align-items:center}.me-quick-action,.verify-mini{flex:0 0 auto;width:auto;min-width:0;height:32px;padding:0 10px!important;font-size:12px;justify-content:center}.verify-mini .user-verify-v{width:16px!important;height:16px!important;min-width:16px!important;min-height:16px!important}.me-tabs{scrollbar-width:none}.me-tabs::-webkit-scrollbar{display:none}}
</style>
<?= function_exists('clay_medal_display_style_tag') ? clay_medal_display_style_tag() : '' ?>

</head>
<body>
<?php require __DIR__ . '/../layouts/topbar.php'; ?>

<main class="me-page">
  <?php if ($flashSuccess !== ''): ?><div class="flash-success"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
  <div class="container me-shell">
    <section class="author-card">
      <form id="meUploadForm" method="POST" action="/index.php?path=me/edit" enctype="multipart/form-data" data-no-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="nickname" value="<?= htmlspecialchars($fullUser['nickname'] ?? '') ?>">
        <input type="hidden" name="bio" value="<?= htmlspecialchars($fullUser['bio'] ?? '') ?>">
        <input type="hidden" name="quick_upload" value="1">
        <input type="hidden" name="_action" id="uploadAction" value="">
        <input class="hidden-file" id="coverInput" type="file" name="cover" accept="image/*">
        <input class="hidden-file" id="avatarInput" type="file" name="avatar" accept="image/*">
      </form>

      <div class="author-cover">
        <?php if ($cover !== ''): ?><img class="author-cover-img" src="<?= htmlspecialchars($cover) ?>" alt=""><?php endif; ?>
        <div class="author-top-actions">
          <button class="cover-upload-btn" type="button" id="coverBtn" aria-label="上传背景图">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="M17 8l-5-5-5 5"></path><path d="M12 3v12"></path></svg><span>背景</span>
          </button>
          <a class="me-settings" href="/index.php?path=settings" aria-label="设置">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21a2 2 0 1 1-4 0v-.09A1.7 1.7 0 0 0 8.6 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.6 8.6a1.7 1.7 0 0 0-.34-1.88l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3a2 2 0 1 1 4 0v.09A1.7 1.7 0 0 0 15.4 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.23.36.6 1 .6 1h.1a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1 .6z"></path></svg>
          </a>
        </div>
        <div class="me-social-stats"><a class="me-social-stat" href="/index.php?path=user/follows&id=<?= (int)$fullUser['id'] ?>&type=following"><strong><?= (int)($followStats['following'] ?? 0) ?></strong><span>关注</span></a><a class="me-social-stat" href="/index.php?path=user/follows&id=<?= (int)$fullUser['id'] ?>&type=followers"><strong><?= (int)($followStats['followers'] ?? 0) ?></strong><span>粉丝</span></a></div>
        <div class="author-avatar-layer">
          <div class="me-avatar-wrap">
            <button class="me-avatar-button" id="avatarBtn" type="button" aria-label="头像上传">
              <?= user_avatar_html($fullUser, 'me-avatar', 112) ?>
            </button>
            <div class="avatar-upload-pop" id="avatarPop">
              <strong>上传头像图片</strong>
              <label><input id="avatarFileProxy" type="file" accept="image/*"></label>
              <button class="btn btn-primary" type="button" id="avatarSubmit">上传头像</button>
              <?php if (!empty($fullUser['avatar'])): ?><button class="btn btn-light" type="button" id="avatarRemove">移除头像</button><?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="author-info">
        <div class="author-title-line"><?php if (function_exists('clay_nameplate_my_page')): ?><a class="name-level-inline me-nameplate-link" href="/index.php?path=me/nameplates" style="text-decoration:none;color:inherit;"><h1 class="author-name"><?= user_nameplate_html($fullUser, $displayName) ?></h1><?= user_level_badge_html($fullUser) ?></a><?php else: ?><span class="name-level-inline"><h1 class="author-name"><?= user_nameplate_html($fullUser, $displayName) ?></h1><?= user_level_badge_html($fullUser) ?></span><?php endif; ?></div>
        <?php $growthSummary = (new \App\Services\GrowthService())->summary((int)$fullUser['id']); $growthPercent = min(100, max(0, (int)round((($growthSummary['progress_current'] ?? 0) / max(1, ($growthSummary['progress_total'] ?? 1))) * 100))); ?>
        <div class="me-badges-row"><?= (new \App\Services\MedalService())->renderUserBadges((int)($fullUser['id'] ?? 0), 'clay-user-badges thread-time-medals me-icon-medals', 8, false) ?></div><div class="author-meta"><?php if(!empty($fullUser['public_id'])): ?><span class="author-badge">ID <?= htmlspecialchars((string)$fullUser['public_id']) ?></span><?php endif; ?><span class="author-badge"><?= htmlspecialchars($roleLabel) ?></span><?php if ($activeVerification): ?><?php $verificationColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($activeVerification['verification_color'] ?? '')) ? (string)$activeVerification['verification_color'] : '#2563eb'; ?><span class="author-badge"><?= user_verification_badge_html($fullUser) ?> <?= user_verification_label_html($activeVerification) ?></span><?php endif; ?></div>
        <p class="author-bio"><?= htmlspecialchars(trim((string)($fullUser['bio'] ?? '')) !== '' ? (string)$fullUser['bio'] : '还没有填写个人简介。') ?></p>
        <div class="me-quick-actions"><a class="me-quick-action" href="/index.php?path=tasks">任务中心</a><a class="me-quick-action" href="/index.php?path=wallet">我的钱包</a><a class="me-quick-action" href="/index.php?path=credit">用户信用</a><?= \App\Core\Hook::filter('user.center.quick_actions', '', ['user' => $fullUser ?? []]) ?><a class="verify-mini" href="/index.php?path=verification/apply"><?= $activeVerification ? '我的认证' : (($latestVerificationRequest && ($latestVerificationRequest['status'] ?? '') === 'pending') ? '认证审核中' : '申请认证') ?></a></div>
      </div>
    </section>

    <section class="me-tabs-card">
      <div class="me-tabs">
        <a href="/index.php?path=me&tab=threads" class="<?= $tab === 'threads' ? 'active' : '' ?>">我的帖子 <?= (int)($fullUser['thread_count'] ?? 0) ?></a>
        <a href="/index.php?path=me&tab=pending" class="<?= $tab === 'pending' ? 'active' : '' ?>">待审核</a>
        <a href="/index.php?path=me&tab=replies" class="<?= $tab === 'replies' ? 'active' : '' ?>">我的回复 <?= (int)($fullUser['reply_count_stat'] ?? 0) ?></a>
        <a href="/index.php?path=me&tab=favorites" class="<?= $tab === 'favorites' ? 'active' : '' ?>">我的收藏</a>
        <a href="/index.php?path=me&tab=following_feed" class="<?= $tab === 'following_feed' ? 'active' : '' ?>">关注动态</a>
        <a href="/index.php?path=me&tab=blocks" class="<?= $tab === 'blocks' ? 'active' : '' ?>">黑名单</a>
        <a href="/index.php?path=drafts" class="<?= $tab === 'drafts' ? 'active' : '' ?>">草稿箱</a>
      </div>
      <div class="me-list">
        <?php if ($tab === 'threads' || $tab === 'favorites' || $tab === 'pending'): ?>
          <?php if ($tab === 'favorites'): ?><?php $favSubTab = in_array((string)($_GET['fav'] ?? 'favorites'), ['favorites','later'], true) ? (string)($_GET['fav'] ?? 'favorites') : 'favorites'; ?><nav class="me-subtabs" role="tablist" aria-label="我的收藏分类"><a role="tab" aria-selected="<?= $favSubTab==='favorites'?'true':'false' ?>" class="me-subtab <?= $favSubTab==='favorites'?'active':'' ?>" href="/index.php?path=me&tab=favorites&fav=favorites">收藏 <?= (int)($favoriteCount ?? 0) ?></a><a role="tab" aria-selected="<?= $favSubTab==='later'?'true':'false' ?>" class="me-subtab <?= $favSubTab==='later'?'active':'' ?>" href="/index.php?path=me&tab=favorites&fav=later">稍后看 <?= (int)($laterCount ?? 0) ?></a></nav><?php endif; ?>
          <?php if (empty($threads)): ?><div class="empty"><?= $tab === 'favorites' ? (($favSubTab ?? 'favorites') === 'later' ? '暂无稍后看的帖子' : '还没有收藏帖子') : ($tab === 'pending' ? '暂无待审核帖子' : '还没有发过帖子') ?></div><?php else: ?>
            <?= thread_card_styles() ?>
            <div class="thread-card-grid"><?php foreach ($threads as $t): ?><?= render_thread_card($t) ?><?php endforeach; ?></div>
          <?php endif; ?>
        <?php elseif ($tab === 'following_feed'): ?>
          <?php if (empty($feedItems)): ?><div class="empty">关注的人还没有新动态</div><?php else: ?>
            <?php foreach ($feedItems as $it): ?><?php $name = trim((string)($it['nickname'] ?? '')) ?: (string)($it['username'] ?? '用户'); $url = ($it['item_type'] ?? '') === 'thread' ? '/index.php?path=thread&id=' . (int)$it['item_id'] : '/index.php?path=thread&id=' . (int)$it['thread_id'] . '#post-' . (int)$it['item_id']; ?><div class="reply-item"><div class="content"><a href="<?= htmlspecialchars($url) ?>" style="color:inherit;text-decoration:none;font-weight:900;"><?= htmlspecialchars($name) ?> <?= ($it['item_type'] ?? '') === 'thread' ? '发布了帖子《' . htmlspecialchars((string)$it['title']) . '》' : '回复了帖子《' . htmlspecialchars((string)$it['thread_title']) . '》' ?></a></div><div class="meta"><?= htmlspecialchars((string)($it['section_name'] ?? '')) ?> · <?= date('Y-m-d H:i', strtotime((string)$it['created_at'])) ?></div><div class="content" style="margin-top:6px;color:var(--text-soft,#64748b);"><?= htmlspecialchars(mb_substr(trim(strip_tags((string)($it['summary'] ?: $it['content']))), 0, 140)) ?></div></div><?php endforeach; ?>
          <?php endif; ?>
        <?php elseif ($tab === 'blocks'): ?>
          <?php if (empty($blockedUsers)): ?><div class="empty">还没有屏蔽用户</div><?php else: ?>
            <?php foreach ($blockedUsers as $u): ?><div class="reply-item"><div class="content"><?= htmlspecialchars($u['nickname'] ?: $u['username']) ?></div><div class="meta">@<?= htmlspecialchars($u['username']) ?> · <form method="post" action="/index.php?path=user/block" data-ajax-refresh style="display:inline;"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>"><input type="hidden" name="action" value="unblock"><button class="soft-btn" type="submit">取消屏蔽</button></form></div></div><?php endforeach; ?>
          <?php endif; ?>
        <?php else: ?>
          <?php if (empty($replies)): ?><div class="empty">还没有回复过帖子</div><?php else: ?>
            <?php foreach ($replies as $r): ?><div class="reply-item"><div class="content"><?= htmlspecialchars($r['content']) ?></div><div class="meta">回复：<a href="/index.php?path=thread&id=<?= $r['thread_id'] ?>#post-<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['thread_title'] ?? '帖子') ?></a> · <?= date('Y-m-d', strtotime($r['created_at'])) ?></div></div><?php endforeach; ?>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($totalPages > 1): ?>
          <div class="pagination">
            <?php if ($page > 1): ?><a href="/index.php?path=me&tab=<?= $tab ?><?= $tab==='favorites' ? '&fav=' . urlencode($favSubTab ?? 'favorites') : '' ?>&page=<?= $page - 1 ?>">上一页</a><?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?><?php if ($i === $page): ?><span class="current"><?= $i ?></span><?php else: ?><a href="/index.php?path=me&tab=<?= $tab ?><?= $tab==='favorites' ? '&fav=' . urlencode($favSubTab ?? 'favorites') : '' ?>&page=<?= $i ?>"><?= $i ?></a><?php endif; ?><?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="/index.php?path=me&tab=<?= $tab ?><?= $tab==='favorites' ? '&fav=' . urlencode($favSubTab ?? 'favorites') : '' ?>&page=<?= $page + 1 ?>">下一页</a><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</main>

<?php require __DIR__ . '/../../layouts/theme-toggle.php'; ?>
<?php require __DIR__ . '/../layouts/bottom-nav.php'; ?>
<script>
(function(){
  var pop=null, timer=null;
  function ensurePop(){
    if(pop) return pop;
    pop=document.createElement('div');
    pop.className='clay-medal-popover';
    pop.setAttribute('role','status');
    pop.setAttribute('aria-live','polite');
    document.body.appendChild(pop);
    return pop;
  }
  function showMedal(btn){
    var name=btn.getAttribute('data-medal-name')||btn.getAttribute('aria-label')||btn.getAttribute('title')||'';
    if(!name) return;
    var p=ensurePop();
    p.textContent=name;
    p.classList.add('is-open');
    var r=btn.getBoundingClientRect();
    p.style.left=Math.max(42,Math.min(window.innerWidth-42,r.left+r.width/2))+'px';
    p.style.top=Math.max(40,r.top-8)+'px';
    clearTimeout(timer);
    timer=setTimeout(function(){p.classList.remove('is-open');},1800);
  }
  function hideMedal(){ if(pop) pop.classList.remove('is-open'); }
  document.addEventListener('click',function(e){
    var btn=e.target.closest('.me-icon-medals .clay-user-medal.is-icon-only');
    if(!btn){hideMedal();return;}
    e.preventDefault();
    e.stopPropagation();
    showMedal(btn);
  });
  document.addEventListener('keydown',function(e){if(e.key==='Escape')hideMedal();});
  window.addEventListener('scroll',hideMedal,{passive:true});
})();
</script>
<script>
(function(){
  var form=document.getElementById('meUploadForm');
  var avatarBtn=document.getElementById('avatarBtn');
  var avatarPop=document.getElementById('avatarPop');
  var avatarProxy=document.getElementById('avatarFileProxy');
  var avatarInput=document.getElementById('avatarInput');
  var avatarSubmit=document.getElementById('avatarSubmit');
  var avatarRemove=document.getElementById('avatarRemove');
  var uploadAction=document.getElementById('uploadAction');
  var coverBtn=document.getElementById('coverBtn');

  var coverInput=document.getElementById('coverInput');
  if(avatarBtn&&avatarPop){avatarBtn.addEventListener('click',function(e){e.stopPropagation();avatarPop.classList.toggle('is-open');});document.addEventListener('click',function(e){if(!avatarPop.contains(e.target)&&e.target!==avatarBtn){avatarPop.classList.remove('is-open');}});}
  if(avatarSubmit&&avatarProxy&&avatarInput&&form){avatarSubmit.addEventListener('click',function(){if(!avatarProxy.files||!avatarProxy.files.length)return;if(uploadAction)uploadAction.value='';var dt=new DataTransfer();dt.items.add(avatarProxy.files[0]);avatarInput.files=dt.files;form.submit();});}
  if(avatarRemove&&form&&uploadAction){avatarRemove.addEventListener('click',function(){uploadAction.value='remove_avatar';form.submit();});}
  if(coverBtn&&coverInput&&form){coverBtn.addEventListener('click',function(){coverInput.click();});coverInput.addEventListener('change',function(){if(coverInput.files&&coverInput.files.length)form.submit();});}
})();
</script>
<script>
document.addEventListener('click',function(e){
  var card=e.target.closest('.thread-card-v2[data-href]');
  if(!card)return;
  if(e.target.closest('a,button,input,select,textarea'))return;
  window.location.href=card.dataset.href;
});
</script>
<?= user_verification_modal_html() ?>
</body>
</html>
