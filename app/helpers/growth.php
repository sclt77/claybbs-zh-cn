<?php

declare(strict_types=1);

function user_growth_for_badge(array $user): array
{
    $uid = (int)($user['id'] ?? $user['user_id'] ?? $user['author_id'] ?? $user['follower_id'] ?? $user['following_id'] ?? 0);
    if ($uid <= 0) return [];
    static $cache = [];
    if (!array_key_exists($uid, $cache)) {
        try {
            $cache[$uid] = (new \App\Services\GrowthService())->summary($uid);
        } catch (\Throwable $e) {
            $cache[$uid] = [];
        }
    }
    return is_array($cache[$uid]) ? $cache[$uid] : [];
}

function user_level_badge_html(array $user, string $class = 'level-badge'): string
{
    $growth = user_growth_for_badge($user);
    $level = (int)($growth['level'] ?? 1);
    if ($level <= 0) $level = 1;
    $name = trim((string)($growth['name'] ?? ('Lv.' . $level)));
    $color = trim((string)($growth['color'] ?? '#64748b'));
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#64748b';
    $title = $name . ' · Lv.' . $level;
    if (isset($growth['total_exp'])) $title .= ' · ' . (int)$growth['total_exp'] . ' 经验';
    return '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" style="--level-color:' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . '">Lv.' . $level . '<i></i></span>';
}

function user_level_badge_styles(): string
{
    static $printed = false;
    if ($printed) return '';
    $printed = true;
    return '<style>.name-level-inline{display:inline-flex;align-items:center;min-width:0;line-height:1.12;vertical-align:middle}.name-level-inline>h1{margin:0}.level-badge{--level-gap:.34em;--level-scale:.42;position:relative;display:inline-flex;align-items:center;height:1em;padding:0 0 .18em;border:0;border-radius:0;background:transparent;color:color-mix(in srgb,var(--level-color,#64748b) 78%,var(--text-soft,#64748b));font-size:clamp(11px,calc(1em * var(--level-scale)),15px);font-weight:850;line-height:1;vertical-align:middle;white-space:nowrap;opacity:.92;margin-left:var(--level-gap);transform:translateY(.03em)}.level-badge::before{content:"·";display:inline-flex;align-items:center;margin-right:var(--level-gap);color:var(--text-muted,#94a3b8);font-weight:800;line-height:1;transform:translateY(-.01em)}.level-badge i{position:absolute;left:calc(.62em + var(--level-gap));right:1px;bottom:-.12em;height:2px;border-radius:999px;background:linear-gradient(90deg,var(--level-color,#64748b),transparent);opacity:.55;pointer-events:none}.level-badge.small{--level-scale:.78;font-size:clamp(10px,calc(1em * var(--level-scale)),12px);padding-bottom:.16em}.level-badge.small i{left:calc(.62em + var(--level-gap));height:2px}.author-title-line .level-badge,.profile-title-line .level-badge{--level-scale:.30;transform:translateY(.08em)}.author-title-line .level-badge i,.profile-title-line .level-badge i{bottom:-.16em}.author-name .level-badge,.profile-name .level-badge,.follow-title .level-badge{--level-scale:.36}.level-badge:hover{opacity:1}.level-badge:hover i{opacity:.85}html[data-theme="dark"] .level-badge{color:color-mix(in srgb,var(--level-color,#94a3b8) 82%,#e5e7eb)}</style>';
}
