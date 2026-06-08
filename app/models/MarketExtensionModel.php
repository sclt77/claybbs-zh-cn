<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class MarketExtensionModel
{
    public function ensureTable(): void
    {
        Database::connection()->exec("CREATE TABLE IF NOT EXISTS market_extensions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            extension_type VARCHAR(20) NOT NULL DEFAULT 'plugin',
            slug VARCHAR(100) NOT NULL,
            name VARCHAR(160) DEFAULT NULL,
            version VARCHAR(60) DEFAULT NULL,
            license_required TINYINT(1) NOT NULL DEFAULT 0,
            license_key VARCHAR(160) DEFAULT NULL,
            package_hash VARCHAR(64) DEFAULT NULL,
            manifest_hash VARCHAR(64) DEFAULT NULL,
            manifest_json MEDIUMTEXT DEFAULT NULL,
            installed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_market_ext_type_slug (extension_type, slug),
            KEY idx_market_ext_license (license_required),
            KEY idx_market_ext_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function recordInstall(string $type, string $slug, array $manifest, string $packageHash = ''): void
    {
        $type = $this->safeType($type);
        $slug = $this->safeSlug($slug);
        if ($type === '' || $slug === '') return;
        $this->ensureTable();
        $license = $manifest['license'] ?? [];
        $licenseRequired = is_array($license) && !empty($license['required']) ? 1 : 0;
        $manifestJson = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $manifestJson = $manifestJson === false ? '{}' : $manifestJson;
        $stmt = Database::connection()->prepare("INSERT INTO market_extensions
            (extension_type, slug, name, version, license_required, license_key, package_hash, manifest_hash, manifest_json, installed_at, updated_at)
            VALUES (:type, :slug, :name, :version, :required, :license_key, :package_hash, :manifest_hash, :manifest_json, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                name=VALUES(name),
                version=VALUES(version),
                license_required=GREATEST(license_required, VALUES(license_required)),
                license_key=COALESCE(NULLIF(VALUES(license_key), ''), license_key),
                package_hash=VALUES(package_hash),
                manifest_hash=VALUES(manifest_hash),
                manifest_json=VALUES(manifest_json),
                updated_at=NOW()");
        $stmt->execute([
            ':type' => $type,
            ':slug' => $slug,
            ':name' => mb_substr((string)($manifest['name'] ?? $slug), 0, 160),
            ':version' => mb_substr((string)($manifest['version'] ?? ''), 0, 60),
            ':required' => $licenseRequired,
            ':license_key' => mb_substr((string)($manifest['license_key'] ?? ($license['license_key'] ?? '')), 0, 160),
            ':package_hash' => $packageHash !== '' ? $packageHash : null,
            ':manifest_hash' => hash('sha256', $manifestJson),
            ':manifest_json' => $manifestJson,
        ]);
    }

    public function find(string $type, string $slug): ?array
    {
        $type = $this->safeType($type);
        $slug = $this->safeSlug($slug);
        if ($type === '' || $slug === '') return null;
        $this->ensureTable();
        $stmt = Database::connection()->prepare('SELECT * FROM market_extensions WHERE extension_type=:type AND slug=:slug LIMIT 1');
        $stmt->execute([':type' => $type, ':slug' => $slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function licenseRequired(string $type, string $slug): ?bool
    {
        $row = $this->find($type, $slug);
        if (!$row) return null;
        return !empty($row['license_required']);
    }

    private function safeType(string $type): string
    {
        return in_array($type, ['plugin', 'theme'], true) ? $type : '';
    }

    private function safeSlug(string $slug): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $slug) ?? '';
    }
}
