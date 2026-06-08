<?php

declare(strict_types=1);

function is_ajax_request(): bool
{
    return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function ajax_ok(array $extra = []): void
{
    if (!is_ajax_request()) {
        return;
    }
    header('Content-Type: application/json');
    echo json_encode(array_merge(['ok' => true], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function ajax_error(string $message, array $extra = []): void
{
    if (!is_ajax_request()) {
        return;
    }
    header('Content-Type: application/json');
    echo json_encode(array_merge(['ok' => false, 'error' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function normalize_local_redirect(string $redirect, string $fallback = '/index.php'): string
{
    $redirect = trim($redirect);
    if ($redirect === '' || preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $redirect) || str_starts_with($redirect, '//') || preg_match('/[\x00-\x1F\x7F]/', $redirect)) {
        return $fallback;
    }
    $parts = parse_url($redirect);
    if ($parts === false || !empty($parts['host']) || !empty($parts['scheme'])) {
        return $fallback;
    }
    $path = (string)($parts['path'] ?? '');
    if ($path === '') {
        return $fallback;
    }
    if ($path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }
    $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . (string)$parts['query'] : '';
    $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#' . rawurlencode((string)$parts['fragment']) : '';
    return $path . $query . $fragment;
}

function redirect_or_ajax(string $url, array $extra = []): void
{
    $url = normalize_local_redirect($url, '/index.php');
    if (is_ajax_request()) {
        ajax_ok($extra + ['redirect' => $url]);
    }
    header('Location: ' . $url);
    exit;
}
