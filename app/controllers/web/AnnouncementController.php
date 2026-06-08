<?php

namespace App\Controllers\Web;

use App\Models\AnnouncementModel;

class AnnouncementController
{
    public function index(): void
    {
        $announcements = [];

        try {
            $announcements = (new AnnouncementModel())->active(50);
        } catch (\Throwable $e) {
            $announcements = [];
        }

        require theme_view('web/announcement/index.php');
    }

    public function show(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $announcement = null;

        if ($id > 0) {
            try {
                $announcement = (new AnnouncementModel())->find($id);
            } catch (\Throwable $e) {
                $announcement = null;
            }
        }

        if (!$announcement) {
            http_response_code(404);
        }

        require theme_view('web/announcement/show.php');
    }
}
