<?php
$path = $_GET['path'] ?? '';
$_GET['path'] = $path === '' ? 'admin' : 'admin/' . ltrim((string)$path, '/');
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
