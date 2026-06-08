<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class SettingModel
{
    public function ensureTable(): void
    {
        
    }

    public function all(): array
    {
        $this->ensureTable();
        $rows = Database::connection()->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }
        return $map;
    }

    public function set(string $key, string $value): void
    {
        $this->ensureTable();
        $stmt = Database::connection()->prepare(
            "INSERT INTO settings (setting_key, setting_value)
             VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=NOW()"
        );
        $stmt->execute([':key' => $key, ':value' => $value]);
    }

    public function saveMany(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->set((string) $key, (string) $value);
        }
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $all = $this->all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);
        if ($value === null || $value === '') {
            return $default;
        }
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public function getSiteConfig(): array
    {
        $all = $this->all();
        return [
            'site_name' => $all['site_name'] ?? 'ClayBBS',
            'site_logo_text' => $all['site_logo_text'] ?? 'ClayBBS',
            'site_tagline' => $all['site_tagline'] ?? '一个轻量、可持续迭代的社区论坛系统。',
            'footer_text' => $all['footer_text'] ?? ('© ' . date('Y') . ' ClayBBS'),
        ];
    }
}
