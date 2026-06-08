<?php

namespace App\Controllers\Web;

use App\Models\SectionModel;
use App\Models\ThreadModel;

class SectionController
{
    public function index(): void
    {
        $sectionId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $page      = max(1, (int) ($_GET['page'] ?? 1));
        $filterRaw = (string) ($_GET['filter'] ?? 'all');
        $filter    = in_array($filterRaw, ['all', 'hot', 'recommended', 'featured'], true) ? $filterRaw : 'all';
        $pageSize  = 20;
        $offset    = ($page - 1) * $pageSize;

        $section    = null;
        $threads    = [];
        $total      = 0;
        $totalPages = 1;
        $moderators = [];
        $topThreads = [];
        $sectionFollowed = false;
        $sectionFollowerCount = 0;

        try {
            $sectionModel = new SectionModel();
            $threadModel  = new ThreadModel();

            $section    = $sectionModel->findById($sectionId);
            $moderators = $section ? $sectionModel->moderators($sectionId) : [];
            if ($section) {
                $followModel = new \App\Models\SectionFollowModel();
                $sectionFollowerCount = $followModel->countBySection($sectionId);
                $sectionFollowed = auth_check() ? $followModel->isFollowing((int)(auth_user()['id'] ?? 0), $sectionId) : false;
            }
            $topThreads = ($section && $page === 1) ? array_merge($threadModel->topGlobal(8), $threadModel->topForSection($sectionId, 8)) : [];
            $threads    = $threadModel->bySectionId($sectionId, $pageSize, $offset, $filter);
            $total      = $threadModel->countBySectionId($sectionId, $filter);
            $totalPages = $total > 0 ? (int) ceil($total / $pageSize) : 1;
        } catch (\Throwable $e) {
            
        }

        require theme_view('web/section/index.php');
    }

    public function toggleFollow(): void
    {
        if (!auth_check()) { header('Location: /index.php?path=login'); exit; }
        csrf_verify();
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $section = $sectionId > 0 ? (new SectionModel())->findById($sectionId) : null;
        if ($section) {
            (new \App\Models\SectionFollowModel())->toggle((int)(auth_user()['id'] ?? 0), $sectionId);
        }
        redirect_or_ajax('/index.php?path=section&id=' . $sectionId);
    }
}
