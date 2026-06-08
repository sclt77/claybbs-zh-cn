<?php

namespace App\Models;

use App\Core\Database;

class AttachmentModel
{
    public function create(array $data): int
    {
        $sql = "INSERT INTO attachments (user_id, thread_id, post_id, original_name, stored_name, path, mime, size, kind, created_at)
                VALUES (:user_id, :thread_id, :post_id, :original_name, :stored_name, :path, :mime, :size, :kind, NOW())";
        Database::connection()->prepare($sql)->execute([
            ':user_id' => $data['user_id'],
            ':thread_id' => $data['thread_id'] ?? null,
            ':post_id' => $data['post_id'] ?? null,
            ':original_name' => $data['original_name'],
            ':stored_name' => $data['stored_name'],
            ':path' => $data['path'],
            ':mime' => $data['mime'],
            ':size' => $data['size'],
            ':kind' => $data['kind'] ?? 'file',
        ]);
        return (int)Database::connection()->lastInsertId();
    }
}
