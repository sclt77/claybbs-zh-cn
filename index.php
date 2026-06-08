<?php

declare(strict_types=1);




require_once __DIR__ . '/app/core/bootstrap.php';


$__installPath = __DIR__ . '/install.lock';
$__requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__scriptName = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$__isInstallRequest = in_array($__requestPath, ['/install', '/install.php'], true) || $__scriptName === 'install.php' || in_array(($_GET['path'] ?? ''), ['install', 'install.php'], true);
$__isApiRequest = str_starts_with($__requestPath, '/api') || $__scriptName === 'api.php';
if (!file_exists($__installPath) && !$__isInstallRequest && !$__isApiRequest) {
    header('Location: /install.php');
    exit;
}


use App\Core\Router;

$router = new Router();
require_once __DIR__ . '/routes/web.php';
\App\Core\Hook::fire('web.routes', ['router' => $router]);
require_once __DIR__ . '/routes/admin.php';
\App\Core\Hook::fire('admin.routes', ['router' => $router]);


$__uri = $_SERVER['REQUEST_URI'] ?? '/';
$__script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$__query = [];
$__queryString = parse_url($__uri, PHP_URL_QUERY);
if (is_string($__queryString) && $__queryString !== '') {
    parse_str($__queryString, $__query);
}
$__pathParam = $_GET['path'] ?? ($__query['path'] ?? null);
if ($__script === 'admin.php') {
    $__adminPath = $_GET['path'] ?? ($__query['path'] ?? '');
    $__adminPath = is_string($__adminPath) && $__adminPath !== '' ? '/' . ltrim($__adminPath, '/') : '';
    $__qs = array_merge($__query, $_GET);
    unset($__qs['path']);
    $__uri = '/admin' . $__adminPath . ($__qs ? '?' . http_build_query($__qs) : '');
} elseif (is_string($__pathParam) && $__pathParam !== '') {
    $__path = '/' . ltrim($__pathParam, '/');
    $__qs = array_merge($__query, $_GET);
    unset($__qs['path']);
    $__uri = $__path . ($__qs ? '?' . http_build_query($__qs) : '');
} elseif ($__script === 'install.php') {
    $__uri = '/install' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
} elseif ($__script === 'api.php') {
    $__apiPath = $_GET['path'] ?? ($__query['path'] ?? '');
    $__apiPath = is_string($__apiPath) && $__apiPath !== '' ? '/' . ltrim($__apiPath, '/') : '';
    $__qs = array_merge($__query, $_GET);
    unset($__qs['path']);
    $__uri = '/api' . $__apiPath . ($__qs ? '?' . http_build_query($__qs) : '');
} elseif ($__script === 'index.php') {
    $__requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $__uri = ($__requestPath === '/index.php' ? '/' : $__requestPath) . ($__query ? '?' . http_build_query($__query) : '');
}

$__writePaths = [
    'section/follow','thread/edit','thread/manage','post/delete','thread/favorite','thread/later','thread/reward','thread/accept-answer','thread/close-bounty','thread/unlock-paid','content/like','content/report','report','user/block','publish','draft/save','draft/delete','draft/batch-delete','draft/discard-autosave','upload/image','user/follow','payment/create','payment/redeem','tasks/claim','tasks/submit','growth/checkin','growth/claim','growth/submit','verification/apply','verification/revoke','settings/devices','settings/privacy','notification-settings','me/edit','medals','avatar-frames','decoration','me/avatar-frames','bubbles','me/bubbles','me/medals','api/app/publish','api/app/upload-image','messages/clear-history','api/private-chat/send','api/private-chat/send-image','api/private-chat/moments','api/private-chat/moment-cover','api/private-chat/revoke','api/private-chat/clear-revoked-content','api/private-chat/follow','api/private-chat/hide','api/private-chat/pin','api/private-chat/mute','api/private-chat/report','api/group-chat/create','api/group-chat/send','api/group-chat/send-image','api/group-chat/update','api/group-chat/upload-avatar','api/group-chat/member-settings','api/group-chat/clear-history','api/group-chat/leave','api/group-chat/member-action','api/group-chat/revoke','api/group-chat/clear-revoked-content','api/group-chat/invite','api/group-chat/invite-handle','api/group-chat/join','api/group-chat/join-mode','api/group-chat/review-join','api/group-chat/report','software/rate','software/review','software/submission/create','software/submission/version','software/submission/update'
];
$__guardPath = trim((string)($__pathParam ?? ''), '/');
if ($__guardPath === '') {
    $__guardPath = trim((string)parse_url($__uri, PHP_URL_PATH), '/');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $__guardPath !== '' && !str_starts_with($__guardPath, 'admin/') && in_array($__guardPath, $__writePaths, true)) {
    (new \App\Services\LicenseGuardService())->guardFrontendWrite(str_starts_with($__guardPath, 'api/'));
}

$router->dispatch($__uri, $_SERVER['REQUEST_METHOD'] ?? 'GET');
