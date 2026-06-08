<?php

namespace App\Services;

use App\Core\Database;
use App\Models\WalletModel;
use App\Models\SystemMessageModel;
use PDO;
use RuntimeException;

class TaskService
{
    private const STATE_SINGLETON_ACTIONS = [
        'login_daily' => true,
        'email_verified' => true,
        'verification_approved' => true,
        'oauth_bound' => true,
        'profile_completed' => true,
    ];

    private const REGISTRATION_STATE_ACTIONS = [
        'email_verified' => true,
    ];

    public const ACTIONS = [
        'login_daily' => '每日登录',
        'thread_publish' => '发布帖子',
        'post_publish' => '发布回复',
        'thread_liked' => '帖子被点赞',
        'post_liked' => '回复被点赞',
        'email_verified' => '邮箱验证',
        'verification_approved' => '认证通过',
        'thread_reward_sent' => '打赏他人',
        'thread_reward_received' => '收到打赏',
        'profile_completed' => '完善资料',
        'follow_user' => '关注用户',
        'favorite_thread' => '收藏帖子',
        'oauth_bound' => '绑定登录方式',
        'manual' => '手动任务',
    ];

    public const CATEGORIES = [
        'newbie' => '新手任务',
        'daily' => '每日任务',
        'weekly' => '每周任务',
        'activity' => '活动任务',
        'normal' => '普通任务',
    ];

    public function tasks(string $status = '', string $category = ''): array
    {
        $where = 'WHERE 1=1'; $params = [];
        if ($status !== '') { $where .= ' AND status=:status'; $params[':status']=$status; }
        if ($category !== '') { $where .= ' AND category=:category'; $params[':category']=$category; }
        $stmt = Database::connection()->prepare("SELECT * FROM tasks {$where} ORDER BY sort_order ASC,id ASC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function availableTasksForUser(int $userId): array
    {
        $this->syncStateTasks($userId);
        $tasks = $this->activeTasks();
        foreach ($tasks as &$task) {
            $task['cycle_key'] = $this->cycleKey($task);
            $progress = $this->progressRow($userId, (int)$task['id'], (string)$task['cycle_key']);
            $task['user_progress'] = $progress ?: [
                'progress'=>0,
                'target_count'=>(int)($task['target_count'] ?? 1),
                'status'=>'doing',
                'claimed_at'=>null,
            ];
        }
        unset($task);
        return $tasks;
    }


    public function syncStateTasks(int $userId): void
    {
        $this->syncStateTasksForContext($userId, false);
    }

    public function syncRegistrationStateTasks(int $userId): void
    {
        $this->syncStateTasksForContext($userId, true);
    }

    private function syncStateTasksForContext(int $userId, bool $registrationOnly): void
    {
        if ($userId <= 0) return;
        try {
            if (!$registrationOnly && $this->shouldAwardDailyLogin($userId)) {
                $this->syncDailyLogin($userId);
            }
            if ($this->userEmailVerified($userId) && (!$registrationOnly || isset(self::REGISTRATION_STATE_ACTIONS['email_verified']))) {
                $this->recordAction($userId, 'email_verified', 'user', $userId);
            }
            if (!$registrationOnly && $this->userHasActiveVerification($userId)) {
                $this->recordAction($userId, 'verification_approved', 'verification', $userId);
            }
            if (!$registrationOnly && $this->userHasOAuthBinding($userId)) {
                $this->recordAction($userId, 'oauth_bound', 'oauth', $userId);
            }
        } catch (\Throwable $e) {}
    }

    private function syncDailyLogin(int $userId): void
    {
        foreach ($this->matchingTasks('login_daily') as $task) {
            if ((string)($task['cycle_type'] ?? '') !== 'daily') {
                $task['cycle_type'] = 'daily';
            }
            $cycleKey = $this->cycleKey($task);
            if (!$this->progressRow($userId, (int)$task['id'], $cycleKey)) {
                $this->increaseProgress($userId, $task, 1, $cycleKey, 'login', $userId);
            }
        }
    }

    private function shouldAwardDailyLogin(int $userId): bool
    {
        
        $today = date('Y-m-d');
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM user_task_progress p JOIN tasks t ON t.id=p.task_id WHERE p.user_id=:uid AND t.action='login_daily' AND p.cycle_key=:today");
        $stmt->execute([':uid' => $userId, ':today' => $today]);
        return (int)$stmt->fetchColumn() === 0;
    }

    private function userEmailVerified(int $userId): bool
    {
        $stmt = Database::connection()->prepare("SELECT IFNULL(email_verified,0) FROM users WHERE id=:id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        return (int)$stmt->fetchColumn() === 1;
    }

    private function userHasActiveVerification(int $userId): bool
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM user_verifications WHERE user_id=:uid AND status='active'");
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function userHasOAuthBinding(int $userId): bool
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM user_oauth_accounts WHERE user_id=:uid");
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function recordAction(int $userId, string $action, string $refType = '', ?int $refId = null): void
    {
        if ($userId <= 0 || $action === '') return;
        foreach ($this->matchingTasks($action) as $task) {
            if ((int)($task['manual_review'] ?? 0) === 1) continue;
            $taskId = (int)$task['id'];
            $cycleKey = $this->cycleKey($task);
            if ($cycleKey === '') continue;
            if (isset(self::STATE_SINGLETON_ACTIONS[$action]) && $this->progressRow($userId, $taskId, $cycleKey)) continue;
            if (!empty($task['once_per_ref']) && $refType !== '' && $refId !== null && $this->refUsed($userId, $taskId, $refType, $refId)) continue;
            $this->increaseProgress($userId, $task, 1, $cycleKey, $refType, $refId);
        }
    }

    public function claim(int $userId, int $taskId): array
    {
        $task = $this->find($taskId);
        if (!$task || ($task['status'] ?? '') !== 'active') throw new RuntimeException('任务不存在或未启用');
        $cycleKey = $this->cycleKey($task);
        $progress = $this->progressRow($userId, $taskId, $cycleKey);
        if (!$progress || ($progress['status'] ?? '') !== 'completed') throw new RuntimeException('任务尚未完成或已领取');
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $lock = $db->prepare("SELECT * FROM user_task_progress WHERE id=:id FOR UPDATE");
            $lock->execute([':id'=>(int)$progress['id']]);
            $progress = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$progress || ($progress['status'] ?? '') !== 'completed') throw new RuntimeException('任务尚未完成或已领取');
            $db->prepare("UPDATE user_task_progress SET status='claimed',claimed_at=NOW(),updated_at=NOW() WHERE id=:id")->execute([':id'=>(int)$progress['id']]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        $this->grantRewards($userId, $task, (int)$progress['id']);
        return ['ok'=>true, 'message'=>'奖励已领取'];
    }

    public function submitManual(int $userId, int $taskId, string $content): void
    {
        $task = $this->find($taskId);
        if (!$task || (int)($task['manual_review'] ?? 0) !== 1 || ($task['status'] ?? '') !== 'active') throw new RuntimeException('任务不可提交');
        $cycleKey = $this->cycleKey($task);
        if ($this->submissionPending($userId, $taskId, $cycleKey)) throw new RuntimeException('该任务已有提交正在审核中');
        Database::connection()->prepare("INSERT INTO task_submissions (user_id,task_id,cycle_key,content,status,created_at,updated_at) VALUES (:uid,:tid,:cycle,:content,'pending',NOW(),NOW())")
            ->execute([':uid'=>$userId, ':tid'=>$taskId, ':cycle'=>$cycleKey, ':content'=>trim($content)]);
    }

    public function reviewSubmission(int $submissionId, string $action, int $adminId, string $note = ''): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare("SELECT s.*,t.title,t.reward_exp,t.reward_currencies,t.target_count FROM task_submissions s JOIN tasks t ON t.id=s.task_id WHERE s.id=:id LIMIT 1");
        $stmt->execute([':id'=>$submissionId]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sub || ($sub['status'] ?? '') !== 'pending') return null;
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $db->prepare("UPDATE task_submissions SET status=:status,review_note=:note,reviewed_by=:admin,reviewed_at=NOW(),updated_at=NOW() WHERE id=:id")
            ->execute([':status'=>$status, ':note'=>$note, ':admin'=>$adminId, ':id'=>$submissionId]);
        if ($status === 'approved') {
            $task = $this->find((int)$sub['task_id']);
            if ($task) {
                $cycleKey = (string)$sub['cycle_key'];
                $this->increaseProgress((int)$sub['user_id'], $task, (int)($task['target_count'] ?? 1), $cycleKey, 'task_submission', $submissionId, true);
                $progress = $this->progressRow((int)$sub['user_id'], (int)$sub['task_id'], $cycleKey);
                if ($progress && ($progress['status'] ?? '') === 'completed') {
                    $this->claim((int)$sub['user_id'], (int)$sub['task_id']);
                }
            }
            try { (new SystemMessageModel())->createPersonal((int)$sub['user_id'], '任务审核通过', '你提交的任务“' . (string)$sub['title'] . '”已通过审核，奖励已发放。', 1); } catch (\Throwable $e) {}
        } else {
            try { (new SystemMessageModel())->createPersonal((int)$sub['user_id'], '任务审核未通过', '你提交的任务“' . (string)$sub['title'] . '”未通过审核。' . ($note !== '' ? "\n原因：" . $note : ''), 1); } catch (\Throwable $e) {}
        }
        return $sub;
    }

    public function submissions(string $status = '', int $limit = 200): array
    {
        $where='WHERE 1=1'; $params=[];
        if ($status !== '') { $where.=' AND s.status=:status'; $params[':status']=$status; }
        $stmt = Database::connection()->prepare("SELECT s.*,t.title AS task_title,u.username,u.nickname,u.avatar,admin.username AS reviewer_name FROM task_submissions s JOIN tasks t ON t.id=s.task_id JOIN users u ON u.id=s.user_id LEFT JOIN users admin ON admin.id=s.reviewed_by {$where} ORDER BY FIELD(s.status,'pending','approved','rejected'),s.id DESC LIMIT :limit");
        foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
        $stmt->bindValue(':limit', max(1,min(500,$limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function progressRows(int $userId, int $limit = 100): array
    {
        $stmt = Database::connection()->prepare("SELECT p.*,t.title,t.description,t.reward_exp,t.reward_currencies,t.cycle_type FROM user_task_progress p JOIN tasks t ON t.id=p.task_id WHERE p.user_id=:uid ORDER BY p.updated_at DESC,p.id DESC LIMIT :limit");
        $stmt->bindValue(':uid',$userId,PDO::PARAM_INT); $stmt->bindValue(':limit',$limit,PDO::PARAM_INT); $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveTask(array $data): void
    {
        $id = (int)($data['id'] ?? 0);
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') return;
        $rewardCurrencies = $this->normalizeRewardCurrencies($data);
        $payload = [
            ':title'=>$title,
            ':description'=>trim((string)($data['description'] ?? '')),
            ':category'=>array_key_exists((string)($data['category'] ?? ''), self::CATEGORIES) ? (string)$data['category'] : 'normal',
            ':action'=>trim((string)($data['action'] ?? 'manual')), 
            ':cycle_type'=>in_array(($data['cycle_type'] ?? 'once'), ['once','daily','weekly','monthly','permanent','limited'], true) ? (string)$data['cycle_type'] : 'once',
            ':target_count'=>max(1,(int)($data['target_count'] ?? 1)),
            ':reward_exp'=>max(0,(int)($data['reward_exp'] ?? 0)),
            ':reward_currencies'=>json_encode($rewardCurrencies, JSON_UNESCAPED_UNICODE),
            ':manual_review'=>!empty($data['manual_review']) ? 1 : 0,
            ':claim_required'=>!empty($data['claim_required']) ? 1 : 0,
            ':max_claims_per_user'=>max(1,(int)($data['max_claims_per_user'] ?? 1)),
            ':once_per_ref'=>!empty($data['once_per_ref']) ? 1 : 0,
            ':start_at'=>trim((string)($data['start_at'] ?? '')) ?: null,
            ':end_at'=>trim((string)($data['end_at'] ?? '')) ?: null,
            ':status'=>in_array(($data['status'] ?? 'active'), ['active','inactive'], true) ? (string)$data['status'] : 'active',
            ':sort_order'=>(int)($data['sort_order'] ?? 0),
        ];
        if ($id > 0) {
            $payload[':id']=$id;
            Database::connection()->prepare("UPDATE tasks SET title=:title,description=:description,category=:category,action=:action,cycle_type=:cycle_type,target_count=:target_count,reward_exp=:reward_exp,reward_currencies=:reward_currencies,manual_review=:manual_review,claim_required=:claim_required,max_claims_per_user=:max_claims_per_user,once_per_ref=:once_per_ref,start_at=:start_at,end_at=:end_at,status=:status,sort_order=:sort_order,updated_at=NOW() WHERE id=:id")->execute($payload);
        } else {
            Database::connection()->prepare("INSERT INTO tasks (title,description,category,action,cycle_type,target_count,reward_exp,reward_currencies,manual_review,claim_required,max_claims_per_user,once_per_ref,start_at,end_at,status,sort_order,created_at,updated_at) VALUES (:title,:description,:category,:action,:cycle_type,:target_count,:reward_exp,:reward_currencies,:manual_review,:claim_required,:max_claims_per_user,:once_per_ref,:start_at,:end_at,:status,:sort_order,NOW(),NOW())")->execute($payload);
        }
    }

    public function deleteTask(int $id): void
    {
        if ($id <= 0) return;
        Database::connection()->prepare("DELETE FROM tasks WHERE id=:id")->execute([':id'=>$id]);
    }

    public function find(int $id): ?array
    {
        $stmt=Database::connection()->prepare("SELECT * FROM tasks WHERE id=:id LIMIT 1");
        $stmt->execute([':id'=>$id]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function rewardText(array $task): string
    {
        $parts=[];
        if ((int)($task['reward_exp'] ?? 0) > 0) $parts[] = '+' . (int)$task['reward_exp'] . ' 经验';
        foreach ($this->decodeCurrencies((string)($task['reward_currencies'] ?? '[]')) as $r) $parts[] = '+' . currency_pay_label((float)$r['amount'], (string)$r['currency_code']);
        return $parts ? implode('、', $parts) : '无奖励';
    }

    private function activeTasks(): array
    {
        $now = date('Y-m-d H:i:s');
        $stmt=Database::connection()->prepare("SELECT * FROM tasks WHERE status='active' AND (start_at IS NULL OR start_at<=:now) AND (end_at IS NULL OR end_at>=:now) ORDER BY sort_order ASC,id ASC");
        $stmt->execute([':now'=>$now]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function matchingTasks(string $action): array
    {
        $tasks = array_values(array_filter($this->activeTasks(), static fn($task) => (string)($task['action'] ?? '') === $action));
        if (isset(self::STATE_SINGLETON_ACTIONS[$action])) {
            return array_slice($tasks, 0, 1);
        }
        return $tasks;
    }

    private function cycleKey(array $task): string
    {
        $type = (string)($task['cycle_type'] ?? 'once');
        return match ($type) {
            'daily' => date('Y-m-d'),
            'weekly' => date('o-\WW'),
            'monthly' => date('Y-m'),
            'permanent' => 'permanent',
            'limited' => 'limited:' . (int)($task['id'] ?? 0),
            default => 'once',
        };
    }

    private function progressRow(int $userId, int $taskId, string $cycleKey): ?array
    {
        $stmt=Database::connection()->prepare("SELECT * FROM user_task_progress WHERE user_id=:uid AND task_id=:tid AND cycle_key=:cycle LIMIT 1");
        $stmt->execute([':uid'=>$userId, ':tid'=>$taskId, ':cycle'=>$cycleKey]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function increaseProgress(int $userId, array $task, int $amount, string $cycleKey, string $refType = '', ?int $refId = null, bool $forceComplete = false): void
    {
        $taskId=(int)$task['id']; $target=max(1,(int)($task['target_count'] ?? 1));
        $db=Database::connection();
        $db->beginTransaction();
        try {
            $stmt=$db->prepare("SELECT * FROM user_task_progress WHERE user_id=:uid AND task_id=:tid AND cycle_key=:cycle FOR UPDATE");
            $stmt->execute([':uid'=>$userId, ':tid'=>$taskId, ':cycle'=>$cycleKey]);
            $row=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $progress=min($target, max(0,$amount));
                $status=($progress >= $target || $forceComplete) ? 'completed' : 'doing';
                $db->prepare("INSERT INTO user_task_progress (user_id,task_id,cycle_key,progress,target_count,status,completed_at,created_at,updated_at) VALUES (:uid,:tid,:cycle,:progress,:target,:status," . ($status==='completed'?'NOW()':'NULL') . ",NOW(),NOW())")
                    ->execute([':uid'=>$userId, ':tid'=>$taskId, ':cycle'=>$cycleKey, ':progress'=>$progress, ':target'=>$target, ':status'=>$status]);
                $progressId=(int)$db->lastInsertId();
            } else {
                if (in_array((string)$row['status'], ['completed','claimed'], true)) { $db->commit(); return; }
                $progress=min($target, (int)$row['progress'] + max(0,$amount));
                $status=($progress >= $target || $forceComplete) ? 'completed' : 'doing';
                $db->prepare("UPDATE user_task_progress SET progress=:progress,status=:status,completed_at=IF(:status2='completed',NOW(),completed_at),updated_at=NOW() WHERE id=:id")
                    ->execute([':progress'=>$progress, ':status'=>$status, ':status2'=>$status, ':id'=>(int)$row['id']]);
                $progressId=(int)$row['id'];
            }
            if ($refType !== '' && $refId !== null) {
                $db->prepare("INSERT IGNORE INTO user_task_refs (user_id,task_id,ref_type,ref_id,created_at) VALUES (:uid,:tid,:type,:rid,NOW())")
                    ->execute([':uid'=>$userId, ':tid'=>$taskId, ':type'=>$refType, ':rid'=>$refId]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        if (($status ?? '') === 'completed' && empty($task['claim_required'])) {
            try { $this->claim($userId, $taskId); } catch (\Throwable $e) {}
        }
    }

    private function grantRewards(int $userId, array $task, int $progressId): void
    {
        $title = (string)($task['title'] ?? '任务');
        if ((int)($task['reward_exp'] ?? 0) > 0) {
            (new GrowthService())->award($userId, (int)$task['reward_exp'], 'task_reward', 'task', (int)$task['id'], '完成任务「' . $title . '」');
        }
        foreach ($this->decodeCurrencies((string)($task['reward_currencies'] ?? '[]')) as $reward) {
            $code = strtoupper((string)$reward['currency_code']); $amount = (float)$reward['amount'];
            if ($code === '' || $amount <= 0) continue;
            (new WalletModel())->addTransaction($userId, $code, number_format($amount, 6, '.', ''), 'task_reward', '任务奖励', '完成任务「' . $title . '」奖励', null, null, 'task', (int)$task['id']);
        }
        try { (new SystemMessageModel())->createPersonal($userId, '任务奖励到账', '你完成了「' . $title . '」，获得：' . $this->rewardText($task) . '。', 0); } catch (\Throwable $e) {}
    }

    private function refUsed(int $userId, int $taskId, string $refType, int $refId): bool
    {
        $stmt=Database::connection()->prepare("SELECT COUNT(*) FROM user_task_refs WHERE user_id=:uid AND task_id=:tid AND ref_type=:type AND ref_id=:rid");
        $stmt->execute([':uid'=>$userId, ':tid'=>$taskId, ':type'=>$refType, ':rid'=>$refId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function submissionPending(int $userId, int $taskId, string $cycleKey): bool
    {
        $stmt=Database::connection()->prepare("SELECT COUNT(*) FROM task_submissions WHERE user_id=:uid AND task_id=:tid AND cycle_key=:cycle AND status='pending'");
        $stmt->execute([':uid'=>$userId, ':tid'=>$taskId, ':cycle'=>$cycleKey]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function normalizeRewardCurrencies(array $data): array
    {
        $codes = $data['reward_currency_code'] ?? [];
        $amounts = $data['reward_currency_amount'] ?? [];
        if (!is_array($codes)) $codes = [$codes];
        if (!is_array($amounts)) $amounts = [$amounts];
        $out=[];
        foreach ($codes as $i=>$code) {
            $code=strtoupper(trim((string)$code)); $amount=(float)($amounts[$i] ?? 0);
            if ($code !== '' && $amount > 0) $out[]=['currency_code'=>$code,'amount'=>$amount];
        }
        return $out;
    }

    private function decodeCurrencies(string $json): array
    {
        $arr=json_decode($json,true);
        return is_array($arr) ? $arr : [];
    }
}
