<?php

namespace App\Controllers\Web;

use App\Middleware\Permission;
use App\Models\BubbleModel;
use App\Services\BubbleService;

class BubbleController
{
    public function index(): void
    {
        $model = new BubbleModel();
        $service = new BubbleService();
        $userId = (int)($_SESSION['auth_user']['id'] ?? 0);
        if ($userId <= 0) {
            header('Location: /index.php?path=login&redirect=' . urlencode('/index.php?path=decoration&tab=bubbles'));
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Permission::requireLogin();
            csrf_verify();
            try {
                $action = (string)($_POST['_action'] ?? '');
                if ($action === 'equip') {
                    $service->equip($userId, (int)($_POST['bubble_id'] ?? 0));
                    redirect_or_ajax('/index.php?path=decoration&tab=bubbles&msg=equipped');
                }
                redirect_or_ajax('/index.php?path=decoration&tab=bubbles');
            } catch (\Throwable $e) {
                if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
                    http_response_code(422);
                    header('Content-Type: application/json; charset=UTF-8');
                    echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $error = $e->getMessage();
            }
        }
        
        header('Location: /index.php?path=decoration&tab=bubbles');
        exit;
    }
}
