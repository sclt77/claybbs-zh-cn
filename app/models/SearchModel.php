<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class SearchModel
{
    

    public function searchThreads(string $keyword, int $limit = 20, int $offset = 0, int $sectionId = 0, string $type = 'thread', string $sort = 'relevance'): array
    {
        $keyword = trim($keyword);
        $kw = '%' . $keyword . '%';
        $boolean = $this->booleanKeyword($keyword);
        $order = $sort === 'hot'
            ? "(t.like_count*3+t.reply_count*2+t.view_count*0.02) DESC, t.created_at DESC"
            : ($sort === 'new' ? "t.created_at DESC" : "score DESC, t.created_at DESC");
        $sql = "SELECT t.id, t.user_id, t.section_id, t.title, t.summary, t.content, t.cover,
                       t.reply_count, t.view_count, t.like_count,
                       t.is_top, t.is_featured, t.is_recommended, t.is_locked, t.created_at,
                       MATCH(t.title,t.content) AGAINST(:match IN BOOLEAN MODE) AS score,
                       u.nickname AS author_name, u.avatar AS author_avatar, COALESCE(NULLIF(uv.display_name,''), vt.name) AS verification_name, vt.color AS verification_color, vt.description AS verification_description,
                       s.name AS section_name,
                       (SELECT r.name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=t.user_id AND (ur.expires_at IS NULL OR ur.expires_at > NOW()) ORDER BY r.level DESC LIMIT 1) AS author_role_label
                FROM threads t
                LEFT JOIN users u ON u.id = t.user_id
                LEFT JOIN user_verifications uv ON uv.user_id=t.user_id AND uv.status='active'
                LEFT JOIN verification_types vt ON vt.id=uv.type_id AND vt.status='active'
                LEFT JOIN sections s ON s.id = t.section_id
                WHERE t.status = 'published'
                  AND (:section_id = 0 OR t.section_id = :section_id)
                  AND (MATCH(t.title,t.content) AGAINST(:match2 IN BOOLEAN MODE) OR t.title LIKE :kw1 OR t.content LIKE :kw2 OR (:type = 'user' AND (u.nickname LIKE :kw3 OR u.username LIKE :kw4)) OR (:type = 'section' AND s.name LIKE :kw5))
                ORDER BY {$order}
                LIMIT :limit OFFSET :offset";
        $stmt = Database::connection()->prepare($sql);
        foreach([':match',':match2'] as $m) $stmt->bindValue($m, $boolean, PDO::PARAM_STR);
        $stmt->bindValue(':kw1', $kw, PDO::PARAM_STR); $stmt->bindValue(':kw2', $kw, PDO::PARAM_STR); $stmt->bindValue(':kw3', $kw, PDO::PARAM_STR); $stmt->bindValue(':kw4', $kw, PDO::PARAM_STR); $stmt->bindValue(':kw5', $kw, PDO::PARAM_STR);
        $stmt->bindValue(':section_id', $sectionId, PDO::PARAM_INT); $stmt->bindValue(':type', $type, PDO::PARAM_STR); $stmt->bindValue(':limit', $limit, PDO::PARAM_INT); $stmt->bindValue(':offset', $offset, PDO::PARAM_INT); $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countThreads(string $keyword, int $sectionId = 0, string $type = 'thread'): int
    {
        $keyword = trim($keyword);
        $kw = '%' . $keyword . '%';
        $boolean = $this->booleanKeyword($keyword);
        $sql = "SELECT COUNT(*) FROM threads t LEFT JOIN users u ON u.id=t.user_id LEFT JOIN sections s ON s.id=t.section_id
                WHERE t.status = 'published'
                  AND (:section_id = 0 OR t.section_id = :section_id)
                  AND (MATCH(t.title,t.content) AGAINST(:match IN BOOLEAN MODE) OR t.title LIKE :kw1 OR t.content LIKE :kw2 OR (:type = 'user' AND (u.nickname LIKE :kw3 OR u.username LIKE :kw4)) OR (:type = 'section' AND s.name LIKE :kw5))";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':match', $boolean, PDO::PARAM_STR); $stmt->bindValue(':kw1', $kw, PDO::PARAM_STR); $stmt->bindValue(':kw2', $kw, PDO::PARAM_STR); $stmt->bindValue(':kw3', $kw, PDO::PARAM_STR); $stmt->bindValue(':kw4', $kw, PDO::PARAM_STR); $stmt->bindValue(':kw5', $kw, PDO::PARAM_STR); $stmt->bindValue(':section_id', $sectionId, PDO::PARAM_INT); $stmt->bindValue(':type', $type, PDO::PARAM_STR); $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function hotKeywords(int $limit = 8): array
    {
        return Database::connection()->query("SELECT title FROM threads WHERE status='published' ORDER BY reply_count DESC, view_count DESC, id DESC LIMIT " . max(1, min(20, $limit)))->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    private function booleanKeyword(string $keyword): string
    {
        $parts = preg_split('/\s+/u', trim($keyword)) ?: [];
        $parts = array_values(array_filter(array_map(static fn($w)=>preg_replace('/[^\p{L}\p{N}_-]+/u','',$w), $parts)));
        if (!$parts) $parts = [$keyword];
        return implode(' ', array_map(static fn($w)=>'+' . $w . '*', $parts));
    }

    public function sections(): array
    {
        return Database::connection()->query("SELECT id,name FROM sections WHERE status='active' ORDER BY sort_order ASC,id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
