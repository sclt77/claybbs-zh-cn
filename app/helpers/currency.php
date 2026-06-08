<?php

declare(strict_types=1);

function currency_icon_html(array $currency, string $class = 'currency-icon', int $size = 28): string
{
    
    return '';
}


function currency_amount_text(float $amount, array $currency, ?int $precision = null): string
{
    $code = (string)($currency['currency_code'] ?? $currency['code'] ?? '');
    $precision = $precision ?? (int)($currency['precision'] ?? ($code !== '' ? currency_precision_by_code($code) : 0));
    $precision = max(0, min(6, $precision));
    $name = trim((string)($currency['name'] ?? $currency['currency_name'] ?? ''));
    if ($name === '' && $code !== '') $name = currency_name_by_code($code);
    $num = number_format($amount, $precision);
    return $name !== '' ? $num . ' ' . $name : $num;
}

function currency_trim_amount($amount, int $maxPrecision = 6): string
{
    $value = number_format((float)$amount, max(0, min(6, $maxPrecision)), '.', '');
    $value = rtrim(rtrim($value, '0'), '.');
    return $value === '' || $value === '-0' ? '0' : $value;
}

function currency_legacy_names(): array
{
    return [
        'COPPER' => '铜币',
        'SILVER' => '银币',
        'GOLD' => '金币',
        'CNY' => '人民币',
    ];
}

function currency_resolve_code(string $code): string
{
    $code = strtoupper(trim($code));
    if ($code === '') return '';
    static $cache = [];
    if (array_key_exists($code, $cache)) return $cache[$code];
    try {
        $db = \App\Core\Database::connection();
        $stmt = $db->prepare("SELECT code FROM currencies WHERE code=:code AND status='active' LIMIT 1");
        $stmt->execute([':code' => $code]);
        $activeCode = strtoupper(trim((string)$stmt->fetchColumn()));
        if ($activeCode !== '') return $cache[$code] = $activeCode;

        $legacyNames = currency_legacy_names();
        if (!empty($legacyNames[$code])) {
            $stmt = $db->prepare("SELECT code FROM currencies WHERE name=:name AND status='active' ORDER BY sort_order ASC, id ASC LIMIT 1");
            $stmt->execute([':name' => $legacyNames[$code]]);
            $mappedCode = strtoupper(trim((string)$stmt->fetchColumn()));
            if ($mappedCode !== '') return $cache[$code] = $mappedCode;
        }
    } catch (\Throwable $e) {
        
    }
    return $cache[$code] = $code;
}

function currency_name_by_code(string $code): string
{
    $code = strtoupper(trim($code));
    if ($code === '') return '';
    static $cache = [];
    if (array_key_exists($code, $cache)) return $cache[$code];
    try {
        $resolvedCode = currency_resolve_code($code);
        $stmt = \App\Core\Database::connection()->prepare("SELECT name FROM currencies WHERE code=:code LIMIT 1");
        $stmt->execute([':code' => $resolvedCode]);
        $name = trim((string)$stmt->fetchColumn());
        $legacyNames = currency_legacy_names();
        $cache[$code] = $name !== '' ? $name : ($legacyNames[$code] ?? $code);
    } catch (\Throwable $e) {
        $legacyNames = currency_legacy_names();
        $cache[$code] = $legacyNames[$code] ?? $code;
    }
    return $cache[$code];
}

function currency_precision_by_code(string $code): int
{
    $code = currency_resolve_code($code);
    if ($code === '') return 0;
    static $cache = [];
    if (array_key_exists($code, $cache)) return $cache[$code];
    try {
        $stmt = \App\Core\Database::connection()->prepare("SELECT `precision` FROM currencies WHERE code=:code LIMIT 1");
        $stmt->execute([':code' => $code]);
        $precision = $stmt->fetchColumn();
        $cache[$code] = max(0, min(6, (int)$precision));
    } catch (\Throwable $e) {
        $cache[$code] = 0;
    }
    return $cache[$code];
}

function currency_validate_amount($amount, string $code, string $label = '金额'): float
{
    if (!is_numeric($amount)) {
        throw new \RuntimeException($label . '不正确');
    }
    $value = (float)$amount;
    if ($value <= 0) {
        throw new \RuntimeException($label . '必须大于 0');
    }
    $precision = currency_precision_by_code($code);
    $rounded = round($value, $precision);
    if ($precision === 0 && abs($value - floor($value)) > 0.000001) {
        throw new \RuntimeException('该货币只支持整数' . $label);
    }
    if (abs($value - $rounded) > 0.000001) {
        throw new \RuntimeException($label . '最多支持 ' . $precision . ' 位小数');
    }
    return $rounded;
}

function currency_pay_label($amount, string $code): string
{
    $code = strtoupper(trim($code));
    $name = currency_name_by_code($code);
    $num = currency_trim_amount($amount, currency_precision_by_code($code));
    return trim($num . ' ' . $name);
}

function currency_format_amount($amount, string $code): string
{
    return currency_pay_label($amount, $code);
}

function currency_display_name(string $code): string
{
    return currency_name_by_code($code);
}

function currency_localize_text(string $text): string
{
    if ($text === '') return '';
    return preg_replace_callback('/(?<![A-Za-z0-9_])(COIN_\d+|[A-Z][A-Z0-9_]{1,19})(?![A-Za-z0-9_])/u', static function (array $m): string {
        $code = strtoupper((string)$m[1]);
        $name = currency_name_by_code($code);
        return $name !== '' && $name !== $code ? $name : $m[1];
    }, $text) ?? $text;
}
