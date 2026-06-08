<?php
if (isset($_GET['route']) && !isset($_GET['path'])) {
    $map = [
        'users.search' => 'api/users/search',
        'messages.unread' => 'api/messages/unread',
        'messages.list' => 'api/messages',
        'messages.read' => 'api/messages/read',
    ];
    $_GET['path'] = $map[$_GET['route']] ?? ('api/' . str_replace('.', '/', trim((string)$_GET['route'], '/')));
} else {
    $_GET['path'] = 'api/' . ltrim((string)($_GET['path'] ?? ''), '/');
}
$_SERVER['SCRIPT_NAME'] = '/api.php';
require __DIR__ . '/index.php';
