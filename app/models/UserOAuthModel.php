<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class UserOAuthModel
{
    public function byProviderAccount(string $provider, string $openid): ?array
    {
        $stmt = Database::connection()->prepare("SELECT oa.*, u.username, u.nickname, u.email, u.status FROM user_oauth_accounts oa JOIN users u ON u.id=oa.user_id WHERE oa.provider=:provider AND oa.openid=:openid LIMIT 1");
        $stmt->execute([':provider' => $provider, ':openid' => $openid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function byUser(int $userId): array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM user_oauth_accounts WHERE user_id=:uid ORDER BY bound_at DESC,id DESC");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function byUserProvider(int $userId, string $provider): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM user_oauth_accounts WHERE user_id=:uid AND provider=:provider LIMIT 1");
        $stmt->execute([':uid' => $userId, ':provider' => $provider]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function bind(int $userId, string $provider, array $profile, string $tokenJson = ''): void
    {
        $openid = trim((string)($profile['openid'] ?? ''));
        if ($userId <= 0 || $provider === '' || $openid === '') {
            throw new \RuntimeException('第三方账号信息不完整');
        }
        $stmt = Database::connection()->prepare("INSERT INTO user_oauth_accounts (user_id,provider,openid,unionid,nickname,avatar,email,access_token_json,bound_at,last_login_at,created_at,updated_at)
            VALUES (:uid,:provider,:openid,:unionid,:nickname,:avatar,:email,:token,NOW(),NOW(),NOW(),NOW())
            ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), openid=VALUES(openid), unionid=VALUES(unionid), nickname=VALUES(nickname), avatar=VALUES(avatar), email=VALUES(email), access_token_json=VALUES(access_token_json), last_login_at=NOW(), updated_at=NOW()");
        $stmt->execute([
            ':uid' => $userId,
            ':provider' => $provider,
            ':openid' => $openid,
            ':unionid' => trim((string)($profile['unionid'] ?? '')) ?: null,
            ':nickname' => trim((string)($profile['nickname'] ?? '')),
            ':avatar' => trim((string)($profile['avatar'] ?? '')),
            ':email' => trim((string)($profile['email'] ?? '')) ?: null,
            ':token' => $tokenJson,
        ]);
    }

    public function touchLogin(int $id, string $tokenJson = ''): void
    {
        $stmt = Database::connection()->prepare("UPDATE user_oauth_accounts SET access_token_json=:token,last_login_at=NOW(),updated_at=NOW() WHERE id=:id");
        $stmt->execute([':token' => $tokenJson, ':id' => $id]);
    }

    public function unbind(int $userId, string $provider): void
    {
        Database::connection()->prepare("DELETE FROM user_oauth_accounts WHERE user_id=:uid AND provider=:provider")
            ->execute([':uid' => $userId, ':provider' => $provider]);
    }

    public function hasAnyBinding(int $userId): bool
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM user_oauth_accounts WHERE user_id=:uid");
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
