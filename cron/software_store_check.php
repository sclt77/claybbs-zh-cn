#!/usr/bin/env php
<?php



require_once __DIR__ . '/../app/core/bootstrap.php';

use App\Core\Database;
use App\Models\SoftwareModel;
use App\Models\NotificationModel;

$logFile = __DIR__ . '/../storage/logs/software_store_check.log';
@mkdir(dirname($logFile), 0755, true);

function logMsg(string $msg): void
{
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}


function checkDownloadLinks(): void
{
    logMsg('开始检查软件下载链接...');

    $db = Database::connection();
    $stmt = $db->query("SELECT id, name, download_url, uploader_id FROM softwares WHERE status='published' AND download_url IS NOT NULL AND download_url != ''");
    $softwares = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $checked = 0;
    $failed = 0;

    foreach ($softwares as $s) {
        $url = trim((string)$s['download_url']);
        if ($url === '') continue;

        $isValid = false;
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'ClayBBS-LinkChecker/1.0');
            curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $isValid = $httpCode >= 200 && $httpCode < 400;
        } catch (\Throwable $e) {
            $isValid = false;
        }

        $checked++;

        if (!$isValid) {
            $failed++;
            
            $db->prepare("UPDATE softwares SET status='removed', admin_note='下载链接已失效（自动检测）', updated_at=NOW() WHERE id=:id")
                ->execute([':id' => $s['id']]);

            
            (new NotificationModel())->create(
                (int)$s['uploader_id'],
                'software_link_invalid',
                '软件下载链接已失效',
                '您的软件《' . $s['name'] . '》下载链接已失效，已被自动下架。请更新有效链接后重新提交审核。'
            );

            logMsg("[FAIL] ID={$s['id']} {$s['name']} - 链接失效，已下架并通知用户");
        }
    }

    logMsg("检查完成: {$checked} 个软件，{$failed} 个失效已下架");
}


try {
    checkDownloadLinks();
} catch (\Throwable $e) {
    logMsg('错误: ' . $e->getMessage());
}

logMsg('定时任务结束');
