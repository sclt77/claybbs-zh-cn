<?php

namespace App\Services;

class LicenseGuardService
{
    public const GRACE_SECONDS = 604800;
    public const ONLINE_CACHE_SECONDS = 300;

    private string $root;
    private string $statePath;
    private string $secretPath;

    public function __construct()
    {
        $this->root = dirname(__DIR__, 2);
        $this->statePath = $this->root . '/storage/license/state.json';
        $this->secretPath = $this->root . '/storage/license/local_secret.key';
    }

    public function adminStatus(): array
    {
        $config = $this->loadConfig();
        if (!$this->isEnabled($config)) {
            return ['state' => 'valid', 'enabled' => false, 'reason' => '正版验证守卫未开启', 'remaining_grace_seconds' => self::GRACE_SECONDS, 'payload' => []];
        }

        $now = time();
        $state = $this->loadState();
        if (!$state) {
            $this->bootstrapFromConfig($config);
            $state = $this->loadState();
        }
        if (!$state) {
            return $this->locked('尚未完成正版验证');
        }
        if ((int)($state['last_time_seen'] ?? 0) > 0 && $now + 300 < (int)$state['last_time_seen']) {
            return $this->locked('检测到系统时间明显回拨');
        }
        if (!$this->verifyLocalSignature($state)) {
            return $this->locked('本地授权状态文件签名异常，疑似被篡改');
        }

        $payload = $state['payload'] ?? [];
        $remoteSig = (string)($state['remote_sig'] ?? '');
        $publicKey = (string)($state['public_key'] ?? $config['public_key'] ?? '');
        if (!is_array($payload) || $payload === [] || !(new LicenseVerifier())->verify(['payload' => $payload, 'sig' => $remoteSig], $publicKey)) {
            return $this->locked('官方授权签名验证失败');
        }

        $licensedDomain = $this->normalizeDomain((string)($payload['domain'] ?? ''));
        $currentDomain = $this->currentDomain($config);
        if ($licensedDomain === '' || $currentDomain === '' || $licensedDomain !== $currentDomain) {
            return $this->locked('当前域名与授权绑定域名不一致');
        }

        $lastVerifiedAt = (int)($state['last_verified_at'] ?? 0);
        if ($lastVerifiedAt <= 0) {
            return $this->locked('缺少最近一次在线验证时间');
        }

        $remoteState = $this->remotePayloadState($payload);
        if ($remoteState !== 'active') {
            return $this->locked($this->humanError($remoteState));
        }

        $this->touchLastSeen($state, $now);
        $elapsed = $now - $lastVerifiedAt;
        if ($this->shouldAutoRefresh($state, $now)) {
            try {
                return $this->onlineVerify((string)($state['license_key'] ?? $payload['license_key'] ?? ''), $currentDomain);
            } catch (\Throwable $e) {
                return $this->locked('无法连接官方授权服务器：' . $e->getMessage());
            }
        }

        if ($elapsed <= self::GRACE_SECONDS) {
            $remaining = max(0, self::GRACE_SECONDS - $elapsed);
            $lastError = (string)($state['last_error'] ?? '');
            return [
                'state' => $elapsed > 86400 ? 'grace' : 'valid',
                'enabled' => true,
                'reason' => $elapsed > 86400 ? ('正版验证处于离线宽限期内' . ($lastError !== '' ? '：' . $lastError : '')) : '正版验证正常',
                'remaining_grace_seconds' => $remaining,
                'payload' => $payload,
                'last_verified_at' => $lastVerifiedAt,
            ];
        }

        return $this->locked('离线宽限已超过 7 天，请重新完成正版验证');
    }

    public function guardAdmin(): void
    {
        $status = $this->adminStatus();
        if (($status['state'] ?? 'valid') === 'valid' || ($status['state'] ?? '') === 'trial') return;
        $path = trim((string)($_GET['path'] ?? ''), '/');
        if (!in_array($path, ['license', 'logout'], true)) {
            header('Location: /admin.php?path=license');
            exit;
        }
    }

    public function onlineVerify(string $licenseKey, string $domain = ''): array
    {
        $config = $this->loadConfig();
        $licenseKey = trim($licenseKey);
        $domain = $this->normalizeDomain($domain !== '' ? $domain : $this->currentDomain($config));
        if ($licenseKey === '' || $domain === '') {
            throw new \RuntimeException('请填写授权码和当前域名');
        }

        $client = new UpdateCenterClient($config);
        $res = $client->verifyLicense($licenseKey, $domain);
        if (empty($res['ok']) || empty($res['payload'])) {
            throw new \RuntimeException($this->humanError((string)($res['error'] ?? 'verify_failed')));
        }
        $payload = $res['payload'];
        $sig = (string)($res['sig'] ?? '');
        $publicKey = (string)($config['public_key'] ?? '');
        if (!is_array($payload) || $sig === '' || !(new LicenseVerifier())->verify(['payload' => $payload, 'sig' => $sig], $publicKey)) {
            throw new \RuntimeException('官方授权签名验证失败');
        }
        if ($this->normalizeDomain((string)($payload['domain'] ?? '')) !== $domain) {
            throw new \RuntimeException('官方返回的授权域名与当前域名不一致');
        }
        $remoteState = $this->remotePayloadState($payload);
        if ($remoteState !== 'active') {
            throw new \RuntimeException($this->humanError($remoteState));
        }

        $config['license_key'] = (string)($payload['license_key'] ?? $licenseKey);
        $config['domain'] = (string)($payload['domain'] ?? $domain);
        $config['owner'] = (string)($payload['owner'] ?? ($config['owner'] ?? ''));
        $config['site_id'] = (string)($payload['site_id'] ?? ($config['site_id'] ?? ''));
        $config['token'] = (string)($payload['token'] ?? ($config['token'] ?? ''));
        $config['license_data'] = json_encode($res, JSON_UNESCAPED_UNICODE);
        $this->saveConfig($config);

        $now = time();
        $state = [
            'version' => 1,
            'license_key' => $licenseKey,
            'official_url' => (string)($config['url'] ?? 'https://www.claybbs.com'),
            'public_key' => $publicKey,
            'payload' => $payload,
            'remote_sig' => $sig,
            'last_verified_at' => $now,
            'last_checked_at' => $now,
            'last_time_seen' => $now,
        ];
        $this->saveState($state);
        return $this->adminStatus();
    }

    public function maskedLicenseKey(array $status = []): string
    {
        $payload = $status['payload'] ?? [];
        $config = $this->loadConfig();
        $key = (string)($payload['license_key'] ?? $config['license_key'] ?? '');
        if ($key === '') return '未填写';
        return strlen($key) <= 10 ? str_repeat('*', strlen($key)) : substr($key, 0, 4) . '****' . substr($key, -4);
    }

    public function currentDomain(array $config = []): string
    {
        $configured = $this->normalizeDomain((string)($config['domain'] ?? ''));
        if ($configured !== '') return $configured;
        $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
        return $this->normalizeDomain($host);
    }

    public function loadConfig(): array
    {
        $path = $this->root . '/config/update-center.php';
        if (!is_file($path)) return ['url' => 'https://www.claybbs.com'];
        $cfg = include $path;
        return is_array($cfg) ? $cfg : ['url' => 'https://www.claybbs.com'];
    }

    private function saveConfig(array $cfg): void
    {
        $content = "<?php\n\nreturn " . var_export($cfg, true) . ";\n";
        file_put_contents($this->root . '/config/update-center.php', $content, LOCK_EX);
    }

    private function isEnabled(array $config): bool
    {
        return (string)($config['license_guard_enabled'] ?? '1') === '1';
    }

    private function bootstrapFromConfig(array $config): void
    {
        $licenseData = json_decode((string)($config['license_data'] ?? ''), true);
        if (!is_array($licenseData) || empty($licenseData['payload']) || empty($licenseData['sig']) || empty($config['public_key'])) return;
        if (!(new LicenseVerifier())->verify($licenseData, (string)$config['public_key'])) return;
        $issued = (int)($licenseData['payload']['issued_at'] ?? time());
        $now = time();
        $this->saveState([
            'version' => 1,
            'license_key' => (string)($config['license_key'] ?? $licenseData['payload']['license_key'] ?? ''),
            'official_url' => (string)($config['url'] ?? 'https://www.claybbs.com'),
            'public_key' => (string)$config['public_key'],
            'payload' => $licenseData['payload'],
            'remote_sig' => (string)$licenseData['sig'],
            'last_verified_at' => $issued > 0 ? $issued : $now,
            'last_checked_at' => $now,
            'last_time_seen' => $now,
        ]);
    }

    private function locked(string $reason): array
    {
        return ['state' => 'locked', 'enabled' => true, 'reason' => $reason, 'remaining_grace_seconds' => 0, 'payload' => []];
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') return '';
        if (str_contains($domain, '://')) {
            $host = (string)parse_url($domain, PHP_URL_HOST);
            $domain = $host !== '' ? $host : $domain;
        }
        $domain = preg_replace('#/.*$#', '', $domain) ?? $domain;
        $domain = preg_replace('#:\d+$#', '', $domain) ?? $domain;
        return trim($domain, ". \t\r\n/");
    }

    public function guardFrontendWrite(bool $json = false): void
    {
        $status = $this->adminStatus();
        if (in_array(($status['state'] ?? ''), ['valid','trial'], true)) return;
        $msg = '授权状态异常，当前禁止发布、回复、上传等写入操作：' . (string)($status['reason'] ?? '请联系官方处理');
        if ($json || str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            header('Content-Type: application/json');
            echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(403);
            echo '<!doctype html><meta charset="utf-8"><title>授权异常</title><div style="max-width:720px;margin:80px auto;font-family:sans-serif;padding:28px;border:1px solid #fee2e2;border-radius:16px;background:#fff1f2;color:#991b1b;"><h2>授权异常</h2><p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p><p><a href="/index.php">返回首页</a></p></div>';
        }
        exit;
    }

    public function trialInfo(): array
    {
        $status = $this->adminStatus();
        $payload = $status['payload'] ?? [];
        if (!is_array($payload) || ($payload['license_type'] ?? '') !== 'trial') return [];
        $remaining = (int)($payload['remaining_seconds'] ?? 0);
        return ['is_trial'=>true, 'remaining_seconds'=>$remaining, 'expires_at'=>(int)($payload['expires_at'] ?? 0), 'notice'=>(string)($payload['trial_notice'] ?? '当前为 ClayBBS 体验授权，请购买正式授权。')];
    }

    private function remotePayloadState(array $payload): string
    {
        $status = (string)($payload['license_status'] ?? 'active');
        if (in_array($status, ['locked','disabled','expired'], true)) return $status;
        if ((string)($payload['license_type'] ?? 'permanent') === 'trial') {
            $exp = (int)($payload['expires_at'] ?? 0);
            if ($exp > 0 && $exp < time()) return 'expired';
            return 'active';
        }
        return $status === '' ? 'active' : $status;
    }

    private function shouldAutoRefresh(array $state, int $now): bool
    {
        return $now - (int)($state['last_checked_at'] ?? 0) > self::ONLINE_CACHE_SECONDS;
    }

    private function isDefinitiveLicenseFailure(string $message): bool
    {
        foreach (['授权码无效', '授权已被禁用', '授权绑定域名不一致', '授权域名与当前域名不一致', '站点挑战签名无效'] as $needle) {
            if (str_contains($message, $needle)) return true;
        }
        return false;
    }

    private function loadState(): ?array
    {
        if (!is_file($this->statePath)) return null;
        $data = json_decode((string)file_get_contents($this->statePath), true);
        return is_array($data) ? $data : null;
    }

    private function saveState(array $state): void
    {
        unset($state['local_sig']);
        $state['local_sig'] = $this->localSignature($state);
        @mkdir(dirname($this->statePath), 0755, true);
        file_put_contents($this->statePath, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function verifyLocalSignature(array $state): bool
    {
        $sig = (string)($state['local_sig'] ?? '');
        if ($sig === '') return false;
        unset($state['local_sig']);
        return hash_equals($this->localSignature($state), $sig);
    }

    private function touchLastSeen(array $state, int $now): void
    {
        if ($now > (int)($state['last_time_seen'] ?? 0) + 60) {
            $state['last_time_seen'] = $now;
            $this->saveState($state);
        }
    }

    private function localSignature(array $state): string
    {
        $copy = $state;
        ksort($copy);
        return hash_hmac('sha256', json_encode($copy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $this->localSecret());
    }

    private function localSecret(): string
    {
        if (!is_file($this->secretPath)) {
            @mkdir(dirname($this->secretPath), 0755, true);
            file_put_contents($this->secretPath, bin2hex(random_bytes(32)), LOCK_EX);
        }
        return trim((string)file_get_contents($this->secretPath));
    }

    private function humanError(string $err): string
    {
        return match ($err) {
            'invalid_key' => '授权码无效',
            'disabled' => '授权已被禁用或吊销',
            'locked' => '授权已被官方锁定',
            'expired' => '授权已过期',
            'domain_mismatch' => '当前域名与授权绑定域名不一致',
            'missing_params' => '授权验证参数不完整',
            'invalid signature' => '站点挑战签名无效',
            default => '正版验证失败：' . $err,
        };
    }
}
