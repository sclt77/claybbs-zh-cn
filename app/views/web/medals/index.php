<?php if(!defined('CLAY_ACCESS')){http_response_code(404);exit;}
$pageTitle='勋章中心';
$hideContainer=$hideBottomNav=false;
$bodyClass='layout-default';
$breadcrumb=[['label'=>'个人中心','url'=>'/index.php?path=me'],['label'=>'勋章中心']];
$siteCfg = $siteCfg ?? (new \App\Models\SettingModel())->getSiteConfig();
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($siteCfg['site_name'] ?? 'ClayBBS') ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="<?= htmlspecialchars($bodyClass) ?>">
<?php require __DIR__.'/../layouts/topbar.php';?>
<?php require __DIR__.'/_content.php'; ?>
<?php require dirname(__DIR__, 2) . '/layouts/theme-toggle.php'; ?><?php require dirname(__DIR__) . '/layouts/bottom-nav.php'; ?></body></html>
