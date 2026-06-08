<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class AiProviderModel
{
    public function all(bool $enabledOnly = false): array
    {
        $sql = "SELECT * FROM ai_providers" . ($enabledOnly ? " WHERE enabled=1" : "") . " ORDER BY sort_order ASC, id ASC";
        return Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM ai_providers WHERE id=:id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function active(?int $preferredId = null): ?array
    {
        if ($preferredId && ($provider = $this->find($preferredId)) && !empty($provider['enabled'])) {
            return $provider;
        }
        $stmt = Database::connection()->query("SELECT * FROM ai_providers WHERE enabled=1 ORDER BY sort_order ASC, id ASC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        $id = (int)($data['id'] ?? 0);
        $params = [
            ':name' => trim((string)($data['name'] ?? '')),
            ':type' => trim((string)($data['type'] ?? 'openai_compatible')) ?: 'openai_compatible',
            ':base_url' => rtrim(trim((string)($data['base_url'] ?? '')), '/'),
            ':api_key' => (string)($data['api_key'] ?? ''),
            ':model' => trim((string)($data['model'] ?? '')),
            ':endpoint_path' => trim((string)($data['endpoint_path'] ?? '')) ?: null,
            ':request_template' => (string)($data['request_template'] ?? ''),
            ':response_path' => trim((string)($data['response_path'] ?? '')) ?: null,
            ':temperature' => (float)($data['temperature'] ?? 0),
            ':max_tokens' => max(1, (int)($data['max_tokens'] ?? 600)),
            ':timeout_seconds' => max(1, (int)($data['timeout_seconds'] ?? 12)),
            ':enabled' => !empty($data['enabled']) ? 1 : 0,
            ':sort_order' => max(0, (int)($data['sort_order'] ?? 0)),
        ];
        if ($params[':name'] === '' || $params[':base_url'] === '' || $params[':model'] === '') {
            throw new \InvalidArgumentException('请填写提供商名称、Base URL 和模型名');
        }
        if (!in_array($params[':type'], ['openai_compatible', 'custom_json'], true)) {
            throw new \InvalidArgumentException('提供商类型无效');
        }
        if ($id > 0) {
            $params[':id'] = $id;
            Database::connection()->prepare("UPDATE ai_providers SET name=:name,type=:type,base_url=:base_url,api_key=:api_key,model=:model,endpoint_path=:endpoint_path,request_template=:request_template,response_path=:response_path,temperature=:temperature,max_tokens=:max_tokens,timeout_seconds=:timeout_seconds,enabled=:enabled,sort_order=:sort_order WHERE id=:id")->execute($params);
            return $id;
        }
        Database::connection()->prepare("INSERT INTO ai_providers (name,type,base_url,api_key,model,endpoint_path,request_template,response_path,temperature,max_tokens,timeout_seconds,enabled,sort_order,created_at,updated_at) VALUES (:name,:type,:base_url,:api_key,:model,:endpoint_path,:request_template,:response_path,:temperature,:max_tokens,:timeout_seconds,:enabled,:sort_order,NOW(),NOW())")->execute($params);
        return (int)Database::connection()->lastInsertId();
    }

    public function delete(int $id): void
    {
        Database::connection()->prepare("DELETE FROM ai_providers WHERE id=:id")->execute([':id' => $id]);
    }
}
