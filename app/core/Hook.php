<?php

namespace App\Core;

class Hook
{
    private static array $listeners = [];
    private static array $stack = [];

    public static function listen(string $name, callable $callback, int $priority = 10): void
    {
        self::$listeners[$name][$priority][] = $callback;
        ksort(self::$listeners[$name]);
    }

    public static function fire(string $name, array $payload = []): array
    {
        self::$stack[] = ['name' => $name, 'payload' => $payload];
        try {
            foreach (self::$listeners[$name] ?? [] as $callbacks) {
                foreach ($callbacks as $callback) {
                    $result = $callback($payload);
                    if (is_array($result)) {
                        $payload = $result;
                        self::$stack[array_key_last(self::$stack)]['payload'] = $payload;
                    }
                }
            }
            return $payload;
        } finally {
            array_pop(self::$stack);
        }
    }

    public static function filter(string $name, mixed $value, array $context = []): mixed
    {
        $payload = ['value' => $value, 'context' => $context];
        $payload = self::fire($name, $payload);
        return $payload['value'] ?? $value;
    }

    public static function currentName(): string
    {
        $current = self::$stack[array_key_last(self::$stack) ?? -1] ?? [];
        return (string)($current['name'] ?? '');
    }

    public static function currentPayload(): array
    {
        $current = self::$stack[array_key_last(self::$stack) ?? -1] ?? [];
        return is_array($current['payload'] ?? null) ? $current['payload'] : [];
    }
}
