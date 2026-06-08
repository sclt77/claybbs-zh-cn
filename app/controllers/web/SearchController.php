<?php

namespace App\Controllers\Web;

use App\Models\SearchModel;

class SearchController
{
    public function index(): void
    {
        $keyword = trim($_GET['q'] ?? '');
        $requestedType = (string)($_GET['type'] ?? 'thread');
        $type = in_array($requestedType, ['thread','user','section'], true) ? $requestedType : 'thread';
        $sectionId = max(0, (int)($_GET['section_id'] ?? 0));
        $sortRaw = (string)($_GET['sort'] ?? 'relevance');
        $sort = in_array($sortRaw, ['relevance','new','hot'], true) ? $sortRaw : 'relevance';
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $limit   = 15;
        $offset  = ($page - 1) * $limit;

        $threads    = [];
        $total      = 0;
        $totalPages = 1;
        $error      = '';
        $sections = [];
        $hotKeywords = [];
        try { $searchModel = new SearchModel(); $sections = $searchModel->sections(); $hotKeywords = $searchModel->hotKeywords(); } catch (\Throwable $e) { $sections = []; $hotKeywords = []; }

        if ($keyword !== '') {
            if (mb_strlen($keyword) < 2) {
                $error = '关键词至少 2 个字符';
            } else {
                try {
                    $model   = new SearchModel();
                    $threads = $model->searchThreads($keyword, $limit, $offset, $sectionId, $type, $sort);
                    $total   = $model->countThreads($keyword, $sectionId, $type);
                    $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;
                } catch (\Throwable $e) {
                    $error = '搜索失败，请稍后重试';
                }
            }
        }

        require theme_view('web/search/index.php');
    }
}
