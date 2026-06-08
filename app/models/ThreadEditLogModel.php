<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ThreadEditLogModel
{
    public function create(int $threadId, int $editorId, string $editorType, array $old, array $new): void
    {
        $sql = "INSERT INTO thread_edit_logs (thread_id, editor_id, editor_type, old_title, new_title, old_content, new_content, old_section_id, new_section_id, old_status, new_status, created_at)
                VALUES (:thread_id, :editor_id, :editor_type, :old_title, :new_title, :old_content, :new_content, :old_section_id, :new_section_id, :old_status, :new_status, NOW())";
        Database::connection()->prepare($sql)->execute([
            ':thread_id' => $threadId,
            ':editor_id' => $editorId,
            ':editor_type' => $editorType,
            ':old_title' => $old['title'] ?? null,
            ':new_title' => $new['title'] ?? null,
            ':old_content' => $old['content'] ?? null,
            ':new_content' => $new['content'] ?? null,
            ':old_section_id' => $old['section_id'] ?? null,
            ':new_section_id' => $new['section_id'] ?? null,
            ':old_status' => $old['status'] ?? null,
            ':new_status' => $new['status'] ?? null,
        ]);
    }

    public function byThreadId(int $threadId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT l.*, u.nickname AS editor_name FROM thread_edit_logs l LEFT JOIN users u ON u.id = l.editor_id WHERE l.thread_id = :thread_id ORDER BY l.created_at DESC, l.id DESC"
        );
        $stmt->execute([':thread_id' => $threadId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
