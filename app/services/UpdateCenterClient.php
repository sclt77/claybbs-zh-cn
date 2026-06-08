<?php

namespace App\Services;

class UpdateCenterClient
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function checkUpdate(string $currentVersion, string $branch, string $domain, string $owner): array
    {
        $payload = [
            'site_id' => $this->config['site_id'] ?? '',
            'token' => $this->config['token'] ?? '',
            'project_id' => $this->config['project_id'] ?? 0,
            'version' => $currentVersion,
            'branch' => $branch,
            'domain' => $domain,
            'owner' => $owner,
            'supports_full' => true,
        ];
        $payload = $this->signPayload($payload, 'check-update');
        return $this->postJson('/api.php?path=check-update', $payload);
    }

    public function downloadPackage(int $packageId, string $kind = 'package', string $ticket = ''): string
    {
        $payload = [
            'site_id' => $this->config['site_id'] ?? '',
            'token' => $this->config['token'] ?? '',
            'package_id' => $packageId,
            'kind' => $kind,
            'ticket' => $ticket,
        ];
        $payload = $this->signPayload($payload, 'download');
        $base = (string) ($this->config['url'] ?? 'https://www.claybbs.com');
        $url = rtrim($base, '/') . '/api.php?path=download';

        $ch = curl_init($url);
        curl_setopt_array($ch, $this->buildCurlOptions([
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]));
        $data = curl_exec($ch);
        if ($data === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('下载失败：' . $this->normalizeCurlError($error));
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status !== 200) {
            throw new \RuntimeException('下载失败，HTTP ' . $status);
        }
        return $data;
    }

    public function report(int $packageId, string $status, string $log, string $event = '', string $fullKey = '', array $extra = []): void
    {
        $payload = array_merge([
            'site_id' => $this->config['site_id'] ?? '',
            'token' => $this->config['token'] ?? '',
            'package_id' => $packageId,
            'status' => $status,
            'log' => $log,
            'event' => $event,
            'full_key' => $fullKey,
        ], $extra);
        $payload = $this->signPayload($payload, 'report');
        $this->postJson('/api.php?path=report', $payload);
    }


    public function marketList(string $type = ''): array
    {
        $payload = [
            'site_id' => $this->config['site_id'] ?? '',
            'token' => $this->config['token'] ?? '',
        ];
        if ($type !== '') {
            $payload['type'] = $type;
        }
        $payload = $this->signPayload($payload, 'market-list');
        return $this->postJson('/api.php?path=market/list', $payload);
    }

    public function marketAcquire(int $itemId): array
    {
        $payload = [
            'site_id' => $this->config['site_id'] ?? '',
            'token' => $this->config['token'] ?? '',
            'item_id' => $itemId,
        ];
        $payload = $this->signPayload($payload, 'market-acquire');
        return $this->postJson('/api.php?path=market/acquire', $payload);
    }

    public function marketDownload(int $itemId): string
    {
        $payload = [
            'site_id' => $this->config['site_id'] ?? '',
            'token' => $this->config['token'] ?? '',
            'item_id' => $itemId,
        ];
        $payload = $this->signPayload($payload, 'market-download');
        $base = (string) ($this->config['url'] ?? 'https://www.claybbs.com');
        $url = rtrim($base, '/') . '/api.php?path=market/download';
        $ch = curl_init($url);
        curl_setopt_array($ch, $this->buildCurlOptions([
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]));
        $data = curl_exec($ch);
        if ($data === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('市场包下载失败：' . $this->normalizeCurlError($error));
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status !== 200) {
            throw new \RuntimeException('市场包下载失败，HTTP ' . $status);
        }
        return $data;
    }


    public function marketDownloadByKey(string $key, string $domain): string
    {
        $payload = [
            'key' => $key,
            'domain' => $domain,
            'site_id' => (string)($this->config['site_id'] ?? ''),
            'token' => (string)($this->config['token'] ?? ''),
        ];
        $payload = $this->signPayload($payload, 'market-key-download');
        $base = (string) ($this->config['url'] ?? 'https://www.claybbs.com');
        $url = rtrim($base, '/') . '/api.php?path=market/key-download';
        $ch = curl_init($url);
        curl_setopt_array($ch, $this->buildCurlOptions([
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]));
        $data = curl_exec($ch);
        if ($data === false) { $error = curl_error($ch); curl_close($ch); throw new \RuntimeException('应用包下载失败：' . $this->normalizeCurlError($error)); }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status !== 200) throw new \RuntimeException('应用包下载失败，HTTP ' . $status . '：' . substr((string)$data, 0, 120));
        return $data;
    }

    public function verifyLicense(string $licenseKey, string $domain): array
    {
        $payload = [
            'license_key' => $licenseKey,
            'domain' => $domain,
        ];
        if (!empty($this->config['site_id']) && !empty($this->config['token'])) {
            $payload['site_id'] = (string)$this->config['site_id'];
            $payload = $this->signPayload($payload, 'license-verify');
        }
        return $this->postJson('/api.php?path=license/verify', $payload);
    }


    private function signPayload(array $payload, string $action): array
    {
        $payload['auth_ts'] = time();
        $payload['auth_nonce'] = bin2hex(random_bytes(12));
        $payload['auth_action'] = $action;
        $payload['auth_sig'] = $this->signature($payload);
        return $payload;
    }

    private function signature(array $payload): string
    {
        $token = (string)($this->config['token'] ?? '');
        unset($payload['auth_sig']);
        ksort($payload);
        return hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $token);
    }

    private function postJson(string $path, array $payload): array
    {
        $base = (string) ($this->config['url'] ?? 'https://www.claybbs.com');
        $url = rtrim($base, '/') . $path;
        $ch = curl_init($url);
        curl_setopt_array($ch, $this->buildCurlOptions([
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]));
        $res = curl_exec($ch);
        if ($res === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('请求失败：' . $this->normalizeCurlError($error));
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status !== 200) {
            throw new \RuntimeException('请求失败，HTTP ' . $status);
        }
        $data = json_decode($res, true);
        return is_array($data) ? $data : [];
    }

    private function buildCurlOptions(array $options): array
    {
        $options[CURLOPT_SSL_VERIFYPEER] = true;
        $options[CURLOPT_SSL_VERIFYHOST] = 2;
        $options[CURLOPT_CONNECTTIMEOUT] = 15;
        $options[CURLOPT_TIMEOUT] = 60;

        $caFile = $this->resolveCaFile();
        if ($caFile !== null) {
            $options[CURLOPT_CAINFO] = $caFile;
        }

        return $options;
    }

    private function resolveCaFile(): ?string
    {
        $projectRoot = dirname(__DIR__, 2);
        $candidates = [
            ini_get('curl.cainfo') ?: '',
            ini_get('openssl.cafile') ?: '',
            ($this->config['ca_file'] ?? ''),
            $projectRoot . '/storage/certs/cacert.pem',
            $projectRoot . '/storage/cacert.pem',
            'C:/BtSoft/php/82/extras/ssl/cacert.pem',
            'C:/BtSoft/php/82/extras/ssl/cacert.pem',
            'C:/BtSoft/php/74/extras/ssl/cacert.pem',
            'C:/phpstudy_pro/Extensions/php/php8.2.9nts/extras/ssl/cacert.pem',
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/cert.pem',
            '/etc/openssl/certs/cacert.pem',
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }
            if (!$this->canAccessPath($candidate)) {
                continue;
            }
            if (@is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function canAccessPath(string $path): bool
    {
        $openBaseDir = trim((string) ini_get('open_basedir'));
        if ($openBaseDir === '') {
            return true;
        }

        $normalizedPath = str_replace('\\', '/', $path);
        $allowedDirs = preg_split('/[;:]/', $openBaseDir) ?: [];
        foreach ($allowedDirs as $dir) {
            $dir = trim((string) $dir);
            if ($dir === '' || $dir === '.') {
                continue;
            }
            $normalizedDir = rtrim(str_replace('\\', '/', $dir), '/');
            if ($normalizedDir !== '' && str_starts_with($normalizedPath, $normalizedDir . '/')) {
                return true;
            }
            if ($normalizedPath === $normalizedDir) {
                return true;
            }
        }

        return false;
    }

    private function normalizeCurlError(string $error): string
    {
        $error = trim($error);
        if ($error === '') {
            return '未知 cURL 错误';
        }

        if (stripos($error, 'SSL certificate problem') !== false || stripos($error, 'unable to get local issuer certificate') !== false) {
            $caFile = $this->resolveCaFile();
            if ($caFile !== null) {
                return $error . '（已尝试 CA 文件：' . $caFile . '）';
            }
            return $error . '（未找到可用 CA 证书文件，请配置 curl.cainfo / openssl.cafile，或在 config/update-center.php 中增加 ca_file）';
        }

        return $error;
    }
}
