<?php

declare(strict_types=1);

function safe_url(?string $url, string $fallback = ''): string
{
    $url = trim((string)$url);
    if ($url === '') {
        return $fallback;
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
        return $fallback;
    }
    if (str_starts_with($url, '//')) {
        return $fallback;
    }
    if (preg_match('/^https?:\/\//i', $url)) {
        return $url;
    }
    if (str_starts_with($url, '/')) {
        if (str_contains($url, '\\')) {
            return $fallback;
        }
        return $url;
    }
    return $fallback;
}

function safe_image_url(?string $url, string $fallback = ''): string
{
    $url = trim((string)$url);
    if ($url === '') {
        return $fallback;
    }
    $normal = safe_url($url, '');
    if ($normal !== '') {
        return $normal;
    }
    if (strlen($url) <= 2 * 1024 * 1024 && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,[a-z0-9+/=\r\n]+$#i', $url)) {
        return $url;
    }
    return $fallback;
}

function safe_html(?string $html): string
{
    $html = (string)$html;
    if ($html === '') {
        return '';
    }

    $html = preg_replace('/^\xEF\xBB\xBF/', '', $html) ?? $html;
    $html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $html) ?? $html;

    if (!class_exists('DOMDocument')) {
        return safe_html_regex_fallback($html);
    }

    $allowedTags = [
        'p'=>true,'br'=>true,'strong'=>true,'b'=>true,'em'=>true,'i'=>true,'u'=>true,'s'=>true,
        'blockquote'=>true,'ol'=>true,'ul'=>true,'li'=>true,'a'=>true,'img'=>true,
        'h1'=>true,'h2'=>true,'h3'=>true,'span'=>true,'table'=>true,'thead'=>true,'tbody'=>true,
        'tr'=>true,'th'=>true,'td'=>true,'pre'=>true,'code'=>true,
    ];
    $allowedAttrs = [
        '*' => ['class'],
        'a' => ['href','title','target','rel','class'],
        'img' => ['src','alt','title'],
        'th' => ['class'],
        'td' => ['class'],
    ];

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $wrapped = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body><div id="__clay_safe_root__">' . $html . '</div></body></html>';
    $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $root = $dom->getElementById('__clay_safe_root__');
    if (!$root) {
        return '';
    }
    html_sanitize_dom_node($root, $allowedTags, $allowedAttrs);

    $out = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $out .= $dom->saveHTML($child);
    }
    $out = preg_replace('/<\/?(?:html|body|head|meta)[^>]*>/i', '', $out) ?? $out;
    return trim($out);
}

function html_sanitize_dom_node(DOMNode $node, array $allowedTags, array $allowedAttrs): void
{
    if (!$node->hasChildNodes()) {
        if ($node instanceof DOMElement) {
            html_sanitize_dom_element($node, $allowedTags, $allowedAttrs);
        }
        return;
    }

    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
            $node->removeChild($child);
            continue;
        }
        if ($child instanceof DOMText) {
            continue;
        }
        if ($child instanceof DOMElement) {
            $tag = strtolower($child->tagName);
            if (!isset($allowedTags[$tag])) {
                if (in_array($tag, ['script','style','iframe','object','embed','link','meta','base','form','input','button','textarea','select','option'], true)) {
                    $node->removeChild($child);
                    continue;
                }
                html_sanitize_dom_node($child, $allowedTags, $allowedAttrs);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            html_sanitize_dom_element($child, $allowedTags, $allowedAttrs);
            html_sanitize_dom_node($child, $allowedTags, $allowedAttrs);
            continue;
        }
        $node->removeChild($child);
    }
}

function html_sanitize_dom_element(DOMElement $element, array $allowedTags, array $allowedAttrs): void
{
    $tag = strtolower($element->tagName);
    $allowed = array_merge($allowedAttrs['*'] ?? [], $allowedAttrs[$tag] ?? []);
    foreach (iterator_to_array($element->attributes ?? []) as $attr) {
        $name = strtolower($attr->name);
        $value = html_entity_decode((string)$attr->value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (str_starts_with($name, 'on') || $name === 'style' || !in_array($name, $allowed, true)) {
            $element->removeAttributeNode($attr);
            continue;
        }
        if ($name === 'href') {
            $safe = safe_url($value, '#');
            if ($safe === '#') {
                $element->removeAttribute('href');
                $element->removeAttribute('target');
                $element->removeAttribute('rel');
            } else {
                $element->setAttribute('href', $safe);
                $element->setAttribute('rel', 'noopener noreferrer');
                $target = $element->getAttribute('target');
                if ($target !== '' && !in_array($target, ['_blank','_self'], true)) {
                    $element->removeAttribute('target');
                }
            }
            continue;
        }
        if ($name === 'src') {
            $safe = safe_image_url($value, '');
            if ($safe === '') {
                $element->removeAttribute('src');
            } else {
                $element->setAttribute('src', $safe);
            }
            continue;
        }
        if ($name === 'target' && !in_array($value, ['_blank','_self'], true)) {
            $element->removeAttribute($name);
            continue;
        }
        if ($name === 'class') {
            $classes = [];
            foreach (preg_split('/\s+/', trim($value)) ?: [] as $className) {
                if (preg_match('/^(ql-align-(center|right|justify)|clay-paid-block|clay-paid-block-block|mention-link)$/', $className)) {
                    $classes[] = $className;
                }
            }
            if (!$classes) {
                $element->removeAttribute($name);
            } else {
                $element->setAttribute($name, implode(' ', array_unique($classes)));
            }
            continue;
        }
        $element->setAttribute($name, mb_substr($value, 0, 500));
    }
    if ($tag === 'a' && $element->hasAttribute('href')) {
        $element->setAttribute('rel', 'noopener noreferrer');
    }
    if ($tag === 'img') {
        if (!$element->hasAttribute('src')) {
            $element->parentNode?->removeChild($element);
            return;
        }
        $element->setAttribute('loading', 'lazy');
        $element->setAttribute('decoding', 'async');
    }
}

function safe_html_regex_fallback(string $html): string
{
    $allowedTags = '<p><br><strong><b><em><i><u><s><blockquote><ol><ul><li><a><img><h1><h2><h3><span><table><thead><tbody><tr><th><td><pre><code>';
    $html = strip_tags($html, $allowedTags);
    $html = preg_replace_callback('/<(p|h1|h2|h3|li|table|thead|tbody|tr|th|td|pre|code)\b([^>]*)>/i', static function (array $m): string {
        $tag = strtolower($m[1]);
        $attrs = html_safe_attrs($m[2], ['class'], $tag);
        return '<' . $tag . html_render_attrs($attrs) . '>';
    }, $html) ?? $html;
    $html = preg_replace_callback('/<a\b([^>]*)>/i', static function (array $m): string {
        $attrs = html_safe_attrs($m[1], ['href', 'title', 'target', 'rel', 'class'], 'a');
        if (!isset($attrs['href'])) {
            unset($attrs['target'], $attrs['rel']);
        } else {
            $attrs['href'] = safe_url($attrs['href'], '#');
            if ($attrs['href'] === '#') unset($attrs['target']); else $attrs['rel'] = 'noopener noreferrer';
        }
        return '<a' . html_render_attrs($attrs) . '>';
    }, $html) ?? $html;
    $html = preg_replace_callback('/<img\b([^>]*)>/i', static function (array $m): string {
        $attrs = html_safe_attrs($m[1], ['src', 'alt', 'title'], 'img');
        if (empty($attrs['src'])) return '';
        $attrs['src'] = safe_image_url($attrs['src'], '');
        if ($attrs['src'] === '') return '';
        $attrs['loading'] = 'lazy';
        $attrs['decoding'] = 'async';
        return '<img' . html_render_attrs($attrs) . '>';
    }, $html) ?? $html;
    $html = preg_replace('/<([a-z0-9]+)\b([^>]*)\s(on[a-z]+|style)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '<$1$2', $html) ?? $html;
    $html = preg_replace('/\s(href|src)\s*=\s*("|\')\s*(javascript|data):[^"\']*\2/i', '', $html) ?? $html;
    return $html;
}

function html_safe_attrs(string $raw, array $allowed, string $tag): array
{
    $attrs = [];
    if (preg_match_all('/([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/', $raw, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $name = strtolower($m[1]);
            $allowClass = $name === 'class' && in_array('class', $allowed, true);
            if (str_starts_with($name, 'on') || $name === 'style' || (!in_array($name, $allowed, true) && !$allowClass)) {
                continue;
            }
            $value = html_entity_decode($m[3] !== '' ? $m[3] : ($m[4] !== '' ? $m[4] : $m[5]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($name === 'target' && !in_array($value, ['_blank', '_self'], true)) {
                continue;
            }
            if ($name === 'class') {
                $safeClasses = [];
                foreach (preg_split('/\s+/', trim($value)) ?: [] as $className) {
                    if (preg_match('/^(ql-align-(center|right|justify)|clay-paid-block|clay-paid-block-block|mention-link)$/', $className)) {
                        $safeClasses[] = $className;
                    }
                }
                if (!$safeClasses) {
                    continue;
                }
                $value = implode(' ', array_unique($safeClasses));
            }
            $attrs[$name] = mb_substr($value, 0, 500);
        }
    }
    return $attrs;
}

function mention_user_map_from_text(string $text): array
{
    if ($text === '' || !preg_match_all('/@([\p{L}\p{N}_\x{4e00}-\x{9fa5}]{2,30})/u', $text, $m)) {
        return [];
    }
    try {
        $users = (new \App\Models\MentionModel())->findUsersByNames(array_values(array_unique($m[1] ?? [])));
    } catch (\Throwable $e) {
        return [];
    }
    $map = [];
    foreach ($users as $user) {
        $label = (string)($user['nickname'] ?: $user['username']);
        if ($label !== '') {
            $map[$label] = (int)$user['id'];
        }
        if (!empty($user['username'])) {
            $map[(string)$user['username']] = (int)$user['id'];
        }
    }
    return $map;
}

function link_mentions(string $html): string
{
    $map = mention_user_map_from_text(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if (!$map) {
        return $html;
    }
    $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return $html;
    }
    foreach ($parts as $i => $part) {
        if ($part === '' || $part[0] === '<') {
            continue;
        }
        $parts[$i] = preg_replace_callback('/(^|[^\p{L}\p{N}_])@([\p{L}\p{N}_\x{4e00}-\x{9fa5}]{2,30})/u', static function (array $match) use ($map): string {
            $prefix = $match[1];
            $name = $match[2];
            if (empty($map[$name])) {
                return $match[0];
            }
            return $prefix . '<a class="mention-link" href="/index.php?path=user&id=' . (int)$map[$name] . '">@' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a>';
        }, $part) ?? $part;
    }
    return implode('', $parts);
}

function render_mentions_text(?string $text): string
{
    $safe = nl2br(htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'));
    return link_mentions($safe);
}

function render_paid_rich_content(?string $html, bool $canViewPaid, array $thread = []): string
{
    $safe = safe_html($html);
    $hasPaidBlock = preg_match('/<p\b[^>]*class="[^"]*\b(?:clay-paid-block|clay-paid-block-block)\b[^"]*"[^>]*>/i', $safe) === 1;
    if (!$canViewPaid && !empty($thread['paid_visible_enabled']) && !$hasPaidBlock) {
        return paid_content_unlock_box($thread, '这篇帖子设置了付费查看。');
    }
    $safe = preg_replace_callback('/<p\b([^>]*)class="([^"]*\b(?:clay-paid-block|clay-paid-block-block)\b[^"]*)"([^>]*)>(.*?)<\/p>/is', static function (array $m) use ($canViewPaid, $thread): string {
        $inner = trim((string)$m[4]);
        if ($canViewPaid) {
            return '<div class="paid-content-block"><div class="paid-content-label">付费内容</div><div class="paid-content-body">' . $inner . '</div></div>';
        }
        return paid_content_unlock_box($thread);
    }, $safe) ?? $safe;
    return link_mentions($safe);
}

function paid_content_unlock_box(array $thread, string $desc = ''): string
{
    $threadId = (int)($thread['id'] ?? 0);
    $payLabel = currency_pay_label($thread['paid_visible_price'] ?? 0, (string)($thread['paid_visible_currency'] ?? ''));
    $label = $payLabel !== '' ? '支付 ' . htmlspecialchars($payLabel, ENT_QUOTES, 'UTF-8') . ' 后查看' : '付费后查看';
    $desc = trim($desc) !== '' ? $desc : '购买后可查看这部分内容。';
    $confirmText = $payLabel !== '' ? '确认支付 ' . $payLabel . ' 解锁该内容？' : '确认支付并解锁该内容？';
    $confirmAttr = htmlspecialchars('return confirm(' . json_encode($confirmText, JSON_UNESCAPED_UNICODE) . ');', ENT_QUOTES, 'UTF-8');
    $button = $threadId > 0
        ? '<form class="paid-inline-unlock" method="post" action="/index.php?path=thread/unlock-paid" onsubmit="' . $confirmAttr . '">' . csrf_field() . '<input type="hidden" name="thread_id" value="' . $threadId . '"><button type="submit">支付 ' . htmlspecialchars($payLabel, ENT_QUOTES, 'UTF-8') . '</button></form>'
        : '';
    return '<div class="paid-content-locked"><div class="paid-lock-copy"><strong>付费内容</strong><span>' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</span><em>' . $label . '</em></div>' . $button . '</div>';
}

function render_rich_content(?string $html): string
{
    return render_paid_rich_content($html, true);
}

function html_render_attrs(array $attrs): string
{
    $out = '';
    foreach ($attrs as $name => $value) {
        $out .= ' ' . htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '"';
    }
    return $out;
}
