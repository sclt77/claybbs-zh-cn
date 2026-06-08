<?php

declare(strict_types=1);

namespace App\Extension;

use App\Core\ThemeManager;



final class ThemeApi
{
    public const VERSION = '1.0.0';

    private function __construct()
    {
    }

    public static function version(): string
    {
        return self::VERSION;
    }

    public static function active(): string
    {
        return (new ThemeManager())->active();
    }

    public static function view(string $view): string
    {
        return (new ThemeManager())->resolveView($view);
    }

    public static function assetUrl(string $path, ?string $slug = null): string
    {
        $slug = $slug !== null ? self::safeSlug($slug) : self::active();
        if ($slug === '' || $slug === 'default') {
            return '/' . ltrim(str_replace(['\\', '..'], ['/', ''], $path), '/');
        }
        $safePath = ltrim(str_replace(['\\', '..'], ['/', ''], $path), '/');
        return '/themes/' . rawurlencode($slug) . '/' . str_replace('%2F', '/', rawurlencode($safePath));
    }

    public static function cssTag(string $path = 'assets/css/theme.css', ?string $slug = null): string
    {
        $href = self::assetUrl($path, $slug);
        return '<link rel="stylesheet" href="' . self::e($href) . '">' . "\n";
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    private static function safeSlug(string $slug): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $slug) ?? '';
    }
}
