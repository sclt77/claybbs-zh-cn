<?php

namespace App\Models;

use App\Core\Database;
use App\Middleware\Permission;
use PDO;
use App\Models\ThreadModel;

class AdminThreadModel
{
    public function list(string $keyword = '', ?int $userId = null, array $filters = [], int $page = 1, int $pageSize = 20): array
    {
        [$where, $params] = $this->buildWhere($keyword, $userId, $filters);
        $offset = max(0, ($page - 1) * $pageSize);
        $sql = "SELECT t.id, t.title, t.status, t.created_at, t.section_id, t.is_top, t.top_scope, t.is_featured, t.featured_reason, t.is_recommended, t.recommended_reason, t.is_locked, s.name AS section_name, s.category_id, u.nickname AS author_name
                FROM threads t
                LEFT JOIN sections s ON s.id = t.section_id
                LEFT JOIN users u ON u.id = t.user_id
                {$where}
                ORDER BY t.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(string $keyword = '', ?int $userId = null, array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($keyword, $userId, $filters);
        $sql = "SELECT COUNT(*) FROM threads t LEFT JOIN sections s ON s.id = t.section_id {$where}";
        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function updateStatus(int $id, string $status): void
    {
        (new ThreadModel())->updateStatus($id, $status);
    }

    public function updateModeration(int $id, array $flags): void
    {
        (new ThreadModel())->updateModeration($id, $flags);
    }

    public function deleteHard(int $id): void
    {
        (new ThreadModel())->deleteHard($id);
    }

    public function move(int $id, int $sectionId): void
    {
        $thread = (new ThreadModel())->find($id);
        if (!$thread) {
            return;
        }
        (new ThreadModel())->updateByAdmin($id, [
            'section_id' => $sectionId,
            'title' => (string)$thread['title'],
            'content' => (string)$thread['content'],
            'summary' => $thread['summary'] ?? mb_substr(strip_tags((string)$thread['content']), 0, 120),
            'status' => (string)$thread['status'],
        ]);
    }

    private function buildWhere(string $keyword, ?int $userId, array $filters): array
    {
        $where = "WHERE 1=1";
        $params = [];

        if ($keyword !== '') {
            $where .= " AND t.title LIKE :kw";
            $params[':kw'] = '%' . $keyword . '%';
        }
        $status = (string)($filters['status'] ?? '');
        if ($status !== '' && in_array($status, ['published', 'pending', 'hidden', 'deleted'], true)) {
            $where .= " AND t.status = :status";
            $params[':status'] = $status;
        }
        $sectionId = (int)($filters['section_id'] ?? 0);
        if ($sectionId > 0) {
            $where .= " AND t.section_id = :section_id";
            $params[':section_id'] = $sectionId;
        }

        if ($userId && !Permission::can('thread.edit_any') && !Permission::can('thread.delete_any') && !Permission::can('thread.hide') && !Permission::can('thread.pin') && !Permission::can('thread.feature') && !Permission::can('thread.recommend') && !Permission::can('thread.lock')) {
            $access = $this->getAccessibleSections($userId);
            if (empty($access['all']) && empty($access['sectionIds'])) {
                $where .= " AND 1=0";
            } elseif (empty($access['all']) && !empty($access['sectionIds'])) {
                $placeholders = [];
                foreach ($access['sectionIds'] as $index => $sid) {
                    $ph = ':sid_' . $index;
                    $placeholders[] = $ph;
                    $params[$ph] = (int)$sid;
                }
                $where .= " AND t.section_id IN (" . implode(',', $placeholders) . ")";
            }
        }

        return [$where, $params];
    }

    private function getAccessibleSections(int $userId): array
    {
        $roles = Permission::getUserRoles($userId);
        $sectionIds = [];
        $categoryIds = [];
        foreach ($roles as $role) {
            if (($role['scope'] ?? '') === 'section' && !empty($role['scope_id'])) {
                $sectionIds[] = (int) $role['scope_id'];
            }
            if (($role['scope'] ?? '') === 'category' && !empty($role['scope_id'])) {
                $categoryIds[] = (int) $role['scope_id'];
            }
        }
        $sectionIds = array_values(array_unique($sectionIds));
        $categoryIds = array_values(array_unique($categoryIds));
        if ($categoryIds) {
            $placeholders = [];
            $params = [];
            foreach ($categoryIds as $index => $cid) {
                $ph = ':cid_' . $index;
                $placeholders[] = $ph;
                $params[$ph] = $cid;
            }
            $sql = "SELECT id FROM sections WHERE category_id IN (" . implode(',', $placeholders) . ")";
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);
            $sectionIds = array_merge($sectionIds, array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
        }
        return [
            'all' => false,
            'sectionIds' => array_values(array_unique($sectionIds)),
        ];
    }
}
