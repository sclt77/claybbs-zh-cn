<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class AdminReportModel
{
    public function all(string $status = ''): array
    {
        $where = $status !== '' ? 'WHERE r.status = :status' : 'WHERE 1=1';
        $sql = "SELECT r.*, u.username, u.nickname,
                       handler.username AS handler_username, handler.nickname AS handler_nickname,
                       t.title AS thread_title, t.content AS thread_content, t.status AS thread_status, t.user_id AS thread_user_id, t.section_id AS thread_section_id, tu.nickname AS thread_author_name, tu.username AS thread_author_username,
                       p.thread_id AS post_thread_id, p.content AS post_content, p.status AS post_status, p.user_id AS post_user_id, pt.section_id AS post_section_id, pu.nickname AS post_author_name, pu.username AS post_author_username,
                       pt.title AS post_thread_title, pt.status AS post_thread_status,
                       pm.content AS private_content, pm.status AS private_status, pm.sender_id AS private_sender_id, pm.receiver_id AS private_receiver_id, ps.nickname AS private_sender_name, ps.username AS private_sender_username, pr.nickname AS private_receiver_name, pr.username AS private_receiver_username,
                       (SELECT COUNT(*) FROM content_reports rr WHERE rr.target_type=r.target_type AND rr.target_id=r.target_id) AS same_target_count
                FROM content_reports r
                LEFT JOIN users u ON u.id=r.user_id
                LEFT JOIN users handler ON handler.id=r.handled_by
                LEFT JOIN threads t ON r.target_type='thread' AND t.id=r.target_id
                LEFT JOIN users tu ON tu.id=t.user_id
                LEFT JOIN posts p ON r.target_type='post' AND p.id=r.target_id
                LEFT JOIN users pu ON pu.id=p.user_id
                LEFT JOIN threads pt ON pt.id=p.thread_id
                LEFT JOIN private_messages pm ON r.target_type='private_message' AND pm.id=r.target_id
                LEFT JOIN users ps ON ps.id=pm.sender_id
                LEFT JOIN users pr ON pr.id=pm.receiver_id
                {$where}
                ORDER BY FIELD(r.status,'pending','processing','resolved','rejected'), r.id DESC LIMIT 300";
        $stmt = Database::connection()->prepare($sql);
        if ($status !== '') $stmt->bindValue(':status', $status);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT r.*, u.username, u.nickname,
                       t.title AS thread_title, t.content AS thread_content, t.status AS thread_status, t.user_id AS thread_user_id, t.section_id AS thread_section_id, tu.nickname AS thread_author_name, tu.username AS thread_author_username,
                       p.thread_id AS post_thread_id, p.content AS post_content, p.status AS post_status, p.user_id AS post_user_id, pt.section_id AS post_section_id, pu.nickname AS post_author_name, pu.username AS post_author_username,
                       pt.title AS post_thread_title, pt.status AS post_thread_status,
                       pm.content AS private_content, pm.status AS private_status, pm.sender_id AS private_sender_id, pm.receiver_id AS private_receiver_id, ps.nickname AS private_sender_name, ps.username AS private_sender_username, pr.nickname AS private_receiver_name, pr.username AS private_receiver_username
                FROM content_reports r
                LEFT JOIN users u ON u.id=r.user_id
                LEFT JOIN threads t ON r.target_type='thread' AND t.id=r.target_id
                LEFT JOIN users tu ON tu.id=t.user_id
                LEFT JOIN posts p ON r.target_type='post' AND p.id=r.target_id
                LEFT JOIN users pu ON pu.id=p.user_id
                LEFT JOIN threads pt ON pt.id=p.thread_id
                LEFT JOIN private_messages pm ON r.target_type='private_message' AND pm.id=r.target_id
                LEFT JOIN users ps ON ps.id=pm.sender_id
                LEFT JOIN users pr ON pr.id=pm.receiver_id
                WHERE r.id=:id LIMIT 1";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function stats(): array
    {
        $rows = Database::connection()->query("SELECT status, COUNT(*) AS total FROM content_reports GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
        $stats = ['pending' => 0, 'processing' => 0, 'resolved' => 0, 'rejected' => 0, 'all' => 0];
        foreach ($rows as $row) {
            $key = (string)($row['status'] ?? '');
            $count = (int)($row['total'] ?? 0);
            if (array_key_exists($key, $stats)) $stats[$key] = $count;
            $stats['all'] += $count;
        }
        return $stats;
    }

    public function handle(int $id, string $status, string $note, int $adminId, string $targetAction = ''): void
    {
        if (!in_array($status, ['pending','processing','resolved','rejected'], true)) return;
        $oldStatus = (string)(Database::connection()->query('SELECT status FROM content_reports WHERE id=' . (int)$id . ' LIMIT 1')->fetchColumn() ?: '');
        $hasEnhanced = $this->hasColumn('content_reports', 'resolution');
        if ($hasEnhanced) {
            Database::connection()->prepare("UPDATE content_reports SET status=:status, admin_note=:note, resolution=:resolution, target_action=:target_action, handled_by=:admin, handled_at=NOW() WHERE id=:id")
                ->execute([':status'=>$status, ':note'=>$note, ':resolution'=>$status, ':target_action'=>$targetAction ?: null, ':admin'=>$adminId, ':id'=>$id]);
        } else {
            Database::connection()->prepare("UPDATE content_reports SET status=:status, admin_note=:note, handled_by=:admin, handled_at=NOW() WHERE id=:id")
                ->execute([':status'=>$status, ':note'=>$note, ':admin'=>$adminId, ':id'=>$id]);
        }
        $this->log($id, $adminId, $targetAction !== '' ? $targetAction : ('status_' . $status), $note, ['from'=>$oldStatus, 'to'=>$status]);
    }

    public function log(int $reportId, int $operatorId, string $action, string $note = '', array $payload = []): void
    {
        try {
            Database::connection()->prepare("INSERT INTO content_report_logs (report_id,operator_id,action,note,payload,created_at) VALUES (:rid,:uid,:action,:note,:payload,NOW())")
                ->execute([':rid'=>$reportId, ':uid'=>$operatorId ?: null, ':action'=>$action, ':note'=>$note, ':payload'=>$payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null]);
        } catch (\Throwable $e) {}
    }

    private function hasColumn(string $table, string $column): bool
    {
        try { $stmt = Database::connection()->query("SHOW COLUMNS FROM `{$table}` LIKE " . Database::connection()->quote($column)); return (bool)$stmt->fetch(); }
        catch (\Throwable $e) { return false; }
    }
}
