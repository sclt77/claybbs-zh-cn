<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\AdminAuditLogModel;
use App\Core\Database;
use App\Models\AdminFinanceModel;
use App\Models\AdminUserModel;


class FinanceController
{
    public function __construct()
    {
        AdminAuth::check();
        Permission::require('finance.view');
    }

    public function index(): void
    {
        $tab = (string)($_GET['tab'] ?? 'wallets');
        $model = new AdminFinanceModel();
        $currencies = $model->currencies();
        $overview = $model->overview();
        $walletUser = null;
        $wallets = [];
        $walletRows = [];
        $walletGroups = [];
        $transactions = [];
        $total = 0;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $totalPages = 1;
        $kw = trim((string)($_GET['kw'] ?? ''));
        try {
            
            $db = Database::connection();
            $db->exec("DELETE FROM wallet_transactions WHERE currency_code NOT IN (SELECT code FROM currencies)");
            $db->exec("DELETE FROM wallets WHERE currency_code NOT IN (SELECT code FROM currencies)");

            if ($tab === 'transactions') {
                $filters = ['kw'=>$kw, 'currency'=>(string)($_GET['currency'] ?? ''), 'type'=>(string)($_GET['type'] ?? '')];
                $transactions = $model->transactions($filters, $page, 30);
                $total = $model->countTransactions($filters);
                $totalPages = max(1, (int)ceil($total / 30));
            } elseif ($tab === 'wallets') {
                $filters = ['kw'=>$kw, 'currency'=>(string)($_GET['currency'] ?? ''), 'balance'=>(string)($_GET['balance'] ?? '')];
                $walletGroups = $model->walletUserGroups($filters, $page, 20);
                $total = $model->countWalletUsers($filters);
                $totalPages = max(1, (int)ceil($total / 20));
                if (!empty($_GET['user'])) {
                    $walletUser = (new AdminUserModel())->findByIdOrUsername((string)$_GET['user']);
                    if ($walletUser) $wallets = $model->userWallets((int)$walletUser['id']);
                } elseif (!empty($_GET['user_id'])) {
                    $walletUser = (new AdminUserModel())->find((int)$_GET['user_id']);
                    if ($walletUser) $wallets = $model->userWallets((int)$walletUser['id']);
                }
            }
        } catch (\Throwable $e) {}
        require dirname(__DIR__, 2) . '/views/admin/content/finance.php';
    }

    public function currency(): void
    {
        Permission::require('finance.currency');
        csrf_verify();
        $data = $_POST;
        (new AdminFinanceModel())->saveCurrency($data);
        (new AdminAuditLogModel())->record('finance.currency.save', 'currency', (int)($data['id'] ?? 0), ['code' => (string)($data['code'] ?? ''), 'name' => (string)($data['name'] ?? '')]);
        redirect_or_ajax('/admin.php?path=finance&tab=currencies');
    }

    public function deleteCurrency(): void
    {
        Permission::require('finance.currency');
        csrf_verify();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            (new AdminFinanceModel())->deleteCurrency($id);
            (new AdminAuditLogModel())->record('finance.currency.delete', 'currency', $id);
        }
        redirect_or_ajax('/admin.php?path=finance&tab=currencies');
    }

    public function reverse(): void
    {
        Permission::require('finance.reverse');
        csrf_verify();
        $id = (int)($_POST['id'] ?? 0);
        $remark = trim((string)($_POST['remark'] ?? ''));
        $confirm = trim((string)($_POST['confirm_text'] ?? ''));
        if ($id > 0 && $remark !== '' && $confirm === 'REVERSE') {
            (new AdminFinanceModel())->reverseTransaction($id, (int)($_SESSION['auth_user']['id'] ?? 0), $remark);
            (new AdminAuditLogModel())->record('finance.transaction.reverse', 'wallet_transaction', $id, ['remark' => $remark]);
        }
        redirect_or_ajax('/admin.php?path=finance&tab=transactions');
    }

    public function adjust(): void
    {
        Permission::require('finance.adjust');
        csrf_verify();
        $userId = (int)($_POST['user_id'] ?? 0);
        $currency = (string)($_POST['currency'] ?? '');
        $amount = trim((string)($_POST['amount'] ?? '0'));
        $title = trim((string)($_POST['title'] ?? '后台余额调整'));
        $remark = trim((string)($_POST['remark'] ?? ''));
        $confirm = trim((string)($_POST['confirm_text'] ?? ''));
        if ($userId > 0 && is_numeric($amount) && $currency !== '' && $remark !== '' && $confirm === 'ADJUST') {
            (new AdminFinanceModel())->adjustWallet($userId, strtoupper($currency), $amount, $title, $remark, (int)($_SESSION['auth_user']['id'] ?? 0));
            (new AdminAuditLogModel())->record('finance.wallet.adjust', 'user', $userId, ['currency' => strtoupper($currency), 'amount' => $amount, 'remark' => $remark]);
        }
        redirect_or_ajax('/admin.php?path=finance&user_id=' . $userId);
    }
}
