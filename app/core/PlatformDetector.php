<?php

declare(strict_types=1);

namespace App\Core;



final class PlatformDetector
{
    
    public const ANDROID = 'android';
    public const IOS     = 'ios';
    public const WINDOWS = 'windows';
    public const MACOS   = 'macos';
    public const UNKNOWN = 'unknown';

    
    public const LABELS = [
        self::ANDROID => '安卓',
        self::IOS     => 'iOS',
        self::WINDOWS => 'Windows',
        self::MACOS   => 'macOS',
        self::UNKNOWN => '全部',
    ];

    

    public static function detect(): string
    {
        $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

        if (self::isIOS($ua)) return self::IOS;
        if (self::isAndroid($ua)) return self::ANDROID;
        if (self::isWindows($ua)) return self::WINDOWS;
        if (self::isMacOS($ua)) return self::MACOS;

        return self::UNKNOWN;
    }

    public static function isIOS(string $ua): bool
    {
        return str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ipod') || str_contains($ua, 'ios');
    }

    public static function isAndroid(string $ua): bool
    {
        return str_contains($ua, 'android');
    }

    public static function isWindows(string $ua): bool
    {
        return str_contains($ua, 'windows') || str_contains($ua, 'win32') || str_contains($ua, 'win64');
    }

    public static function isMacOS(string $ua): bool
    {
        return str_contains($ua, 'macintosh') || str_contains($ua, 'mac os x');
    }

    

    public static function label(string $platform): string
    {
        return self::LABELS[$platform] ?? self::LABELS[self::UNKNOWN];
    }

    

    public static function all(): array
    {
        return [
            self::ANDROID => self::LABELS[self::ANDROID],
            self::IOS     => self::LABELS[self::IOS],
            self::WINDOWS => self::LABELS[self::WINDOWS],
            self::MACOS   => self::LABELS[self::MACOS],
        ];
    }
}
