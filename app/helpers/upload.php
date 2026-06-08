<?php

declare(strict_types=1);

function upload_image(array $file, string $subdir = 'common', int $maxSize = 5242880): string
{
    if (empty($file) || !isset($file['error'])) {
        return '';
    }

    if ((int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('图片上传失败，请重试');
    }

    $tmp  = $file['tmp_name'] ?? '';
    $size = (int) ($file['size'] ?? 0);

    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('未检测到有效上传文件');
    }

    if ($size <= 0) {
        throw new RuntimeException('上传文件为空');
    }

    if ($size > $maxSize) {
        throw new RuntimeException('图片不能超过 5MB');
    }

    $info = @getimagesize($tmp);
    if ($info === false) {
        throw new RuntimeException('上传文件不是有效图片');
    }
    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $mime = (string)($info['mime'] ?? '');
    if (!isset($mimeToExt[$mime])) {
        throw new RuntimeException('仅支持 jpg、jpeg、png、gif、webp 图片');
    }
    $ext = $mimeToExt[$mime];

    $subdir = trim(str_replace('\\', '/', $subdir), '/');
    if ($subdir === '' || str_contains($subdir, '..') || !preg_match('#^[A-Za-z0-9/_-]+$#', $subdir)) {
        throw new RuntimeException('上传目录无效');
    }

    $rootDir = dirname(__DIR__, 2);
    $dir = $rootDir . '/uploads/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('上传目录创建失败');
    }

    $filename = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $dir . '/' . $filename;

    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('保存图片失败，请检查 uploads 目录权限');
    }

    return '/uploads/' . $subdir . '/' . $filename;
}

function is_local_upload_path(?string $path): bool
{
    if (!$path) {
        return false;
    }
    return strpos($path, '/uploads/') === 0;
}

function delete_local_upload(?string $path): void
{
    if (!is_local_upload_path($path)) {
        return;
    }

    $rootDir = dirname(__DIR__, 2);
    $fullPath = $rootDir . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    $uploadsRoot = realpath($rootDir . DIRECTORY_SEPARATOR . 'uploads');
    $realFile = realpath($fullPath);

    if (!$uploadsRoot || !$realFile) {
        return;
    }

    if (strpos($realFile, $uploadsRoot) !== 0) {
        return;
    }

    if (is_file($realFile)) {
        @unlink($realFile);
    }
}
