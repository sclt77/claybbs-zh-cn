<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function auth_user(): ?array
{
    return $_SESSION['auth_user'] ?? null;
}

function auth_check(): bool
{
    return !empty($_SESSION['auth_user']);
}

function auth_login(array $user): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['auth_user'] = [
        'id'       => $user['id'],
        'username' => $user['username'] ?? '',
        'public_id'=> $user['public_id'] ?? '',
        'nickname' => $user['nickname'] ?? '',
        'email'    => $user['email'] ?? '',
        'role'     => $user['role'] ?? 'user',
        'avatar'   => $user['avatar'] ?? '',
        'cover'    => $user['cover'] ?? '',
    ];
    $_SESSION['user_daily_refresh_login_skip_' . (int)$user['id']] = time();
    try { (new \App\Models\LoginDeviceModel())->recordCurrent((int)$user['id']); } catch (\Throwable $e) {}
}

function auth_logout(): void
{
    try { if (!empty($_SESSION['auth_user']['id'])) { (new \App\Models\LoginDeviceModel())->revokeCurrent((int)$_SESSION['auth_user']['id']); } } catch (\Throwable $e) {}
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params['path'] ?: '/', $params['domain'] ?: '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
