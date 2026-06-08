<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Core\Database;
use App\Services\LicenseVerifier;
use App\Services\ReportQueue;
use App\Services\UpdateCenterClient;
use App\Services\UpdateCheckService;
use App\Services\UpdateInstaller;

class UpdateCenterController
{
    public function __construct()
    {
        AdminAuth::check();
        Permission::require('admin.update_center');
    }

    public function index(): void
    {
        $config = $this->loadConfig();
        $message = '';
        $error = '';
        $licenseLocalOk = $this->verifyLocalLicense($config);
        $lastCheck = $_SESSION['update_last_check'] ?? null;
        $snapshots = $this->listSnapshots();
        $reportQueue = new ReportQueue();
        $queuedReports = $reportQueue->all();
        $databaseRepairPreview = $this->databaseRepairPreview();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $action = trim((string) ($_POST['_action'] ?? ''));

            if ($action === 'save_config') {
                try {
                    $config = $this->syncOfficialLicense($_POST);
                    $message = '已从官方站同步授权信息';
                } catch (\Throwable $e) {
                    $error = '同步授权失败：' . $e->getMessage();
                }
            }

            if ($action === 'check') {
                try {
                    $client = new UpdateCenterClient($config);
                    $licenseKey = trim((string) ($config['license_key'] ?? ''));
                    $domain = trim((string) ($config['domain'] ?? ''));
                    if ($licenseKey === '' || $domain === '') {
                        throw new \RuntimeException('请先完成正版授权绑定');
                    }
                    $verify = $client->verifyLicense($licenseKey, $domain);
                    if (empty($verify['ok'])) {
                        throw new \RuntimeException('正版授权校验失败：' . (string) ($verify['error'] ?? 'unknown'));
                    }
                    if (!empty($verify['payload']['site_id'])) {
                        $config['site_id'] = (string) $verify['payload']['site_id'];
                    }
                    if (!empty($verify['payload']['token'])) {
                        $config['token'] = (string) $verify['payload']['token'];
                    }
                    $this->saveConfig($config);
                    $res = $client->checkUpdate(
                        $config['current_version'] ?? '0.0.0',
                        $config['branch'] ?? 'main',
                        $config['domain'] ?? '',
                        $config['owner'] ?? ''
                    );
                    (new UpdateCheckService())->checkNow();
                    $lastCheck = $res;
                    $_SESSION['update_last_check'] = $res;
                    $this->flushReportQueue($client);
                    if (!empty($res['update'])) {
                        $message = '已获取官方更新信息';
                    } else {
                        $message = '当前已是最新版本';
                    }
                } catch (\Throwable $e) {
                    $error = '检查更新失败：' . $e->getMessage();
                }
            }

            if ($action === 'retry_reports') {
                try {
                    $client = new UpdateCenterClient($config);
                    $licenseKey = trim((string) ($config['license_key'] ?? ''));
                    $domain = trim((string) ($config['domain'] ?? ''));
                    if ($licenseKey === '' || $domain === '') {
                        throw new \RuntimeException('请先完成正版授权绑定');
                    }
                    $verify = $client->verifyLicense($licenseKey, $domain);
                    if (empty($verify['ok'])) {
                        throw new \RuntimeException('正版授权校验失败：' . (string) ($verify['error'] ?? 'unknown'));
                    }
                    if (!empty($verify['payload']['site_id'])) {
                        $config['site_id'] = (string) $verify['payload']['site_id'];
                    }
                    if (!empty($verify['payload']['token'])) {
                        $config['token'] = (string) $verify['payload']['token'];
                    }
                    $this->saveConfig($config);

                    $count = count($queuedReports);
                    $this->flushReportQueue($client);
                    $queuedReports = $reportQueue->all();
                    if (empty($queuedReports)) {
                        $message = $count > 0 ? '补发上报已重试完成，队列已清空' : '当前没有待补发上报';
                    } else {
                        $message = '已尝试补发上报，仍有 ' . count($queuedReports) . ' 条待重试';
                    }
                } catch (\Throwable $e) {
                    $error = '补发上报失败：' . $e->getMessage();
                }
            }

            if ($action === 'repair_database') {
                $confirm = trim((string)($_POST['confirm_text'] ?? ''));
                if ($confirm !== 'REPAIR') {
                    $error = '二次确认未通过，请输入 REPAIR';
                } else {
                    try {
                        $result = $this->repairDatabaseBaseline();
                        $message = '数据库修复完成：' . $result['summary'];
                    } catch (\Throwable $e) {
                        $error = '数据库修复失败：' . $e->getMessage();
                    }
                }
            }

            if ($action === 'apply') {
                $confirm = trim((string) ($_POST['confirm_text'] ?? ''));
                $kind = trim((string) ($_POST['kind'] ?? 'package'));
                $pkgId = (int) ($_POST['package_id'] ?? 0);
                $pkgInfo = $lastCheck['package'] ?? null;

                if (!in_array($kind, ['package', 'full', 'rollback'], true)) {
                    $error = '更新类型无效';
                } elseif ($confirm !== 'UPDATE') {
                    $error = '二次确认未通过，请输入 UPDATE';
                } elseif (!$pkgInfo || $pkgId <= 0) {
                    $error = '没有可应用的官方更新包，请先检查更新';
                } elseif ($pkgId !== (int)($pkgInfo['id'] ?? 0)) {
                    $error = '更新包 ID 与最近检查结果不一致，请重新检查更新';
                } else {
                    try {
                        $client = new UpdateCenterClient($config);
                        $licenseKey = trim((string) ($config['license_key'] ?? ''));
                        $domain = trim((string) ($config['domain'] ?? ''));
                        if ($licenseKey === '' || $domain === '') {
                            throw new \RuntimeException('请先完成正版授权绑定');
                        }
                        $verify = $client->verifyLicense($licenseKey, $domain);
                        if (empty($verify['ok'])) {
                            throw new \RuntimeException('正版授权校验失败：' . (string) ($verify['error'] ?? 'unknown'));
                        }
                        if (!empty($verify['payload']['site_id'])) {
                            $config['site_id'] = (string) $verify['payload']['site_id'];
                        }
                        if (!empty($verify['payload']['token'])) {
                            $config['token'] = (string) $verify['payload']['token'];
                        }
                        $this->saveConfig($config);

                        $installer = new UpdateInstaller();
                        $startedAt = microtime(true);

                        $fileMeta = $pkgInfo['files'][$kind] ?? [];
                        if (empty($fileMeta)) {
                            throw new \RuntimeException('官方未返回该类型更新包元数据');
                        }
                        $checks = $installer->preflight($pkgInfo + ['current_version' => (string)($config['current_version'] ?? '0.0.0')], $kind);
                        if (!$installer->preflightOk($checks)) {
                            throw new \RuntimeException('更新前预检查未通过：' . implode('；', array_map(static fn($c) => $c['name'] . '=' . $c['message'], array_filter($checks, static fn($c) => empty($c['ok'])))));
                        }

                        $downloadName = $kind . '_' . $pkgId . '.zip';
                        $ticket = (string)($fileMeta['ticket'] ?? ($pkgInfo['tickets'][$kind] ?? ($pkgInfo['ticket'] ?? '')));
                        $bin = $client->downloadPackage($pkgId, $kind, $ticket);
                        $file = $installer->savePackage($bin, $downloadName);

                        $expectedHash = (string)($fileMeta['hash'] ?? ($kind === 'package' ? ($pkgInfo['hash'] ?? '') : ''));
                        if (!$installer->verifyHash($file, $expectedHash)) {
                            throw new \RuntimeException('更新包 SHA256 校验失败');
                        }
                        $signature = (string) ($fileMeta['signature'] ?? ($kind === 'package' ? ($pkgInfo['signature'] ?? '') : ''));
                        $publicKey = (string) ($config['public_key'] ?? '');
                        if ($signature === '' || $publicKey === '' || !$installer->verifySignature($file, $signature, $publicKey)) {
                            throw new \RuntimeException('签名校验失败，请确认论坛端公钥与官方中心签名一致');
                        }

                        $snapshot = $installer->createSnapshot([], ['package_id' => $pkgId, 'kind' => $kind, 'from_version' => (string)($config['current_version'] ?? ''), 'to_version' => (string)($pkgInfo['to_version'] ?? '')]);
                        $extractDir = $installer->extract($file);
                        $installer->validateManifest($extractDir, $pkgInfo, $kind, (string)($config['current_version'] ?? '0.0.0'));

                        $fullKey = '';
                        $logArr = $installer->applyUpdate($extractDir, function ($meta) use (&$fullKey) {
                            if (isset($meta['full_key'])) {
                                $fullKey = (string) $meta['full_key'];
                            }
                        });
                        $health = $installer->healthCheck((string)($pkgInfo['to_version'] ?? ''));
                        $log = "kind: {$kind}\nsnapshot: {$snapshot}\npreflight: " . json_encode($checks, JSON_UNESCAPED_UNICODE) . "\nhealth: " . json_encode($health, JSON_UNESCAPED_UNICODE) . "\n" . implode("\n", $logArr);
                        $message = implode("\n", $logArr) . "\n安装后健康检查：" . ($installer->checksOk($health) ? '通过' : '存在异常');
                        $_SESSION['update_last_health'] = $health;
                        if (!$installer->checksOk($health)) {
                            throw new \RuntimeException('安装后健康检查未通过：' . json_encode($health, JSON_UNESCAPED_UNICODE));
                        }

                        $fromVersionForReport = (string)($config['current_version'] ?? '');
                        if (in_array($kind, ['package', 'full'], true) && !empty($pkgInfo['to_version'])) {
                            $config['current_version'] = (string) $pkgInfo['to_version'];
                            $this->saveConfig($config);
                            $this->writeLocalVersion((string)$pkgInfo['to_version']);
                            $config = $this->loadConfig();
                        }

                        try {
                            $client->report($pkgId, 'success', $log, $fullKey !== '' ? 'full_key_used' : '', $fullKey, [
                                'from_version' => (string)($pkgInfo['from_version'] ?? $fromVersionForReport),
                                'to_version' => (string)($pkgInfo['to_version'] ?? ''),
                                'kind' => $kind,
                                'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
                                'health_json' => json_encode($health, JSON_UNESCAPED_UNICODE),
                            ]);
                        } catch (\Throwable $e) {
                            $reportQueue->add([
                                'package_id' => $pkgId,
                                'status' => 'success',
                                'log' => $log,
                                'event' => $fullKey !== '' ? 'full_key_used' : '',
                                'full_key' => $fullKey,
                                'from_version' => (string)($pkgInfo['from_version'] ?? $fromVersionForReport),
                                'to_version' => (string)($pkgInfo['to_version'] ?? ''),
                                'kind' => $kind,
                                'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
                            ]);
                        }

                        
                        if ($kind === 'package' && !empty($pkgInfo['to_version'])) {
                            try {
                                $db = Database::connection();
                                $stmt = $db->prepare("INSERT INTO system_updates (version, description, applied_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE applied_at=NOW()");
                                $stmt->execute([$pkgInfo['to_version'], $log]);
                            } catch (\Throwable $ignored) {}
                        }

                        $_SESSION['update_last_check'] = null;
                        (new UpdateCheckService())->clear();
                        $lastCheck = null;
                        $pkgInfo = null;
                        $pkg = [];
                        $message = $kind === 'rollback' ? '官方回滚已应用' : '官方更新已完成';

                    } catch (\Throwable $e) {
                        $error = '应用官方更新失败：' . $e->getMessage();
                    }
                }
            }

            if ($action === 'rollback') {
                if ((string)($_POST['confirm_text'] ?? '') !== 'ROLLBACK') {
                    $error = '请输入 ROLLBACK 确认回滚';
                } else {
                $snap = trim((string) ($_POST['snapshot_path'] ?? ''));
                if ($snap === '' || !is_dir($snap)) {
                    $error = '快照目录不存在';
                } else {
                    try {
                        $installer = new UpdateInstaller();
                        $installer->rollbackFromSnapshot($snap);
                        $message = '已从本地快照回滚';
                    } catch (\Throwable $e) {
                        $error = '本地快照回滚失败：' . $e->getMessage();
                    }
                }
                }
            }

            if ($action === 'rollback_pkg') {
                if ((string)($_POST['confirm_text'] ?? '') !== 'ROLLBACK') {
                    $error = '请输入 ROLLBACK 确认回滚';
                } else {
                try {
                    $installer = new UpdateInstaller();
                    $installer->rollbackFromPackage();
                    $message = '已从最近一次官方回滚包回滚';
                } catch (\Throwable $e) {
                    $error = '官方回滚包回滚失败：' . $e->getMessage();
                }
                }
            }

            $snapshots = $this->listSnapshots();
            $queuedReports = $reportQueue->all();
            $databaseRepairPreview = $this->databaseRepairPreview();
        }

        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $lastCheck === null) {
            try {
                $status = (new UpdateCheckService())->checkIfStale();
                if (!empty($status['raw']) && is_array($status['raw'])) {
                    $_SESSION['update_last_check'] = $status['raw'];
                    $lastCheck = $status['raw'];
                }
            } catch (\Throwable $e) {
                
            }
        }

        

        require dirname(__DIR__, 2) . '/views/admin/update_center/index.php';
    }

    private function flushReportQueue(UpdateCenterClient $client): void
    {
        try {
            $queue = new ReportQueue();
            $items = $queue->all();
            if (!$items) {
                return;
            }
            foreach ($items as $it) {
                $client->report(
                    (int) ($it['package_id'] ?? 0),
                    (string) ($it['status'] ?? 'success'),
                    (string) ($it['log'] ?? ''),
                    (string) ($it['event'] ?? ''),
                    (string) ($it['full_key'] ?? '')
                );
            }
            $queue->clear();
        } catch (\Throwable $e) {
            
        }
    }

    private function listSnapshots(): array
    {
        $dir = dirname(__DIR__, 3) . '/storage/backups';
        if (!is_dir($dir)) {
            return [];
        }
        $items = glob($dir . '/*', GLOB_ONLYDIR) ?: [];
        $items = array_values(array_filter($items, static function (string $path): bool {
            return is_file(rtrim($path, '/\\') . '/snapshot.json');
        }));
        rsort($items);
        return array_map(static function (string $path): array {
            $meta = json_decode((string)@file_get_contents(rtrim($path, '/\\') . '/snapshot.json'), true);
            if (!is_array($meta)) $meta = [];
            return [
                'path' => $path,
                'label' => basename($path) . (!empty($meta['from_version']) || !empty($meta['to_version']) ? (' · ' . (string)($meta['from_version'] ?? '') . ' → ' . (string)($meta['to_version'] ?? '')) : ''),
                'kind' => (string)($meta['kind'] ?? ''),
                'created_at' => (string)($meta['created_at'] ?? ''),
            ];
        }, $items);
    }

    private function loadConfig(): array
    {
        $path = dirname(__DIR__, 3) . '/config/update-center.php';
        if (!file_exists($path)) {
            return [
                'url' => 'https://www.claybbs.com',
                'site_id' => '',
                'token' => '',
                'project_id' => 0,
                'branch' => 'main',
                'current_version' => '0.0.0',
                'public_key' => '',
                'domain' => '',
                'owner' => '',
            ];
        }
        $cfg = include $path;
        if (!is_array($cfg)) {
            $cfg = [];
        }
        $detected = $this->detectInstalledVersion($cfg);
        if ($detected !== '' && (($cfg['current_version'] ?? '') === '' || ($cfg['current_version'] ?? '') === '0.0.0')) {
            $cfg['current_version'] = $detected;
            $this->saveConfig($cfg);
        }
        return $cfg;
    }

    private function detectInstalledVersion(array $cfg = []): string
    {
        $root = dirname(__DIR__, 3);
        $manifest = $root . '/manifest.json';
        if (is_file($manifest)) {
            $data = json_decode((string)file_get_contents($manifest), true);
            if (is_array($data)) {
                $version = trim((string)($data['version'] ?? ''));
                if ($version !== '' && $version !== '0.0.0') {
                    return $version;
                }
            }
        }
        $appPath = $root . '/config/app.php';
        if (is_file($appPath)) {
            $app = @include $appPath;
            if (is_array($app)) {
                $version = trim((string)($app['version'] ?? ''));
                if ($version !== '' && $version !== '0.0.0') {
                    return $version;
                }
            }
        }
        $current = trim((string)($cfg['current_version'] ?? ''));
        return $current !== '0.0.0' ? $current : '';
    }


    private function syncOfficialLicense(array $post): array
    {
        $old = $this->loadConfig();
        $licenseKey = trim((string)($post['license_key'] ?? ($old['license_key'] ?? '')));
        $domain = trim((string)($post['domain'] ?? ($old['domain'] ?? '')));
        if ($licenseKey === '' || $domain === '') {
            throw new \RuntimeException('请填写授权码和授权域名');
        }
        $base = trim((string)($old['url'] ?? 'https://www.claybbs.com')) ?: 'https://www.claybbs.com';
        $client = new UpdateCenterClient($old + ['url' => $base]);
        $res = $client->verifyLicense($licenseKey, $domain);
        if (empty($res['ok']) || empty($res['payload'])) {
            throw new \RuntimeException((string)($res['error'] ?? '官方授权校验失败'));
        }
        $payload = $res['payload'];
        $cfg = $old;
        $cfg['url'] = $base;
        $cfg['license_key'] = (string)($payload['license_key'] ?? $licenseKey);
        $cfg['domain'] = (string)($payload['domain'] ?? $domain);
        $cfg['owner'] = (string)($payload['owner'] ?? ($cfg['owner'] ?? ''));
        $cfg['site_id'] = (string)($payload['site_id'] ?? ($cfg['site_id'] ?? ''));
        $payloadToken = trim((string)($payload['token'] ?? ''));
        $cfg['token'] = $payloadToken !== '' ? $payloadToken : (string)($old['token'] ?? '');
        $cfg['project_id'] = (int)($cfg['project_id'] ?? 0);
        $cfg['branch'] = (string)($cfg['branch'] ?? 'main');
        $cfg['current_version'] = $this->detectCurrentVersion($cfg);
        $cfg['public_key'] = (string)($cfg['public_key'] ?? '');
        $cfg['license_data'] = json_encode($res, JSON_UNESCAPED_UNICODE);
        $this->saveConfig($cfg);
        return $cfg;
    }

    private function detectCurrentVersion(array $cfg): string
    {
        $detected = $this->detectInstalledVersion($cfg);
        return $detected !== '' ? $detected : '0.0.0';
    }

    private function saveConfig(array $post): void
    {
        $cfg = [
            'url' => trim((string) ($post['url'] ?? 'https://www.claybbs.com')) ?: 'https://www.claybbs.com',
            'site_id' => trim((string) ($post['site_id'] ?? '')),
            'token' => trim((string) ($post['token'] ?? '')),
            'project_id' => (int) ($post['project_id'] ?? 0),
            'branch' => trim((string) ($post['branch'] ?? 'main')),
            'current_version' => trim((string) ($post['current_version'] ?? '0.0.0')),
            'public_key' => trim((string) ($post['public_key'] ?? '')),
            'domain' => trim((string) ($post['domain'] ?? '')),
            'owner' => trim((string) ($post['owner'] ?? '')),
            'license_key' => trim((string) ($post['license_key'] ?? '')),
            'license_data' => trim((string) ($post['license_data'] ?? '')),
            'license_guard_enabled' => (string)($post['license_guard_enabled'] ?? '1'),
        ];
        $content = "<?php\n\nreturn " . var_export($cfg, true) . ";\n";
        file_put_contents(dirname(__DIR__, 3) . '/config/update-center.php', $content);
    }


    private function writeLocalVersion(string $version): void
    {
        $version = trim($version);
        if ($version === '') {
            return;
        }
        $root = dirname(__DIR__, 3);

        $manifestPath = $root . '/manifest.json';
        $manifest = [];
        if (is_file($manifestPath)) {
            $decoded = json_decode((string)file_get_contents($manifestPath), true);
            if (is_array($decoded)) {
                $manifest = $decoded;
            }
        }
        $manifest['version'] = $version;
        @file_put_contents($manifestPath, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");

        $appPath = $root . '/config/app.php';
        if (is_file($appPath)) {
            $app = @include $appPath;
            if (is_array($app)) {
                $app['version'] = $version;
                @file_put_contents($appPath, "<?php\n\nreturn " . var_export($app, true) . ";\n");
            }
        }
    }

    private function databaseRepairPreview(): array
    {
        $items = [];
        try {
            $db = Database::connection();
            $exists = $db->query("SHOW TABLES LIKE 'currencies'")->fetchColumn();
            if (!$exists) {
                return [['level'=>'warn', 'title'=>'货币表不存在', 'desc'=>'当前库未检测到 currencies 表，请先完成安装或官方更新。']];
            }
            $baseline = $this->currencyBaseline();
            foreach ($baseline as $code => $base) {
                $stmt = $db->prepare("SELECT code,name,exchange_rate,`precision`,status,sort_order FROM currencies WHERE code=:code LIMIT 1");
                $stmt->execute([':code'=>$code]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (!$row) {
                    $items[] = ['level'=>'muted', 'title'=>$code . ' 已删除/不存在', 'desc'=>'不会自动重建用户已删除的默认货币。'];
                    continue;
                }
                $changes = [];
                foreach (['name','exchange_rate','precision','status','sort_order'] as $field) {
                    $old = (string)($row[$field] ?? '');
                    $new = (string)$base[$field];
                    if ($field === 'exchange_rate' && abs((float)$old - (float)$new) < 0.000001) continue;
                    if ($field === 'precision' || $field === 'sort_order') { if ((int)$old === (int)$new) continue; }
                    elseif ($old === $new) continue;
                    $changes[] = $field . ': ' . $old . ' → ' . $new;
                }
                $items[] = $changes
                    ? ['level'=>'fix', 'title'=>$code . ' 需要修复', 'desc'=>implode('；', $changes)]
                    : ['level'=>'ok', 'title'=>$code . ' 正常', 'desc'=>'默认货币基线一致。'];
            }
            foreach (['GOLD'=>'COIN','SILVER'=>'COIN_1','COPPER'=>'COIN_2'] as $old => $new) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM currencies WHERE code=:code");
                $stmt->execute([':code'=>$old]);
                if ((int)$stmt->fetchColumn() > 0) {
                    $items[] = ['level'=>'warn', 'title'=>'检测到旧货币 ' . $old, 'desc'=>'修复工具会将旧默认货币标记为停用；不会合并或删除用户已删除的数据。'];
                }
            }
        } catch (\Throwable $e) {
            $items[] = ['level'=>'warn', 'title'=>'预检查失败', 'desc'=>$e->getMessage()];
        }
        return $items;
    }

    private function repairDatabaseBaseline(): array
    {
        $db = Database::connection();
        $summary = [];
        $baseline = $this->currencyBaseline();
        $updated = 0;
        $skipped = 0;
        $stmt = $db->prepare("UPDATE currencies SET name=:name, exchange_rate=:exchange_rate, `precision`=:precision, status=:status, sort_order=:sort_order WHERE code=:code");
        foreach ($baseline as $code => $base) {
            $exists = $db->prepare("SELECT COUNT(*) FROM currencies WHERE code=:code");
            $exists->execute([':code'=>$code]);
            if ((int)$exists->fetchColumn() <= 0) {
                $skipped++;
                continue;
            }
            $stmt->execute([
                ':name'=>$base['name'],
                ':exchange_rate'=>$base['exchange_rate'],
                ':precision'=>$base['precision'],
                ':status'=>$base['status'],
                ':sort_order'=>$base['sort_order'],
                ':code'=>$code,
            ]);
            $updated += $stmt->rowCount();
        }
        $summary[] = '默认货币修复 ' . $updated . ' 项，跳过已删除默认货币 ' . $skipped . ' 项';

        foreach (['GOLD'=>'COIN','SILVER'=>'COIN_1','COPPER'=>'COIN_2'] as $old => $new) {
            $oldExists = $db->prepare("SELECT COUNT(*) FROM currencies WHERE code=:code");
            $oldExists->execute([':code'=>$old]);
            if ((int)$oldExists->fetchColumn() <= 0) continue;
            $newExists = $db->prepare("SELECT COUNT(*) FROM currencies WHERE code=:code");
            $newExists->execute([':code'=>$new]);
            if ((int)$newExists->fetchColumn() > 0) {
                $db->prepare("UPDATE currencies SET status='inactive' WHERE code=:code")->execute([':code'=>$old]);
                $summary[] = $old . ' 已存在且新版 ' . $new . ' 也存在，已停用旧默认货币';
            }
        }

        return ['summary'=>implode('；', $summary)];
    }

    private function currencyBaseline(): array
    {
        return [
            'COIN' => ['name'=>'金币', 'exchange_rate'=>100, 'precision'=>0, 'status'=>'active', 'sort_order'=>10],
            'COIN_1' => ['name'=>'银币', 'exchange_rate'=>10, 'precision'=>0, 'status'=>'active', 'sort_order'=>20],
            'COIN_2' => ['name'=>'铜币', 'exchange_rate'=>1, 'precision'=>0, 'status'=>'active', 'sort_order'=>30],
        ];
    }

    private function verifyLocalLicense(array $config): bool
    {
        $licenseDataStr = (string) ($config['license_data'] ?? '');
        $publicKey = (string) ($config['public_key'] ?? '');
        if ($licenseDataStr === '' || $publicKey === '') {
            return false;
        }
        $licenseData = json_decode($licenseDataStr, true);
        if (!is_array($licenseData)) {
            return false;
        }
        return (new LicenseVerifier())->verify($licenseData, $publicKey);
    }
}
