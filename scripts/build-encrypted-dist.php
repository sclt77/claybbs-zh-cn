<?php

declare(strict_types=1);

$clayguardRoot = '/www/wwwroot/ClayGuard';
require_once $clayguardRoot . '/runtime/ClayGuardCrypto.php';

$clayguardRoot = '/www/wwwroot/ClayGuard';
$source = dirname(__DIR__);
$dist = rtrim($argv[1] ?? '/www/wwwroot/clayG', '/');
$dryRun = in_array('--dry-run', $argv, true);
$root = $clayguardRoot;
$buildId = 'CG-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$releaseVersion = trim((string)(getenv('CLAYBBS_VERSION') ?: ''));
$license = $root . '/examples/clayguard.lic';
$public = $root . '/license/public.json';
$loader = $root . '/loaders/linux-x86_64/php-8.2/nts/clayguard.so';
$encoder = $root . '/encoder/encode.php';

$encryptedFiles = [
    'app/services/MarketLicenseService.php',
    'app/services/LicenseGuardService.php',
    'app/models/MarketExtensionModel.php',
    'app/controllers/admin/ExtensionController.php',
    'app/controllers/admin/LicenseController.php',
    'app/controllers/admin/UpdateCenterController.php',
    'app/controllers/SoftwareStoreController.php',
    'app/controllers/SoftwareSubmissionController.php',
    'app/controllers/admin/AdminSoftwareController.php',
    'app/models/SoftwareModel.php',
    'app/models/SoftwareVersionModel.php',
    'app/models/SoftwareDownloadModel.php',
    'app/models/SoftwareCategoryModel.php',
    'app/models/SoftwareTypeModel.php',
    'app/models/SoftwareReviewModel.php',
    'app/models/SoftwareRatingModel.php',
    'app/models/SoftwareScreenshotModel.php',
    'app/services/UpdateCenterClient.php',
    'app/services/UpdateCheckService.php',
    'app/services/UpdateInstaller.php',
    'app/middleware/AdminAuth.php',
];

$excludeDirs = [
    'ClayBE', 'ClayJM', '.git', '.well-known', 'storage', 'uploads', 'runtime', 'node_modules', 'vendor', 'scripts', 'tests', 'trash', 'backup',
    // Commercial base package must not bundle installable third-party/developer plugins.
    // Keep app/Extension developer interfaces, but ship plugins/themes as empty user-managed areas.
    'plugins', 'themes',
];
$excludeFiles = [
    '.user.ini',
    'config/database.php',
    'config/database.php.bak',
    'config/update-center.php',
    'install.lock',
    'installed.lock',
];

function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    rmdir($dir);
}

function shouldExclude(string $rel, array $excludeDirs, array $excludeFiles): bool {
    $rel = trim(str_replace('\\', '/', $rel), '/');
    if ($rel === '') return false;
    if (in_array($rel, $excludeFiles, true)) return true;
    foreach ($excludeDirs as $dir) {
        $dir = trim($dir, '/');
        if ($rel === $dir || str_starts_with($rel, $dir . '/')) return true;
    }
    return false;
}

function copyTree(string $source, string $dist, array $excludeDirs, array $excludeFiles): void {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $f) {
        $path = $f->getPathname();
        $rel = ltrim(str_replace('\\', '/', substr($path, strlen($source))), '/');
        if (shouldExclude($rel, $excludeDirs, $excludeFiles)) continue;
        $target = $dist . '/' . $rel;
        if ($f->isDir()) {
            if (!is_dir($target)) mkdir($target, 0755, true);
        } else {
            if (!is_dir(dirname($target))) mkdir(dirname($target), 0755, true);
            copy($path, $target);
        }
    }
}

function replacePhpArrayValueFile(string $file, string $key, string $value): void {
    $content = (string)file_get_contents($file);
    $quotedKey = preg_quote($key, '#');
    $replacement = "'" . $key . "' => '" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
    $patterns = [
        "#'{$quotedKey}'\\s*=>\\s*'[^']*'#",
        '#"' . $quotedKey . '"\\s*=>\\s*"[^"]*"#',
        "#'{$quotedKey}'\\s*=>\\s*\"[^\"]*\"#",
        "#\"{$quotedKey}\"\\s*=>\\s*'[^']*'#",
    ];
    foreach ($patterns as $pattern) {
        $new = preg_replace($pattern, $replacement, $content, 1, $count);
        if ($count > 0 && is_string($new)) {
            file_put_contents($file, $new);
            return;
        }
    }
    $array = @include $file;
    if (is_array($array)) {
        $array[$key] = $value;
        file_put_contents($file, "<?php\n\nreturn " . var_export($array, true) . ";\n");
    }
}

function syncReleaseVersion(string $dist, string $version): void {
    $version = trim($version);
    if ($version === '') return;
    $manifestPath = $dist . '/manifest.json';
    $manifest = [];
    if (is_file($manifestPath)) {
        $decoded = json_decode((string)file_get_contents($manifestPath), true);
        if (is_array($decoded)) $manifest = $decoded;
    }
    $manifest['version'] = $version;
    $manifest['generated_at'] = date(DATE_ATOM);
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

    $appConfig = $dist . '/config/app.php';
    if (is_file($appConfig)) replacePhpArrayValueFile($appConfig, 'version', $version);

    $updateConfig = $dist . '/config/update-center.php';
    if (is_file($updateConfig)) replacePhpArrayValueFile($updateConfig, 'current_version', $version);
}

function classInfo(string $php): array {
    $tokens = token_get_all($php);
    $namespace = '';
    $class = '';
    $abstract = false;
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $t = $tokens[$i];
        if (is_array($t) && $t[0] === T_NAMESPACE) {
            $parts = [];
            for ($j = $i + 1; $j < $count; $j++) {
                $x = $tokens[$j];
                if ($x === ';' || $x === '{') break;
                if (is_array($x) && in_array($x[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) $parts[] = $x[1];
            }
            $namespace = trim(implode('', $parts));
        }
        if (is_array($t) && $t[0] === T_CLASS) {
            for ($k = $i - 1; $k >= 0; $k--) {
                if (is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) continue;
                $abstract = is_array($tokens[$k]) && $tokens[$k][0] === T_ABSTRACT;
                break;
            }
            for ($j = $i + 1; $j < $count; $j++) {
                $x = $tokens[$j];
                if (is_array($x) && $x[0] === T_STRING) { $class = $x[1]; break 2; }
            }
        }
    }
    if ($namespace === '' || $class === '') throw new RuntimeException('class info not found');
    return [$namespace, $class, $abstract];
}

function transformForClay(string $php, string $rel, array $info): string {
    global $source;
    [$namespace, $class, $abstract] = $info;
    $impl = 'ClayGuard' . preg_replace('/[^A-Za-z0-9_]/', '_', $namespace . '_' . $class) . 'Impl';
    $knownParents = ['PluginManager' => 'ExtensionManager', 'ThemeManager' => 'ExtensionManager'];
    $php = preg_replace('/^\s*<\?php\s*/', "<?php\n", $php, 1) ?? $php;
    $php = preg_replace('/declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;\s*/', '', $php, 1) ?? $php;
    $php = preg_replace('/namespace\s+' . preg_quote($namespace, '/') . '\s*;/', '', $php, 1) ?? $php;
    $php = str_replace('__DIR__', "'" . dirname($rel) . "'", $php);
    $useMap = [];
    if (preg_match_all('/^\s*use\s+([^;]+)\s*;\s*$/m', $php, $m)) {
        foreach ($m[1] as $fqcn) {
            $fqcn = trim($fqcn);
            $parts = explode("\\", $fqcn);
            $short = end($parts);
            if ($short !== $class) $useMap[$short] = '\\' . $fqcn;
        }
    }
    $php = preg_replace('/^\s*use\s+[^;]+;\s*$/m', '', $php) ?? $php;
    $php = preg_replace('/\b(abstract\s+class|class)\s+' . preg_quote($class, '/') . '\b/', ($abstract ? 'abstract class ' : 'class ') . $impl, $php, 1) ?? $php;
    if (!empty($knownParents[$class])) {
        $useMap[$knownParents[$class]] = '\\' . $namespace . '\\' . $knownParents[$class];
    }
    $sameDir = dirname($rel);
    foreach (glob($source . '/' . $sameDir . '/*.php') ?: [] as $sibling) {
        $siblingClass = basename($sibling, '.php');
        if ($siblingClass !== $class && preg_match('/(?<![\\\\\w])' . preg_quote($siblingClass, '/') . '(?![\\\\\w])/', $php)) {
            $useMap[$siblingClass] = '\\' . $namespace . '\\' . $siblingClass;
        }
    }
    foreach ($useMap as $short => $fqcn) {
        $php = preg_replace('/(?<![\\\\\w])' . preg_quote($short, '/') . '(?![\\\\\w])/', $fqcn, $php) ?? $php;
    }
    return $php;
}

function stubFor(string $rel, string $clayBase, array $info): string {
    [$namespace, $class] = $info;
    $impl = 'ClayGuard' . preg_replace('/[^A-Za-z0-9_]/', '_', $namespace . '_' . $class) . 'Impl';
    $levels = substr_count(dirname($rel), '/') + 1;
    return "<?php\nnamespace {$namespace};\nif (!extension_loaded('clayguard')) { http_response_code(500); exit('ClayGuard Loader not installed'); }\nif (function_exists('clayguard_verify_manifest') && !clayguard_verify_manifest(dirname(__DIR__, {$levels}))) { http_response_code(500); exit('ClayGuard manifest invalid'); }\nclayguard_require(__DIR__ . '/" . addslashes($clayBase) . "', dirname(__DIR__, {$levels}) . '/storage/license/clayguard.lic');\nif (!class_exists(__NAMESPACE__ . '\\\\{$class}', false)) { class_alias('{$impl}', __NAMESPACE__ . '\\\\{$class}'); }\n";
}


function manifestExcluded(string $rel): bool {
    $rel = trim(str_replace('\\', '/', $rel), '/');
    if ($rel === '' || $rel === 'CLAYGUARD_DIST.json' || $rel === 'CLAYGUARD_MANIFEST.sig') return true;
    $runtimeDirs = ['storage', 'uploads'];
    foreach ($runtimeDirs as $dir) {
        if ($rel === $dir || str_starts_with($rel, $dir . '/')) return true;
    }
    $runtimeFiles = ['.user.ini', 'config/database.php', 'config/database.php.bak', 'config/update-center.php', 'install.lock', 'installed.lock'];
    return in_array($rel, $runtimeFiles, true);
}

function collectManifestFiles(string $dist): array {
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dist, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $rel = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($dist))), '/');
        if (manifestExcluded($rel)) continue;
        $files[$rel] = hash_file('sha256', $f->getPathname());
    }
    ksort($files);
    return $files;
}

if (!is_dir($source)) throw new RuntimeException('source missing: ' . $source);
if (!is_file($loader)) throw new RuntimeException('loader missing: ' . $loader);
if (!is_file($license)) throw new RuntimeException('license missing: ' . $license);
if (!is_file($encoder)) throw new RuntimeException('encoder missing: ' . $encoder);
if ($dryRun) {
    echo "ClayGuard self encoder ready\n";
    echo "Source: {$source}\n";
    echo "Target: {$dist}\n";
    echo "Encrypted files: " . count($encryptedFiles) . "\n";
    foreach ($encryptedFiles as $rel) echo "  - {$rel}\n";
    exit(0);
}

rrmdir($dist);
mkdir($dist, 0755, true);
copyTree($source, $dist, $excludeDirs, $excludeFiles);
syncReleaseVersion($dist, $releaseVersion);

$infos = [];
foreach ($encryptedFiles as $rel) {
    $src = $source . '/' . $rel;
    $dstPhp = $dist . '/' . $rel;
    $dstClay = preg_replace('/\.php$/', '.clay', $dstPhp);
    if (!is_file($src)) throw new RuntimeException('encrypt source missing: ' . $src);
    $info = classInfo((string)file_get_contents($src));
    $infos[$rel] = $info;
    $tmp = sys_get_temp_dir() . '/clayguard_transform_' . md5($rel) . '.php';
    file_put_contents($tmp, transformForClay((string)file_get_contents($src), $rel, $info));
    passthru(sprintf('php %s --in=%s --out=%s --source=%s --license=%s --build-id=%s', escapeshellarg($encoder), escapeshellarg($tmp), escapeshellarg($dstClay), escapeshellarg($rel), escapeshellarg($license), escapeshellarg($buildId)), $code);
    @unlink($tmp);
    if ($code !== 0) throw new RuntimeException('encode failed: ' . $rel);
    file_put_contents($dstPhp, stubFor($rel, basename((string)$dstClay), $info));
}

// Plugin/theme contents are not part of the commercial base package. These
// directories remain as empty user-managed development/installation areas.
@mkdir($dist . '/plugins', 0755, true);
@mkdir($dist . '/themes', 0755, true);
file_put_contents($dist . '/plugins/.gitkeep', "");
file_put_contents($dist . '/themes/.gitkeep', "");

@mkdir($dist . '/loaders/linux-x86_64/php-8.2/nts', 0755, true);
copy($loader, $dist . '/loaders/linux-x86_64/php-8.2/nts/clayguard.so');
@mkdir($dist . '/tools', 0755, true);
copy($root . '/tools/clayguard-check.php', $dist . '/tools/clayguard-check.php');
copy($root . '/tools/verify-manifest.php', $dist . '/tools/verify-manifest.php');
copy($root . '/tools/bt-install-loader.sh', $dist . '/tools/bt-install-loader.sh');
copy($root . '/tools/install-clayguard-loader.sh', $dist . '/tools/install-clayguard-loader.sh');
@chmod($dist . '/tools/bt-install-loader.sh', 0755);
@chmod($dist . '/tools/install-clayguard-loader.sh', 0755);
@mkdir($dist . '/license', 0755, true);
copy($public, $dist . '/license/public.json');
@mkdir($dist . '/storage/license', 0755, true);
// 正式分发包不内置运行授权；安装时由官方授权接口按用户域名写入 storage/license/clayguard.lic。

$guardTest = <<<'PHP'
<?php
spl_autoload_register(function($class) {
    $prefixes = [
        'App\\Core\\' => '/app/core/',
        'App\\Services\\' => '/app/services/',
        'App\\Models\\' => '/app/models/',
        'App\\Controllers\\Admin\\' => '/app/controllers/admin/',
        'App\\Controllers\\Web\\' => '/app/controllers/web/',
        'App\\Controllers\\' => '/app/controllers/',
        'App\\Middleware\\' => '/app/middleware/',
    ];
    foreach ($prefixes as $prefix => $dir) {
        if (strncmp($class, $prefix, strlen($prefix)) === 0) {
            $short = substr($class, strlen($prefix));
            $file = __DIR__ . $dir . str_replace('\\', '/', $short) . '.php';
            if (is_file($file)) require_once $file;
            return;
        }
    }
});
require_once __DIR__ . '/app/services/MarketLicenseService.php';
require_once __DIR__ . '/app/services/LicenseGuardService.php';
require_once __DIR__ . '/app/models/MarketExtensionModel.php';
require_once __DIR__ . '/app/core/ExtensionManager.php';
require_once __DIR__ . '/app/core/PluginManager.php';
require_once __DIR__ . '/app/core/ThemeManager.php';
require_once __DIR__ . '/app/controllers/admin/ExtensionController.php';
require_once __DIR__ . '/app/controllers/admin/LicenseController.php';
require_once __DIR__ . '/app/controllers/admin/UpdateCenterController.php';
require_once __DIR__ . '/app/controllers/SoftwareStoreController.php';
require_once __DIR__ . '/app/controllers/SoftwareSubmissionController.php';
require_once __DIR__ . '/app/controllers/admin/AdminSoftwareController.php';
require_once __DIR__ . '/app/models/SoftwareModel.php';
require_once __DIR__ . '/app/models/SoftwareVersionModel.php';
require_once __DIR__ . '/app/models/SoftwareDownloadModel.php';
require_once __DIR__ . '/app/models/SoftwareCategoryModel.php';
require_once __DIR__ . '/app/models/SoftwareTypeModel.php';
require_once __DIR__ . '/app/models/SoftwareReviewModel.php';
require_once __DIR__ . '/app/models/SoftwareRatingModel.php';
require_once __DIR__ . '/app/models/SoftwareScreenshotModel.php';
require_once __DIR__ . '/app/services/UpdateCenterClient.php';
require_once __DIR__ . '/app/services/UpdateCheckService.php';
require_once __DIR__ . '/app/services/UpdateInstaller.php';
require_once __DIR__ . '/app/middleware/AdminAuth.php';
require_once __DIR__ . '/app/controllers/web/InstallController.php';
$checks = [
    'MarketLicenseService_loaded' => class_exists('App\Services\MarketLicenseService'),
    'LicenseGuardService_loaded' => class_exists('App\Services\LicenseGuardService'),
    'LicenseVerifier_loaded' => class_exists('App\Services\LicenseVerifier'),
    'MarketExtensionModel_loaded' => class_exists('App\Models\MarketExtensionModel'),
    'ExtensionManager_loaded' => class_exists('App\Core\ExtensionManager'),
    'PluginManager_loaded' => class_exists('App\Core\PluginManager'),
    'ThemeManager_loaded' => class_exists('App\Core\ThemeManager'),
    'ExtensionController_loaded' => class_exists('App\Controllers\Admin\ExtensionController'),
    'LicenseController_loaded' => class_exists('App\Controllers\Admin\LicenseController'),
    'UpdateCenterController_loaded' => class_exists('App\Controllers\Admin\UpdateCenterController'),
    'SoftwareStoreController_loaded' => class_exists('App\Controllers\SoftwareStoreController'),
    'SoftwareSubmissionController_loaded' => class_exists('App\Controllers\SoftwareSubmissionController'),
    'AdminSoftwareController_loaded' => class_exists('App\Controllers\Admin\AdminSoftwareController'),
    'SoftwareModel_loaded' => class_exists('App\Models\SoftwareModel'),
    'SoftwareVersionModel_loaded' => class_exists('App\Models\SoftwareVersionModel'),
    'SoftwareDownloadModel_loaded' => class_exists('App\Models\SoftwareDownloadModel'),
    'SoftwareCategoryModel_loaded' => class_exists('App\Models\SoftwareCategoryModel'),
    'SoftwareTypeModel_loaded' => class_exists('App\Models\SoftwareTypeModel'),
    'SoftwareReviewModel_loaded' => class_exists('App\Models\SoftwareReviewModel'),
    'SoftwareRatingModel_loaded' => class_exists('App\Models\SoftwareRatingModel'),
    'SoftwareScreenshotModel_loaded' => class_exists('App\Models\SoftwareScreenshotModel'),
    'UpdateCenterClient_loaded' => class_exists('App\Services\UpdateCenterClient'),
    'UpdateCheckService_loaded' => class_exists('App\Services\UpdateCheckService'),
    'UpdateInstaller_loaded' => class_exists('App\Services\UpdateInstaller'),
    'AdminAuth_loaded' => class_exists('App\Middleware\AdminAuth'),
    'InstallController_loaded' => class_exists('App\Controllers\Web\InstallController'),
    'MarketLicenseService_valid_method_ok' => method_exists('App\\Services\\MarketLicenseService', 'valid'),
    'PluginManager_extends_ok' => is_subclass_of('App\\Core\\PluginManager', 'App\\Core\\ExtensionManager'),
    'ThemeManager_extends_ok' => is_subclass_of('App\\Core\\ThemeManager', 'App\\Core\\ExtensionManager'),
];
foreach ($checks as $name => $ok) echo $name . '=' . ($ok ? 'yes' : 'no') . "\n";
PHP;
file_put_contents($dist . '/clayguard-smoke.php', $guardTest);

$manifest = [
    'name' => 'ClayBBS OVO ClayGuard encrypted distribution',
    'source' => $source,
    'build_id' => $buildId,
    'created_at' => date(DATE_ATOM),
    'encrypted_files' => $encryptedFiles,
    'excluded_dirs' => $excludeDirs,
    'excluded_files' => $excludeFiles,
    'loader' => 'loaders/linux-x86_64/php-8.2/nts/clayguard.so',
    'hardened' => [
        'container_version' => 2,
        'per_file_salt' => true,
        'build_id_kdf' => true,
        'encoder_minify' => true,
        'loader_strip' => true,
        'manifest_sha256' => true,
    ],
    'files' => collectManifestFiles($dist),
];
ksort($manifest['files']);
file_put_contents($dist . '/CLAYGUARD_DIST.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
$manifestHash = hash_file('sha256', $dist . '/CLAYGUARD_DIST.json');
$manifestSig = ClayGuardCrypto::sign(json_decode((string)file_get_contents($dist . '/CLAYGUARD_DIST.json'), true, 512, JSON_THROW_ON_ERROR), $root . '/license/private.json');
file_put_contents($dist . '/CLAYGUARD_MANIFEST.sig', $manifestSig . "\n");
echo "CLAYGUARD_SELF_ENCODER active\n";
echo "DIST_READY {$dist}\n";
echo "MANIFEST_SHA256 {$manifestHash}\n";
echo "MANIFEST_SIG {$manifestSig}\n";
