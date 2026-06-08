<?php

namespace App\Controllers\Web;

use App\Models\ThreadModel;
use App\Models\SectionModel;
use App\Models\BannerModel;
use App\Models\AnnouncementModel;
use App\Models\SettingModel;

class HomeController
{
    public function index(): void
    {
        
        try {
            $settings = new SettingModel();
            $siteMode = $settings->get('site_mode', 'forum');
            if ($siteMode === 'store' && $settings->get('software_store_enabled', '1') === '1') {
                
                $_GET['path'] = 'software';
                $GLOBALS['__clay_store_home'] = true;
                (new \App\Controllers\SoftwareStoreController())->renderIndex();
                return;
            }
        } catch (\Throwable $e) {
            
        }

        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $feedRaw  = (string) ($_GET['feed'] ?? 'latest');
        $feed     = in_array($feedRaw, ['latest', 'hot', 'featured', 'bounty', 'following'], true) ? $feedRaw : 'latest';
        $viewerId = function_exists('auth_check') && auth_check() ? (int)(auth_user()['id'] ?? 0) : 0;
        if ($feed === 'following' && $viewerId <= 0) $feed = 'latest';
        $pageSize = 20;
        $offset   = ($page - 1) * $pageSize;

        $threads        = [];
        $sections       = [];
        $hotSections    = [];
        $banners        = [];
        $announcements  = [];
        $topThreads     = [];
        $activeUsers    = [];
        $total          = 0;
        $site           = [
            'site_name' => 'ClayBBS',
            'site_logo_text' => 'ClayBBS',
            'site_tagline' => '一个轻量、可持续迭代的社区论坛系统。',
            'footer_text' => '© ' . date('Y') . ' ClayBBS',
        ];

        try {
            $threadModel       = new ThreadModel();
            $sectionModel      = new SectionModel();
            $bannerModel       = new BannerModel();
            $announcementModel = new AnnouncementModel();

            $threads       = $threadModel->latest($pageSize, $offset, $feed, $viewerId);
            $topThreads    = ($page === 1 && $feed === 'latest') ? $threadModel->topGlobal(8) : [];
            $total         = $threadModel->countPublished($feed, $viewerId);
            $sections      = $sectionModel->list();
            $hotSections   = $sectionModel->hot(5);
            $banners       = $bannerModel->active();
            $announcements = $announcementModel->active(6);
            $activeUsers   = $threadModel->activeAuthors(8);
            $site          = (new SettingModel())->getSiteConfig();
        } catch (\Throwable $e) {
            
        }

        $totalPages = $total > 0 ? (int) ceil($total / $pageSize) : 1;

        require theme_view('web/home/index.php');
    }
}
