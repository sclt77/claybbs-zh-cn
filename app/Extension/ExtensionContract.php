<?php

declare(strict_types=1);

namespace App\Extension;



final class ExtensionContract
{
    public const API_VERSION = '1.0.0';
    public const MIN_CORE_VERSION = '1.0.0';

    

    public const ENCRYPTION_EXCLUDE = [
        'app/Extension',
        'app/core/Hook.php',
        'app/core/Router.php',
        'app/core/PluginManager.php',
        'app/core/ThemeManager.php',
        'app/helpers/theme.php',
        'plugins',
        'themes',
        'config',
        'database',
        'public',
        'assets',
        'storage/.htaccess',
    ];

    private function __construct()
    {
    }
}
