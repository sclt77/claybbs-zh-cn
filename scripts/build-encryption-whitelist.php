#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$out = $argv[1] ?? ($root . '/storage/build/encryption-whitelist.json');
$exclude = [
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
    'docs',
];

$data = [
    'generated_at' => date('c'),
    'project_root' => $root,
    'purpose' => 'ClayBBS 商业发行加密排除白名单；这些路径应保持明文或兼容可调用，避免插件/主题生态失效。',
    'exclude_from_encryption' => $exclude,
];

$dir = dirname($out);
if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    fwrite(STDERR, "创建目录失败: {$dir}\n");
    exit(1);
}

file_put_contents($out, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
echo $out . PHP_EOL;
