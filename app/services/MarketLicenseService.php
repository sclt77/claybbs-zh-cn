<?php

namespace App\Services;

use App\Models\MarketExtensionModel;

class MarketLicenseService
{
    public static function manifest(string $type, string $slug): array
    {
        $type = self::safeType($type);
        $slug = self::safeSlug($slug);
        if ($type === '' || $slug === '') return [];
        $root = dirname(__DIR__, 2);
        $file = $root . '/' . ($type === 'theme' ? 'themes' : 'plugins') . '/' . $slug . '/' . ($type === 'theme' ? 'theme.json' : 'plugin.json');
        if (!is_file($file)) return [];
        $json = json_decode((string)file_get_contents($file), true);
        return is_array($json) ? $json : [];
    }

    public static function requiresLicense(string $type, string $slug, ?array $manifest = null): bool
    {
        $type = self::safeType($type);
        $slug = self::safeSlug($slug);
        if ($type === '' || $slug === '') return false;

        try {
            $recordRequired = (new MarketExtensionModel())->licenseRequired($type, $slug);
            if ($recordRequired !== null) return $recordRequired;
        } catch (\Throwable $e) {
            // 兼容安装中/数据库异常场景，回退到 manifest 声明。
        }

        $manifest = $manifest ?? self::manifest($type, $slug);
        $license = $manifest['license'] ?? [];
        return is_array($license) && !empty($license['required']);
    }

    public static function valid(string $type, string $slug, ?array $manifest = null): bool
    {
        $type = self::safeType($type);
        $slug = self::safeSlug($slug);
        if ($type === '' || $slug === '') return false;
        $manifest = $manifest ?? self::manifest($type, $slug);
        if (!self::requiresLicense($type, $slug, $manifest)) return true;
        $license = self::licenseData($type, $slug);
        if (!$license) return false;
        $payload = $license['payload'] ?? [];
        $sig = (string)($license['sig'] ?? '');
        if (!is_array($payload) || $sig === '') return false;
        if (($payload['type'] ?? '') !== $type || ($payload['slug'] ?? '') !== $slug) return false;
        if (!self::timeWindowValid($payload)) return false;
        $domain = self::normalizeDomain((string)($payload['domain'] ?? ''));
        $cfgDomain = self::configuredDomain();
        if ($domain === '' || ($cfgDomain !== '' && $domain !== $cfgDomain)) return false;
        return self::verifySignature($payload, $sig);
    }

    public static function featureAllowed(string $type, string $slug, string $feature): bool
    {
        $manifest = self::manifest($type, $slug);
        if (!self::requiresLicense($type, $slug, $manifest)) return true;
        $features = $manifest['license']['protected_features'] ?? [];
        if (is_array($features) && $features && !in_array($feature, array_map('strval', $features), true)) return true;
        return self::valid($type, $slug, $manifest);
    }

    public static function routeAllowed(string $type, string $slug, string $route): bool
    {
        $manifest = self::manifest($type, $slug);
        if (!self::requiresLicense($type, $slug, $manifest)) return true;
        $routes = $manifest['license']['protected_routes'] ?? [];
        if (is_array($routes) && $routes && !in_array($route, array_map('strval', $routes), true)) return true;
        return self::valid($type, $slug, $manifest);
    }

    public static function status(string $type, string $slug): array
    {
        $manifest = self::manifest($type, $slug);
        $required = self::requiresLicense($type, $slug, $manifest);
        $valid = self::valid($type, $slug, $manifest);
        $license = self::licenseData($type, $slug);
        $payload = is_array($license['payload'] ?? null) ? $license['payload'] : [];
        $expiresAt = self::expiryTimestamp($payload);
        return [
            'required' => $required,
            'valid' => $valid,
            'domain' => (string)($payload['domain'] ?? ''),
            'license_key' => (string)($payload['license_key'] ?? ''),
            'issued_at' => (int)($payload['issued_at'] ?? 0),
            'expires_at' => $expiresAt,
            'expired' => $expiresAt > 0 && time() > $expiresAt,
            'source' => self::licenseRequirementSource($type, $slug),
        ];
    }

    public static function guard(string $type, string $slug, string $feature = ''): void
    {
        if ($feature !== '' ? self::featureAllowed($type, $slug, $feature) : self::valid($type, $slug)) return;
        http_response_code(403);
        exit('应用未授权或授权域名不匹配');
    }

    private static function licenseData(string $type, string $slug): array
    {
        $root = dirname(__DIR__, 2);
        $base = $root . '/' . ($type === 'theme' ? 'themes' : 'plugins') . '/' . $slug;
        foreach ([$base . '/license.json', $base . '/market-license.json', $base . '/.license.json'] as $file) {
            if (!is_file($file)) continue;
            $json = json_decode((string)file_get_contents($file), true);
            if (is_array($json)) return $json;
        }
        return [];
    }

    private static function verifySignature(array $payload, string $signature): bool
    {
        $cfg = self::updateConfig();
        $pub = (string)($cfg['public_key'] ?? '');
        if ($pub === '') return false;
        $data = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($data === false) return false;
        $decoded = base64_decode($signature, true);
        if ($decoded === false) return false;
        return openssl_verify($data, $decoded, $pub, OPENSSL_ALGO_SHA256) === 1;
    }

    private static function timeWindowValid(array $payload): bool
    {
        $notBefore = self::payloadTimestamp($payload, ['not_before', 'nbf', 'valid_from']);
        if ($notBefore > 0 && time() < $notBefore) return false;
        $expiresAt = self::expiryTimestamp($payload);
        if ($expiresAt > 0 && time() > $expiresAt) return false;
        return true;
    }

    private static function expiryTimestamp(array $payload): int
    {
        return self::payloadTimestamp($payload, ['expires_at', 'expire_at', 'expired_at', 'valid_until', 'expires']);
    }

    private static function payloadTimestamp(array $payload, array $keys): int
    {
        foreach ($keys as $key) {
            if (!isset($payload[$key]) || $payload[$key] === '') continue;
            $value = $payload[$key];
            if (is_numeric($value)) return max(0, (int)$value);
            if (is_string($value)) {
                $ts = strtotime($value);
                if ($ts !== false) return $ts;
            }
        }
        return 0;
    }

    private static function licenseRequirementSource(string $type, string $slug): string
    {
        try {
            $recordRequired = (new MarketExtensionModel())->licenseRequired($type, $slug);
            if ($recordRequired !== null) return 'market_extensions';
        } catch (\Throwable $e) {}
        return 'manifest';
    }

    private static function updateConfig(): array
    {
        $file = dirname(__DIR__, 2) . '/config/update-center.php';
        if (!is_file($file)) return [];
        $cfg = include $file;
        return is_array($cfg) ? $cfg : [];
    }

    private static function configuredDomain(): string
    {
        $cfg = self::updateConfig();
        return self::normalizeDomain((string)($cfg['domain'] ?? ($_SERVER['HTTP_HOST'] ?? '')));
    }

    private static function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') return '';
        if (str_contains($domain, '://')) $domain = (string)(parse_url($domain, PHP_URL_HOST) ?: $domain);
        $domain = preg_replace('#/.*$#', '', $domain) ?? $domain;
        $domain = preg_replace('#:\d+$#', '', $domain) ?? $domain;
        return trim($domain, ". \t\r\n/");
    }

    private static function safeType(string $type): string
    {
        return in_array($type, ['plugin','theme'], true) ? $type : '';
    }

    private static function safeSlug(string $slug): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $slug) ?? '';
    }
}
