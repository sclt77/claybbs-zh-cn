<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\SoftwareModel;
use App\Models\SoftwareCategoryModel;
use App\Models\SoftwareScreenshotModel;
use App\Models\SoftwareTypeModel;
use App\Models\SoftwareDownloadModel;
use App\Models\SoftwareRatingModel;
use App\Models\SoftwareReviewModel;
use App\Models\UserModel;
use App\Models\SettingModel;
use App\Models\SoftwareVersionModel;
use App\Middleware\Permission;

class SoftwareSubmissionController
{
    private SoftwareModel $softwareModel;
    private SoftwareCategoryModel $categoryModel;
    private SoftwareScreenshotModel $screenshotModel;

    public function __construct()
    {
        $this->softwareModel   = new SoftwareModel();
        $this->categoryModel   = new SoftwareCategoryModel();
        $this->screenshotModel = new SoftwareScreenshotModel();
    }


    private function ensureEnabled(bool $json = false): bool
    {
        try {
            if ((new SettingModel())->get('software_store_enabled', '1') !== '1') {
                http_response_code(404);
                if ($json) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'error' => '软件库系统已关闭']);
                } else {
                    echo '软件库系统已关闭';
                }
                return false;
            }
        } catch (\Throwable $e) {
            
        }
        return true;
    }

    public function index(): void
    {
        if (!$this->ensureEnabled(false)) return;
        if (empty($_SESSION['auth_user']['id'])) {
            header('Location: /index.php?path=login');
            exit;
        }
        $userId = (int)$_SESSION['auth_user']['id'];
        $mySoftwares = $this->softwareModel->all(['status' => '', 'uploader_id' => $userId], 1, 100);
        $myVersionSubmissions = (new SoftwareVersionModel())->byUploader($userId, 100);
        $categories = $this->categoryModel->all();
        
        $isAdmin = Permission::can('software.manage');
        $softwareTypes = $isAdmin ? (new SoftwareTypeModel())->all(true) : (new SoftwareTypeModel())->userSelectable(true);
        require dirname(__DIR__) . '/views/web/software/submission.php';
    }


    private function handleImageUpload(string $field, string $prefix, bool $required = false): ?string
    {
        if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
            if ($required) {
                throw new \RuntimeException('请上传应用Logo');
            }
            return null;
        }

        $file = $_FILES[$field];
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            if ($required) {
                throw new \RuntimeException('请上传应用Logo');
            }
            return null;
        }
        if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) {
            throw new \RuntimeException('Logo 上传失败，请重新选择图片');
        }
        if ((int)($file['size'] ?? 0) > 2 * 1024 * 1024) {
            throw new \RuntimeException('Logo 图片不能超过 2MB');
        }

        $tmp = (string)$file['tmp_name'];
        $info = @getimagesize($tmp);
        $mime = (string)($info['mime'] ?? '');
        $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($map[$mime])) {
            throw new \RuntimeException('Logo 仅支持 JPG、PNG、WEBP、GIF 图片');
        }

        $filename = $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $map[$mime];
        $dir = dirname(__DIR__, 2) . '/uploads/software/logo';
        @mkdir($dir, 0755, true);
        $path = $dir . '/' . $filename;
        if (!move_uploaded_file($tmp, $path)) {
            throw new \RuntimeException('Logo 保存失败');
        }
        return '/uploads/software/logo/' . $filename;
    }


    private function handleScreenshotUploads(int $softwareId, bool $replaceExisting = false): void
    {
        if (empty($_FILES['screenshots']) || !is_array($_FILES['screenshots']) || empty($_FILES['screenshots']['tmp_name'])) {
            return;
        }
        $files = $_FILES['screenshots'];
        $uploaded = [];
        $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        foreach ((array)$files['tmp_name'] as $i => $tmp) {
            if (count($uploaded) >= 6) break;
            $error = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE || !$tmp) continue;
            if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string)$tmp)) {
                throw new \RuntimeException('第 ' . ($i + 1) . ' 张展示图上传失败');
            }
            if ((int)($files['size'][$i] ?? 0) > 4 * 1024 * 1024) {
                throw new \RuntimeException('展示图不能超过 4MB');
            }
            $info = @getimagesize((string)$tmp);
            $mime = (string)($info['mime'] ?? '');
            if (!isset($map[$mime])) {
                throw new \RuntimeException('展示图仅支持 JPG、PNG、WEBP、GIF 图片');
            }
            $filename = 'screenshot_' . $softwareId . '_' . date('YmdHis') . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.' . $map[$mime];
            $dir = dirname(__DIR__, 2) . '/uploads/software/screenshots';
            @mkdir($dir, 0755, true);
            $path = $dir . '/' . $filename;
            if (!move_uploaded_file((string)$tmp, $path)) {
                throw new \RuntimeException('展示图保存失败');
            }
            $uploaded[] = '/uploads/software/screenshots/' . $filename;
        }
        if (!$uploaded) return;
        if ($replaceExisting) {
            $this->screenshotModel->deleteBySoftware($softwareId);
        }
        foreach ($uploaded as $i => $url) {
            $this->screenshotModel->create($softwareId, $url, $i);
        }
    }

    public function create(): void
    {
        if (!$this->ensureEnabled(true)) return;
        header('Content-Type: application/json; charset=utf-8');
        if (empty($_SESSION['auth_user']['id'])) {
            echo json_encode(['ok' => false, 'error' => '请先登录']);
            return;
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower((string)($_POST['slug'] ?? '')));
        $platform = (string)($_POST['platform'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $developer = trim((string)($_POST['developer'] ?? ''));
        $version = trim((string)($_POST['version'] ?? '1.0.0')) ?: '1.0.0';
        $size = trim((string)($_POST['size'] ?? '')) ?: null;
        $downloadUrl = trim((string)($_POST['download_url'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $detail = trim((string)($_POST['detail'] ?? ''));
        $type = trim((string)($_POST['type'] ?? ''));

        if ($name === '' || $slug === '' || $downloadUrl === '') {
            echo json_encode(['ok' => false, 'error' => '请填写完整信息']);
            return;
        }

        try {
            $icon = $this->handleImageUpload('icon', 'logo', true);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            return;
        }

        $userId = (int)$_SESSION['auth_user']['id'];

        $id = $this->softwareModel->create([
            'icon'         => $icon,
            'name'         => $name,
            'slug'         => $slug,
            'platform'     => $platform,
            'category_id'  => $categoryId > 0 ? $categoryId : null,
            'uploader_id'  => $userId,
            'developer'    => $developer ?: null,
            'version'      => $version,
            'size'         => $size,
            'download_url' => $downloadUrl,
            'description'  => $description,
            'detail'       => $detail,
            'type'         => $type ?: '',
            'status'       => 'pending',
        ]);

        try {
            $this->handleScreenshotUploads($id, false);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            return;
        }

        echo json_encode(['ok' => true, 'id' => $id]);
    }

    public function versionForm(): void
    {
        if (!$this->ensureEnabled(false)) return;
        if (empty($_SESSION['auth_user']['id'])) {
            header('Location: /index.php?path=login');
            exit;
        }
        $id = (int)($_GET['id'] ?? 0);
        $software = $this->softwareModel->find($id);
        if (!$software || (int)$software['uploader_id'] !== (int)$_SESSION['auth_user']['id']) {
            http_response_code(403);
            echo '无权提交新版本';
            return;
        }
        if (($software['status'] ?? '') !== 'published') {
            http_response_code(400);
            echo '只有已上架软件可以提交新版本';
            return;
        }
        require dirname(__DIR__) . '/views/web/software/version_submit.php';
    }

    public function versionHistory(): void
    {
        if (!$this->ensureEnabled(false)) return;
        if (empty($_SESSION['auth_user']['id'])) {
            header('Location: /index.php?path=login');
            exit;
        }
        $id = (int)($_GET['id'] ?? 0);
        $software = $this->softwareModel->find($id);
        if (!$software || (int)$software['uploader_id'] !== (int)$_SESSION['auth_user']['id']) {
            http_response_code(403);
            echo '无权查看版本历史';
            return;
        }
        $versions = (new SoftwareVersionModel())->history($id);
        $submissions = (new SoftwareVersionModel())->byUploader((int)$_SESSION['auth_user']['id'], 100);
        $submissions = array_values(array_filter($submissions, fn($v) => (int)($v['software_id'] ?? 0) === $id));
        require dirname(__DIR__) . '/views/web/software/version_history.php';
    }

    public function createVersion(): void
    {
        if (!$this->ensureEnabled(true)) return;
        header('Content-Type: application/json; charset=utf-8');
        if (empty($_SESSION['auth_user']['id'])) {
            echo json_encode(['ok' => false, 'error' => '请先登录']);
            return;
        }
        $userId = (int)$_SESSION['auth_user']['id'];
        $softwareId = (int)($_POST['software_id'] ?? 0);
        $software = $this->softwareModel->find($softwareId);
        if (!$software || (int)$software['uploader_id'] !== $userId) {
            echo json_encode(['ok' => false, 'error' => '无权提交该软件的新版本']);
            return;
        }
        if (($software['status'] ?? '') !== 'published') {
            echo json_encode(['ok' => false, 'error' => '只有已上架软件可以提交新版本']);
            return;
        }

        $version = trim((string)($_POST['version'] ?? ''));
        $size = trim((string)($_POST['size'] ?? '')) ?: null;
        $downloadUrl = trim((string)($_POST['download_url'] ?? ''));
        $changelog = trim((string)($_POST['changelog'] ?? ''));
        if ($version === '' || $downloadUrl === '' || $changelog === '') {
            echo json_encode(['ok' => false, 'error' => '请填写版本号、下载链接和更新日志']);
            return;
        }
        if ($version === (string)($software['version'] ?? '')) {
            echo json_encode(['ok' => false, 'error' => '新版本号不能与当前线上版本相同']);
            return;
        }

        try {
            $icon = $this->handleImageUpload('icon', 'logo', false);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            return;
        }

        $id = (new SoftwareVersionModel())->createSubmission([
            'software_id' => $softwareId,
            'uploader_id' => $userId,
            'version' => $version,
            'size' => $size,
            'download_url' => $downloadUrl,
            'changelog' => $changelog,
            'icon' => $icon,
            'status' => 'pending',
        ]);
        echo json_encode(['ok' => true, 'id' => $id]);
    }

    public function edit(): void
    {
        if (!$this->ensureEnabled(false)) return;
        if (empty($_SESSION['auth_user']['id'])) {
            header('Location: /index.php?path=login');
            exit;
        }
        $id = (int)($_GET['id'] ?? 0);
        $software = $this->softwareModel->find($id);
        if (!$software || (int)$software['uploader_id'] !== (int)$_SESSION['auth_user']['id']) {
            http_response_code(403);
            echo '无权编辑';
            return;
        }
        $categories = $this->categoryModel->all();
        $typeModel = new SoftwareTypeModel();
        
        $isAdmin = Permission::can('software.manage');
        $softwareTypes = $isAdmin ? $typeModel->all(true) : $typeModel->userSelectable(true);
        
        if (!empty($software['type'])) {
            $typeMapAll = $typeModel->map(true);
            $hasCurrentType = false;
            foreach ($softwareTypes as $t) { if (($t['slug'] ?? '') === $software['type']) { $hasCurrentType = true; break; } }
            if (isset($typeMapAll[$software['type']]) && !$hasCurrentType) {
                $softwareTypes[] = $typeMapAll[$software['type']];
            }
        }
        $screenshots = $this->screenshotModel->findBySoftware($id);
        require dirname(__DIR__) . '/views/web/software/edit.php';
    }

    public function update(): void
    {
        if (!$this->ensureEnabled(true)) return;
        header('Content-Type: application/json; charset=utf-8');
        if (empty($_SESSION['auth_user']['id'])) {
            echo json_encode(['ok' => false, 'error' => '请先登录']);
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $software = $this->softwareModel->find($id);
        if (!$software || (int)$software['uploader_id'] !== (int)$_SESSION['auth_user']['id']) {
            echo json_encode(['ok' => false, 'error' => '无权编辑']);
            return;
        }

        $updateData = [
            'name'         => trim((string)($_POST['name'] ?? '')),
            'slug'         => preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower((string)($_POST['slug'] ?? ''))),
            'platform'     => (string)($_POST['platform'] ?? ''),
            'category_id'  => (int)($_POST['category_id'] ?? 0) > 0 ? (int)$_POST['category_id'] : null,
            'developer'    => trim((string)($_POST['developer'] ?? '')) ?: null,
            'version'      => trim((string)($_POST['version'] ?? '1.0.0')) ?: '1.0.0',
            'size'         => trim((string)($_POST['size'] ?? '')) ?: null,
            'download_url' => trim((string)($_POST['download_url'] ?? '')),
            'description'  => trim((string)($_POST['description'] ?? '')),
            'detail'       => trim((string)($_POST['detail'] ?? '')),
            'type'         => trim((string)($_POST['type'] ?? '')),
            'status'       => 'pending',
        ];

        try {
            $newIcon = $this->handleImageUpload('icon', 'logo', false);
            if ($newIcon !== null) {
                $updateData['icon'] = $newIcon;
            }
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            return;
        }

        if (empty($software['icon']) && empty($updateData['icon'])) {
            echo json_encode(['ok' => false, 'error' => '请上传应用Logo']);
            return;
        }

        try {
            $this->handleScreenshotUploads($id, true);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            return;
        }

        $this->softwareModel->update($id, $updateData);

        echo json_encode(['ok' => true]);
    }
}
