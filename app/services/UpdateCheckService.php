<?php

namespace App\Services;

class UpdateCheckService
{
    private string $root;
    private string $cacheFile;
    private int $ttl;

    public function __construct(?string $root = null, int $ttl = 1800)
    {
        $this->root = $root ?: dirname(__DIR__, 2);
        $this->cacheFile = $this->root . '/storage/update-check-cache.json';
        $this->ttl = max(60, $ttl);
    }

    public function getCachedStatus(): array
    {
        $cache = $this->readCache();
        return $cache ?: [
            'checked_at' => 0,
            'ok' => false,
            'update' => false,
            'message' => '',
        ];
    }

    public function checkIfStale(bool $force = false): array
    {
        $cache = $this->readCache();
        $now = time();
        if (!$force && $cache && (int)($cache['checked_at'] ?? 0) > 0 && $now - (int)$cache['checked_at'] < $this->ttl) {
            return $cache;
        }
        return $this->checkNow();
    }

    public function checkNow(): array
    {
        $started = time();
        try {
            $config = $this->loadConfig();
            $licenseKey = trim((string)($config['license_key'] ?? ''));
            $domain = trim((string)($config['domain'] ?? ''));
            $siteId = trim((string)($config['site_id'] ?? ''));
            $token = trim((string)($config['token'] ?? ''));
            if ($licenseKey === '' || $domain === '' || $siteId === '' || $token === '') {
                throw new \RuntimeException('请先完成正版授权绑定');
            }

            $client = new UpdateCenterClient($config);
            $res = $client->checkUpdate(
                (string)($config['current_version'] ?? '0.0.0'),
                (string)($config['branch'] ?? 'main'),
                (string)($config['domain'] ?? ''),
                (string)($config['owner'] ?? '')
            );

            $status = [
                'checked_at' => $started,
                'ok' => true,
                'update' => !empty($res['update']),
                'current_version' => (string)($config['current_version'] ?? '0.0.0'),
                'latest_version' => (string)($res['latest_version'] ?? ($res['package']['to_version'] ?? '')),
                'package_id' => (int)($res['package']['id'] ?? 0),
                'raw' => $res,
                'message' => !empty($res['update']) ? '发现新版本' : '当前已是最新版本',
            ];
            $this->writeCache($status);
            return $status;
        } catch (\Throwable $e) {
            $status = [
                'checked_at' => $started,
                'ok' => false,
                'update' => false,
                'message' => $e->getMessage(),
            ];
            $this->writeCache($status);
            return $status;
        }
    }

    public function clear(): void
    {
        if (is_file($this->cacheFile)) {
            @unlink($this->cacheFile);
        }
    }

    private function readCache(): array
    {
        if (!is_file($this->cacheFile)) {
            return [];
        }
        $data = json_decode((string)@file_get_contents($this->cacheFile), true);
        return is_array($data) ? $data : [];
    }

    private function writeCache(array $status): void
    {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($this->cacheFile, json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function loadConfig(): array
    {
        $path = $this->root . '/config/update-center.php';
        $cfg = is_file($path) ? (@include $path) : [];
        if (!is_array($cfg)) {
            $cfg = [];
        }
        $detected = $this->detectInstalledVersion($cfg);
        if ($detected !== '' && (($cfg['current_version'] ?? '') === '' || ($cfg['current_version'] ?? '') === '0.0.0')) {
            $cfg['current_version'] = $detected;
        }
        return $cfg + [
            'url' => 'https://www.claybbs.com',
            'site_id' => '',
            'token' => '',
            'project_id' => 0,
            'branch' => 'main',
            'current_version' => '0.0.0',
            'domain' => '',
            'owner' => '',
            'license_key' => '',
        ];
    }

    private function detectInstalledVersion(array $cfg = []): string
    {
        $manifest = $this->root . '/manifest.json';
        if (is_file($manifest)) {
            $data = json_decode((string)@file_get_contents($manifest), true);
            if (is_array($data)) {
                $version = trim((string)($data['version'] ?? ''));
                if ($version !== '' && $version !== '0.0.0') {
                    return $version;
                }
            }
        }
        $appPath = $this->root . '/config/app.php';
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
}
