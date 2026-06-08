<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class AdminFinanceModel
{
    public function currencies(): array
    {
        (new WalletModel())->ensureDefaultCurrencies();
        return Database::connection()->query("SELECT c.*, COALESCE(w.wallet_count,0) AS wallet_count, COALESCE(w.balance_sum,0) AS balance_sum, COALESCE(tx.tx_count,0) AS tx_count FROM currencies c LEFT JOIN (SELECT currency_code, COUNT(*) AS wallet_count, SUM(balance) AS balance_sum FROM wallets GROUP BY currency_code) w ON w.currency_code=c.code LEFT JOIN (SELECT currency_code, COUNT(*) AS tx_count FROM wallet_transactions GROUP BY currency_code) tx ON tx.currency_code=c.code ORDER BY c.sort_order ASC, c.id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function overview(): array
    {
        $db = Database::connection();
        $wallets = $db->query("SELECT COUNT(*) AS wallet_count, COALESCE(SUM(balance),0) AS balance_sum, COALESCE(SUM(locked_balance),0) AS locked_sum FROM wallets")->fetch(PDO::FETCH_ASSOC) ?: [];
        $tx = $db->query("SELECT COUNT(*) AS tx_count, COALESCE(SUM(CASE WHEN amount>0 THEN amount ELSE 0 END),0) AS income, COALESCE(SUM(CASE WHEN amount<0 THEN amount ELSE 0 END),0) AS expense FROM wallet_transactions")->fetch(PDO::FETCH_ASSOC) ?: [];
        $users = $db->query("SELECT COUNT(DISTINCT user_id) FROM wallets WHERE balance<>0 OR locked_balance<>0")->fetchColumn();
        return ['wallet_count'=>(int)($wallets['wallet_count'] ?? 0), 'balance_sum'=>(float)($wallets['balance_sum'] ?? 0), 'locked_sum'=>(float)($wallets['locked_sum'] ?? 0), 'tx_count'=>(int)($tx['tx_count'] ?? 0), 'income'=>(float)($tx['income'] ?? 0), 'expense'=>(float)($tx['expense'] ?? 0), 'active_users'=>(int)$users];
    }

    public function walletRows(array $filters = [], int $page = 1, int $pageSize = 30): array
    {
        [$where, $params] = $this->walletWhere($filters);
        $offset = ($page - 1) * $pageSize;
        $sql = "SELECT w.*, u.username, u.nickname, c.name AS currency_name, c.symbol, c.precision, c.status AS currency_status FROM wallets w LEFT JOIN users u ON u.id=w.user_id LEFT JOIN currencies c ON c.code=w.currency_code {$where} ORDER BY w.updated_at DESC, w.id DESC LIMIT :limit OFFSET :offset";
        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $k=>$v) $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countWalletRows(array $filters = []): int
    {
        [$where, $params] = $this->walletWhere($filters);
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM wallets w LEFT JOIN users u ON u.id=w.user_id {$where}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }


    public function walletUserGroups(array $filters = [], int $page = 1, int $pageSize = 20): array
    {
        [$where, $params] = $this->walletWhere($filters);
        $offset = max(0, ($page - 1) * $pageSize);
        $sql = "SELECT u.id AS user_id, u.username, u.nickname, MAX(w.updated_at) AS wallet_updated_at,
                       COUNT(w.id) AS wallet_count, COALESCE(SUM(w.balance),0) AS balance_sum, COALESCE(SUM(w.locked_balance),0) AS locked_sum
                FROM wallets w
                LEFT JOIN users u ON u.id=w.user_id
                {$where}
                GROUP BY u.id, u.username, u.nickname
                ORDER BY wallet_updated_at DESC, u.id DESC
                LIMIT :limit OFFSET :offset";
        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $k=>$v) $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$groups) return [];

        $userIds = array_map(static fn($row) => (int)$row['user_id'], $groups);
        $placeholders = [];
        $walletParams = [];
        foreach ($userIds as $i => $uid) {
            $ph = ':uid_' . $i;
            $placeholders[] = $ph;
            $walletParams[$ph] = $uid;
        }
        $currencySql = '';
        $currency = strtoupper(trim((string)($filters['currency'] ?? '')));
        if ($currency !== '') {
            $currencySql = ' AND w.currency_code = :currency';
            $walletParams[':currency'] = $currency;
        }
        $balance = trim((string)($filters['balance'] ?? ''));
        $balanceSql = '';
        if ($balance === 'positive') $balanceSql = ' AND w.balance > 0';
        elseif ($balance === 'zero') $balanceSql = ' AND w.balance = 0 AND w.locked_balance = 0';
        elseif ($balance === 'locked') $balanceSql = ' AND w.locked_balance > 0';

        $walletSql = "SELECT w.*, c.name AS currency_name, c.symbol, c.precision, c.status AS currency_status
                      FROM wallets w
                      LEFT JOIN currencies c ON c.code=w.currency_code
                      WHERE w.user_id IN (" . implode(',', $placeholders) . ") {$currencySql} {$balanceSql}
                      ORDER BY c.sort_order ASC, c.id ASC, w.currency_code ASC";
        $walletStmt = Database::connection()->prepare($walletSql);
        $walletStmt->execute($walletParams);
        $walletMap = [];
        foreach ($walletStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $wallet) {
            $walletMap[(int)$wallet['user_id']][] = $wallet;
        }
        foreach ($groups as &$group) {
            $group['wallets'] = $walletMap[(int)$group['user_id']] ?? [];
        }
        unset($group);
        return $groups;
    }

    public function countWalletUsers(array $filters = []): int
    {
        [$where, $params] = $this->walletWhere($filters);
        $stmt = Database::connection()->prepare("SELECT COUNT(DISTINCT w.user_id) FROM wallets w LEFT JOIN users u ON u.id=w.user_id {$where}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function saveCurrency(array $data): void
    {
        $id = (int)($data['id'] ?? 0);
        $db = Database::connection();
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') return;

        $existing = null;
        if ($id > 0) {
            $stmt = $db->prepare("SELECT * FROM currencies WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $payload = [
            ':name' => $name,
            ':symbol' => trim((string)($data['symbol'] ?? ($existing['symbol'] ?? ''))),
            ':exchange_rate' => max(0.000001, (float)($data['exchange_rate'] ?? 1)),
            ':precision' => 0,
            ':status' => in_array(($data['status'] ?? 'active'), ['active','inactive'], true) ? $data['status'] : 'active',
            ':sort_order' => (int)($data['sort_order'] ?? 0),
        ];
        if ($id > 0 && $existing) {
            $payload[':id'] = $id;
            $db->prepare("UPDATE currencies SET name=:name,symbol=:symbol,exchange_rate=:exchange_rate,`precision`=:precision,status=:status,sort_order=:sort_order WHERE id=:id")->execute($payload);
        } else {
            $payload[':code'] = $this->generateCurrencyCode($name);
            $db->prepare("INSERT INTO currencies (code,name,symbol,exchange_rate,`precision`,status,sort_order,created_at) VALUES (:code,:name,:symbol,:exchange_rate,:precision,:status,:sort_order,NOW())")->execute($payload);
        }
    }

    private function generateCurrencyCode(string $name): string
    {
        $base = strtoupper(trim((string)preg_replace('/[^A-Za-z0-9]+/', '_', $name), '_'));
        if ($base === '' || preg_match('/^[0-9]/', $base)) {
            $base = 'COIN';
        }
        $base = substr($base, 0, 14) ?: 'COIN';
        $db = Database::connection();
        $code = $base;
        $i = 1;
        $stmt = $db->prepare("SELECT COUNT(*) FROM currencies WHERE code = :code");
        while (true) {
            $stmt->execute([':code' => $code]);
            if ((int)$stmt->fetchColumn() === 0) return $code;
            $suffix = '_' . $i++;
            $code = substr($base, 0, 20 - strlen($suffix)) . $suffix;
        }
    }

    public function deleteCurrency(int $id): bool
    {
        if ($id <= 0) return false;
        $db = Database::connection();
        $stmt = $db->prepare("SELECT code FROM currencies WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $code = (string)$stmt->fetchColumn();
        if ($code === '') return false;

        
        $tables = ['wallet_transactions', 'wallets'];
        foreach ($tables as $table) {
            try {
                $del = $db->prepare("DELETE FROM {$table} WHERE currency_code = :code");
                $del->execute([':code' => $code]);
            } catch (\Throwable $e) {}
        }
        $delete = $db->prepare("DELETE FROM currencies WHERE id = :id");
        $delete->execute([':id'=>$id]);
        return $delete->rowCount() > 0;
    }

    public function userWallets(int $userId): array
    {
        return (new WalletModel())->balances($userId);
    }

    public function transactions(array $filters = [], int $page = 1, int $pageSize = 30): array
    {
        [$where, $params] = $this->txWhere($filters);
        $offset = ($page - 1) * $pageSize;
        $sql = "SELECT t.*, u.username, u.nickname, c.symbol, c.name AS currency_name, c.precision, rb.id AS reversed_by, src.id AS reversal_source_id, src.title AS reversal_source_title FROM wallet_transactions t LEFT JOIN users u ON u.id=t.user_id LEFT JOIN currencies c ON c.code=t.currency_code LEFT JOIN wallet_transactions rb ON rb.reversal_of=t.id LEFT JOIN wallet_transactions src ON src.id=t.reversal_of {$where} ORDER BY t.created_at DESC, t.id DESC LIMIT :limit OFFSET :offset";
        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $k=>$v) $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countTransactions(array $filters = []): int
    {
        [$where, $params] = $this->txWhere($filters);
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM wallet_transactions t LEFT JOIN users u ON u.id=t.user_id {$where}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function adjustWallet(int $userId, string $currency, string $amount, string $title, string $remark = '', ?int $operatorId = null, ?int $reversalOf = null, string $type = 'admin_adjust'): void
    {
        $currencyCode = strtoupper($currency);
        (new WalletModel())->addTransaction($userId, $currencyCode, $amount, $type, $title, $remark, $operatorId, $reversalOf);
        try {
            (new SystemMessageModel())->createPersonal($userId, '财务变动通知', $title . '：' . currency_pay_label((float)$amount, $currencyCode) . ($remark !== '' ? "\n" . $remark : ''), 1);
        } catch (\Throwable $e) {}
    }

    public function findTransaction(int $id): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM wallet_transactions WHERE id = :id LIMIT 1");
        $stmt->execute([':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function reverseTransaction(int $id, int $operatorId, string $remark): void
    {
        $tx = $this->findTransaction($id);
        if (!$tx || $remark === '' || ($tx['type'] ?? '') === 'reversal') return;
        $check = Database::connection()->prepare("SELECT COUNT(*) FROM wallet_transactions WHERE reversal_of = :id");
        $check->execute([':id'=>$id]);
        if ((int)$check->fetchColumn() > 0) return;
        $amount = (string)(-(float)$tx['amount']);
        $this->adjustWallet((int)$tx['user_id'], (string)$tx['currency_code'], $amount, '财务冲正', $remark, $operatorId, $id, 'reversal');
    }

    private function txWhere(array $filters): array
    {
        $where = 'WHERE 1=1'; $params = [];
        $kw = trim((string)($filters['kw'] ?? ''));
        if ($kw !== '') { $where .= ' AND (u.username LIKE :kw OR u.nickname LIKE :kw OR t.title LIKE :kw OR t.remark LIKE :kw)'; $params[':kw'] = "%{$kw}%"; }
        $currency = strtoupper(trim((string)($filters['currency'] ?? '')));
        if ($currency !== '') { $where .= ' AND t.currency_code = :currency'; $params[':currency'] = $currency; }
        $type = trim((string)($filters['type'] ?? ''));
        if ($type !== '') {
            if ($type === 'income') $where .= ' AND t.amount > 0';
            elseif ($type === 'expense') $where .= ' AND t.amount < 0';
            elseif ($type === 'reversal') $where .= " AND t.type = 'reversal'";
            else { $where .= ' AND t.type = :type'; $params[':type'] = $type; }
        }
        return [$where, $params];
    }

    private function walletWhere(array $filters): array
    {
        $where = 'WHERE 1=1'; $params = [];
        $kw = trim((string)($filters['kw'] ?? ''));
        if ($kw !== '') { $where .= ' AND (u.username LIKE :wkw OR u.nickname LIKE :wkw OR w.user_id = :wuid)'; $params[':wkw'] = "%{$kw}%"; $params[':wuid'] = ctype_digit($kw) ? (int)$kw : 0; }
        $currency = strtoupper(trim((string)($filters['currency'] ?? '')));
        if ($currency !== '') { $where .= ' AND w.currency_code = :wcurrency'; $params[':wcurrency'] = $currency; }
        $balance = trim((string)($filters['balance'] ?? ''));
        if ($balance === 'positive') $where .= ' AND w.balance > 0';
        elseif ($balance === 'zero') $where .= ' AND w.balance = 0 AND w.locked_balance = 0';
        elseif ($balance === 'locked') $where .= ' AND w.locked_balance > 0';
        return [$where, $params];
    }
}
