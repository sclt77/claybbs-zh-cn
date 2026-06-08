<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Shanghai');

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR;

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);

    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
        return;
    }

    
    $parts = explode('\\', $relativeClass);
    $resolved = $baseDir;

    foreach ($parts as $index => $part) {
        $isLast = $index === count($parts) - 1;
        $targetName = $isLast ? $part . '.php' : $part;

        if (!is_dir($resolved)) {
            return;
        }

        $matched = null;
        foreach (scandir($resolved) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (strcasecmp($entry, $targetName) === 0) {
                $matched = $entry;
                break;
            }
        }

        if ($matched === null) {
            return;
        }

        $resolved .= $matched;
        if (!$isLast) {
            $resolved .= DIRECTORY_SEPARATOR;
        }
    }

    if (is_file($resolved)) {
        require $resolved;
    }
});

if (!function_exists('app_is_https')) {
    function app_is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $forwardedSsl = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
        return $forwardedProto === 'https' || $forwardedSsl === 'on';
    }
}

if (session_status() === PHP_SESSION_NONE) {
    $secure = app_is_https();
    $params = session_get_cookie_params();
    session_name('FORUMSESSID');
    session_set_cookie_params([
        'lifetime' => 86400 * 30,
        'path' => $params['path'] ?: '/',
        'domain' => $params['domain'] ?: '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', (string) (86400 * 30));
    session_start();
}

define('CLAY_ACCESS', true);

require_once dirname(__DIR__) . '/helpers/auth.php';
require_once dirname(__DIR__) . '/helpers/csrf.php';
require_once dirname(__DIR__) . '/helpers/ajax.php';
require_once dirname(__DIR__) . '/helpers/theme.php';
require_once dirname(__DIR__) . '/helpers/html.php';
require_once dirname(__DIR__) . '/helpers/upload.php';
require_once dirname(__DIR__) . '/helpers/avatar.php';
require_once dirname(__DIR__) . '/helpers/medals.php';
require_once dirname(__DIR__) . '/helpers/currency.php';
require_once dirname(__DIR__) . '/helpers/editor.php';
require_once dirname(__DIR__) . '/helpers/growth.php';
require_once dirname(__DIR__) . '/helpers/thread_card.php';

try {
    \App\Services\MedalService::bootHooks();
    \App\Services\AvatarFrameService::bootHooks();
    \App\Services\BubbleService::bootHooks();
    \App\Services\NameplateService::bootHooks();
} catch (\Throwable $e) {
    
}

$__rootPath = dirname(__DIR__, 2);
$__requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__scriptName = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$__isInstallRequest = in_array($__requestPath, ['/install', '/install.php'], true)
    || $__scriptName === 'install.php'
    || in_array(($_GET['path'] ?? ''), ['install', 'install.php'], true);
$__hasDatabaseConfig = is_file($__rootPath . '/config/database.php');

if (!$__isInstallRequest && $__hasDatabaseConfig) {
    try {
        if (!empty($_SESSION['auth_user']['id'])) {
            $deviceModel = new \App\Models\LoginDeviceModel();
            $userId = (int)$_SESSION['auth_user']['id'];
            $currentUser = (new \App\Models\UserModel())->find($userId);
            $isBanned = !$currentUser
                || (($currentUser['status'] ?? 'active') !== 'active')
                || (!empty($currentUser['banned_until']) && strtotime((string)$currentUser['banned_until']) > time());
            if ($isBanned || $deviceModel->currentRevoked($userId)) {
                auth_logout();
                if (!headers_sent()) {
                    header('Location: /index.php?path=login&device=' . ($isBanned ? 'banned' : 'revoked'));
                    exit;
                }
            } else {
                $deviceModel->touchCurrent($userId);
                try { (new \App\Services\UserDailyRefreshService())->touch($userId, 'web_boot', true); } catch (\Throwable $e) { error_log('[ClayBBS] UserDailyRefreshService touch failed: ' . $e->getMessage()); }
            }
        }
    } catch (\Throwable $e) {}
    try {
        
        if (!isset($GLOBALS['_clay_plugin_manager'])) {
            $GLOBALS['_clay_plugin_manager'] = new \App\Core\PluginManager();
        }
        $GLOBALS['_clay_plugin_manager']->boot();
        \App\Core\Hook::fire('app.booted');
    } catch (\Throwable $e) {
        
    }
}
