<?php

declare(strict_types=1);

function user_display_name(array $user, string $fallback = '用户'): string
{
    $name = trim((string)($user['nickname'] ?? $user['author_name'] ?? $user['username'] ?? ''));
    return $name !== '' ? $name : $fallback;
}

function user_nameplate_html(array $user, ?string $name = null): string
{
    $displayName = $name !== null ? $name : user_display_name($user, '用户');
    if (!class_exists('\\App\\Core\\Hook')) {
        return htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
    }
    try {
        return (string)\App\Core\Hook::filter('user.nameplate', htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'), ['user' => $user, 'name' => $displayName]);
    } catch (\Throwable $e) {
        return htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
    }
}

function user_role_label(int $userId, string $fallback = ''): string
{
    if ($userId <= 0) return $fallback;
    try {
        $roles = \App\Middleware\Permission::getUserRoles($userId);
        if (!$roles) return $fallback;
        usort($roles, static fn($a, $b) => (int)($b['level'] ?? 0) <=> (int)($a['level'] ?? 0));
        return trim((string)($roles[0]['name'] ?? '')) ?: $fallback;
    } catch (\Throwable $e) {
        return $fallback;
    }
}

function user_role_badge_html(array $user, string $class = 'role-badge'): string
{
    $label = trim((string)($user['role_label'] ?? $user['author_role_label'] ?? ''));
    if ($label === '') {
        $uid = (int)($user['id'] ?? $user['user_id'] ?? $user['author_id'] ?? 0);
        $label = user_role_label($uid, '');
    }
    if ($label === '') return '';
    return '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

function user_badges_html(array $user, string $class = 'clay-user-badges', int $limit = 6): string
{
    $uid = (int)($user['author_id'] ?? $user['user_id'] ?? $user['follower_id'] ?? $user['following_id'] ?? $user['id'] ?? 0);
    if ($uid <= 0 || !class_exists('\\App\\Core\\Hook')) return '';
    try {
        return (string)\App\Core\Hook::filter('user.badges', '', ['user_id' => $uid, 'class' => $class, 'limit' => $limit]);
    } catch (\Throwable $e) {
        return '';
    }
}


function user_verification_for_avatar(array $user): array
{
    if (trim((string)($user['verification_name'] ?? '')) !== '') {
        return $user;
    }
    
    
    $uid = (int)($user['author_id'] ?? $user['user_id'] ?? $user['follower_id'] ?? $user['following_id'] ?? $user['id'] ?? 0);
    if ($uid <= 0) return $user;
    static $cache = [];
    if (!array_key_exists($uid, $cache)) {
        try {
            $cache[$uid] = (new \App\Models\VerificationModel())->activeForUser($uid) ?: null;
        } catch (\Throwable $e) {
            $cache[$uid] = null;
        }
    }
    if ($cache[$uid]) {
        $user['verification_name'] = (string)($cache[$uid]['verification_name'] ?? '');
        $user['verification_color'] = (string)($cache[$uid]['verification_color'] ?? '#2563eb');
    }
    return $user;
}

function user_verification_badge_html(array $user, string $class = 'user-verify-v'): string
{
    $user = user_verification_for_avatar($user);
    $name = trim((string)($user['verification_name'] ?? ''));
    if ($name === '') return '';
    $color = trim((string)($user['verification_color'] ?? '#2563eb'));
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#2563eb';
    return '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" style="--verify-color:' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . '">V</span>';
}

function user_verification_label_html(array $user, string $class = 'user-verify-label'): string
{
    $user = user_verification_for_avatar($user);
    $name = trim((string)($user['verification_name'] ?? ''));
    if ($name === '') return '';
    $color = trim((string)($user['verification_color'] ?? '#2563eb'));
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#2563eb';
    $description = trim((string)($user['verification_description'] ?? $user['description'] ?? ''));
    return '<button type="button" class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" style="color:' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . '" data-verify-name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" data-verify-desc="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</button>';
}

function user_verification_modal_html(): string
{
    static $printed = false;
    if ($printed) return '';
    $printed = true;
    return '<div class="verify-info-modal" id="verifyInfoModal" aria-hidden="true"><div class="verify-info-box" role="dialog" aria-modal="true" aria-labelledby="verifyInfoTitle"><button type="button" class="verify-info-close" data-verify-close>×</button><h3 id="verifyInfoTitle">认证说明</h3><div class="verify-info-name" id="verifyInfoName"></div><p id="verifyInfoDesc"></p></div></div><script>(function(){if(window.__clayVerifyInfoBound)return;window.__clayVerifyInfoBound=true;document.addEventListener("click",function(e){var b=e.target.closest("[data-verify-name]");var m=document.getElementById("verifyInfoModal");if(b&&m){document.getElementById("verifyInfoName").textContent=b.dataset.verifyName||"认证";document.getElementById("verifyInfoDesc").textContent=b.dataset.verifyDesc||"暂无认证说明。";m.classList.add("is-open");m.setAttribute("aria-hidden","false");return;}if(e.target.closest("[data-verify-close]")||e.target===m){m.classList.remove("is-open");m.setAttribute("aria-hidden","true");}});document.addEventListener("keydown",function(e){var m=document.getElementById("verifyInfoModal");if(e.key==="Escape"&&m){m.classList.remove("is-open");m.setAttribute("aria-hidden","true");}});})();</script>';
}

function user_avatar_html(array $user, string $class = 'oc-avatar', int $size = 42): string
{
    $user = user_verification_for_avatar($user);
    $name = user_display_name($user);
    $avatar = trim((string)($user['avatar'] ?? $user['author_avatar'] ?? ''));
    $rawClass = trim('oc-user-avatar ' . $class);
    $safeClass = htmlspecialchars($rawClass, ENT_QUOTES, 'UTF-8');
    $style = '--avatar-size:' . max(20, $size) . 'px;';
    $verify = user_verification_badge_html($user, 'user-verify-v avatar-verify-badge');
    $uid = (int)($user['author_id'] ?? $user['user_id'] ?? $user['follower_id'] ?? $user['following_id'] ?? $user['id'] ?? 0);
    $frame = null;
    if ($uid > 0 && class_exists('\\App\\Core\\Hook')) {
        try { $frame = \App\Core\Hook::filter('user.avatar_frame', null, ['user_id' => $uid, 'user' => $user]); } catch (\Throwable $e) { $frame = null; }
    }
    $frameHtml = '';
    if (is_array($frame) && trim((string)($frame['image'] ?? '')) !== '') {
        $frameName = trim((string)($frame['name'] ?? '头像框')) ?: '头像框';
        $frameHtml = '<img class="avatar-frame-img" src="' . htmlspecialchars((string)$frame['image'], ENT_QUOTES, 'UTF-8') . '" alt="" title="' . htmlspecialchars($frameName, ENT_QUOTES, 'UTF-8') . '" loading="lazy">';
    }

    if ($avatar !== '') {
        $inner = '<span class="' . $safeClass . ' has-image"><img src="' . htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"></span>' . $frameHtml;
    } else {
        $initial = function_exists('mb_substr') ? mb_substr($name, 0, 1) : substr($name, 0, 1);
        $inner = '<span class="' . $safeClass . '">' . htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') . '</span>' . $frameHtml;
    }
    return '<span class="avatar-verify-wrap' . ($frameHtml !== '' ? ' has-avatar-frame' : '') . '" style="' . $style . '">' . $inner . $verify . '</span>';
}

function user_avatar_verify_styles(): string
{
    static $printed = false;
    if ($printed) return '';
    $printed = true;
    $css = '.avatar-verify-wrap{--avatar-radius:50%;--avatar-bg:linear-gradient(135deg,#0284c7,#6366f1);--avatar-color:#fff;--avatar-font-size:calc(var(--avatar-size,42px) * .42);--avatar-font-weight:950;--avatar-border:0;--avatar-shadow:none;--avatar-image-bg:transparent;position:relative!important;display:inline-grid!important;place-items:center!important;width:var(--avatar-size,42px)!important;height:var(--avatar-size,42px)!important;vertical-align:middle!important;flex:0 0 auto!important;overflow:visible!important}.oc-user-avatar,.avatar-verify-wrap>span:not(.avatar-verify-badge){width:100%!important;height:100%!important;min-width:100%!important;min-height:100%!important;max-width:100%!important;max-height:100%!important;aspect-ratio:1/1!important;border-radius:var(--avatar-radius,50%)!important;overflow:hidden!important;position:relative!important;display:inline-grid!important;place-items:center!important;box-sizing:border-box!important;flex:0 0 var(--avatar-size,42px)!important;background:var(--avatar-bg,linear-gradient(135deg,#0284c7,#6366f1))!important;color:var(--avatar-color,#fff)!important;font-size:var(--avatar-font-size,calc(var(--avatar-size,42px) * .42))!important;font-weight:var(--avatar-font-weight,950)!important;border:var(--avatar-border,0)!important;box-shadow:var(--avatar-shadow,none)!important;line-height:1!important}.oc-user-avatar.has-image,.avatar-verify-wrap>span.has-image:not(.avatar-verify-badge){background:var(--avatar-image-bg,transparent)!important}.avatar-verify-wrap .avatar-frame-img{position:absolute!important;inset:-16%!important;width:132%!important;height:132%!important;min-width:132%!important;min-height:132%!important;max-width:none!important;max-height:none!important;object-fit:contain!important;border-radius:0!important;display:block!important;pointer-events:none!important;z-index:2!important}.avatar-verify-wrap.has-avatar-frame .avatar-verify-badge{z-index:4!important}.avatar-verify-wrap img:not(.avatar-frame-img){width:100%!important;height:100%!important;min-width:100%!important;min-height:100%!important;max-width:none!important;max-height:none!important;object-fit:cover!important;border-radius:inherit!important;display:block!important}.user-verify-v{position:static!important;width:18px!important;height:18px!important;min-width:18px!important;min-height:18px!important;max-width:18px!important;max-height:18px!important;border-radius:50%!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;background:var(--verify-color,#2563eb)!important;color:#fff!important;border:0!important;font-family:Arial,Helvetica,sans-serif!important;font-size:10px!important;font-weight:900!important;line-height:1!important;letter-spacing:-.04em!important;padding-top:1px!important;box-sizing:border-box!important;box-shadow:none!important;vertical-align:middle!important;flex:0 0 auto!important;inset:auto!important;transform:none!important}.user-verify-label{border:0;background:transparent;padding:0;margin:0;font:inherit;font-weight:950;cursor:pointer;vertical-align:middle}.user-verify-label:hover{text-decoration:underline}.verify-info-modal{position:fixed;inset:0;z-index:1200;display:none;align-items:center;justify-content:center;background:rgba(15,23,42,.45);padding:20px}.verify-info-modal.is-open{display:flex}.verify-info-box{width:min(360px,100%);border-radius:20px;background:var(--card-bg,#fff);border:1px solid var(--line-soft,#e2e8f0);box-shadow:0 28px 80px rgba(15,23,42,.28);padding:20px;position:relative}.verify-info-box h3{margin:0 0 10px;font-size:18px;color:var(--text-main,#0f172a)}.verify-info-name{font-weight:950;color:var(--primary,#0284c7);margin-bottom:8px}.verify-info-box p{margin:0;color:var(--text-soft,#64748b);font-size:14px;line-height:1.7;white-space:pre-wrap}.verify-info-close{position:absolute;right:12px;top:10px;border:0;background:transparent;color:var(--text-muted,#94a3b8);font-size:26px;line-height:1;cursor:pointer}html[data-theme="dark"] .verify-info-box{background:#111827;border-color:#263244}.avatar-verify-wrap .avatar-verify-badge{position:absolute!important;right:-2px!important;bottom:-2px!important;left:auto!important;top:auto!important;transform:none!important;width:calc(var(--avatar-size,42px) * .34)!important;height:calc(var(--avatar-size,42px) * .34)!important;min-width:13px!important;min-height:13px!important;max-width:26px!important;max-height:26px!important;font-size:calc(var(--avatar-size,42px) * .15)!important;border:2px solid var(--card-bg,#fff)!important;box-shadow:0 3px 8px rgba(15,23,42,.18)!important;z-index:3!important}.profile-avatar,.me-avatar{--avatar-bg:linear-gradient(135deg,#0284c7,#6366f1);--avatar-border:4px solid rgba(255,255,255,.96);--avatar-shadow:0 20px 50px rgba(15,23,42,.24);--avatar-font-size:42px}.mod-avatar{--avatar-bg:#fff;--avatar-color:#0284c7;--avatar-font-size:22px}.mini-avatar{--avatar-font-size:11px;--avatar-border:2px solid rgba(255,255,255,.86)}';
    return '<style>' . $css . '</style>';
}
