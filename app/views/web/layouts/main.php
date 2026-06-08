<?php



if (!isset($site) || !is_array($site)) {
    try {
        $site = (new \App\Models\SettingModel())->getSiteConfig();
    } catch (\Throwable $e) {
        $site = ['site_name' => 'ClayBBS'];
    }
}

$_pageTitle = $__pageTitle ?? ($site['site_name'] ?? 'ClayBBS');
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($_pageTitle) ?> - <?= htmlspecialchars($site['site_name'] ?? 'ClayBBS') ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
<?php
$__pluginStyles = \App\Core\Hook::filter('view.styles', '');
if ($__pluginStyles !== '') {
    echo $__pluginStyles . "\n";
}
?>
<?php require __DIR__ . '/theme-init.php'; ?>
</head>
<body>
<?php require __DIR__ . '/topbar.php'; ?>