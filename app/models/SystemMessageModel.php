<?php

namespace App\Models;

use App\Core\Database;

class SystemMessageModel
{
    private \PDO $db;
    private ?bool $linkColumnsAvailable = null;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function all(): array
    {
        return $this->db->query(
            "SELECT m.*, u.username as creator_name FROM system_messages m
             LEFT JOIN users u ON u.id = m.created_by
             ORDER BY m.id DESC LIMIT 500"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM system_messages WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createPersonal(int $userId, string $title, string $content, int $priority = 0, ?string $category = null, string $linkUrl = '', string $refType = '', int $refId = 0): int
    {
        return $this->create([
            'title' => $title,
            'content' => $content,
            'priority' => $priority,
            'target_type' => 'user',
            'target_users' => [$userId],
            'status' => 'active',
            'category' => $category ?: $this->guessCategory($title, $content),
            'link_url' => $linkUrl,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'created_by' => 0,
        ]);
    }

    public function create(array $data): int
    {
        $hasLinkColumns = $this->hasLinkColumns();
        $stmt = $this->db->prepare(
            $hasLinkColumns
                ? "INSERT INTO system_messages (title, content, link_url, ref_type, ref_id, priority, category, target_type, target_roles, target_users, status, sent_at, created_by)
                   VALUES (:title, :content, :link_url, :ref_type, :ref_id, :priority, :category, :target_type, :target_roles, :target_users, :status, :sent_at, :created_by)"
                : "INSERT INTO system_messages (title, content, priority, category, target_type, target_roles, target_users, status, sent_at, created_by)
                   VALUES (:title, :content, :priority, :category, :target_type, :target_roles, :target_users, :status, :sent_at, :created_by)"
        );
        $params = [
            ':title'        => $data['title'],
            ':content'      => $data['content'],
            ':priority'     => $data['priority'] ?? 0,
            ':category'     => $data['category'] ?? $this->guessCategory((string)($data['title'] ?? ''), (string)($data['content'] ?? '')),
            ':target_type'  => $data['target_type'] ?? 'all',
            ':target_roles' => isset($data['target_roles']) ? json_encode(array_map('intval', (array)$data['target_roles'])) : null,
            ':target_users' => isset($data['target_users']) ? json_encode(array_map('intval', (array)$data['target_users'])) : null,
            ':status'       => $data['status'] ?? 'active',
            ':sent_at'      => ($data['status'] ?? 'active') === 'active' ? date('Y-m-d H:i:s') : null,
            ':created_by'   => $data['created_by'],
        ];
        if ($hasLinkColumns) {
            $params[':link_url'] = trim((string)($data['link_url'] ?? '')) ?: null;
            $params[':ref_type'] = trim((string)($data['ref_type'] ?? '')) ?: null;
            $params[':ref_id'] = !empty($data['ref_id']) ? (int)$data['ref_id'] : null;
        }
        $stmt->execute($params);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            "UPDATE system_messages SET title=:title, content=:content, priority=:priority, category=:category,
             target_type=:target_type, target_roles=:target_roles, target_users=:target_users,
             status=:status, sent_at=:sent_at WHERE id=:id"
        );
        $stmt->execute([
            ':title'        => $data['title'],
            ':content'      => $data['content'],
            ':priority'     => $data['priority'] ?? 0,
            ':category'     => $data['category'] ?? $this->guessCategory((string)($data['title'] ?? ''), (string)($data['content'] ?? '')),
            ':target_type'  => $data['target_type'] ?? 'all',
            ':target_roles' => isset($data['target_roles']) ? json_encode(array_map('intval', (array)$data['target_roles'])) : null,
            ':target_users' => isset($data['target_users']) ? json_encode(array_map('intval', (array)$data['target_users'])) : null,
            ':status'       => $data['status'] ?? 'active',
            ':sent_at'      => ($data['status'] ?? 'active') === 'active' ? date('Y-m-d H:i:s') : null,
            ':id'           => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM system_messages WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    

    public function unreadCount(int $userId, array $roleIds = []): int
    {
        $roles = $roleIds !== [] ? $roleIds : $this->roleIdsForUser($userId);
        $conditions = $this->visibilityConditions($roles, $userId);
        $params = $conditions['params'] + [':user_id' => $userId];

        $sql = "SELECT COUNT(*) FROM system_messages m
                WHERE m.status = 'active'
                AND m.sent_at IS NOT NULL
                AND ({$conditions['sql']})
                AND m.id NOT IN (
                    SELECT message_id FROM user_message_reads WHERE user_id = :user_id
                )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    

    public function unreadForUser(int $userId): array
    {
        $roles = $this->roleIdsForUser($userId);
        $conditions = $this->visibilityConditions($roles, $userId);
        $params = $conditions['params'] + [':user_id' => $userId];

        $stmt = $this->db->prepare(
            "SELECT m.* FROM system_messages m
             WHERE m.status = 'active'
             AND m.sent_at IS NOT NULL
             AND ({$conditions['sql']})
             AND m.id NOT IN (
                 SELECT message_id FROM user_message_reads WHERE user_id = :user_id
             )
             ORDER BY m.priority DESC, m.sent_at DESC
             LIMIT 50"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    

    public function listForUser(int $userId): array
    {
        return $this->listForUserByCategory($userId, '', 'all', 100, 0);
    }

    public function listForUserByCategory(int $userId, string $category = '', string $box = 'unread', int $limit = 20, int $offset = 0, string $keyword = ''): array
    {
        $roles = $this->roleIdsForUser($userId);
        $conditions = $this->visibilityConditions($roles, $userId);
        $params = $conditions['params'] + [':user_id' => $userId];
        [$extraSql, $extraParams] = $this->messageFilterSql($category, $box, $keyword);
        $params += $extraParams;
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $stmt = $this->db->prepare(
            "SELECT DISTINCT m.*, r.read_at
             FROM system_messages m
             LEFT JOIN user_message_reads r ON r.message_id = m.id AND r.user_id = :user_id
             WHERE m.status = 'active'
             AND m.sent_at IS NOT NULL
             AND ({$conditions['sql']})
             {$extraSql}
             ORDER BY m.priority DESC, m.sent_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countForUserByCategory(int $userId, string $category = '', string $box = 'unread', string $keyword = ''): int
    {
        $roles = $this->roleIdsForUser($userId);
        $conditions = $this->visibilityConditions($roles, $userId);
        $params = $conditions['params'] + [':user_id' => $userId];
        [$extraSql, $extraParams] = $this->messageFilterSql($category, $box, $keyword);
        $params += $extraParams;
        $stmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT m.id)
             FROM system_messages m
             LEFT JOIN user_message_reads r ON r.message_id = m.id AND r.user_id = :user_id
             WHERE m.status = 'active'
             AND m.sent_at IS NOT NULL
             AND ({$conditions['sql']})
             {$extraSql}"
        );
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    private function messageFilterSql(string $category, string $box, string $keyword = ''): array
    {
        $sql = '';
        $params = [];
        if ($category !== '') {
            $knownCategories = ['fans','reply','like','favorite','private','review','finance','system'];
            if ($category === 'system') {
                $placeholders = [];
                foreach ($knownCategories as $i => $known) {
                    if ($known === 'system') continue;
                    $key = ':known_category_' . $i;
                    $placeholders[] = $key;
                    $params[$key] = $known;
                }
                $sql .= " AND (COALESCE(NULLIF(m.category, ''), 'system') = 'system' OR COALESCE(NULLIF(m.category, ''), 'system') NOT IN (" . implode(',', $placeholders) . "))";
            } else {
                $sql .= " AND COALESCE(NULLIF(m.category, ''), 'system') = :category";
                $params[':category'] = $category;
            }
        }
        $keyword = trim($keyword);
        if ($keyword !== '') {
            $sql .= " AND (m.title LIKE :keyword OR m.content LIKE :keyword)";
            $params[':keyword'] = '%' . $keyword . '%';
        }
        if ($box === 'history') {
            $sql .= " AND r.read_at IS NOT NULL";
        } elseif ($box === 'unread') {
            $sql .= " AND r.read_at IS NULL";
        }
        return [$sql, $params];
    }

    private function roleIdsForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT role_id FROM user_roles
             WHERE user_id = :user_id
             AND (expires_at IS NULL OR expires_at > NOW())"
        );
        $stmt->execute([':user_id' => $userId]);
        return array_values(array_unique(array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN))));
    }

    private function visibilityConditions(array $roleIds, int $userId): array
    {
        $sql = "m.target_type = 'all' OR (m.target_type = 'user' AND JSON_CONTAINS(m.target_users, :uid_json))";
        $params = [':uid_json' => (string) $userId];

        if (!empty($roleIds)) {
            $roleParts = [];
            foreach (array_values($roleIds) as $i => $roleId) {
                $key = ':role_json_' . $i;
                $roleParts[] = "JSON_CONTAINS(m.target_roles, {$key})";
                $params[$key] = (string) (int) $roleId;
            }
            $sql .= " OR (m.target_type = 'role' AND (" . implode(' OR ', $roleParts) . "))";
        }

        return ['sql' => $sql, 'params' => $params];
    }

    

    public function markRead(int $userId, int $messageId): void
    {
        if (!$this->isVisibleToUser($userId, $messageId)) {
            return;
        }

        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO user_message_reads (user_id, message_id) VALUES (:user_id, :message_id)"
        );
        $stmt->execute([':user_id' => $userId, ':message_id' => $messageId]);
    }

    private function isVisibleToUser(int $userId, int $messageId): bool
    {
        $roles = $this->roleIdsForUser($userId);
        $conditions = $this->visibilityConditions($roles, $userId);
        $params = $conditions['params'] + [':message_id' => $messageId];

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM system_messages m
             WHERE m.id = :message_id
             AND m.status = 'active'
             AND m.sent_at IS NOT NULL
             AND ({$conditions['sql']})"
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    

    public function markAllRead(int $userId, string $category = ''): void
    {
        $roles = $this->roleIdsForUser($userId);
        $conditions = $this->visibilityConditions($roles, $userId);
        $params = $conditions['params'] + [':user_id' => $userId, ':user_id2' => $userId];
        $categorySql = '';
        if ($category !== '') {
            $categorySql = " AND COALESCE(NULLIF(m.category, ''), 'system') = :category";
            $params[':category'] = $category;
        }

        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO user_message_reads (user_id, message_id)
             SELECT :user_id, m.id FROM system_messages m
             WHERE m.status = 'active'
             AND m.sent_at IS NOT NULL
             AND ({$conditions['sql']})
             {$categorySql}
             AND m.id NOT IN (SELECT message_id FROM user_message_reads WHERE user_id = :user_id2)"
        );
        $stmt->execute($params);
    }



    public function clearHistory(int $userId, string $category = ''): void
    {
        
        
        $this->markAllRead($userId, $category);
    }

    public function unreadCountsByCategory(int $userId): array
    {
        $roles = $this->roleIdsForUser($userId);
        $conditions = $this->visibilityConditions($roles, $userId);
        $params = $conditions['params'] + [':user_id' => $userId];

        $stmt = $this->db->prepare(
            "SELECT COALESCE(NULLIF(m.category, ''), 'system') AS category, COUNT(DISTINCT m.id) AS total
             FROM system_messages m
             WHERE m.status = 'active'
             AND m.sent_at IS NOT NULL
             AND ({$conditions['sql']})
             AND m.id NOT IN (
                 SELECT message_id FROM user_message_reads WHERE user_id = :user_id
             )
             GROUP BY COALESCE(NULLIF(m.category, ''), 'system')"
        );
        $stmt->execute($params);
        $counts = ['all' => 0, 'fans' => 0, 'reply' => 0, 'like' => 0, 'favorite' => 0, 'private' => 0, 'review' => 0, 'finance' => 0, 'system' => 0];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $category = (string)($row['category'] ?? 'system');
            if (!isset($counts[$category])) $category = 'system';
            $counts[$category] += (int)($row['total'] ?? 0);
            $counts['all'] += (int)($row['total'] ?? 0);
        }
        return $counts;
    }


    private function hasLinkColumns(): bool
    {
        if ($this->linkColumnsAvailable !== null) return $this->linkColumnsAvailable;
        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM system_messages LIKE 'link_url'");
            $this->linkColumnsAvailable = (bool)$stmt->fetch();
        } catch (\Throwable $e) {
            $this->linkColumnsAvailable = false;
        }
        return $this->linkColumnsAvailable;
    }

    public function guessCategory(string $title, string $content = ''): string
    {
        $text = $title . ' ' . $content;
        if (str_contains($text, '粉丝') || str_contains($text, '关注')) return 'fans';
        if (str_contains($text, '回复') || str_contains($text, '提到') || str_contains($text, '@')) return 'reply';
        if (str_contains($text, '点赞') || str_contains($text, '赞了')) return 'like';
        if (str_contains($text, '收藏')) return 'favorite';
        if (str_contains($text, '私聊') || str_contains($text, '私信')) return 'private';
        if (str_contains($text, '审核') || str_contains($text, '退回') || str_contains($text, '驳回') || str_contains($text, '认证')) return 'review';
        if (str_contains($text, '财务') || str_contains($text, '钱包') || str_contains($text, '余额') || str_contains($text, '收入') || str_contains($text, '消费') || str_contains($text, '打赏')) return 'finance';
        return 'system';
    }

    

    public function latestReadAtForCategory(int $userId, string $category): ?string
    {
        $roles = $this->roleIdsForUser($userId);
        $conditions = $this->visibilityConditions($roles, $userId);
        $params = $conditions['params'] + [':user_id' => $userId, ':category' => $category];
        $stmt = $this->db->prepare(
            "SELECT MAX(r.read_at)
             FROM user_message_reads r
             INNER JOIN system_messages m ON m.id = r.message_id
             WHERE r.user_id = :user_id
             AND m.status = 'active'
             AND m.sent_at IS NOT NULL
             AND COALESCE(NULLIF(m.category, ''), 'system') = :category
             AND ({$conditions['sql']})"
        );
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value ? (string)$value : null;
    }

    

    public function unreadCountSimple(int $userId): int
    {
        return $this->unreadCount($userId);
    }
}
