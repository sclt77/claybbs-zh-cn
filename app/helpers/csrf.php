<?php

declare(strict_types=1);




if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}



function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
}



function csrf_verify(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if (!csrf_check()) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="zh"><head><meta charset="UTF-8"><title>403</title></head>'
            . '<body style="font-family:sans-serif;text-align:center;padding:80px;">'
            . '<h1 style="color:#e53935;">403</h1><p>请求验证失败（CSRF），请刷新页面后重试。</p>'
            . '<a href="javascript:history.back()">返回</a></body></html>';
        exit;
    }
}

function csrf_check(bool $consume = true): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }

    $submitted = (string)($_POST['_csrf_token'] ?? '');
    $expected  = (string)($_SESSION['_csrf_token'] ?? '');

    if ($expected === '' || $submitted === '' || !hash_equals($expected, $submitted)) {
        return false;
    }

    if ($consume && strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest') {
        unset($_SESSION['_csrf_token']);
    }

    return true;
}
