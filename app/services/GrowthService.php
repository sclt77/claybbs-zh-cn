<?php

namespace App\Services;

use App\Core\Database;
use App\Models\SystemMessageModel;
use PDO;

class GrowthService
{
    public function summary(int $userId): array
    {
        if ($userId <= 0) return $this->fallbackSummary();
        $this->ensureStats($userId);
        $stmt = Database::connection()->prepare("SELECT s.*, l.name, l.color, next_l.level AS next_level, next_l.name AS next_name, next_l.min_exp AS next_min_exp FROM user_growth_stats s LEFT JOIN levels l ON l.level=s.current_level AND l.status='active' LEFT JOIN levels next_l ON next_l.level=(SELECT MIN(level) FROM levels WHERE status='active' AND min_exp>s.total_exp) WHERE s.user_id=:uid LIMIT 1");
        $stmt->execute([':uid'=>$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!$row) return $this->fallbackSummary();
        $currentMin = $this->minExpForLevel((int)($row['current_level'] ?? 1));
        $nextMin = isset($row['next_min_exp']) ? (int)$row['next_min_exp'] : null;
        $total = (int)($row['total_exp'] ?? 0);
        return [
            'user_id' => $userId,
            'total_exp' => $total,
            'today_exp' => (int)($row['today_exp'] ?? 0),
            'level' => (int)($row['current_level'] ?? 1),
            'name' => (string)($row['name'] ?? '新人'),
            'color' => (string)($row['color'] ?? '#64748b'),
            'current_min_exp' => $currentMin,
            'next_level' => $row['next_level'] !== null ? (int)$row['next_level'] : null,
            'next_name' => $row['next_name'] ?? null,
            'next_min_exp' => $nextMin,
            'progress_current' => max(0, $total - $currentMin),
            'progress_total' => $nextMin !== null ? max(1, $nextMin - $currentMin) : 1,
            'need_exp' => $nextMin !== null ? max(0, $nextMin - $total) : 0,
        ];
    }

    public function logs(int $userId, int $limit = 30): array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM user_exp_logs WHERE user_id=:uid ORDER BY id DESC LIMIT :limit");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function award(int $userId, int $exp, string $action, string $refType = '', ?int $refId = null, string $remark = '', ?int $operatorId = null): array
    {
        if ($userId <= 0 || $exp === 0) return ['ok'=>false, 'message'=>'invalid'];
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $this->ensureStats($userId);
            $stmt = $db->prepare("SELECT * FROM user_growth_stats WHERE user_id=:uid FOR UPDATE");
            $stmt->execute([':uid'=>$userId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_exp'=>0, 'current_level'=>1, 'today_exp'=>0, 'today_date'=>date('Y-m-d')];
            $today = date('Y-m-d');
            $beforeExp = (int)($stats['total_exp'] ?? 0);
            $beforeLevel = (int)($stats['current_level'] ?? 1);
            $todayExp = ((string)($stats['today_date'] ?? '') === $today) ? (int)($stats['today_exp'] ?? 0) : 0;
            $afterExp = max(0, $beforeExp + $exp);
            $afterLevel = $this->levelForExp($afterExp);
            $delta = $afterExp - $beforeExp;
            if ($delta === 0) {
                $db->rollBack();
                return ['ok'=>false, 'message'=>'no_change'];
            }
            $db->prepare("UPDATE user_growth_stats SET total_exp=:total,current_level=:level,today_exp=:today_exp,today_date=:today_date,updated_at=NOW() WHERE user_id=:uid")
                ->execute([':total'=>$afterExp, ':level'=>$afterLevel, ':today_exp'=>max(0, $todayExp + max(0, $delta)), ':today_date'=>$today, ':uid'=>$userId]);
            $db->prepare("INSERT INTO user_exp_logs (user_id,action,exp_change,before_exp,after_exp,before_level,after_level,ref_type,ref_id,remark,operator_id,created_at) VALUES (:uid,:action,:delta,:before_exp,:after_exp,:before_level,:after_level,:ref_type,:ref_id,:remark,:operator_id,NOW())")
                ->execute([':uid'=>$userId, ':action'=>$action, ':delta'=>$delta, ':before_exp'=>$beforeExp, ':after_exp'=>$afterExp, ':before_level'=>$beforeLevel, ':after_level'=>$afterLevel, ':ref_type'=>$refType !== '' ? $refType : null, ':ref_id'=>$refId, ':remark'=>$remark, ':operator_id'=>$operatorId]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        if ($afterLevel > $beforeLevel) {
            try {
                $level = $this->levelRow($afterLevel);
                (new SystemMessageModel())->createPersonal($userId, '等级提升', '恭喜你升级到 Lv.' . $afterLevel . ' ' . (string)($level['name'] ?? '') . '。', 1);
            } catch (\Throwable $e) {}
        }
        return ['ok'=>true, 'before_exp'=>$beforeExp, 'after_exp'=>$afterExp, 'before_level'=>$beforeLevel, 'after_level'=>$afterLevel, 'delta'=>$delta];
    }

    public function adjust(int $userId, int $exp, string $remark, int $operatorId): array
    {
        return $this->award($userId, $exp, 'admin_adjust', 'admin', null, $remark, $operatorId);
    }

    public function ensureStats(int $userId): void
    {
        if ($userId <= 0) return;
        Database::connection()->prepare("INSERT IGNORE INTO user_growth_stats (user_id,total_exp,current_level,today_exp,today_date,created_at,updated_at) VALUES (:uid,0,1,0,CURDATE(),NOW(),NOW())")->execute([':uid'=>$userId]);
    }

    public function levelForExp(int $exp): int
    {
        $stmt = Database::connection()->prepare("SELECT level FROM levels WHERE status='active' AND min_exp<=:exp ORDER BY min_exp DESC, level DESC LIMIT 1");
        $stmt->execute([':exp'=>max(0,$exp)]);
        return max(1, (int)($stmt->fetchColumn() ?: 1));
    }

    public function levelRows(): array
    {
        return Database::connection()->query("SELECT * FROM levels ORDER BY min_exp ASC, level ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveLevel(array $data): void
    {
        $id = (int)($data['id'] ?? 0);
        $level = max(1, (int)($data['level'] ?? 1));
        $name = trim((string)($data['name'] ?? '')) ?: ('Lv.' . $level);
        $color = trim((string)($data['color'] ?? '#64748b'));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#64748b';
        $payload = [':level'=>$level, ':name'=>$name, ':min_exp'=>max(0,(int)($data['min_exp'] ?? 0)), ':color'=>$color, ':status'=>in_array(($data['status'] ?? 'active'), ['active','inactive'], true) ? (string)$data['status'] : 'active', ':sort_order'=>(int)($data['sort_order'] ?? 0)];
        if ($id > 0) {
            $payload[':id'] = $id;
            Database::connection()->prepare("UPDATE levels SET level=:level,name=:name,min_exp=:min_exp,color=:color,status=:status,sort_order=:sort_order,updated_at=NOW() WHERE id=:id")->execute($payload);
        } else {
            Database::connection()->prepare("INSERT INTO levels (level,name,min_exp,color,status,sort_order,created_at,updated_at) VALUES (:level,:name,:min_exp,:color,:status,:sort_order,NOW(),NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),min_exp=VALUES(min_exp),color=VALUES(color),status=VALUES(status),sort_order=VALUES(sort_order),updated_at=NOW()")->execute($payload);
        }
        $this->recalculateAllLevels();
    }

    public function deleteLevel(int $id): void
    {
        if ($id <= 0) return;
        Database::connection()->prepare("DELETE FROM levels WHERE id=:id AND level<>1")->execute([':id'=>$id]);
        $this->recalculateAllLevels();
    }

    public function userRows(string $kw = '', int $limit = 100): array
    {
        $where = 'WHERE 1=1'; $params = [];
        if ($kw !== '') { $where .= ' AND (u.username LIKE :kw OR u.nickname LIKE :kw OR u.id=:id)'; $params[':kw']='%'.$kw.'%'; $params[':id']=(int)$kw; }
        $stmt = Database::connection()->prepare("SELECT u.id,u.username,u.nickname,u.avatar,COALESCE(s.total_exp,0) AS total_exp,COALESCE(s.current_level,1) AS current_level,COALESCE(s.today_exp,0) AS today_exp,l.name AS level_name,l.color AS level_color FROM users u LEFT JOIN user_growth_stats s ON s.user_id=u.id LEFT JOIN levels l ON l.level=COALESCE(s.current_level,1) {$where} ORDER BY total_exp DESC,u.id ASC LIMIT :limit");
        foreach ($params as $k=>$v) $stmt->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
        $stmt->bindValue(':limit', max(1,min(300,$limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fallbackSummary(): array
    {
        return ['total_exp'=>0,'today_exp'=>0,'level'=>1,'name'=>'新人','color'=>'#64748b','current_min_exp'=>0,'next_level'=>null,'next_min_exp'=>null,'progress_current'=>0,'progress_total'=>1,'need_exp'=>0];
    }

    private function minExpForLevel(int $level): int
    {
        $row = $this->levelRow($level);
        return (int)($row['min_exp'] ?? 0);
    }

    private function levelRow(int $level): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM levels WHERE level=:level LIMIT 1");
        $stmt->execute([':level'=>$level]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function recalculateAllLevels(): void
    {
        $rows = Database::connection()->query("SELECT user_id,total_exp FROM user_growth_stats")->fetchAll(PDO::FETCH_ASSOC);
        $stmt = Database::connection()->prepare("UPDATE user_growth_stats SET current_level=:level,updated_at=NOW() WHERE user_id=:uid");
        foreach ($rows as $row) {
            $stmt->execute([':level'=>$this->levelForExp((int)$row['total_exp']), ':uid'=>(int)$row['user_id']]);
        }
    }
}
