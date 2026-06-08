<?php

namespace App\Controllers\Web;

use App\Models\AvatarFrameModel;
use App\Services\AvatarFrameService;

class AvatarFrameController
{
    private AvatarFrameService $service;
    private AvatarFrameModel $model;

    public function __construct()
    {
        $this->service = new AvatarFrameService();
        $this->service->ensureSchema();
        $this->service->seedDefaults();
        $this->model = new AvatarFrameModel();
    }

    public function index(): void
    {
        if (!function_exists('auth_check') || !auth_check()) {
            redirect_or_ajax('/index.php?path=login&redirect=' . urlencode('/index.php?path=avatar-frames'));
            return;
        }
        $user = $_SESSION['auth_user'] ?? [];
        $userId = (int)($user['id'] ?? 0);
        $message = '';
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $action = (string)($_POST['_action'] ?? '');
            try {
                if ($action === 'equip') {
                    $this->service->equip($userId, (int)($_POST['frame_id'] ?? 0));
                    redirect_or_ajax('/index.php?path=avatar-frames');
                    return;
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }
        $frames = $this->model->userFrames($userId, false);
        $equipped = $this->model->equippedForUser($userId);
        require dirname(__DIR__, 2) . '/views/web/avatar_frames/index.php';
    }
}
