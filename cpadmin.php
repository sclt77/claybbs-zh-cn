<?php
declare(strict_types=1);
$path = $_GET['path'] ?? '';
$_GET['path'] = $path === '' ? 'admin' : 'admin/' . ltrim((string)$path, '/');
$_SERVER['SCRIPT_NAME'] = '/admin.php';
require __DIR__ . '/index.php';
