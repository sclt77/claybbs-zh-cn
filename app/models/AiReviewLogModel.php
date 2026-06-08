<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class AiReviewLogModel
{
    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare("INSERT INTO ai_review_logs (provider_id,user_id,target_type,target_id,draft_id,title,content_excerpt,status,risk_level,categories,reason,suggestion,request_payload,response_raw,parsed_result,error_message,created_at) VALUES (:provider_id,:user_id,:target_type,:target_id,:draft_id,:title,:content_excerpt,:status,:risk_level,:categories,:reason,:suggestion,:request_payload,:response_raw,:parsed_result,:error_message,NOW())");
        $stmt->execute([
            ':provider_id' => $data['provider_id'] ?? null,
            ':user_id' => (int)($data['user_id'] ?? 0),
            ':target_type' => (string)($data['target_type'] ?? ''),
            ':target_id' => $data['target_id'] ?? null,
            ':draft_id' => $data['draft_id'] ?? null,
            ':title' => $data['title'] ?? null,
            ':content_excerpt' => $data['content_excerpt'] ?? null,
            ':status' => (string)($data['status'] ?? 'error'),
            ':risk_level' => $data['risk_level'] ?? null,
            ':categories' => is_array($data['categories'] ?? null) ? implode(',', $data['categories']) : ($data['categories'] ?? null),
            ':reason' => $data['reason'] ?? null,
            ':suggestion' => $data['suggestion'] ?? null,
            ':request_payload' => $data['request_payload'] ?? null,
            ':response_raw' => $data['response_raw'] ?? null,
            ':parsed_result' => isset($data['parsed_result']) ? (is_string($data['parsed_result']) ? $data['parsed_result'] : json_encode($data['parsed_result'], JSON_UNESCAPED_UNICODE)) : null,
            ':error_message' => $data['error_message'] ?? null,
        ]);
        return (int)Database::connection()->lastInsertId();
    }

    public function latest(int $limit = 80): array
    {
        $stmt = Database::connection()->prepare("SELECT l.*, p.name AS provider_name, u.nickname, u.username FROM ai_review_logs l LEFT JOIN ai_providers p ON p.id=l.provider_id LEFT JOIN users u ON u.id=l.user_id ORDER BY l.id DESC LIMIT :limit");
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare("SELECT l.*, p.name AS provider_name, u.nickname, u.username FROM ai_review_logs l LEFT JOIN ai_providers p ON p.id=l.provider_id LEFT JOIN users u ON u.id=l.user_id WHERE l.id=:id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
