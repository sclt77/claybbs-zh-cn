<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ThreadPaywallModel
{
    public function ensureTable(): void
    {
        
    }


    public function canView(array $thread, int $userId): bool
    {
        if (empty($thread['paid_visible_enabled'])) return true;
        if ($userId > 0 && (int)($thread['user_id'] ?? 0) === $userId) return true;
        if (\App\Middleware\Permission::canAnyScope('admin.access')) return true;
        if ($userId <= 0) return false;
        $this->ensureTable();
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM thread_paywall_unlocks WHERE thread_id=:tid AND user_id=:uid');
        $stmt->execute([':tid'=>(int)$thread['id'], ':uid'=>$userId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function unlock(int $userId, int $threadId): void
    {
        if ($userId <= 0) throw new \RuntimeException('请先登录');
        $this->ensureTable();
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM threads WHERE id=:id AND status=\'published\' LIMIT 1');
        $stmt->execute([':id'=>$threadId]);
        $thread = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$thread || empty($thread['paid_visible_enabled'])) throw new \RuntimeException('帖子无需付费解锁');
        if ((int)$thread['user_id'] === $userId) return;
        if ($this->canView($thread, $userId)) return;
        $configuredCurrency = strtoupper(trim((string)($thread['paid_visible_currency'] ?? '')));
        $currency = function_exists('currency_resolve_code') ? currency_resolve_code($configuredCurrency) : $configuredCurrency;
        $amount = (float)($thread['paid_visible_price'] ?? 0);
        if ($currency === '' || $amount <= 0) throw new \RuntimeException('付费可见配置不完整');
        $authorId = (int)$thread['user_id'];
        $wallet = new WalletModel();
        $wallet->ensureWallets($userId); $wallet->ensureWallets($authorId);
        $amountString = number_format($amount, 6, '.', '');
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT balance FROM wallets WHERE user_id=:uid AND currency_code=:code FOR UPDATE');
            $stmt->execute([':uid'=>$userId, ':code'=>$currency]);
            $buyerBalanceRaw = $stmt->fetchColumn();
            if ($buyerBalanceRaw === false) throw new \RuntimeException('付费货币不存在或已停用');
            $buyerBalance = (float)$buyerBalanceRaw;
            if ($buyerBalance + 0.000001 < $amount) throw new \RuntimeException('余额不足');
            $stmt->execute([':uid'=>$authorId, ':code'=>$currency]);
            $authorBalanceRaw = $stmt->fetchColumn();
            if ($authorBalanceRaw === false) throw new \RuntimeException('作者钱包不存在，请稍后重试');
            $authorBalance = (float)$authorBalanceRaw;
            $buyerAfter = $buyerBalance - $amount; $authorAfter = $authorBalance + $amount;
            $db->prepare('UPDATE wallets SET balance=:b, updated_at=NOW() WHERE user_id=:uid AND currency_code=:code')->execute([':b'=>number_format($buyerAfter,6,'.',''), ':uid'=>$userId, ':code'=>$currency]);
            $db->prepare('UPDATE wallets SET balance=:b, updated_at=NOW() WHERE user_id=:uid AND currency_code=:code')->execute([':b'=>number_format($authorAfter,6,'.',''), ':uid'=>$authorId, ':code'=>$currency]);
            $db->prepare('INSERT INTO thread_paywall_unlocks (thread_id,user_id,currency_code,amount,created_at) VALUES (:tid,:uid,:code,:amount,NOW())')->execute([':tid'=>$threadId, ':uid'=>$userId, ':code'=>$currency, ':amount'=>$amountString]);
            $title = mb_substr((string)$thread['title'], 0, 80);
            $tx = $db->prepare("INSERT INTO wallet_transactions (user_id,currency_code,amount,balance_after,type,title,remark,ref_type,ref_id,created_at) VALUES (:uid,:code,:amount,:balance,:type,:title,:remark,'thread_paywall',:ref,NOW())");
            $tx->execute([':uid'=>$userId, ':code'=>$currency, ':amount'=>'-'.$amountString, ':balance'=>number_format($buyerAfter,6,'.',''), ':type'=>'thread_paywall', ':title'=>'付费阅读', ':remark'=>'解锁帖子《'.$title.'》', ':ref'=>$threadId]);
            $tx->execute([':uid'=>$authorId, ':code'=>$currency, ':amount'=>$amountString, ':balance'=>number_format($authorAfter,6,'.',''), ':type'=>'thread_paywall', ':title'=>'付费阅读收入', ':remark'=>'帖子《'.$title.'》被付费解锁', ':ref'=>$threadId]);
            $db->commit();
        } catch (\Throwable $e) { $db->rollBack(); throw $e; }
        try { (new SystemMessageModel())->createPersonal($authorId, '收到付费阅读收入', '你的帖子《' . mb_substr((string)$thread['title'],0,80) . '》被付费解锁。', 1, 'finance'); } catch (\Throwable $e) {}
    }
}
