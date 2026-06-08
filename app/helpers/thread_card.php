<?php

declare(strict_types=1);

function thread_cover_url(array $thread): string
{
    $cover = trim((string)($thread['cover'] ?? ''));
    if ($cover !== '') return safe_image_url($cover);
    $content = (string)($thread['content'] ?? '');
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m)) {
        return safe_image_url((string)$m[1]);
    }
    return '';
}

function thread_image_urls(array $thread, int $limit = 12): array
{
    $images = [];
    $content = (string)($thread['content'] ?? '');
    if ($content !== '' && preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches)) {
        foreach (($matches[1] ?? []) as $src) {
            $safe = safe_image_url((string)$src);
            if ($safe !== '') $images[] = $safe;
            if (count($images) >= $limit) break;
        }
    }
    $cover = thread_cover_url($thread);
    if ($cover !== '' && !in_array($cover, $images, true)) {
        array_unshift($images, $cover);
    }
    return array_slice(array_values(array_unique($images)), 0, $limit);
}

function thread_plain_text(array $thread): string
{
    $text = trim(strip_tags((string)($thread['summary'] ?? '')));
    if ($text === '') {
        $content = preg_replace('/<img\b[^>]*>/i', '', (string)($thread['content'] ?? '')) ?? (string)($thread['content'] ?? '');
        $text = trim(strip_tags($content));
    }
    return preg_replace('/\s+/u', ' ', $text) ?? $text;
}

function thread_excerpt(array $thread, int $length = 86): string
{
    $text = thread_plain_text($thread);
    if (function_exists('mb_strlen') && mb_strlen($text) > $length) return mb_substr($text, 0, $length) . '...';
    if (!function_exists('mb_strlen') && strlen($text) > $length) return substr($text, 0, $length) . '...';
    return $text;
}


function render_top_thread_strip(array $threads, string $context = 'home'): string
{
    if (!$threads) return '';
    $safeContext = preg_replace('/[^a-z0-9_-]/i', '', $context) ?: 'home';
    $items = '';
    foreach ($threads as $thread) {
        $id = (int)($thread['id'] ?? 0);
        if ($id <= 0) continue;
        $title = htmlspecialchars((string)($thread['title'] ?? '未命名帖子'), ENT_QUOTES, 'UTF-8');
        $sectionName = trim((string)($thread['section_name'] ?? ''));
        $section = htmlspecialchars($sectionName !== '' ? $sectionName : '帖子', ENT_QUOTES, 'UTF-8');
        $createdTs = strtotime((string)($thread['created_at'] ?? '')) ?: 0;
        $time = $createdTs > 0 ? date('m-d', $createdTs) : '';
        $views = (int)($thread['view_count'] ?? 0);
        $replies = (int)($thread['reply_count'] ?? 0);
        $meta = [];
        if ($section !== '') $meta[] = '<span>' . $section . '</span>';
        if ($time !== '') $meta[] = '<span>' . htmlspecialchars($time, ENT_QUOTES, 'UTF-8') . '</span>';
        if ($views > 0) $meta[] = '<span>' . $views . ' 阅读</span>';
        if ($replies > 0) $meta[] = '<span>' . $replies . ' 回复</span>';
        $items .= '<a class="top-thread-item" href="/index.php?path=thread&id=' . $id . '">'
            . '<span class="top-thread-main"><span class="top-thread-title"><span class="top-thread-label">置顶</span>' . $title . '</span><span class="top-thread-meta">' . implode('', $meta) . '</span></span>'
            . '</a>';
    }
    if ($items === '') return '';
    return '<section class="top-thread-strip top-thread-strip-' . htmlspecialchars($safeContext, ENT_QUOTES, 'UTF-8') . '"><div class="top-thread-list">' . $items . '</div></section>';
}

function top_thread_strip_styles(): string
{
    static $printed = false;
    if ($printed) return '';
    $printed = true;
    return '<style>
.top-thread-strip{margin:4px 0 14px;padding:0;background:transparent;border:0;border-radius:0;box-shadow:none;overflow:visible}.top-thread-head{display:flex;align-items:center;gap:8px;margin:0 0 8px;color:#92400e}.top-thread-head-icon{width:26px;height:26px;border-radius:999px;display:inline-grid;place-items:center;background:rgba(251,191,36,.20);font-size:14px}.top-thread-head strong{font-size:15px;font-weight:950;letter-spacing:-.02em}.top-thread-head span:last-child{color:#b45309;font-size:12px;font-weight:800;opacity:.72}.top-thread-list{display:grid;gap:7px}.top-thread-item{display:block;min-height:46px;padding:11px 14px;border-radius:14px;background:rgba(255,251,235,.62);border:1px solid rgba(254,243,199,.78);color:var(--text-main,#0f172a);text-decoration:none;transition:.16s ease}.top-thread-item:hover{transform:translateY(-1px);border-color:rgba(251,191,36,.58);box-shadow:0 10px 24px rgba(180,83,9,.08);background:#fff}.top-thread-pin{width:30px;height:30px;border-radius:11px;display:inline-grid;place-items:center;background:linear-gradient(135deg,#f59e0b,#ef4444);color:#fff;font-size:13px;font-weight:950;box-shadow:0 8px 18px rgba(245,158,11,.20)}.top-thread-main{min-width:0;display:grid;gap:4px}.top-thread-title{font-size:15px;font-weight:950;line-height:1.35;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.top-thread-label{display:inline-flex;align-items:center;margin-right:7px;padding:1px 5px;border-radius:5px;background:rgba(245,158,11,.10);color:#b45309;font-size:11px;font-weight:900;line-height:1.45;vertical-align:1px}.top-thread-meta{display:flex;gap:8px;align-items:center;min-width:0;color:#b7a083;font-size:12px;font-weight:800;white-space:nowrap;overflow:hidden}.top-thread-meta span{min-width:0;overflow:hidden;text-overflow:ellipsis}.top-thread-meta span+span::before{content:"·";margin-right:8px;color:#d6b977}.top-thread-scope{justify-self:end;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:950;white-space:nowrap;background:#fff7ed;color:#b45309}.top-thread-scope.section{background:#eff6ff;color:#2563eb}.top-thread-strip-section{margin:0 0 2px!important;padding:4px 0 1px;border-bottom:1px solid var(--line-soft,#e2e8f0)}.top-thread-strip-section .top-thread-item{min-height:40px;padding:8px 12px}
html[data-theme="dark"] .top-thread-strip{background:transparent;border-color:transparent;box-shadow:none}html[data-theme="dark"] .top-thread-head{color:#fbbf24}html[data-theme="dark"] .top-thread-head-icon{background:rgba(251,191,36,.16)}html[data-theme="dark"] .top-thread-head span:last-child{color:#fcd34d}html[data-theme="dark"] .top-thread-item{background:rgba(69,43,16,.22);border-color:rgba(120,113,108,.34);color:#e5e7eb}html[data-theme="dark"] .top-thread-item:hover{background:rgba(17,24,39,.98);border-color:rgba(251,191,36,.36);box-shadow:0 12px 28px rgba(0,0,0,.24)}html[data-theme="dark"] .top-thread-meta{color:#c4a676}html[data-theme="dark"] .top-thread-label{background:rgba(251,191,36,.12);color:#fbbf24}html[data-theme="dark"] .top-thread-meta span+span::before{color:#8b7355}html[data-theme="dark"] .top-thread-scope{background:rgba(251,191,36,.14);color:#fbbf24}html[data-theme="dark"] .top-thread-scope.section{background:rgba(59,130,246,.16);color:#93c5fd}
@media(max-width:640px){.top-thread-strip{margin:4px 0 12px;padding:0;border-radius:0}.top-thread-list{gap:7px}.top-thread-item{padding:10px 12px;min-height:46px;border-radius:14px}.top-thread-title{font-size:14px}.top-thread-label{margin-right:6px;padding:1px 4px;font-size:10px}.top-thread-meta{font-size:11px;gap:6px}.top-thread-meta span+span::before{margin-right:6px}.top-thread-strip-section{margin:0 0 2px!important;padding:4px 0 1px}.top-thread-strip-section .top-thread-item{min-height:38px;padding:7px 11px}}
</style>';
}

function render_thread_card(array $thread, string $class = ''): string
{
    $id = (int)($thread['id'] ?? 0);
    $url = '/index.php?path=thread&id=' . $id;
    $title = htmlspecialchars((string)($thread['title'] ?? '未命名帖子'), ENT_QUOTES, 'UTF-8');
    $sectionName = trim((string)($thread['section_name'] ?? ''));
    $section = htmlspecialchars($sectionName !== '' ? $sectionName : '帖子', ENT_QUOTES, 'UTF-8');
    $sectionId = (int)($thread['section_id'] ?? 0);
    $sectionUrl = '/index.php?path=section&id=' . $sectionId;
    $authorName = user_display_name($thread, '匿名');
    $authorId = (int)($thread['user_id'] ?? $thread['author_id'] ?? 0);
    $author = user_nameplate_html(['id' => $authorId] + $thread, $authorName);
    $authorUrl = '/index.php?path=user&id=' . $authorId;
    $excerpt = htmlspecialchars(thread_excerpt($thread, 120), ENT_QUOTES, 'UTF-8');
    $createdRaw = (string)($thread['created_at'] ?? 'now');
    $createdTs = strtotime($createdRaw) ?: time();
    $created = htmlspecialchars(date('Y年m月d日', $createdTs), ENT_QUOTES, 'UTF-8');
    $views = (int)($thread['view_count'] ?? 0);
    $replies = (int)($thread['reply_count'] ?? 0);
    $likes = (int)($thread['like_count'] ?? 0);
    $safeClass = trim('thread-card-v2 home-latest-card ' . $class);

    $badge = '';
    if (!empty($thread['section_is_question']) && (string)($thread['question_status'] ?? 'none') === 'resolved') $badge = '<span class="home-latest-badge solved thread-badge solved">已解决</span>';
    elseif (!empty($thread['is_top'])) $badge = '<span class="home-latest-badge top thread-badge top">置顶</span>';
    elseif (!empty($thread['is_featured'])) $badge = '<span class="home-latest-badge featured thread-badge featured">精华</span>';
    elseif (!empty($thread['is_recommended'])) $badge = '<span class="home-latest-badge recommended thread-badge recommended">推荐</span>';
    elseif (!empty($thread['section_is_question']) && !empty($thread['bounty_currency']) && (float)($thread['bounty_amount'] ?? 0) > 0) $badge = '<span class="home-latest-badge bounty thread-badge bounty">' . htmlspecialchars(currency_pay_label((float)$thread['bounty_amount'], (string)$thread['bounty_currency']), ENT_QUOTES, 'UTF-8') . '</span>';
    elseif (!empty($thread['is_locked'])) $badge = '<span class="home-latest-badge locked thread-badge locked">锁定</span>';

    $images = thread_image_urls($thread, 12);
    $visibleImages = array_slice($images, 0, 9);
    $imageCount = count($images);
    $imageClass = $imageCount === 1 ? ' single' : ($imageCount === 2 ? ' two' : ' multi');
    $imageHtml = '';
    if ($visibleImages) {
        $imageHtml .= '<div class="home-feed-images thread-card-images' . $imageClass . '">';
        foreach ($visibleImages as $idx => $img) {
            $imageHtml .= '<span class="home-feed-img thread-card-img"><img src="' . htmlspecialchars($img, ENT_QUOTES, 'UTF-8') . '" alt="">';
            if ($idx === 8 && $imageCount > 9) {
                $imageHtml .= '<span class="home-feed-more">+' . ($imageCount - 9) . '</span>';
            }
            $imageHtml .= '</span>';
        }
        $imageHtml .= '</div>';
    }

    return '<article class="' . htmlspecialchars($safeClass, ENT_QUOTES, 'UTF-8') . '" data-href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" data-thread-id="' . $id . '" data-csrf="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">'
        . '<div class="home-feed-head thread-card-head">'
        . '<a class="home-latest-avatar thread-card-avatar" href="' . htmlspecialchars($authorUrl, ENT_QUOTES, 'UTF-8') . '">' . user_avatar_html($thread, 'thread-card-avatar-inner', 44) . '</a>'
        . '<div class="home-feed-author thread-card-author"><div class="home-feed-name-line"><span class="name-level-inline"><a class="home-feed-name" href="' . htmlspecialchars($authorUrl, ENT_QUOTES, 'UTF-8') . '">' . $author . '</a>' . user_level_badge_html($thread, 'level-badge small') . '</span>' . user_role_badge_html($thread) . '</div><div class="home-feed-date">' . $created . '</div></div>'
        . '</div>'
        . '<div class="home-latest-top thread-card-top">' . ($badge !== '' ? '<div>' . $badge . '</div>' : '<div></div>') . '<a class="home-latest-title thread-card-title" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $title . '</a></div>'
        . ($excerpt !== '' ? '<div class="home-latest-excerpt thread-card-excerpt">' . $excerpt . '</div>' : '')
        . $imageHtml
        . '<div class="home-latest-footer thread-card-footer"><a class="home-section-pill thread-card-section" href="' . htmlspecialchars($sectionUrl, ENT_QUOTES, 'UTF-8') . '">' . $section . '</a><div class="home-latest-meta thread-card-meta"><span title="阅读 ' . $views . '">' . $views . '</span><span title="回复 ' . $replies . '">' . $replies . '</span><span title="点赞 ' . $likes . '">' . $likes . '</span></div></div>'
        . '<div class="thread-card-swipe-hint left" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3.8h12a1 1 0 0 1 1 1v16l-7-4-7 4v-16a1 1 0 0 1 1-1Z"/></svg></div><div class="thread-card-swipe-hint right" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 21.3 10.6 20C5.4 15.2 2 12.1 2 8.3 2 5.2 4.4 3 7.4 3c1.7 0 3.3.8 4.6 2.1C13.3 3.8 14.9 3 16.6 3 19.6 3 22 5.2 22 8.3c0 3.8-3.4 6.9-8.6 11.7L12 21.3Z"/></svg></div><div class="thread-like-pop" aria-hidden="true"></div>'
        . '<div class="thread-card-quick" aria-label="帖子快捷操作"><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '#reply-box">回复</a><button type="button" data-copy-link="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">复制链接</button><a href="/index.php?path=report&type=thread&id=' . $id . '">举报</a></div>'
        . '</article>';
}

function thread_card_styles(): string
{
    static $printed = false;
    if ($printed) return '';
    $printed = true;
    return user_avatar_verify_styles() . user_level_badge_styles() . '<style>
.thread-card-swipe-hint{position:absolute;top:50%;z-index:8;width:56px;height:74px;display:grid;place-items:center;color:#fff;opacity:0;pointer-events:none;transition:opacity .12s ease,transform .12s ease,box-shadow .12s ease}.thread-card-swipe-hint svg{width:25px;height:25px;fill:currentColor;transform:scale(.84);transition:transform .12s ease}.thread-card-swipe-hint.left{right:0;border-radius:999px 0 0 999px;background:linear-gradient(135deg,rgba(245,158,11,.98),rgba(251,191,36,.92));transform:translate(18px,-50%) scale(.9);box-shadow:-8px 0 22px rgba(245,158,11,.16)}.thread-card-swipe-hint.right{left:0;border-radius:0 999px 999px 0;background:linear-gradient(135deg,rgba(239,68,68,.98),rgba(251,113,133,.92));transform:translate(-18px,-50%) scale(.9);box-shadow:8px 0 22px rgba(239,68,68,.16)}.thread-card-v2.swiping-left .thread-card-swipe-hint.left,.home-latest-card.swiping-left .thread-card-swipe-hint.left,.thread-card-v2.swiping-right .thread-card-swipe-hint.right,.home-latest-card.swiping-right .thread-card-swipe-hint.right{opacity:1;transform:translate(0,-50%) scale(calc(.92 + var(--swipe-progress,0) * .08))}.thread-card-v2.swiping-left .thread-card-swipe-hint.left svg,.home-latest-card.swiping-left .thread-card-swipe-hint.left svg,.thread-card-v2.swiping-right .thread-card-swipe-hint.right svg,.home-latest-card.swiping-right .thread-card-swipe-hint.right svg{transform:scale(calc(.9 + var(--swipe-progress,0) * .22))}.thread-card-v2.swipe-ready .thread-card-swipe-hint.left,.home-latest-card.swipe-ready .thread-card-swipe-hint.left{box-shadow:-12px 0 34px rgba(245,158,11,.30)}.thread-card-v2.swipe-ready .thread-card-swipe-hint.right,.home-latest-card.swipe-ready .thread-card-swipe-hint.right{box-shadow:12px 0 34px rgba(239,68,68,.30)}.thread-card-v2.swipe-done{animation:threadCardDone .42s cubic-bezier(.16,1,.3,1)}@keyframes threadCardDone{0%{transform:scale(1)}40%{transform:scale(.985)}100%{transform:scale(1)}}.thread-like-pop{position:absolute;left:50%;top:50%;z-index:6;width:58px;height:58px;transform:translate(-50%,-50%) scale(.7);pointer-events:none;opacity:0;filter:drop-shadow(0 14px 24px rgba(239,68,68,.24))}.thread-like-pop::before,.thread-like-pop::after{content:"";position:absolute;left:29px;top:12px;width:25px;height:40px;border-radius:25px 25px 0 0;background:linear-gradient(180deg,#fb7185,#ef4444);transform:rotate(-45deg);transform-origin:0 100%}.thread-like-pop::after{left:4px;transform:rotate(45deg);transform-origin:100% 100%}.thread-card-v2.like-pop .thread-like-pop{animation:threadLikePop .68s cubic-bezier(.16,1,.3,1)}@keyframes threadLikePop{0%{opacity:0;transform:translate(-50%,-50%) scale(.48)}28%{opacity:1;transform:translate(-50%,-50%) scale(1.02)}58%{opacity:.95;transform:translate(-50%,-58%) scale(.92)}100%{opacity:0;transform:translate(-50%,-76%) scale(.82)}}.thread-image-preview{position:fixed;inset:0;z-index:1600;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(15,23,42,.78);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)}.thread-image-preview.is-open{display:flex}.thread-image-preview img{max-width:100%;max-height:88vh;border-radius:16px;box-shadow:0 28px 80px rgba(0,0,0,.34);object-fit:contain}.thread-image-preview button{position:absolute;right:16px;top:16px;width:38px;height:38px;border:0;border-radius:999px;background:rgba(255,255,255,.16);color:#fff;font-size:24px;line-height:1;cursor:pointer}
.thread-card-v2,.home-latest-card{-webkit-user-select:none!important;user-select:none!important;-webkit-touch-callout:none!important}.thread-card-v2 img,.home-latest-card img{-webkit-user-drag:none;user-drag:none}.thread-card-v2 a,.thread-card-v2 button,.home-latest-card a,.home-latest-card button{-webkit-touch-callout:none!important}
.thread-card-quick{display:none;position:absolute;right:14px;bottom:14px;z-index:4;gap:6px;padding:6px;border-radius:999px;background:rgba(15,23,42,.78);box-shadow:0 12px 28px rgba(15,23,42,.18);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}.thread-card-quick a,.thread-card-quick button{height:28px;border:0;border-radius:999px;background:rgba(255,255,255,.12);color:#fff;text-decoration:none;padding:0 10px;font-size:12px;font-weight:900;display:inline-flex;align-items:center;cursor:pointer}.thread-card-v2.is-quick-open .thread-card-quick{display:flex}.thread-card-v2.is-touching{transform:scale(.985)!important}@media(max-width:640px){.thread-card-quick{right:10px;bottom:10px}.thread-card-quick a,.thread-card-quick button{height:30px;padding:0 9px;font-size:11px}}
.thread-card-grid{display:grid;gap:12px}.thread-card-v2{display:block;color:inherit;text-decoration:none;position:relative;cursor:pointer;overflow:hidden}.thread-card-head{overflow:visible!important}.thread-card-v2.home-latest-card{padding:20px 18px 18px!important;border:1px solid rgba(226,232,240,.72)!important;border-radius:24px!important;background:linear-gradient(180deg,rgba(255,255,255,.94),rgba(255,255,255,.82))!important;box-shadow:0 10px 30px rgba(15,23,42,.045)!important;transition:transform .18s cubic-bezier(.16,1,.3,1),box-shadow .16s ease,background .16s ease}.thread-card-v2.is-dragging{transition:none!important;transform:translateX(var(--card-swipe-x,0px)) scale(.992)!important}.thread-card-v2.home-latest-card:hover{background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(248,252,255,.92))!important;box-shadow:0 16px 40px rgba(15,23,42,.07)!important;transform:translateY(-1px)}.thread-card-head{display:flex;gap:12px;align-items:center;min-height:44px;margin-bottom:12px}.thread-card-avatar{width:44px;height:44px;min-width:44px;border-radius:50%;display:grid;place-items:center;overflow:visible!important;text-decoration:none;background:transparent!important;color:#0284c7;border:0!important;box-shadow:none!important}.thread-card-avatar .avatar-verify-wrap{--avatar-size:44px!important}.thread-card-avatar .avatar-verify-wrap>span:not(.avatar-verify-badge){box-shadow:0 8px 18px rgba(15,23,42,.07)!important;border:1px solid rgba(226,232,240,.9)!important}.thread-card-avatar-inner{width:100%!important;height:100%!important;border-radius:50%!important;display:grid;place-items:center;background:transparent;color:#0284c7;font-weight:950}.thread-card-author{min-width:0;display:flex;flex-direction:column;gap:4px}.home-feed-name-line{display:flex;gap:6px;align-items:center;flex-wrap:nowrap;min-width:0;overflow:hidden}.home-feed-name{font-size:15px;font-weight:950;color:var(--text-main,#0f172a)!important;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}.home-feed-name:hover{color:var(--primary,#0284c7)!important}.home-feed-date{font-size:13px;color:var(--text-muted,#94a3b8)}.role-badge,.thread-role-badge{display:inline-flex;align-items:center;border-radius:999px;background:rgba(2,132,199,.10);color:var(--primary,#0284c7);padding:2px 7px;font-size:11px;font-weight:950;line-height:1.35;white-space:nowrap;flex:0 0 auto}.thread-card-top{display:grid;grid-template-columns:auto minmax(0,1fr);gap:10px;align-items:center;margin:10px 0 7px}.thread-card-top>div:first-child:empty{display:none}.thread-card-top:has(> div:first-child:empty){grid-template-columns:minmax(0,1fr)}.home-latest-badge,.thread-badge{border-radius:9px;padding:6px 12px;font-size:13px;font-weight:950;line-height:1.35}.thread-badge.top{background:#fff7ed;color:#b45309}.thread-badge.featured{background:#fff1db;color:#e56a00}.thread-badge.recommended{background:#e0f2fe;color:#0284c7}.thread-badge.locked{background:#e2e8f0;color:#475569}.thread-badge.bounty{background:#ecfeff;color:#0e7490}.thread-badge.solved{background:#dcfce7;color:#166534}.thread-card-title{font-size:21px!important;line-height:1.42;font-weight:900!important;letter-spacing:-.02em;color:var(--text-main,#0f172a)!important;text-decoration:none!important;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.thread-card-excerpt{margin-top:8px!important;font-size:15px!important;line-height:1.72!important;color:var(--text-soft,#64748b)!important;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.thread-card-images{display:grid;gap:8px;margin-top:15px;max-width:100%}.thread-card-images.single{display:block}.thread-card-images.single .thread-card-img{display:block;aspect-ratio:16/7;border-radius:18px;background:#eef2f7;overflow:hidden}.thread-card-images.single img{display:block;width:100%!important;height:100%!important;max-height:none!important;object-fit:cover!important;object-position:center!important;background:#eef2f7;border-radius:inherit}.thread-card-images.two{grid-template-columns:repeat(2,minmax(0,1fr))}.thread-card-images.two .thread-card-img{aspect-ratio:1.18/1;border-radius:16px}.thread-card-images.multi{grid-template-columns:repeat(3,minmax(0,1fr))}.thread-card-images.multi .thread-card-img{aspect-ratio:1/1;border-radius:14px}.thread-card-img{position:relative;overflow:hidden;background:#f1f5f9}.thread-card-img img{width:100%;height:100%;object-fit:cover;object-position:center;display:block;border-radius:inherit;transition:transform .2s ease}.thread-card-v2:hover .thread-card-img img{transform:scale(1.015)}.home-feed-more{position:absolute;inset:0;border-radius:inherit;background:rgba(15,23,42,.46);color:#fff;display:grid;place-items:center;font-size:32px;font-weight:950;backdrop-filter:blur(1px)}.thread-card-footer{display:flex;justify-content:space-between;gap:14px;align-items:center;margin-top:16px}.thread-card-section{border-radius:999px;background:#f0f1f3!important;color:#4b5563!important;font-size:14px;font-weight:950;padding:8px 18px;min-width:120px;max-width:240px;text-align:center;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.thread-card-section:hover{background:rgba(2,132,199,.10)!important;color:var(--primary,#0284c7)!important}.thread-card-meta{display:flex;gap:15px;align-items:center;justify-content:flex-end;color:#8b8f99;flex-wrap:nowrap;white-space:nowrap;min-width:0}.thread-card-meta span{position:relative;display:inline-flex;align-items:center;gap:5px;background:transparent!important;padding:0!important;height:auto!important;color:#8b8f99!important;font-size:14px;font-weight:500;line-height:1}.thread-card-meta span::before{content:"";display:inline-block;width:18px;height:18px;background-color:#a3a8b1;opacity:.92;flex:0 0 auto}.thread-card-meta span:nth-child(1)::before{mask:url("data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"><path fill=\"black\" d=\"M12 5C6.5 5 2.2 9.2 1 12c1.2 2.8 5.5 7 11 7s9.8-4.2 11-7c-1.2-2.8-5.5-7-11-7Zm0 12.2c-4.2 0-7.6-2.8-9-5.2 1.4-2.4 4.8-5.2 9-5.2s7.6 2.8 9 5.2c-1.4 2.4-4.8 5.2-9 5.2Zm0-8.2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z\"/></svg>") center/contain no-repeat;-webkit-mask:url("data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"><path fill=\"black\" d=\"M12 5C6.5 5 2.2 9.2 1 12c1.2 2.8 5.5 7 11 7s9.8-4.2 11-7c-1.2-2.8-5.5-7-11-7Zm0 12.2c-4.2 0-7.6-2.8-9-5.2 1.4-2.4 4.8-5.2 9-5.2s7.6 2.8 9 5.2c-1.4 2.4-4.8 5.2-9 5.2Zm0-8.2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z\"/></svg>") center/contain no-repeat}.thread-card-meta span:nth-child(2)::before{mask:url("data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"><path fill=\"black\" d=\"M5 5h14v10H8.2L5 18.1V5Zm-2-2v20l6-6h12V3H3Z\"/></svg>") center/contain no-repeat;-webkit-mask:url("data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"><path fill=\"black\" d=\"M5 5h14v10H8.2L5 18.1V5Zm-2-2v20l6-6h12V3H3Z\"/></svg>") center/contain no-repeat}.thread-card-meta span:nth-child(3)::before{mask:url("data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"><path fill=\"black\" d=\"M12 21.3 10.6 20C5.4 15.2 2 12.1 2 8.3 2 5.2 4.4 3 7.4 3c1.7 0 3.3.8 4.6 2.1C13.3 3.8 14.9 3 16.6 3 19.6 3 22 5.2 22 8.3c0 3.8-3.4 6.9-8.6 11.7L12 21.3ZM7.4 5C5.5 5 4 6.4 4 8.3c0 2.8 2.7 5.3 8 10.2 5.3-4.9 8-7.4 8-10.2C20 6.4 18.5 5 16.6 5c-1.5 0-2.9 1-3.4 2.3h-2.4C10.3 6 8.9 5 7.4 5Z\"/></svg>") center/contain no-repeat;-webkit-mask:url("data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"><path fill=\"black\" d=\"M12 21.3 10.6 20C5.4 15.2 2 12.1 2 8.3 2 5.2 4.4 3 7.4 3c1.7 0 3.3.8 4.6 2.1C13.3 3.8 14.9 3 16.6 3 19.6 3 22 5.2 22 8.3c0 3.8-3.4 6.9-8.6 11.7L12 21.3ZM7.4 5C5.5 5 4 6.4 4 8.3c0 2.8 2.7 5.3 8 10.2 5.3-4.9 8-7.4 8-10.2C20 6.4 18.5 5 16.6 5c-1.5 0-2.9 1-3.4 2.3h-2.4C10.3 6 8.9 5 7.4 5Z\"/></svg>") center/contain no-repeat}html[data-theme="dark"] .thread-card-v2.home-latest-card{background:linear-gradient(180deg,rgba(17,24,39,.92),rgba(17,24,39,.72))!important;border-color:#263244!important;box-shadow:0 12px 34px rgba(0,0,0,.24)!important}html[data-theme="dark"] .thread-card-v2.home-latest-card:hover{background:linear-gradient(180deg,rgba(17,24,39,.98),rgba(15,23,42,.86))!important}html[data-theme="dark"] .thread-card-title,html[data-theme="dark"] .home-feed-name{color:#e5e7eb!important}html[data-theme="dark"] .thread-card-excerpt{color:#a8b3c4!important}html[data-theme="dark"] .thread-card-section{background:#1e293b!important;color:#e5e7eb!important}html[data-theme="dark"] .thread-card-meta span{color:#94a3b8!important}@media(max-width:640px){.thread-card-grid{gap:10px}.thread-card-v2.home-latest-card{padding:16px 12px!important;border-radius:20px!important}.thread-card-avatar{width:42px;height:42px;min-width:42px}.thread-card-title{font-size:19px!important}.thread-card-excerpt{font-size:15px!important;line-height:1.65!important}.thread-card-images{gap:8px;margin-top:12px}.thread-card-images.single .thread-card-img{aspect-ratio:16/7}.thread-card-images.single .thread-card-img{aspect-ratio:16/8;border-radius:15px}.thread-card-images.two .thread-card-img,.thread-card-images.multi .thread-card-img{border-radius:13px}.thread-card-footer{gap:10px}.thread-card-section{min-width:112px;max-width:40vw;font-size:14px;padding:8px 16px}.thread-card-meta{gap:8px}.thread-card-meta span{font-size:13px}}
</style>';
}
