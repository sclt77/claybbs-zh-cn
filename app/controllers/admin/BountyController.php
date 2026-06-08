<?php

namespace App\Controllers\Admin;

use App\Middleware\AdminAuth;
use App\Middleware\Permission;
use App\Models\QuestionBountyModel;
use App\Models\PostModel;

class BountyController
{
    public function __construct()
    {
        AdminAuth::check();
        Permission::require('review.bounty');
    }

    public function index(): void
    {
        $model = new QuestionBountyModel();
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            try {
                $action = (string)($_POST['_action'] ?? '');
                if ($action === 'settings') {
                    $model->saveSettings($_POST);
                    redirect_or_ajax('/admin.php?path=bounties');
                }
                if ($action === 'review') {
                    $model->review((int)$_POST['review_id'], (string)$_POST['decision'], (int)($_POST['post_id'] ?? 0), (int)($_SESSION['auth_user']['id'] ?? 0), (string)($_POST['note'] ?? ''));
                    redirect_or_ajax('/admin.php?path=bounties');
                }
            } catch (\Throwable $e) { $error = $e->getMessage(); }
        }
        $settings = $model->settings();
        $reviews = $model->reviews((string)($_GET['status'] ?? 'pending'));
        require dirname(__DIR__, 2) . '/views/admin/content/bounties.php';
    }
}
