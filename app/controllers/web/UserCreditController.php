<?php

namespace App\Controllers\Web;

use App\Models\UserCreditModel;

class UserCreditController
{
    public function index(): void
    {
        $authUser = auth_user();
        if (!$authUser) {
            header('Location: /index.php?path=login&redirect=' . urlencode('/index.php?path=credit'));
            exit;
        }
        $userId = (int)$authUser['id'];
        $model = new UserCreditModel();
        $summary = $model->summary($userId);
        $logs = $model->logs($userId, 80);
        require theme_view('web/user/credit.php');
    }
}
