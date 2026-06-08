<?php

namespace App\Services;

class ReportQueue
{
    private string $file;

    public function __construct()
    {
        $this->file = dirname(__DIR__, 2) . '/storage/updates/report_queue.json';
    }

    public function add(array $payload): void
    {
        $list = $this->all();
        $list[] = $payload;
        file_put_contents($this->file, json_encode($list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function all(): array
    {
        if (!file_exists($this->file)) return [];
        $data = json_decode(file_get_contents($this->file), true);
        return is_array($data) ? $data : [];
    }

    public function clear(): void
    {
        if (file_exists($this->file)) {
            @unlink($this->file);
        }
    }

    public function filePath(): string
    {
        return $this->file;
    }
}
