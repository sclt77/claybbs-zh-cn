<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\LicenseGuardService;
use App\Services\DatabaseSchemaGuard;



class AdminAuth
{
    

    public static function check(bool $guardLicense = true): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = $_SESSION['auth_user'] ?? null;

        
        if (empty($user)) {
            header('Location: /index.php?path=login&redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/admin'));
            exit;
        }

        if (!Permission::canAnyScope('admin.access') && !in_array((string)($user['role'] ?? ''), ['moderator','reviewer','admin','superadmin'], true)) {
            http_response_code(403);
            echo self::render403($user['nickname'] ?? $user['username'] ?? '用户');
            exit;
        }

        try {
            (new DatabaseSchemaGuard())->checkAndRepair(false);
        } catch (\Throwable $e) {
            // 数据库结构兜底不能影响后台打开；错误由 SchemaGuard 写入日志。
        }

        if ($guardLicense) {
            (new LicenseGuardService())->guardAdmin();
        }
    }

    

    public static function requireSuperAdmin(): void
    {
        self::check();
        $user = $_SESSION['auth_user'] ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $roles = $userId > 0 ? Permission::getUserRoles($userId) : [];
        $isSuperAdmin = false;
        if (($user['role'] ?? '') === 'superadmin') {
            $isSuperAdmin = true;
        }
        foreach ($roles as $role) {
            if (($role['slug'] ?? '') === 'superadmin' && ($role['scope'] ?? '') === 'global') {
                $isSuperAdmin = true;
                break;
            }
        }
        if (!$isSuperAdmin) {
            http_response_code(403);
            echo self::render403($user['nickname'] ?? $user['username'] ?? '用户', '此操作仅超级管理员可执行');
            exit;
        }
    }

    private static function render403(string $name, string $msg = '您没有访问后台的权限'): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html lang="zh">
        <head><meta charset="UTF-8"><title>403 禁止访问</title>
        <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f5f5f5;}
        .box{text-align:center;background:#fff;padding:48px 64px;border-radius:12px;box-shadow:0 2px 16px rgba(0,0,0,.08);}
        h1{font-size:48px;color:#e53935;margin:0 0 8px}p{color:#555;margin:4px 0}.back{display:inline-block;margin-top:24px;padding:10px 24px;background:#1a73e8;color:#fff;border-radius:6px;text-decoration:none;}</style>
        </head>
        <body><div class="box">
        <h1>403</h1>
        <p>Hi, {$name}</p>
        <p>{$msg}</p>
        <a class="back" href="/index.php">回到首页</a>
        </div></body></html>
        HTML;
    }
}
