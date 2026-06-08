<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class UserCreditModel
{
    public const DEFAULT_SCORE = 100;

    public function settings(): array
    {
        $settings = (new SettingModel())->all();
        return [
            'enabled' => $this->bool($settings['credit_enabled'] ?? '1'),
            'default_score' => $this->int($settings['credit_default_score'] ?? '100', 0, 1000),
            'min_score' => $this->int($settings['credit_min_score'] ?? '0', 0, 1000),
            'max_score' => $this->int($settings['credit_max_score'] ?? '120', 1, 1000),
            'valid_report_reward' => $this->int($settings['credit_valid_report_reward'] ?? '2', 0, 1000),
            'valid_report_penalty' => $this->int($settings['credit_valid_report_penalty'] ?? '5', 0, 1000),
            'false_report_penalty' => $this->int($settings['credit_false_report_penalty'] ?? '0', 0, 1000),
            'low_threshold' => $this->int($settings['credit_low_threshold'] ?? '60', 0, 1000),
            'excellent_threshold' => $this->int($settings['credit_excellent_threshold'] ?? '100', 0, 1000),
            'daily_report_reward_limit' => $this->int($settings['credit_daily_report_reward_limit'] ?? '10', 0, 1000),
            'restrict_enabled' => $this->bool($settings['credit_restrict_enabled'] ?? '1'),
            'low_daily_threads' => $this->int($settings['credit_low_daily_threads'] ?? '1', 0, 1000),
            'low_daily_posts' => $this->int($settings['credit_low_daily_posts'] ?? '5', 0, 1000),
            'low_daily_private_messages' => $this->int($settings['credit_low_daily_private_messages'] ?? '10', 0, 1000),
            'low_daily_moments' => $this->int($settings['credit_low_daily_moments'] ?? '1', 0, 1000),
            'low_disable_private_images' => $this->bool($settings['credit_low_disable_private_images'] ?? '1'),
            'recovery_enabled' => $this->bool($settings['credit_recovery_enabled'] ?? '1'),
            'recovery_interval_hours' => $this->int($settings['credit_recovery_interval_hours'] ?? '24', 1, 8760),
            'recovery_amount' => $this->int($settings['credit_recovery_amount'] ?? '2', 0, 1000),
            'recovery_cap' => $this->int($settings['credit_recovery_cap'] ?? ($settings['credit_default_score'] ?? '100'), 0, 1000),
        ];
    }

    public function saveSettings(array $data): void
    {
        $defaults = $this->settings();
        $payload = [
            'credit_enabled' => !empty($data['enabled']) ? '1' : '0',
            'credit_default_score' => (string)$this->int($data['default_score'] ?? $defaults['default_score'], 0, 1000),
            'credit_min_score' => (string)$this->int($data['min_score'] ?? $defaults['min_score'], 0, 1000),
            'credit_max_score' => (string)$this->int($data['max_score'] ?? $defaults['max_score'], 1, 1000),
            'credit_valid_report_reward' => (string)$this->int($data['valid_report_reward'] ?? $defaults['valid_report_reward'], 0, 1000),
            'credit_valid_report_penalty' => (string)$this->int($data['valid_report_penalty'] ?? $defaults['valid_report_penalty'], 0, 1000),
            'credit_false_report_penalty' => (string)$this->int($data['false_report_penalty'] ?? $defaults['false_report_penalty'], 0, 1000),
            'credit_low_threshold' => (string)$this->int($data['low_threshold'] ?? $defaults['low_threshold'], 0, 1000),
            'credit_excellent_threshold' => (string)$this->int($data['excellent_threshold'] ?? $defaults['excellent_threshold'], 0, 1000),
            'credit_daily_report_reward_limit' => (string)$this->int($data['daily_report_reward_limit'] ?? $defaults['daily_report_reward_limit'], 0, 1000),
            'credit_restrict_enabled' => !empty($data['restrict_enabled']) ? '1' : '0',
            'credit_low_daily_threads' => (string)$this->int($data['low_daily_threads'] ?? $defaults['low_daily_threads'], 0, 1000),
            'credit_low_daily_posts' => (string)$this->int($data['low_daily_posts'] ?? $defaults['low_daily_posts'], 0, 1000),
            'credit_low_daily_private_messages' => (string)$this->int($data['low_daily_private_messages'] ?? $defaults['low_daily_private_messages'], 0, 1000),
            'credit_low_daily_moments' => (string)$this->int($data['low_daily_moments'] ?? $defaults['low_daily_moments'], 0, 1000),
            'credit_low_disable_private_images' => !empty($data['low_disable_private_images']) ? '1' : '0',
            'credit_recovery_enabled' => !empty($data['recovery_enabled']) ? '1' : '0',
            'credit_recovery_interval_hours' => (string)$this->int($data['recovery_interval_hours'] ?? $defaults['recovery_interval_hours'], 1, 8760),
            'credit_recovery_amount' => (string)$this->int($data['recovery_amount'] ?? $defaults['recovery_amount'], 0, 1000),
            'credit_recovery_cap' => (string)$this->int($data['recovery_cap'] ?? $defaults['recovery_cap'], 0, 1000),
        ];
        if ((int)$payload['credit_min_score'] > (int)$payload['credit_max_score']) {
            $payload['credit_min_score'] = $payload['credit_max_score'];
        }
        (new SettingModel())->saveMany($payload);
    }

    public function summary(int $userId): array
    {
        if ($userId <= 0) return $this->emptySummary();
        $this->ensureUser($userId);
        $this->applyRecovery($userId);
        $stmt = Database::connection()->prepare("SELECT * FROM user_credit_stats WHERE user_id=:uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $score = (int)($row['score'] ?? $this->settings()['default_score']);
        $settings = $this->settings();
        return [
            'score' => $score,
            'level' => $this->level($score, $settings),
            'valid_reports' => (int)($row['valid_reports'] ?? 0),
            'invalid_reports' => (int)($row['invalid_reports'] ?? 0),
            'violations' => (int)($row['violations'] ?? 0),
            'manual_adjustments' => (int)($row['manual_adjustments'] ?? 0),
            'updated_at' => $row['updated_at'] ?? null,
            'settings' => $settings,
        ];
    }

    public function logs(int $userId, int $limit = 30): array
    {
        $stmt = Database::connection()->prepare("SELECT l.*, a.username AS operator_username, a.nickname AS operator_nickname FROM user_credit_logs l LEFT JOIN users a ON a.id=l.operator_id WHERE l.user_id=:uid ORDER BY l.id DESC LIMIT :limit");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function recent(int $limit = 80): array
    {
        $stmt = Database::connection()->prepare("SELECT l.*, u.username,u.nickname,u.public_id, a.username AS operator_username, a.nickname AS operator_nickname FROM user_credit_logs l LEFT JOIN users u ON u.id=l.user_id LEFT JOIN users a ON a.id=l.operator_id ORDER BY l.id DESC LIMIT :limit");
        $stmt->bindValue(':limit', max(1, min(300, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function rankRows(string $keyword = '', int $limit = 100): array
    {
        $where = '';
        $params = [];
        if ($keyword !== '') {
            $where = "WHERE u.public_id LIKE :kw OR u.username LIKE :kw OR u.nickname LIKE :kw";
            $params[':kw'] = '%' . $keyword . '%';
        }
        $sql = "SELECT u.id,u.public_id,u.username,u.nickname,u.email,u.status,COALESCE(s.score,:default_score) AS credit_score,COALESCE(s.valid_reports,0) AS valid_reports,COALESCE(s.invalid_reports,0) AS invalid_reports,COALESCE(s.violations,0) AS violations,s.updated_at FROM users u LEFT JOIN user_credit_stats s ON s.user_id=u.id {$where} ORDER BY credit_score ASC,u.id DESC LIMIT :limit";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':default_score', $this->settings()['default_score'], PDO::PARAM_INT);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', max(1, min(300, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }


    public function checkRestriction(int $userId, string $action): array
    {
        $settings = $this->settings();
        if ($userId > 0) $this->applyRecovery($userId);
        if ($userId <= 0 || !$settings['enabled'] || !$settings['restrict_enabled']) {
            return ['allowed' => true, 'limited' => false, 'message' => '', 'remaining' => null];
        }
        $summary = $this->summary($userId);
        $score = (int)($summary['score'] ?? $settings['default_score']);
        if ($score >= (int)$settings['low_threshold']) {
            return ['allowed' => true, 'limited' => false, 'message' => '', 'remaining' => null, 'score' => $score];
        }
        $map = [
            'thread' => ['table' => 'threads', 'where' => 'user_id=:uid', 'limit' => (int)$settings['low_daily_threads'], 'label' => '发帖'],
            'post' => ['table' => 'posts', 'where' => 'user_id=:uid', 'limit' => (int)$settings['low_daily_posts'], 'label' => '回复'],
            'private_message' => ['table' => 'private_messages', 'where' => 'sender_id=:uid', 'limit' => (int)$settings['low_daily_private_messages'], 'label' => '私聊'],
            'moment' => ['table' => 'moments', 'where' => 'user_id=:uid', 'limit' => (int)$settings['low_daily_moments'], 'label' => '朋友圈'],
        ];
        if (!isset($map[$action])) return ['allowed' => true, 'limited' => false, 'message' => '', 'remaining' => null, 'score' => $score];
        $rule = $map[$action];
        $limit = (int)$rule['limit'];
        if ($limit <= 0) {
            return ['allowed' => false, 'limited' => true, 'score' => $score, 'remaining' => 0, 'limit' => 0, 'used' => 0, 'message' => '你的信用分为 ' . $score . '，低于 ' . (int)$settings['low_threshold'] . '，当前暂不能' . $rule['label'] . '。'];
        }
        $used = $this->todayCount((string)$rule['table'], (string)$rule['where'], $userId);
        $remaining = max(0, $limit - $used);
        if ($used >= $limit) {
            return ['allowed' => false, 'limited' => true, 'score' => $score, 'remaining' => 0, 'limit' => $limit, 'used' => $used, 'message' => '你的信用分为 ' . $score . '，低于 ' . (int)$settings['low_threshold'] . '。今日' . $rule['label'] . '额度已用完（' . $used . '/' . $limit . '），请明天再试或通过有效举报/良好行为恢复信用。'];
        }
        return ['allowed' => true, 'limited' => true, 'score' => $score, 'remaining' => $remaining, 'limit' => $limit, 'used' => $used, 'message' => '低信用额度剩余 ' . $remaining . ' 次'];
    }

    public function checkPrivateImageAllowed(int $userId): array
    {
        $settings = $this->settings();
        $base = $this->checkRestriction($userId, 'private_message');
        if (empty($base['allowed'])) return $base;
        $score = (int)($base['score'] ?? $this->summary($userId)['score'] ?? $settings['default_score']);
        if ($settings['restrict_enabled'] && $settings['low_disable_private_images'] && $score < (int)$settings['low_threshold']) {
            return ['allowed' => false, 'limited' => true, 'score' => $score, 'remaining' => (int)($base['remaining'] ?? 0), 'message' => '你的信用分为 ' . $score . '，低信用期间暂不能发送私聊图片，请先发送文字或恢复信用分。'];
        }
        return $base;
    }

    private function todayCount(string $table, string $where, int $userId): int
    {
        $allowed = ['threads', 'posts', 'private_messages', 'moments'];
        if (!in_array($table, $allowed, true)) return 0;
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where} AND created_at >= CURDATE()");
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn();
    }


    public function applyRecovery(int $userId): ?array
    {
        if ($userId <= 0) return null;
        $settings = $this->settings();
        if (!$settings['enabled'] || !$settings['recovery_enabled'] || (int)$settings['recovery_amount'] <= 0) return null;
        $db = Database::connection();
        $started = !$db->inTransaction();
        if ($started) $db->beginTransaction();
        try {
            $this->ensureUser($userId);
            $stmt = $db->prepare("SELECT score, recovered_at, created_at FROM user_credit_stats WHERE user_id=:uid FOR UPDATE");
            $stmt->execute([':uid'=>$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $score = (int)($row['score'] ?? $settings['default_score']);
            $cap = min((int)$settings['max_score'], max((int)$settings['min_score'], (int)$settings['recovery_cap']));
            if ($score >= $cap) {
                $db->prepare("UPDATE user_credit_stats SET recovered_at=COALESCE(recovered_at,NOW()) WHERE user_id=:uid")->execute([':uid'=>$userId]);
                if ($started) $db->commit();
                return null;
            }
            $base = (string)($row['recovered_at'] ?? $row['created_at'] ?? '');
            $baseTs = $base !== '' ? strtotime($base) : time();
            if (!$baseTs) $baseTs = time();
            $intervalSeconds = max(3600, (int)$settings['recovery_interval_hours'] * 3600);
            $elapsed = time() - $baseTs;
            $steps = $elapsed >= $intervalSeconds ? intdiv($elapsed, $intervalSeconds) : 0;
            if ($steps <= 0) {
                if ($started) $db->commit();
                return null;
            }
            $delta = min($cap - $score, $steps * (int)$settings['recovery_amount']);
            if ($delta <= 0) {
                if ($started) $db->commit();
                return null;
            }
            $after = $score + $delta;
            $advanceSeconds = $steps * $intervalSeconds;
            $newRecoveredAt = date('Y-m-d H:i:s', min(time(), $baseTs + $advanceSeconds));
            $db->prepare("UPDATE user_credit_stats SET score=:score, recovered_at=:recovered_at, updated_at=NOW() WHERE user_id=:uid")
                ->execute([':score'=>$after, ':recovered_at'=>$newRecoveredAt, ':uid'=>$userId]);
            $db->prepare("INSERT INTO user_credit_logs (user_id, action, score_change, before_score, after_score, reason, ref_type, ref_id, operator_id, created_at) VALUES (:uid,'auto_recovery',:change,:before,:after,:reason,'credit',:uid,NULL,NOW())")
                ->execute([':uid'=>$userId, ':change'=>$delta, ':before'=>$score, ':after'=>$after, ':reason'=>'信用分按时间自动恢复']);
            if ($started) $db->commit();
            return ['before'=>$score, 'after'=>$after, 'change'=>$delta, 'steps'=>$steps];
        } catch (\Throwable $e) {
            if ($started && $db->inTransaction()) $db->rollBack();
            error_log('[ClayBBS] user credit recovery failed: ' . $e->getMessage());
            return null;
        }
    }

    public function adjust(int $userId, int $delta, string $reason, ?int $operatorId = null, string $action = 'manual_adjust', ?string $refType = null, ?int $refId = null, bool $uniqueRef = false): ?array
    {
        if ($userId <= 0 || $delta === 0) return null;
        $settings = $this->settings();
        if (!$settings['enabled']) return null;
        $db = Database::connection();
        $started = !$db->inTransaction();
        if ($started) $db->beginTransaction();
        try {
            $this->ensureUser($userId);
            if ($uniqueRef && $refType && $refId) {
                $check = $db->prepare("SELECT id FROM user_credit_logs WHERE user_id=:uid AND action=:action AND ref_type=:ref_type AND ref_id=:ref_id LIMIT 1");
                $check->execute([':uid'=>$userId, ':action'=>$action, ':ref_type'=>$refType, ':ref_id'=>$refId]);
                if ($check->fetchColumn()) {
                    if ($started) $db->commit();
                    return null;
                }
            }
            $stmt = $db->prepare("SELECT score FROM user_credit_stats WHERE user_id=:uid FOR UPDATE");
            $stmt->execute([':uid' => $userId]);
            $before = (int)($stmt->fetchColumn() ?: $settings['default_score']);
            $after = max($settings['min_score'], min($settings['max_score'], $before + $delta));
            $realDelta = $after - $before;
            if ($realDelta === 0) {
                if ($started) $db->commit();
                return null;
            }
            $extraSet = '';
            if ($action === 'report_valid_reward') $extraSet = ', valid_reports=valid_reports+1';
            if ($action === 'report_false_penalty') $extraSet = ', invalid_reports=invalid_reports+1';
            if ($action === 'reported_valid_penalty') $extraSet = ', violations=violations+1';
            if ($action === 'manual_adjust') $extraSet = ', manual_adjustments=manual_adjustments+1';
            $db->prepare("UPDATE user_credit_stats SET score=:score{$extraSet}, updated_at=NOW() WHERE user_id=:uid")
                ->execute([':score'=>$after, ':uid'=>$userId]);
            $db->prepare("INSERT INTO user_credit_logs (user_id, action, score_change, before_score, after_score, reason, ref_type, ref_id, operator_id, created_at) VALUES (:uid,:action,:change,:before,:after,:reason,:ref_type,:ref_id,:operator,NOW())")
                ->execute([':uid'=>$userId, ':action'=>$action, ':change'=>$realDelta, ':before'=>$before, ':after'=>$after, ':reason'=>$reason, ':ref_type'=>$refType, ':ref_id'=>$refId, ':operator'=>$operatorId]);
            if ($started) $db->commit();
            return ['before'=>$before, 'after'=>$after, 'change'=>$realDelta];
        } catch (\Throwable $e) {
            if ($started && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function rewardValidReport(int $reporterId, int $reportId, ?int $operatorId = null, string $reason = '举报核实有效'): ?array
    {
        $settings = $this->settings();
        $delta = (int)$settings['valid_report_reward'];
        if ($delta <= 0 || $this->dailyRewardReached($reporterId, (int)$settings['daily_report_reward_limit'])) return null;
        return $this->adjust($reporterId, $delta, $reason, $operatorId, 'report_valid_reward', 'report', $reportId, true);
    }

    public function penalizeReportedValid(int $userId, int $reportId, ?int $operatorId = null, string $reason = '被举报内容核实违规'): ?array
    {
        $delta = -(int)$this->settings()['valid_report_penalty'];
        return $delta < 0 ? $this->adjust($userId, $delta, $reason, $operatorId, 'reported_valid_penalty', 'report', $reportId, true) : null;
    }

    public function penalizeFalseReport(int $reporterId, int $reportId, ?int $operatorId = null, string $reason = '举报未核实有效'): ?array
    {
        $delta = -(int)$this->settings()['false_report_penalty'];
        return $delta < 0 ? $this->adjust($reporterId, $delta, $reason, $operatorId, 'report_false_penalty', 'report', $reportId, true) : null;
    }

    public function dailyRewardReached(int $userId, int $limit): bool
    {
        if ($limit <= 0) return false;
        $stmt = Database::connection()->prepare("SELECT COALESCE(SUM(score_change),0) FROM user_credit_logs WHERE user_id=:uid AND action='report_valid_reward' AND score_change>0 AND created_at >= CURDATE()");
        $stmt->execute([':uid'=>$userId]);
        return (int)$stmt->fetchColumn() >= $limit;
    }

    public function ensureUser(int $userId): void
    {
        if ($userId <= 0) return;
        $score = $this->settings()['default_score'];
        Database::connection()->prepare("INSERT IGNORE INTO user_credit_stats (user_id, score, recovered_at, created_at, updated_at) VALUES (:uid, :score, NOW(), NOW(), NOW())")
            ->execute([':uid'=>$userId, ':score'=>$score]);
    }

    public function level(int $score, ?array $settings = null): array
    {
        $settings = $settings ?: $this->settings();
        if ($score >= (int)$settings['excellent_threshold']) return ['key'=>'excellent', 'label'=>'优秀', 'tone'=>'ok'];
        if ($score < (int)$settings['low_threshold']) return ['key'=>'low', 'label'=>'较低', 'tone'=>'danger'];
        return ['key'=>'normal', 'label'=>'正常', 'tone'=>'normal'];
    }

    private function emptySummary(): array
    {
        return ['score'=>self::DEFAULT_SCORE, 'level'=>['key'=>'normal','label'=>'正常','tone'=>'normal'], 'valid_reports'=>0, 'invalid_reports'=>0, 'violations'=>0, 'manual_adjustments'=>0, 'updated_at'=>null, 'settings'=>$this->settings()];
    }

    private function bool(string $value): bool
    {
        return in_array(strtolower($value), ['1','true','yes','on'], true);
    }

    private function int($value, int $min, int $max): int
    {
        return max($min, min($max, (int)$value));
    }
}
