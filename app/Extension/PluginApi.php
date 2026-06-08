<?php

declare(strict_types=1);

namespace App\Extension;

use App\Core\Database;
use App\Core\Hook;
use App\Core\Router;
use App\Models\SettingModel;



final class PluginApi
{
    
    public const VERSION = '1.0.0';

    private function __construct()
    {
    }

    

    

    public static function version(): string
    {
        return self::VERSION;
    }

    

    public static function siteUrl(): string
    {
        try {
            $url = trim((string)(new SettingModel())->get('site_url', ''));
            if ($url !== '') return rtrim($url, '/');
        } catch (\Throwable $e) {}
        try {
            $cfg = require dirname(__DIR__, 2) . '/config/app.php';
            $url = trim((string)($cfg['url'] ?? ''));
            if ($url !== '') return rtrim($url, '/');
        } catch (\Throwable $e) {}
        return 'http://localhost';
    }

    

    public static function assetUrl(string $slug, string $path): string
    {
        $safeSlug = self::safeSlug($slug);
        $safePath = ltrim(str_replace(['\\', '..'], ['/', ''], $path), '/');
        return '/plugins/' . rawurlencode($safeSlug) . '/' . str_replace('%2F', '/', rawurlencode($safePath));
    }

    

    

    public static function listen(string $hook, callable $callback, int $priority = 10): void
    {
        Hook::listen($hook, $callback, $priority);
    }

    

    public static function fire(string $hook, array $payload = []): array
    {
        return Hook::fire($hook, $payload);
    }

    

    public static function filter(string $hook, mixed $value, array $context = []): mixed
    {
        return Hook::filter($hook, $value, $context);
    }

    

    

    public static function route(string $method, string $path, callable|array $handler, ?Router $router = null): void
    {
        $router = $router ?: self::currentRouter();
        if (!$router) {
            throw new \RuntimeException('Router is not available in current plugin hook');
        }
        $method = strtoupper($method);
        if ($method === 'GET') {
            $router->get($path, $handler);
            return;
        }
        if ($method === 'POST') {
            $router->post($path, $handler);
            return;
        }
        throw new \InvalidArgumentException('Unsupported route method: ' . $method);
    }

    

    public static function get(string $path, callable|array $handler, ?Router $router = null): void
    {
        self::route('GET', $path, $handler, $router);
    }

    

    public static function post(string $path, callable|array $handler, ?Router $router = null): void
    {
        self::route('POST', $path, $handler, $router);
    }

    

    

    public static function adminMenu(string $html, string $group = 'plugins', int $priority = 10): void
    {
        $hook = $group === 'system' ? 'admin.menu.system' : 'admin.menu.plugins';
        self::listen($hook, static function (array $payload) use ($html): array {
            $payload['value'] = (string)($payload['value'] ?? '') . $html;
            return $payload;
        }, $priority);
    }

    

    public static function userQuickAction(string $html, int $priority = 10): void
    {
        self::listen('user.center.quick_actions', static function (array $payload) use ($html): array {
            $payload['value'] = (string)($payload['value'] ?? '') . $html;
            return $payload;
        }, $priority);
    }

    

    public static function appendStyles(string $html, int $priority = 10): void
    {
        self::listen('view.styles', static function (array $payload) use ($html): array {
            $payload['value'] = (string)($payload['value'] ?? '') . $html;
            return $payload;
        }, $priority);
    }

    

    

    public static function db(): \PDO
    {
        return Database::connection();
    }

    

    

    public static function setting(string $key, mixed $default = null): mixed
    {
        try {
            $settings = (new SettingModel())->all();
            return array_key_exists($key, $settings) ? $settings[$key] : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    

    public static function setSetting(string $key, mixed $value): void
    {
        (new SettingModel())->set($key, is_scalar($value) || $value === null ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE));
    }

    

    public static function pluginSetting(string $slug, string $key, mixed $default = null): mixed
    {
        $safeSlug = self::safeSlug($slug);
        $safeKey = preg_replace('/[^a-zA-Z0-9_.\-]/', '', $key) ?? '';
        if ($safeSlug === '' || $safeKey === '') return $default;
        return self::setting('plugin.' . $safeSlug . '.' . $safeKey, $default);
    }

    

    public static function setPluginSetting(string $slug, string $key, mixed $value): void
    {
        $safeSlug = self::safeSlug($slug);
        $safeKey = preg_replace('/[^a-zA-Z0-9_.\-]/', '', $key) ?? '';
        if ($safeSlug === '' || $safeKey === '') {
            throw new \InvalidArgumentException('Invalid plugin setting key');
        }
        self::setSetting('plugin.' . $safeSlug . '.' . $safeKey, $value);
    }

    

    

    public static function csrfField(): string
    {
        return function_exists('csrf_field') ? csrf_field() : '';
    }

    

    public static function csrfVerify(): void
    {
        if (function_exists('csrf_verify')) csrf_verify();
    }

    

    

    public static function currentUser(): ?array
    {
        return function_exists('auth_user') ? auth_user() : null;
    }

    

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    

    private static function currentRouter(): ?Router
    {
        $payload = Hook::currentPayload();
        $router = $payload['router'] ?? null;
        return $router instanceof Router ? $router : null;
    }

    private static function safeSlug(string $slug): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $slug) ?? '';
    }
}
