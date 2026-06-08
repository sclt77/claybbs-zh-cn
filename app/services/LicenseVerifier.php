<?php

namespace App\Services;

class LicenseVerifier
{
    public function verify(array $licenseData, string $publicKeyPem): bool
    {
        $payload = $licenseData['payload'] ?? null;
        $sigB64 = $licenseData['sig'] ?? '';
        if (!is_array($payload) || $sigB64 === '') {
            return false;
        }
        $data = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($data === false) {
            return false;
        }
        $signature = base64_decode((string) $sigB64, true);
        if ($signature === false || $signature === '') {
            return false;
        }
        $pubKey = openssl_pkey_get_public($publicKeyPem);
        if (!$pubKey) {
            return false;
        }
        $ok = openssl_verify($data, $signature, $pubKey, OPENSSL_ALGO_SHA256);
        return $ok === 1;
    }
}
