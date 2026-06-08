<?php

namespace App\Services;

use App\Core\Database;
use App\Models\UserCreditModel;

class UserDailyRefreshService
{
    private const MIN_INTERVAL_SECONDS = 300;

    public function touch(int $userId, string $source = 'web', bool $skipOnFreshLogin = false): void
    {
        if ($userId <= 0 || !$this->shouldRunForRequest()) return;
        if ($skipOnFreshLogin && $this->isFreshLogin($userId)) return;
        $now = time();
        $sessionKey = 'user_daily_refresh_at_' . $userId;
        if (!empty($_SESSION[$sessionKey]) && ($now - (int)$_SESSION[$sessionKey]) < self::MIN_INTERVAL_SECONDS) {
            return;
        }
        $_SESSION[$sessionKey] = $now;

        try {
            (new TaskService())->syncStateTasks($userId);
            try { (new \App\Services\MedalService())->checkAuto($userId, '每日刷新触发检查'); } catch (\Throwable $e) {}
        } catch (\Throwable $e) {
            error_log('[ClayBBS] daily task refresh failed: ' . $e->getMessage());
        }

        try {
            (new UserCreditModel())->applyRecovery($userId);
        } catch (\Throwable $e) {
            error_log('[ClayBBS] credit recovery touch failed: ' . $e->getMessage());
        }

        try {
            if ($this->shouldPersistMarker($userId)) {
                $this->saveMarker($userId, $source);
            }
        } catch (\Throwable $e) {
            error_log('[ClayBBS] daily refresh marker failed: ' . $e->getMessage());
        }
    }

    private function isFreshLogin(int $userId): bool
    {
        $key = 'user_daily_refresh_login_skip_' . $userId;
        if (!empty($_SESSION[$key])) {
            unset($_SESSION[$key]);
            return true;
        }
        $_SESSION[$key] = 1;
        return false;
    }

    private function shouldRunForRequest(): bool
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'GET' && $method !== 'HEAD') return false;
        $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script !== 'index.php') return false;
        $path = trim((string)($_GET['path'] ?? ''), '/');
        if ($path === '') return true;
        if (str_starts_with($path, 'api/')) return false;
        if (str_starts_with($path, 'payment/notify')) return false;
        if (in_array($path, ['logout', 'install'], true)) return false;
        return true;
    }

    private function shouldPersistMarker(int $userId): bool
    {
        $key = 'user_daily_refresh_marker_' . $userId;
        $today = date('Y-m-d');
        if (($_SESSION[$key] ?? '') === $today) return false;
        $_SESSION[$key] = $today;
        return true;
    }

    private function saveMarker(int $userId, string $source): void
    {
        try {
            Database::connection()->prepare("INSERT INTO user_daily_refreshes (user_id, refresh_date, source, touched_at) VALUES (:uid, CURDATE(), :source, NOW()) ON DUPLICATE KEY UPDATE source=VALUES(source), touched_at=NOW()")
                ->execute([':uid' => $userId, ':source' => mb_substr($source, 0, 40)]);
        } catch (\Throwable $e) {
            try {
                Database::connection()->exec("CREATE TABLE IF NOT EXISTS user_daily_refreshes (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id BIGINT UNSIGNED NOT NULL,
                    refresh_date DATE NOT NULL,
                    source VARCHAR(40) NOT NULL DEFAULT 'web',
                    touched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_user_daily_refresh (user_id, refresh_date),
                    KEY idx_user_daily_refresh_date (refresh_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                Database::connection()->prepare("INSERT INTO user_daily_refreshes (user_id, refresh_date, source, touched_at) VALUES (:uid, CURDATE(), :source, NOW()) ON DUPLICATE KEY UPDATE source=VALUES(source), touched_at=NOW()")
                    ->execute([':uid' => $userId, ':source' => mb_substr($source, 0, 40)]);
            } catch (\Throwable $inner) {
                throw $e;
            }
        }
    }
}
