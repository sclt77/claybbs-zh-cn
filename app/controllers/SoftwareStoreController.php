<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\SoftwareModel;
use App\Models\SoftwareCategoryModel;
use App\Models\SoftwareScreenshotModel;
use App\Models\SoftwareDownloadModel;
use App\Models\SoftwareRatingModel;
use App\Models\SoftwareReviewModel;
use App\Models\SoftwareTypeModel;
use App\Models\UserModel;
use App\Models\SettingModel;
use App\Models\SoftwareVersionModel;

class SoftwareStoreController
{
    private SoftwareModel $softwareModel;
    private SoftwareCategoryModel $categoryModel;
    private SoftwareScreenshotModel $screenshotModel;
    private SoftwareDownloadModel $downloadModel;
    private SoftwareRatingModel $ratingModel;
    private SoftwareReviewModel $reviewModel;

    public function __construct()
    {
        $this->softwareModel    = new SoftwareModel();
        $this->categoryModel    = new SoftwareCategoryModel();
        $this->screenshotModel  = new SoftwareScreenshotModel();
        $this->downloadModel   = new SoftwareDownloadModel();
        $this->ratingModel      = new SoftwareRatingModel();
        $this->reviewModel      = new SoftwareReviewModel();
    }


    private function ensureEnabled(): bool
    {
        try {
            if ((new SettingModel())->get('software_store_enabled', '1') !== '1') {
                http_response_code(404);
                echo '软件库系统已关闭';
                return false;
            }
        } catch (\Throwable $e) {
            
        }
        return true;
    }

    public function index(): void
    {
        $this->renderIndex();
    }

    

    public function renderIndex(): void
    {
        if (!$this->ensureEnabled()) return;
        $platform = trim((string)($_GET['platform'] ?? ''));
        if (!in_array($platform, ['android','ios','windows','macos'], true)) {
            $platform = '';
        }

        $categoryId = (int)($_GET['category'] ?? 0);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $q = trim((string)($_GET['q'] ?? ''));
        $order = (string)($_GET['order'] ?? 'created');
        if (!in_array($order, ['created','score','comments','random'], true)) $order = 'created';

        $filters = ['status' => 'published'];
        if ($platform && in_array($platform, ['android','ios','windows','macos'], true)) {
            $filters['platform'] = $platform;
        }
        if ($categoryId > 0) $filters['category_id'] = $categoryId;
        if ($q) $filters['q'] = $q;

        $orderBy = match($order) {
            'score' => 's.rating_avg DESC, s.created_at DESC',
            'comments' => 's.download_count DESC, s.created_at DESC',
            'random' => 'RAND()',
            default => 's.is_recommended DESC, s.recommended_at DESC, s.created_at DESC',
        };

        $softwares = $this->softwareModel->all($filters, $page, 20, $orderBy);
        $total = $this->softwareModel->count($filters);
        $categories = $this->categoryModel->all();
        $softwareTypes = (new SoftwareTypeModel())->all(true);
        $typeMap = (new SoftwareTypeModel())->map(true);

        
        $featured = $this->softwareModel->all(['status' => 'published', 'is_recommended' => 1], 1, 5, 's.recommended_at DESC, s.created_at DESC');

        $isStoreHome = false;
        try {
            $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            $isRootRequest = !empty($GLOBALS['__clay_store_home']) || ($requestPath === '/' && empty($_GET['path']));
            $isStoreHome = $isRootRequest && (new SettingModel())->get('site_mode', 'forum') === 'store';
        } catch (\Throwable $e) {
            $isStoreHome = false;
        }
        $heroSoftware = $featured[0] ?? ($softwares[0] ?? null);
        $topDownloaded = $this->softwareModel->all(['status' => 'published'], 1, 6, 's.download_count DESC, s.created_at DESC');
        $topRated = $this->softwareModel->all(['status' => 'published'], 1, 6, 's.rating_avg DESC, s.rating_count DESC, s.created_at DESC');
        $storeStats = [
            'apps' => $this->softwareModel->count(['status' => 'published']),
            'downloads' => $this->softwareModel->totalDownloads(),
            'featured' => $this->softwareModel->countRecommended(),
            'categories' => count($categories),
        ];

        require dirname(__DIR__) . '/views/web/software/index.php';
    }
    public function show(): void
    {
        if (!$this->ensureEnabled()) return;
        $slug = (string)($_GET['slug'] ?? '');
        $software = $this->softwareModel->findBySlug($slug);
        if (!$software || $software['status'] !== 'published') {
            http_response_code(404);
            echo '软件不存在';
            return;
        }

        $screenshots = $this->screenshotModel->findBySoftware((int)$software['id']);
        $reviews = $this->reviewModel->findBySoftware((int)$software['id'], 1, 20);
        foreach ($reviews as &$r) {
            $r['replies'] = $this->reviewModel->findReplies((int)$r['id']);
        }
        unset($r);

        $uploader = null;
        if ($software['uploader_id']) {
            $uploader = (new UserModel())->find((int)$software['uploader_id']);
        }

        $typeMap = (new SoftwareTypeModel())->map(true);
        $versions = (new SoftwareVersionModel())->history((int)$software['id']);

        $hasDownloaded = false;
        $userRating = null;
        if (!empty($_SESSION['auth_user']['id'])) {
            $hasDownloaded = $this->downloadModel->hasDownloaded((int)$software['id'], $_SERVER['REMOTE_ADDR'] ?? '');
            $userRating = $this->ratingModel->find((int)$software['id'], (int)$_SESSION['auth_user']['id']);
        }

        require dirname(__DIR__) . '/views/web/software/show.php';
    }

    public function download(): void
    {
        if (!$this->ensureEnabled()) return;
        $id = (int)($_GET['id'] ?? 0);
        $software = $this->softwareModel->find($id);
        if (!$software || $software['status'] !== 'published') {
            http_response_code(404);
            echo '软件不存在';
            return;
        }

        $userId = !empty($_SESSION['auth_user']['id']) ? (int)$_SESSION['auth_user']['id'] : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if ($this->downloadModel->record($id, $userId, $ip, $ua)) {
            $this->softwareModel->incrementDownload($id);
        }

        header('Location: ' . $software['download_url']);
        exit;
    }

    public function rate(): void
    {
        if (!$this->ensureEnabled()) return;
        header('Content-Type: application/json');
        if (empty($_SESSION['auth_user']['id'])) {
            echo json_encode(['ok' => false, 'error' => '请先登录']);
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            echo json_encode(['ok' => false, 'error' => '评分无效']);
            return;
        }

        if (!$this->downloadModel->hasDownloaded($id, $_SERVER['REMOTE_ADDR'] ?? '')) {
            echo json_encode(['ok' => false, 'error' => '下载后才能评分']);
            return;
        }

        $this->ratingModel->rate($id, (int)$_SESSION['auth_user']['id'], $rating);
        $this->softwareModel->recalcRating($id);
        echo json_encode(['ok' => true]);
    }

    public function review(): void
    {
        if (!$this->ensureEnabled()) return;
        header('Content-Type: application/json');
        if (empty($_SESSION['auth_user']['id'])) {
            echo json_encode(['ok' => false, 'error' => '请先登录']);
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $content = trim((string)($_POST['content'] ?? ''));
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

        if ($content === '') {
            echo json_encode(['ok' => false, 'error' => '评论内容不能为空']);
            return;
        }

        $this->reviewModel->create($id, (int)$_SESSION['auth_user']['id'], $content, $parentId);
        echo json_encode(['ok' => true]);
    }
}
