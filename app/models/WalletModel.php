<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class WalletModel
{
    public function currencies(): array
    {
        $this->ensureDefaultCurrencies();
        return Database::connection()->query("SELECT * FROM currencies WHERE status='active' ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ensureDefaultCurrencies(): void
    {
        $db = Database::connection();
        try {
            $active = (int)$db->query("SELECT COUNT(*) FROM currencies WHERE status='active'")->fetchColumn();
            if ($active > 0) {
                return;
            }
            $defaults = [
                ['COIN', '金币', '', '100.000000', 0, 10],
                ['COIN_1', '银币', '', '10.000000', 0, 20],
                ['COIN_2', '铜币', '', '1.000000', 0, 30],
            ];
            $stmt = $db->prepare("INSERT INTO currencies (code,name,symbol,exchange_rate,`precision`,status,sort_order,created_at) VALUES (:code,:name,:symbol,:rate,:precision,'active',:sort,NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name), exchange_rate=VALUES(exchange_rate), `precision`=VALUES(`precision`), status='active', sort_order=VALUES(sort_order)");
            foreach ($defaults as [$code, $name, $symbol, $rate, $precision, $sort]) {
                $stmt->execute([':code'=>$code, ':name'=>$name, ':symbol'=>$symbol, ':rate'=>$rate, ':precision'=>$precision, ':sort'=>$sort]);
            }
        } catch (\Throwable $e) {
            // Keep wallet creation non-fatal during install/upgrade checks.
        }
    }

    public function ensureWallets(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        foreach ($this->currencies() as $currency) {
            Database::connection()->prepare("INSERT IGNORE INTO wallets (user_id, currency_code, balance, locked_balance, created_at, updated_at) VALUES (:user_id, :code, 0, 0, NOW(), NOW())")
                ->execute([':user_id'=>$userId, ':code'=>$currency['code']]);
        }
    }

    public function balances(int $userId): array
    {
        $this->ensureWallets($userId);
        $stmt = Database::connection()->prepare("SELECT w.*, c.name, c.symbol, c.precision FROM wallets w INNER JOIN currencies c ON c.code = w.currency_code WHERE w.user_id = :user_id AND c.status = 'active' ORDER BY c.sort_order ASC, w.id ASC");
        $stmt->execute([':user_id'=>$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function transactions(int $userId, int $limit = 50, string $currency = '', string $type = ''): array
    {
        $where = "WHERE t.user_id = :user_id";
        $params = [':user_id' => $userId];
        $currency = strtoupper(trim($currency));
        if ($currency !== '') {
            $where .= " AND t.currency_code = :currency";
            $params[':currency'] = $currency;
        }
        $type = trim($type);
        if ($type !== '') {
            if ($type === 'income') $where .= " AND t.amount > 0";
            elseif ($type === 'expense') $where .= " AND t.amount < 0";
            elseif ($type === 'frozen') $where .= " AND (t.type LIKE '%lock%' OR t.type LIKE '%freeze%' OR t.title LIKE '%冻结%' OR t.remark LIKE '%冻结%')";
            elseif ($type === 'unfrozen') $where .= " AND (t.type LIKE '%unlock%' OR t.type LIKE '%unfreeze%' OR t.title LIKE '%解冻%' OR t.remark LIKE '%解冻%')";
            elseif ($type === 'reward') $where .= " AND (t.type = 'thread_reward' OR t.ref_type = 'thread_reward')";
            else { $where .= " AND t.type = :type"; $params[':type'] = $type; }
        }
        $stmt = Database::connection()->prepare("SELECT t.*, c.symbol, c.precision, c.name AS currency_name, c.id AS currency_exists, rb.id AS reversed_by, src.id AS reversal_source_id FROM wallet_transactions t LEFT JOIN currencies c ON c.code = t.currency_code LEFT JOIN wallet_transactions rb ON rb.reversal_of=t.id LEFT JOIN wallet_transactions src ON src.id=t.reversal_of {$where} ORDER BY t.created_at DESC, t.id DESC LIMIT :limit");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function summary(int $userId): array
    {
        $balances = $this->balances($userId);
        $active = count($balances);
        $positive = 0;
        $locked = 0.0;
        foreach ($balances as $b) {
            if ((float)$b['balance'] > 0) $positive++;
            $locked += (float)($b['locked_balance'] ?? 0);
        }
        $stmt = Database::connection()->prepare("SELECT COUNT(*) AS tx_count, COALESCE(SUM(CASE WHEN amount>0 THEN amount ELSE 0 END),0) AS income, COALESCE(SUM(CASE WHEN amount<0 THEN amount ELSE 0 END),0) AS expense FROM wallet_transactions WHERE user_id=:uid");
        $stmt->execute([':uid'=>$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['active_currencies'=>$active, 'positive_currencies'=>$positive, 'locked_total'=>$locked, 'tx_count'=>(int)($row['tx_count'] ?? 0), 'income'=>(float)($row['income'] ?? 0), 'expense'=>(float)($row['expense'] ?? 0)];
    }


    public function lockBalance(int $userId, string $currencyCode, string $amount, string $type, string $title, string $remark = '', ?string $refType = null, ?int $refId = null): void
    {
        $this->moveLocked($userId, $currencyCode, (float)$amount, 'lock', $type, $title, $remark, $refType, $refId);
    }

    public function unlockBalance(int $userId, string $currencyCode, string $amount, string $type, string $title, string $remark = '', ?string $refType = null, ?int $refId = null): void
    {
        $this->moveLocked($userId, $currencyCode, (float)$amount, 'unlock', $type, $title, $remark, $refType, $refId);
    }

    public function payFromLocked(int $payerId, int $receiverId, string $currencyCode, string $grossAmount, string $feeAmount, string $title, string $remark = '', ?string $refType = null, ?int $refId = null): void
    {
        $currencyCode = function_exists('currency_resolve_code') ? currency_resolve_code($currencyCode) : strtoupper(trim($currencyCode));
        $gross = (float)$grossAmount;
        $fee = max(0, (float)$feeAmount);
        $net = max(0, $gross - $fee);
        if ($payerId <= 0 || $receiverId <= 0 || $currencyCode === '' || $gross <= 0) throw new \RuntimeException('托管支付参数不正确');
        $this->ensureWallets($payerId);
        $this->ensureWallets($receiverId);
        $db = Database::connection();
        $ownTransaction = !$db->inTransaction();
        if ($ownTransaction) $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT balance,locked_balance FROM wallets WHERE user_id=:uid AND currency_code=:code FOR UPDATE");
            $stmt->execute([':uid'=>$payerId, ':code'=>$currencyCode]);
            $payer = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$payer || (float)$payer['locked_balance'] + 0.000001 < $gross) throw new \RuntimeException('悬赏冻结余额不足');
            $stmt->execute([':uid'=>$receiverId, ':code'=>$currencyCode]);
            $receiver = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$receiver) throw new \RuntimeException('接收方钱包不存在');
            $payerLocked = (float)$payer['locked_balance'] - $gross;
            $receiverBalance = (float)$receiver['balance'] + $net;
            $db->prepare("UPDATE wallets SET locked_balance=:locked, updated_at=NOW() WHERE user_id=:uid AND currency_code=:code")->execute([':locked'=>number_format(max(0,$payerLocked),6,'.',''), ':uid'=>$payerId, ':code'=>$currencyCode]);
            $db->prepare("UPDATE wallets SET balance=:balance, updated_at=NOW() WHERE user_id=:uid AND currency_code=:code")->execute([':balance'=>number_format($receiverBalance,6,'.',''), ':uid'=>$receiverId, ':code'=>$currencyCode]);
            $db->prepare("INSERT INTO wallet_transactions (user_id,currency_code,amount,balance_after,type,title,remark,ref_type,ref_id,created_at) VALUES (:uid,:code,:amount,:balance_after,:type,:title,:remark,:ref_type,:ref_id,NOW())")
                ->execute([':uid'=>$payerId, ':code'=>$currencyCode, ':amount'=>number_format(-$gross,6,'.',''), ':balance_after'=>number_format((float)$payer['balance'],6,'.',''), ':type'=>'question_bounty_locked_pay', ':title'=>$title, ':remark'=>$remark . ($fee>0 ? '；手续费 ' . number_format($fee,6,'.','') : ''), ':ref_type'=>$refType, ':ref_id'=>$refId]);
            if ($net > 0) {
                $db->prepare("INSERT INTO wallet_transactions (user_id,currency_code,amount,balance_after,type,title,remark,ref_type,ref_id,created_at) VALUES (:uid,:code,:amount,:balance_after,:type,:title,:remark,:ref_type,:ref_id,NOW())")
                    ->execute([':uid'=>$receiverId, ':code'=>$currencyCode, ':amount'=>number_format($net,6,'.',''), ':balance_after'=>number_format($receiverBalance,6,'.',''), ':type'=>'question_bounty_receive', ':title'=>'问答悬赏收入', ':remark'=>$remark, ':ref_type'=>$refType, ':ref_id'=>$refId]);
            }
            if ($fee > 0) {
                $db->prepare("INSERT INTO wallet_transactions (user_id,currency_code,amount,balance_after,type,title,remark,ref_type,ref_id,created_at) VALUES (:uid,:code,:amount,:balance_after,:type,:title,:remark,:ref_type,:ref_id,NOW())")
                    ->execute([':uid'=>$payerId, ':code'=>$currencyCode, ':amount'=>number_format(-$fee,6,'.',''), ':balance_after'=>number_format((float)$payer['balance'],6,'.',''), ':type'=>'question_bounty_accept_fee', ':title'=>'问答悬赏采纳手续费', ':remark'=>$remark, ':ref_type'=>$refType, ':ref_id'=>$refId]);
            }
            if ($ownTransaction) $db->commit();
        } catch (\Throwable $e) { if ($ownTransaction && $db->inTransaction()) $db->rollBack(); throw $e; }
    }

    private function moveLocked(int $userId, string $currencyCode, float $amount, string $direction, string $type, string $title, string $remark = '', ?string $refType = null, ?int $refId = null): void
    {
        $currencyCode = function_exists('currency_resolve_code') ? currency_resolve_code($currencyCode) : strtoupper(trim($currencyCode));
        if ($userId <= 0 || $currencyCode === '' || $amount <= 0) throw new \RuntimeException('冻结参数不正确');
        $this->ensureWallets($userId);
        $db = Database::connection();
        $ownTransaction = !$db->inTransaction();
        if ($ownTransaction) $db->beginTransaction();
        try {
            $stmt=$db->prepare("SELECT balance,locked_balance FROM wallets WHERE user_id=:uid AND currency_code=:code FOR UPDATE");
            $stmt->execute([':uid'=>$userId, ':code'=>$currencyCode]);
            $row=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$row) throw new \RuntimeException('钱包不存在');
            $balance=(float)$row['balance']; $locked=(float)$row['locked_balance'];
            if($direction==='lock') { if($balance + 0.000001 < $amount) throw new \RuntimeException('余额不足，无法冻结悬赏'); $balance-=$amount; $locked+=$amount; $delta=-$amount; }
            else { if($locked + 0.000001 < $amount) throw new \RuntimeException('冻结余额不足'); $locked-=$amount; $balance+=$amount; $delta=$amount; }
            $db->prepare("UPDATE wallets SET balance=:balance, locked_balance=:locked, updated_at=NOW() WHERE user_id=:uid AND currency_code=:code")->execute([':balance'=>number_format(max(0,$balance),6,'.',''), ':locked'=>number_format(max(0,$locked),6,'.',''), ':uid'=>$userId, ':code'=>$currencyCode]);
            $db->prepare("INSERT INTO wallet_transactions (user_id,currency_code,amount,balance_after,type,title,remark,ref_type,ref_id,created_at) VALUES (:uid,:code,:amount,:balance_after,:type,:title,:remark,:ref_type,:ref_id,NOW())")
                ->execute([':uid'=>$userId, ':code'=>$currencyCode, ':amount'=>number_format($delta,6,'.',''), ':balance_after'=>number_format(max(0,$balance),6,'.',''), ':type'=>$type, ':title'=>$title, ':remark'=>$remark, ':ref_type'=>$refType, ':ref_id'=>$refId]);
            if ($ownTransaction) $db->commit();
        } catch (\Throwable $e) { if ($ownTransaction && $db->inTransaction()) $db->rollBack(); throw $e; }
    }

    public function addTransaction(int $userId, string $currencyCode, string $amount, string $type, string $title, string $remark = '', ?int $operatorId = null, ?int $reversalOf = null, ?string $refType = null, ?int $refId = null): void
    {
        $currencyCode = function_exists('currency_resolve_code') ? currency_resolve_code($currencyCode) : strtoupper(trim($currencyCode));
        $delta = (float)$amount;
        if ($userId <= 0 || $currencyCode === '' || abs($delta) < 0.0000001) {
            throw new \RuntimeException('钱包变动参数不正确');
        }
        $this->ensureWallets($userId);
        $db = Database::connection();
        $ownTransaction = !$db->inTransaction();
        if ($ownTransaction) $db->beginTransaction();
        try {
            $currencyStmt = $db->prepare("SELECT code FROM currencies WHERE code=:code AND status='active' LIMIT 1");
            $currencyStmt->execute([':code' => $currencyCode]);
            if (!$currencyStmt->fetchColumn()) {
                throw new \RuntimeException('货币不存在或已停用');
            }

            $stmt = $db->prepare("SELECT balance FROM wallets WHERE user_id=:user_id AND currency_code=:code FOR UPDATE");
            $stmt->execute([':user_id'=>$userId, ':code'=>$currencyCode]);
            $balanceRaw = $stmt->fetchColumn();
            if ($balanceRaw === false) {
                throw new \RuntimeException('钱包不存在，请刷新后重试');
            }
            $balance = (float)$balanceRaw;
            $newBalance = $balance + $delta;
            if ($delta < 0 && $newBalance < -0.000001) {
                throw new \RuntimeException('余额不足');
            }
            $newBalanceString = number_format(max(0, $newBalance), 6, '.', '');
            $update = $db->prepare("UPDATE wallets SET balance=:balance, updated_at=NOW() WHERE user_id=:user_id AND currency_code=:code");
            $update->execute([':balance'=>$newBalanceString, ':user_id'=>$userId, ':code'=>$currencyCode]);
            if ($update->rowCount() < 1) {
                throw new \RuntimeException('钱包余额更新失败');
            }
            $db->prepare("INSERT INTO wallet_transactions (user_id, currency_code, amount, balance_after, type, operator_id, reversal_of, title, remark, ref_type, ref_id, created_at) VALUES (:user_id, :code, :amount, :balance_after, :type, :operator_id, :reversal_of, :title, :remark, :ref_type, :ref_id, NOW())")
                ->execute([':user_id'=>$userId, ':code'=>$currencyCode, ':amount'=>number_format($delta, 6, '.', ''), ':balance_after'=>$newBalanceString, ':type'=>$type, ':operator_id'=>$operatorId, ':reversal_of'=>$reversalOf, ':title'=>$title, ':remark'=>$remark, ':ref_type'=>$refType, ':ref_id'=>$refId]);
            if ($ownTransaction) $db->commit();
        } catch (\Throwable $e) {
            if ($ownTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
