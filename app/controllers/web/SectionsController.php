<?php

namespace App\Controllers\Web;

use App\Models\SectionModel;
use App\Models\BannerModel;

class SectionsController
{
    public function index(): void
    {
        $sections = [];
        $sectionBroadcasts = [];
        $hotSections = [];
        $homeBanners = [];
        try {
            $model    = new SectionModel();
            $sections = $model->list();
            $hotSections = $model->hot(5);
            $sectionBroadcasts = (new BannerModel())->sectionBroadcasts(8);
            $homeBanners = (new BannerModel())->active('home');
        } catch (\Throwable $e) {
            
        }

        
        $grouped = [];
        foreach ($sections as $s) {
            $cat = $s['category_name'] ?? '其他';
            $grouped[$cat][] = $s;
        }

        require theme_view('web/sections/index.php');
    }
}
