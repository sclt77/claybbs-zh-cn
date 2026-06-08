<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class UserModel
{
    public function ensureProfileColumns(): void
    {
        
    }

    public function findByAccount(string $account): ?array
    {
        $this->ensureProfileColumns();
        $sql = "SELECT * FROM users WHERE username = :account OR email = :account OR public_id = :account LIMIT 1";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':account', $account, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function find(int $id): ?array
    {
        $this->ensureProfileColumns();
        $stmt = Database::connection()->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function refreshAuthUser(int $id): ?array
    {
        $this->ensureProfileColumns();
        $sql = "SELECT * FROM users WHERE id = :id LIMIT 1";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function existsByUsername(string $username): bool
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function existsByEmail(string $email): bool
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function generatePublicId(string $prefix = 'CY'): string
    {
        $prefix = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($prefix)) ?: 'CY';
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM users WHERE public_id = :public_id");
        do {
            $id = $prefix . str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            $stmt->execute([':public_id' => $id]);
        } while ((int)$stmt->fetchColumn() > 0);
        return $id;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO users (public_id, username, nickname, email, password, bio, status, email_verified, email_verify_token, email_verify_expires_at) VALUES (:public_id, :username, :nickname, :email, :password, :bio, 'active', :email_verified, :email_verify_token, :email_verify_expires_at)";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            ':public_id'               => $data['public_id'] ?? $this->generatePublicId((new SettingModel())->get('friend_id_prefix', 'CY') ?: 'CY'),
            ':username'                => $data['username'],
            ':nickname'               => $data['nickname'],
            ':email'                  => $data['email'],
            ':password'               => $data['password'],
            ':bio'                    => $data['bio'] ?? '',
            ':email_verified'         => $data['email_verified'] ?? 0,
            ':email_verify_token'     => $data['email_verify_token'] ?? null,
            ':email_verify_expires_at' => $data['email_verify_expires_at'] ?? null,
        ]);
        $userId = (int) Database::connection()->lastInsertId();
        $this->assignDefaultRole($userId, (string)($data['role'] ?? 'user'));
        try {
            (new WalletModel())->ensureWallets($userId);
        } catch (\Throwable $e) {
            // Registration should not fail if an old install is still migrating wallet tables.
        }
        return $userId;
    }


    public function assignDefaultRole(int $userId, string $roleSlug = 'user'): void
    {
        $roleSlug = in_array($roleSlug, ['admin','superadmin','moderator','reviewer','developer','user'], true) ? $roleSlug : 'user';
        $stmt = Database::connection()->prepare("SELECT id FROM roles WHERE slug=:slug LIMIT 1");
        $stmt->execute([':slug' => $roleSlug]);
        $roleId = (int)$stmt->fetchColumn();
        if ($roleId <= 0 && $roleSlug !== 'user') {
            $stmt->execute([':slug' => 'user']);
            $roleId = (int)$stmt->fetchColumn();
        }
        if ($roleId > 0) {
            Database::connection()->prepare("INSERT IGNORE INTO user_roles (user_id, role_id, scope, scope_id, granted_by, expires_at) VALUES (:uid, :rid, 'global', 0, NULL, NULL)")
                ->execute([':uid' => $userId, ':rid' => $roleId]);
        }
    }

    public function findByVerifyToken(string $token): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM users WHERE email_verify_token = :token LIMIT 1");
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function markEmailVerified(int $id): void
    {
        $stmt = Database::connection()->prepare("UPDATE users SET email_verified = 1, email_verify_token = NULL WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $stmt = Database::connection()->prepare("UPDATE users SET password = :password WHERE id = :id");
        $stmt->execute([':password' => $hash, ':id' => $id]);
    }

    public function update(int $id, array $data): void
    {
        $this->ensureProfileColumns();
        $allowed = ['nickname', 'bio', 'avatar', 'cover', 'password'];
        $sets = [];
        $params = [':id' => $id];
        foreach ($data as $k => $v) {
            if (!in_array($k, $allowed, true)) {
                continue;
            }
            $sets[] = "`{$k}` = :{$k}";
            $params[":{$k}"] = $v;
        }
        if (!$sets) {
            return;
        }
        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id';
        Database::connection()->prepare($sql)->execute($params);
    }

    public function threadsByUserId(int $userId, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT t.id, t.user_id, t.section_id, t.title, t.summary, t.content, t.cover, t.reply_count, t.view_count, t.like_count, t.is_top, t.is_featured, t.is_recommended, t.is_locked, t.question_status, t.bounty_currency, t.bounty_amount, t.accepted_post_id, t.created_at, s.name AS section_name, s.is_question AS section_is_question, u.nickname AS author_name, u.avatar AS author_avatar, COALESCE(NULLIF(uv.display_name,''), vt.name) AS verification_name, vt.color AS verification_color, vt.description AS verification_description,
                       (SELECT r.name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=t.user_id AND (ur.expires_at IS NULL OR ur.expires_at > NOW()) ORDER BY r.level DESC LIMIT 1) AS author_role_label
                FROM threads t
                LEFT JOIN users u ON u.id = t.user_id
                LEFT JOIN user_verifications uv ON uv.user_id=t.user_id AND uv.status='active'
                LEFT JOIN verification_types vt ON vt.id=uv.type_id AND vt.status='active'
                LEFT JOIN sections s ON s.id = t.section_id
                WHERE t.user_id = :uid AND t.status = 'published'
                ORDER BY t.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countThreadsByUserId(int $userId): int
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM threads WHERE user_id = :uid AND status = 'published'");
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function pendingThreadsByUserId(int $userId, int $limit = 20, int $offset = 0): array
    {
        return (new ThreadRevisionModel())->byUser($userId, $limit, $offset);
    }

    public function countPendingThreadsByUserId(int $userId): int
    {
        return (new ThreadRevisionModel())->countByUser($userId);
    }

    public function repliesByUserId(int $userId, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT p.id, p.content, p.created_at, t.id AS thread_id, t.title AS thread_title
                FROM posts p
                LEFT JOIN threads t ON t.id = p.thread_id
                WHERE p.user_id = :uid AND p.status = 'published' AND t.status = 'published'
                ORDER BY p.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countRepliesByUserId(int $userId): int
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM posts p LEFT JOIN threads t ON t.id = p.thread_id WHERE p.user_id = :uid AND p.status = 'published' AND t.status = 'published'");
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM users WHERE email=:email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function ensurePasswordResetColumns(): void
    {
        try { Database::connection()->exec("ALTER TABLE users ADD COLUMN password_reset_token VARCHAR(64) NULL"); } catch (\Throwable $e) {}
        try { Database::connection()->exec("ALTER TABLE users ADD COLUMN password_reset_expires DATETIME NULL"); } catch (\Throwable $e) {}
    }

    public function setPasswordResetToken(int $userId, string $token, string $expires): void
    {
        $this->ensurePasswordResetColumns();
        $stmt = Database::connection()->prepare("UPDATE users SET password_reset_token=:token, password_reset_expires=:expires WHERE id=:id");
        $stmt->execute([':token' => $token, ':expires' => $expires, ':id' => $userId]);
    }

    public function findByResetToken(string $token): ?array
    {
        $this->ensurePasswordResetColumns();
        $stmt = Database::connection()->prepare(
            "SELECT * FROM users WHERE password_reset_token=:token AND password_reset_expires IS NOT NULL AND password_reset_expires>=NOW() LIMIT 1"
        );
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function resetPassword(int $userId, string $newPassword): void
    {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = Database::connection()->prepare("UPDATE users SET password=:password, password_reset_token=NULL, password_reset_expires=NULL WHERE id=:id");
        $stmt->execute([':password' => $hash, ':id' => $userId]);
    }

    public function clearPasswordResetToken(int $userId): void
    {
        $stmt = Database::connection()->prepare("UPDATE users SET password_reset_token=NULL, password_reset_expires=NULL WHERE id=:id");
        $stmt->execute([':id' => $userId]);
    }
}

