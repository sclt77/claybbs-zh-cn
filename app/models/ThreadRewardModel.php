<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

class ThreadRewardModel
{
    public function currenciesForUser(int $userId): array
    {
        return (new WalletModel())->balances($userId);
    }

    public function reward(int $userId, int $threadId, string $currencyCode, string $amountInput): int
    {
        $currencyCode = strtoupper(trim($currencyCode));
        $amount = (float)$amountInput;
        if ($userId <= 0) {
            throw new RuntimeException('请先登录后再打赏');
        }
        if ($threadId <= 0 || $currencyCode === '' || $amount <= 0) {
            throw new RuntimeException('请选择打赏货币并填写正确金额');
        }

        $db = Database::connection();
        $threadStmt = $db->prepare("SELECT id, user_id, title, status FROM threads WHERE id = :id LIMIT 1");
        $threadStmt->execute([':id' => $threadId]);
        $thread = $threadStmt->fetch(PDO::FETCH_ASSOC);
        if (!$thread || ($thread['status'] ?? '') !== 'published') {
            throw new RuntimeException('帖子不存在或暂不可打赏');
        }
        $authorId = (int)($thread['user_id'] ?? 0);
        if ($authorId <= 0) {
            throw new RuntimeException('帖子作者不存在');
        }
        if ($authorId === $userId) {
            throw new RuntimeException('不能打赏自己的帖子');
        }

        $currencyStmt = $db->prepare("SELECT * FROM currencies WHERE code = :code AND status = 'active' LIMIT 1");
        $currencyStmt->execute([':code' => $currencyCode]);
        $currency = $currencyStmt->fetch(PDO::FETCH_ASSOC);
        if (!$currency) {
            throw new RuntimeException('该货币暂不可用于打赏');
        }

        $precision = max(0, min(6, (int)($currency['precision'] ?? 0)));
        $amount = round($amount, $precision);
        if ($amount <= 0) {
            throw new RuntimeException('打赏金额必须大于 0');
        }
        if ($precision === 0 && abs($amount - floor($amount)) > 0.000001) {
            throw new RuntimeException('该货币只支持整数金额');
        }
        $amountString = number_format($amount, $precision, '.', '');

        $walletModel = new WalletModel();
        $walletModel->ensureWallets($userId);
        $walletModel->ensureWallets($authorId);

        $db->beginTransaction();
        try {
            $senderStmt = $db->prepare("SELECT balance FROM wallets WHERE user_id = :uid AND currency_code = :code FOR UPDATE");
            $senderStmt->execute([':uid' => $userId, ':code' => $currencyCode]);
            $senderBalance = (float)$senderStmt->fetchColumn();
            if ($senderBalance + 0.0000001 < $amount) {
                throw new RuntimeException('余额不足，无法完成打赏');
            }

            $receiverStmt = $db->prepare("SELECT balance FROM wallets WHERE user_id = :uid AND currency_code = :code FOR UPDATE");
            $receiverStmt->execute([':uid' => $authorId, ':code' => $currencyCode]);
            $receiverBalance = (float)$receiverStmt->fetchColumn();

            $rewardStmt = $db->prepare("INSERT INTO thread_rewards (thread_id, user_id, author_id, currency_code, amount, created_at) VALUES (:thread_id, :user_id, :author_id, :currency, :amount, NOW())");
            $rewardStmt->execute([
                ':thread_id' => $threadId,
                ':user_id' => $userId,
                ':author_id' => $authorId,
                ':currency' => $currencyCode,
                ':amount' => $amountString,
            ]);
            $rewardId = (int)$db->lastInsertId();

            $senderAfter = $senderBalance - $amount;
            $receiverAfter = $receiverBalance + $amount;
            $db->prepare("UPDATE wallets SET balance = :balance, updated_at = NOW() WHERE user_id = :uid AND currency_code = :code")
                ->execute([':balance' => number_format($senderAfter, 6, '.', ''), ':uid' => $userId, ':code' => $currencyCode]);
            $db->prepare("UPDATE wallets SET balance = :balance, updated_at = NOW() WHERE user_id = :uid AND currency_code = :code")
                ->execute([':balance' => number_format($receiverAfter, 6, '.', ''), ':uid' => $authorId, ':code' => $currencyCode]);

            $title = mb_substr((string)($thread['title'] ?? '帖子'), 0, 80);
            $txStmt = $db->prepare("INSERT INTO wallet_transactions (user_id, currency_code, amount, balance_after, type, operator_id, reversal_of, title, remark, ref_type, ref_id, created_at) VALUES (:user_id, :currency, :amount, :balance_after, :type, NULL, NULL, :title, :remark, 'thread_reward', :ref_id, NOW())");
            $txStmt->execute([
                ':user_id' => $userId,
                ':currency' => $currencyCode,
                ':amount' => '-' . $amountString,
                ':balance_after' => number_format($senderAfter, 6, '.', ''),
                ':type' => 'thread_reward',
                ':title' => '帖子打赏',
                ':remark' => '打赏帖子《' . $title . '》',
                ':ref_id' => $rewardId,
            ]);
            $txStmt->execute([
                ':user_id' => $authorId,
                ':currency' => $currencyCode,
                ':amount' => $amountString,
                ':balance_after' => number_format($receiverAfter, 6, '.', ''),
                ':type' => 'thread_reward',
                ':title' => '收到帖子打赏',
                ':remark' => '帖子《' . $title . '》收到打赏',
                ':ref_id' => $rewardId,
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        try {
            (new SystemMessageModel())->createPersonal($authorId, '收到帖子打赏', '你的帖子《' . mb_substr((string)($thread['title'] ?? '帖子'), 0, 80) . '》收到打赏：' . $this->formatAmount($amount, $currency), 1);
        } catch (\Throwable $e) {
            
        }

        return $rewardId;
    }

    public function supporterCount(int $threadId): int
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(DISTINCT user_id) FROM thread_rewards WHERE thread_id = :thread_id");
        $stmt->execute([':thread_id' => $threadId]);
        return (int)$stmt->fetchColumn();
    }

    public function topSupporters(int $threadId, int $limit = 10, int $offset = 0): array
    {
        $sql = "SELECT g.user_id,
                       u.nickname AS author_name,
                       u.username,
                       u.avatar AS author_avatar,
                       COALESCE(NULLIF(uv.display_name,''), vt.name) AS verification_name,
                       vt.color AS verification_color,
                       vt.description AS verification_description,
                       SUM(g.amount * COALESCE(c.exchange_rate, 1)) AS value_sum,
                       SUM(g.reward_count) AS reward_count,
                       GROUP_CONCAT(CONCAT(g.currency_code, '|', g.amount, '|', COALESCE(c.symbol,''), '|', COALESCE(c.precision,0), '|', COALESCE(c.name,g.currency_code)) ORDER BY (g.amount * COALESCE(c.exchange_rate, 1)) DESC SEPARATOR ';;') AS amount_parts
                FROM (
                    SELECT user_id, currency_code, SUM(amount) AS amount, COUNT(*) AS reward_count
                    FROM thread_rewards
                    WHERE thread_id = :thread_id
                    GROUP BY user_id, currency_code
                ) g
                LEFT JOIN users u ON u.id = g.user_id
                LEFT JOIN user_verifications uv ON uv.user_id = g.user_id AND uv.status = 'active'
                LEFT JOIN verification_types vt ON vt.id = uv.type_id AND vt.status = 'active'
                LEFT JOIN currencies c ON c.code = g.currency_code
                GROUP BY g.user_id, u.nickname, u.username, u.avatar, vt.name, vt.color
                ORDER BY value_sum DESC, reward_count DESC, g.user_id ASC
                LIMIT :limit OFFSET :offset";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':thread_id', $threadId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['amount_label'] = $this->formatParts((string)($row['amount_parts'] ?? ''));
        }
        unset($row);
        return $rows;
    }

    public function page(int $threadId, int $page = 1, int $pageSize = 10): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min(50, $pageSize));
        $total = $this->supporterCount($threadId);
        $totalPages = max(1, (int)ceil($total / $pageSize));
        if ($page > $totalPages) $page = $totalPages;
        $rows = $this->topSupporters($threadId, $pageSize, ($page - 1) * $pageSize);
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize, 'total_pages' => $totalPages];
    }

    private function formatAmount(float $amount, array $currency): string
    {
        $precision = max(0, min(6, (int)($currency['precision'] ?? 0)));
        $name = (string)($currency['name'] ?? $currency['code'] ?? '');
        return number_format($amount, $precision) . ($name !== '' ? ' ' . $name : '');
    }

    private function formatParts(string $parts): string
    {
        $labels = [];
        foreach (explode(';;', $parts) as $part) {
            if ($part === '') continue;
            [$code, $amount, $symbol, $precision, $name] = array_pad(explode('|', $part, 5), 5, '');
            $num = number_format((float)$amount, max(0, min(6, (int)$precision)));
            $labels[] = $num . ' ' . ($name !== '' ? $name : $code);
        }
        return implode(' / ', $labels);
    }
}
