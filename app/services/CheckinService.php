<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;
use App\Models\SettingModel;

class CheckinService
{
    private const EXP_REWARDS = [2, 3, 4, 5, 6, 8, 12];
    private const COIN_REWARDS = [1, 1, 2, 2, 3, 3, 5];

    public function ensureTables(): void
    {
        $db = Database::connection();
        $db->exec("CREATE TABLE IF NOT EXISTS user_checkins (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            checkin_date DATE NOT NULL,
            streak_days INT UNSIGNED NOT NULL DEFAULT 1,
            reward_exp INT UNSIGNED NOT NULL DEFAULT 0,
            reward_currency_code VARCHAR(20) DEFAULT NULL,
            reward_currency_amount DECIMAL(18,6) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_checkin_user_date (user_id, checkin_date),
            KEY idx_checkin_user_date (user_id, checkin_date),
            KEY idx_checkin_date (checkin_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function summary(int $userId): array
    {
        $this->ensureTables();
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $todayRow = $this->rowByDate($userId, $today);
        $latest = $this->latestRow($userId);
        $streak = 0;
        if ($todayRow) $streak = (int)$todayRow['streak_days'];
        elseif ($latest && (string)$latest['checkin_date'] === $yesterday) $streak = (int)$latest['streak_days'];
        $dayIndex = min(6, $streak % 7);
        if (!$todayRow && $streak > 0 && $streak % 7 === 0) $dayIndex = 0;
        $nextIndex = $todayRow ? min(6, $streak % 7) : min(6, $dayIndex);
        return [
            'today_checked' => (bool)$todayRow,
            'today_row' => $todayRow,
            'streak_days' => $streak,
            'week_index' => $todayRow ? max(0, min(6, ((int)$todayRow['streak_days'] - 1) % 7)) : $nextIndex,
            'rewards' => $this->rewardPlan(),
            'recent' => $this->recentRows($userId, 14),
        ];
    }

    public function checkin(int $userId): array
    {
        if ($userId <= 0) throw new RuntimeException('请先登录');
        $this->ensureTables();
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT * FROM user_checkins WHERE user_id=:uid AND checkin_date=:day FOR UPDATE");
            $stmt->execute([':uid'=>$userId, ':day'=>$today]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) throw new RuntimeException('今天已经签到过了');
            $stmt = $db->prepare("SELECT * FROM user_checkins WHERE user_id=:uid ORDER BY checkin_date DESC LIMIT 1 FOR UPDATE");
            $stmt->execute([':uid'=>$userId]);
            $latest = $stmt->fetch(PDO::FETCH_ASSOC);
            $streak = ($latest && (string)$latest['checkin_date'] === $yesterday) ? ((int)$latest['streak_days'] + 1) : 1;
            $idx = max(0, min(6, ($streak - 1) % 7));
            $settings = $this->settings();
            if (empty($settings['enabled'])) throw new RuntimeException('签到功能暂未开启');
            $exp = (int)$settings['exp_rewards'][$idx];
            $coin = (float)$settings['coin_rewards'][$idx];
            $currency = (string)($settings['currency_rewards'][$idx] ?? $settings['currency']);
            $db->prepare("INSERT INTO user_checkins (user_id,checkin_date,streak_days,reward_exp,reward_currency_code,reward_currency_amount,created_at) VALUES (:uid,:day,:streak,:exp,:code,:amount,NOW())")
                ->execute([':uid'=>$userId, ':day'=>$today, ':streak'=>$streak, ':exp'=>$exp, ':code'=>$currency, ':amount'=>number_format($coin,6,'.','')]);
            $id = (int)$db->lastInsertId();
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        (new GrowthService())->award($userId, $exp, 'daily_checkin', 'checkin', $id, '每日签到奖励');
        if ($currency !== '' && $coin > 0) {
            (new \App\Models\WalletModel())->addTransaction($userId, $currency, number_format($coin,6,'.',''), 'daily_checkin', '每日签到', '连续签到 ' . $streak . ' 天奖励', null, null, 'checkin', $id);
        }
        return ['ok'=>true, 'streak_days'=>$streak, 'reward_exp'=>$exp, 'reward_currency_code'=>$currency, 'reward_currency_amount'=>$coin];
    }

    public function rewardPlan(): array
    {
        $settings = $this->settings();
        $out = [];
        for ($i=0; $i<7; $i++) $out[] = ['day'=>$i+1, 'exp'=>(int)$settings['exp_rewards'][$i], 'coin'=>(float)$settings['coin_rewards'][$i], 'currency'=>(string)($settings['currency_rewards'][$i] ?? $settings['currency'])];
        return $out;
    }


    public function settings(): array
    {
        $model = new SettingModel();
        $exp = $this->normalizeRewards((string)($model->get('checkin_exp_rewards', '') ?? ''), self::EXP_REWARDS, true);
        $coin = $this->normalizeRewards((string)($model->get('checkin_coin_rewards', '') ?? ''), self::COIN_REWARDS, false);
        $currency = $this->defaultCurrency();
        $currencyRewards = $this->normalizeCurrencyRewards((string)($model->get('checkin_currency_rewards', '') ?? ''), $currency);
        return [
            'enabled' => $model->getBool('checkin_enabled', true),
            'currency' => $currency,
            'currency_rewards' => $currencyRewards,
            'exp_rewards' => $exp,
            'coin_rewards' => $coin,
        ];
    }

    public function saveSettings(array $data): void
    {
        $exp = []; $coin = []; $currencies = [];
        for ($i=0; $i<7; $i++) {
            $exp[] = max(0, (int)($data['checkin_exp_rewards'][$i] ?? self::EXP_REWARDS[$i]));
            $coin[] = max(0, (float)($data['checkin_coin_rewards'][$i] ?? self::COIN_REWARDS[$i]));
            $currencies[] = currency_resolve_code((string)($data['checkin_currency_rewards'][$i] ?? ''));
        }
        (new SettingModel())->saveMany([
            'checkin_enabled' => ($data['checkin_enabled'] ?? '0') === '1' ? '1' : '0',
            'checkin_exp_rewards' => implode(',', $exp),
            'checkin_coin_rewards' => implode(',', $coin),
            'checkin_currency_rewards' => implode(',', $currencies),
        ]);
    }

    public function adminStats(): array
    {
        $this->ensureTables();
        $db = Database::connection();
        $today = (int)$db->query("SELECT COUNT(*) FROM user_checkins WHERE checkin_date=CURDATE()")->fetchColumn();
        $totalUsers = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM user_checkins")->fetchColumn();
        $maxStreak = (int)$db->query("SELECT COALESCE(MAX(streak_days),0) FROM user_checkins")->fetchColumn();
        $total = (int)$db->query("SELECT COUNT(*) FROM user_checkins")->fetchColumn();
        return ['today'=>$today, 'total_users'=>$totalUsers, 'max_streak'=>$maxStreak, 'total'=>$total];
    }

    public function adminRecent(int $limit = 80): array
    {
        $this->ensureTables();
        $stmt = Database::connection()->prepare("SELECT c.*,u.username,u.nickname,u.avatar FROM user_checkins c LEFT JOIN users u ON u.id=c.user_id ORDER BY c.id DESC LIMIT :limit");
        $stmt->bindValue(':limit', max(1,min(300,$limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    private function normalizeCurrencyRewards(string $raw, string $fallback): array
    {
        $parts = array_map('trim', explode(',', $raw));
        $out = [];
        for ($i=0; $i<7; $i++) {
            $code = currency_resolve_code((string)($parts[$i] ?? ''));
            $out[] = $code !== '' ? $code : $fallback;
        }
        return $out;
    }

    private function normalizeRewards(string $raw, array $fallback, bool $integer): array
    {
        $parts = array_map('trim', explode(',', $raw));
        $out = [];
        for ($i=0; $i<7; $i++) {
            $v = $parts[$i] ?? $fallback[$i];
            $out[] = $integer ? max(0, (int)$v) : max(0, (float)$v);
        }
        return $out;
    }

    private function rowByDate(int $userId, string $date): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM user_checkins WHERE user_id=:uid AND checkin_date=:day LIMIT 1");
        $stmt->execute([':uid'=>$userId, ':day'=>$date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function latestRow(int $userId): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM user_checkins WHERE user_id=:uid ORDER BY checkin_date DESC LIMIT 1");
        $stmt->execute([':uid'=>$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function recentRows(int $userId, int $limit): array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM user_checkins WHERE user_id=:uid ORDER BY checkin_date DESC LIMIT :limit");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1,min(60,$limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function defaultCurrency(): string
    {
        try {
            $row = Database::connection()->query("SELECT code FROM currencies WHERE status='active' ORDER BY sort_order ASC,id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            return $row ? (string)$row['code'] : '';
        } catch (\Throwable $e) { return ''; }
    }
}
