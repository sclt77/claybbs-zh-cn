<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class LoginDeviceModel
{
    public function recordCurrent(int $userId): void
    {
        if ($userId <= 0 || session_status() !== PHP_SESSION_ACTIVE) return;
        $hash = $this->currentSessionHash();
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $ip = $this->clientIp();
        $device = $this->deviceName($ua);
        $stmt = Database::connection()->prepare("INSERT INTO user_login_sessions (user_id,session_hash,device_name,user_agent,ip_address,login_at,last_active_at,created_at,updated_at)
            VALUES (:uid,:hash,:device,:ua,:ip,NOW(),NOW(),NOW(),NOW())
            ON DUPLICATE KEY UPDATE device_name=VALUES(device_name),user_agent=VALUES(user_agent),ip_address=VALUES(ip_address),revoked_at=NULL,last_active_at=NOW(),updated_at=NOW()");
        $stmt->execute([':uid'=>$userId, ':hash'=>$hash, ':device'=>$device, ':ua'=>$ua, ':ip'=>$ip]);
    }

    public function touchCurrent(int $userId): void
    {
        if ($userId <= 0 || session_status() !== PHP_SESSION_ACTIVE) return;
        $stmt = Database::connection()->prepare("UPDATE user_login_sessions SET last_active_at=NOW(),ip_address=:ip,updated_at=NOW() WHERE user_id=:uid AND session_hash=:hash AND revoked_at IS NULL");
        $stmt->execute([':uid'=>$userId, ':hash'=>$this->currentSessionHash(), ':ip'=>$this->clientIp()]);
    }

    public function currentRevoked(int $userId): bool
    {
        if ($userId <= 0 || session_status() !== PHP_SESSION_ACTIVE) return false;
        $stmt = Database::connection()->prepare("SELECT revoked_at FROM user_login_sessions WHERE user_id=:uid AND session_hash=:hash LIMIT 1");
        $stmt->execute([':uid'=>$userId, ':hash'=>$this->currentSessionHash()]);
        $value = $stmt->fetchColumn();
        return $value !== false && $value !== null && $value !== '';
    }

    public function revokeCurrent(int $userId): void
    {
        if ($userId <= 0 || session_status() !== PHP_SESSION_ACTIVE) return;
        $this->revokeByHash($userId, $this->currentSessionHash());
    }

    public function revoke(int $userId, int $id): bool
    {
        if ($userId <= 0 || $id <= 0) return false;
        $stmt = Database::connection()->prepare("UPDATE user_login_sessions SET revoked_at=NOW(),updated_at=NOW() WHERE id=:id AND user_id=:uid AND session_hash<>:current");
        $stmt->execute([':id'=>$id, ':uid'=>$userId, ':current'=>$this->currentSessionHash()]);
        return $stmt->rowCount() > 0;
    }

    public function rowsForUser(int $userId, int $limit = 80): array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM user_login_sessions WHERE user_id=:uid ORDER BY revoked_at IS NULL DESC,last_active_at DESC,id DESC LIMIT :limit");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $current = $this->currentSessionHash();
        foreach ($rows as &$row) $row['is_current'] = hash_equals($current, (string)$row['session_hash']);
        unset($row);
        return $rows;
    }

    private function revokeByHash(int $userId, string $hash): void
    {
        Database::connection()->prepare("UPDATE user_login_sessions SET revoked_at=NOW(),updated_at=NOW() WHERE user_id=:uid AND session_hash=:hash")
            ->execute([':uid'=>$userId, ':hash'=>$hash]);
    }

    private function currentSessionHash(): string
    {
        return hash('sha256', session_id() ?: '');
    }

    private function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
            $value = trim((string)($_SERVER[$key] ?? ''));
            if ($value === '') continue;
            $ip = trim(explode(',', $value)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
        return '';
    }

    private function deviceName(string $ua): string
    {
        $os = '未知系统';
        foreach (['iPhone'=>'iPhone','iPad'=>'iPad','Android'=>'Android','Windows'=>'Windows','Mac OS X'=>'macOS','Linux'=>'Linux'] as $needle=>$label) {
            if (stripos($ua, $needle) !== false) { $os = $label; break; }
        }
        $browser = '浏览器';
        foreach (['Edg'=>'Edge','Chrome'=>'Chrome','Firefox'=>'Firefox','Safari'=>'Safari','MicroMessenger'=>'微信','QQBrowser'=>'QQ浏览器'] as $needle=>$label) {
            if (stripos($ua, $needle) !== false) { $browser = $label; break; }
        }
        if ($browser === 'Safari' && stripos($ua, 'Chrome') !== false) $browser = 'Chrome';
        return $os . ' · ' . $browser;
    }
}
