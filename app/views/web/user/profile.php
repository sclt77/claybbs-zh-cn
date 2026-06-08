<?php
$profileUser = $profileUser ?? null;
$threads = $threads ?? [];
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars(user_display_name($profileUser ?? [], '用户')) ?> - 个人主页</title>
<link rel="stylesheet" href="/assets/css/style.css">
<?= user_avatar_verify_styles() ?>
<?= user_level_badge_styles() ?>
<?= \App\Core\Hook::filter('view.styles', '') ?>
<?= thread_card_styles() ?>
<style>
.profile-page{padding:18px 0 104px;background:var(--bg-main,#f6f8fb)}.profile-shell{max-width:1080px}.profile-card{position:relative;overflow:hidden;border:1px solid rgba(226,232,240,.9);border-radius:24px;background:var(--card-bg,#fff);box-shadow:0 18px 48px rgba(15,23,42,.08)}.profile-cover{position:relative;height:220px;background:radial-gradient(circle at 16% 20%,rgba(56,189,248,.26),transparent 28%),linear-gradient(135deg,#dff3ff,#eef4ff 48%,#f2efff);overflow:visible}.profile-cover-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center;display:block;background:#f8fafc}.profile-cover::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(15,23,42,.02),rgba(15,23,42,.18));pointer-events:none}.profile-avatar-layer{position:absolute;left:24px;bottom:-54px;z-index:5}.profile-avatar-layer .avatar-verify-wrap{--avatar-size:112px!important}.profile-avatar-layer .avatar-verify-badge{right:4px!important;bottom:4px!important;width:26px!important;height:26px!important;max-width:26px!important;max-height:26px!important;font-size:13px!important;border-width:3px!important}.profile-avatar{width:112px;height:112px;min-width:112px;min-height:112px;aspect-ratio:1/1;border-radius:50%;border:4px solid rgba(255,255,255,.96);background:linear-gradient(135deg,#0284c7,#6366f1);display:grid;place-items:center;color:#fff;font-size:42px;font-weight:950;overflow:hidden;box-shadow:0 20px 50px rgba(15,23,42,.24)}.profile-info{padding:64px 24px 18px}.profile-title-line{display:flex;gap:10px;align-items:baseline;flex-wrap:wrap}.profile-name{margin:0;font-size:32px;line-height:1.1;letter-spacing:-.04em;color:var(--text-main,#0f172a)}.profile-username{color:var(--text-soft,#64748b);font-size:15px;font-weight:800}.profile-meta{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:9px;color:var(--text-soft,#64748b);font-size:13px}.profile-joined{display:block;margin-top:8px;color:var(--text-soft,#64748b);font-size:13px;font-weight:800}.profile-badge{display:inline-flex;align-items:center;border-radius:999px;background:rgba(2,132,199,.12);color:#0284c7;padding:5px 9px;font-size:12px;font-weight:900}.profile-social-stats{position:absolute;right:24px;bottom:22px;z-index:6;display:flex;gap:10px}.profile-social-stat{min-width:82px;border-radius:18px;background:rgba(255,255,255,.86);border:1px solid rgba(226,232,240,.9);box-shadow:0 12px 28px rgba(15,23,42,.10);padding:10px 12px;text-align:center;backdrop-filter:blur(12px);text-decoration:none;display:block}.profile-social-stat strong{display:block;font-size:20px;color:var(--text-main,#0f172a);line-height:1}.profile-social-stat span{display:block;margin-top:5px;font-size:12px;color:var(--text-soft,#64748b);font-weight:900}.profile-bio{margin:12px 0 0;color:var(--text-soft,#64748b);font-size:14px;line-height:1.75}.profile-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;align-items:center}.profile-action-form{margin:0}.profile-action-btn{height:36px!important;min-height:36px!important;border-radius:999px!important;padding:0 14px!important;font-size:13px!important;font-weight:950!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:6px!important;line-height:1!important;box-shadow:none!important}.profile-action-btn.primary{background:var(--primary,#0284c7)!important;color:#fff!important}.profile-action-btn.following{background:var(--input-bg,#f8fafc)!important;color:var(--text-soft,#64748b)!important;border:1px solid var(--line-soft,#e2e8f0)!important}.profile-action-btn.chat{background:rgba(2,132,199,.10)!important;color:var(--primary,#0284c7)!important;border:1px solid rgba(2,132,199,.16)!important}.profile-action-btn.block{background:rgba(239,68,68,.08)!important;color:#ef4444!important;border:1px solid rgba(239,68,68,.18)!important}.profile-list-card{margin-top:14px;padding:18px;border:1px solid rgba(226,232,240,.9);border-radius:22px;background:var(--card-bg,#fff);box-shadow:0 10px 28px rgba(15,23,42,.045)}.profile-section-title{margin:0 0 14px;font-size:18px;font-weight:950;color:var(--text-main,#0f172a)}.empty{text-align:center;padding:48px 16px;color:var(--text-muted,#94a3b8)}.not-found{padding:38px 18px;color:#ef4444}html[data-theme="dark"] .profile-card,html[data-theme="dark"] .profile-list-card{background:#111827;border-color:#263244;box-shadow:0 10px 30px rgba(0,0,0,.25)}html[data-theme="dark"] .profile-social-stat{background:rgba(15,23,42,.72);border-color:#263244}html[data-theme="dark"] .profile-action-btn.following{background:#0f172a!important;border-color:#263244!important;color:#cbd5e1!important}html[data-theme="dark"] .profile-action-btn.chat{background:rgba(56,189,248,.12)!important;border-color:#263244!important;color:#7dd3fc!important}html[data-theme="dark"] .profile-cover{background:linear-gradient(135deg,#13233a,#111827 52%,#1e1b4b)}html[data-theme="dark"] .profile-cover-img{background:#0f172a}@media(max-width:820px){.profile-page{padding-top:0}.container.profile-shell{padding:0}.profile-card,.profile-list-card{border-radius:0;border-left:0;border-right:0}.profile-card{overflow:hidden}.profile-cover{height:170px}.profile-avatar-layer{left:18px;bottom:-44px}.profile-avatar-layer .avatar-verify-wrap{--avatar-size:92px!important}.profile-avatar-layer .avatar-verify-badge{width:23px!important;height:23px!important;max-width:23px!important;max-height:23px!important;font-size:12px!important;right:3px!important;bottom:3px!important}.profile-avatar{width:92px;height:92px;min-width:92px;min-height:92px;font-size:34px}.profile-social-stats{right:16px;bottom:-50px}.profile-social-stat{min-width:66px;padding:8px 10px;border-radius:16px}.profile-social-stat strong{font-size:18px}.profile-info{padding:64px 18px 16px}.profile-name{font-size:28px}.profile-list-card{padding:18px 18px 120px}}
</style>
<?= function_exists('clay_medal_display_style_tag') ? clay_medal_display_style_tag() : '' ?>
</head>
<body>
<?php require __DIR__ . '/../layouts/topbar.php'; ?>
<main class="profile-page">
<?php if ($profileUser): ?>
  <?php $displayName = user_display_name($profileUser, '用户'); $username = (string)($profileUser['username'] ?? ('user' . (int)($profileUser['id'] ?? 0))); ?>
  <div class="container profile-shell">
    <section class="profile-card">
      <div class="profile-cover">
        <?php if (!empty($profileUser['cover'])): ?><img class="profile-cover-img" src="<?= htmlspecialchars($profileUser['cover']) ?>" alt=""><?php endif; ?>
        <div class="profile-social-stats"><a class="profile-social-stat" href="/index.php?path=user/follows&id=<?= (int)$profileUser['id'] ?>&type=following"><strong><?= !empty($followStats['can_view_following']) ? (int)($followStats['following'] ?? 0) : '--' ?></strong><span>关注</span></a><a class="profile-social-stat" href="/index.php?path=user/follows&id=<?= (int)$profileUser['id'] ?>&type=followers"><strong><?= !empty($followStats['can_view_followers']) ? (int)($followStats['followers'] ?? 0) : '--' ?></strong><span>粉丝</span></a></div>
        <div class="profile-avatar-layer"><?= user_avatar_html($profileUser, 'profile-avatar', 112) ?></div>
      </div>
      <div class="profile-info">
        <div class="profile-title-line"><span class="name-level-inline"><h1 class="profile-name"><?= user_nameplate_html($profileUser, $displayName) ?></h1><?= user_level_badge_html($profileUser) ?></span></div>
        <div class="profile-badges-row"><?= user_badges_html($profileUser, 'clay-user-badges', 8) ?></div><div class="profile-meta"><?php if(!empty($profileUser['public_id'])): ?><span class="profile-badge">ID <?= htmlspecialchars((string)$profileUser['public_id']) ?></span><?php endif; ?><span class="profile-badge"><?= htmlspecialchars((string)($profileUser['role_label'] ?? '普通用户')) ?></span><?php if (!empty($profileUser['verification_name'])): ?><span class="profile-badge"><?= user_verification_badge_html($profileUser) ?> <?= user_verification_label_html($profileUser) ?></span><?php endif; ?></div><div class="profile-joined">注册于 <?= htmlspecialchars(date('Y-m-d', strtotime($profileUser['created_at'] ?? 'now'))) ?></div>
        <p class="profile-bio"><?= htmlspecialchars(trim((string)($profileUser['bio'] ?? '')) !== '' ? (string)$profileUser['bio'] : '这个用户暂时没有填写简介。') ?></p>
        <?php if (auth_check() && (int)(auth_user()['id'] ?? 0) !== (int)$profileUser['id']): ?><div class="profile-actions"><?php if (!empty($followStats['can_follow']) || !empty($followStats['is_following'])): ?><form class="profile-action-form" method="post" action="/index.php" data-ajax-refresh><?= csrf_field() ?><input type="hidden" name="path" value="user/follow"><input type="hidden" name="user_id" value="<?= (int)$profileUser['id'] ?>"><input type="hidden" name="action" value="<?= !empty($followStats['is_following']) ? 'unfollow' : 'follow' ?>"><button class="profile-action-btn <?= !empty($followStats['is_following']) ? 'following' : 'primary' ?>" type="submit"><?= !empty($followStats['is_following']) ? '已关注 · 取消' : '+ 关注 TA' ?></button></form><?php else: ?><button class="profile-action-btn following" type="button" disabled>不可关注</button><?php endif; ?><button class="profile-action-btn chat" type="button" onclick="window.ClayOpenPrivateChat&&window.ClayOpenPrivateChat(<?= (int)$profileUser['id'] ?>, '<?= htmlspecialchars($displayName, ENT_QUOTES) ?>', '<?= htmlspecialchars((string)($profileUser['public_id'] ?? ''), ENT_QUOTES) ?>')">私聊</button><form class="profile-action-form" method="post" action="/index.php?path=user/block" data-ajax-refresh onsubmit="return confirm('<?= !empty($isBlocked) ? '确认取消屏蔽该用户？' : '确认屏蔽该用户？屏蔽后对方不能给你发私聊，TA 的动态也会从你的关注流中过滤。' ?>')"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$profileUser['id'] ?>"><input type="hidden" name="action" value="<?= !empty($isBlocked) ? 'unblock' : 'block' ?>"><button class="profile-action-btn <?= !empty($isBlocked) ? 'following' : 'block' ?>" type="submit"><?= !empty($isBlocked) ? '取消屏蔽' : '屏蔽用户' ?></button></form></div><?php endif; ?>
      </div>
    </section>

    <section class="profile-list-card">
      <h2 class="profile-section-title">TA 的帖子 <?= (int)($threadTotal ?? 0) ?></h2>
      <?php if (!empty($threads)): ?><div class="thread-card-grid"><?php foreach ($threads as $thread): ?><?= render_thread_card($thread) ?><?php endforeach; ?></div><?php else: ?><div class="empty">暂无公开帖子</div><?php endif; ?>
    </section>
  </div>
<?php else: ?>
  <section class="container not-found">用户不存在</section>
<?php endif; ?>
</main>
<?php require __DIR__ . '/../../layouts/theme-toggle.php'; ?>
<?php require __DIR__ . '/../layouts/bottom-nav.php'; ?>
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
