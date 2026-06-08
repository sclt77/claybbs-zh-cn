<?php

namespace App\Controllers\Web;

use App\Services\LicenseVerifier;

class InstallController
{
    private string $lockFile;
    private string $sqlDir;
    private string $configFile;

    public function __construct()
    {
        $this->lockFile   = dirname(__DIR__, 3) . '/install.lock';
        $this->sqlDir     = dirname(__DIR__, 3) . '/database';
        $this->configFile = dirname(__DIR__, 3) . '/config/database.php';
    }

    public function index(): void
    {
        if (file_exists($this->lockFile)) {
            $this->renderDone();
            return;
        }

        $step  = (int) ($_POST['step'] ?? $_GET['step'] ?? 1);
        $submittedStep = (int) ($_POST['step'] ?? 0);
        $error = '';
        $info  = '';

        if ($submittedStep === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $licenseKey = trim((string) ($_POST['license_key'] ?? ''));
            $licenseDomain = trim((string) ($_POST['license_domain'] ?? ($_SERVER['HTTP_HOST'] ?? '')));
            if ($licenseKey === '' || $licenseDomain === '') {
                $error = '请填写授权码和授权域名';
                $step = 2;
            } else {
                $licenseData = $this->activateLicense($licenseKey, $licenseDomain, $error);
                if ($licenseData !== null) {
                    $pubKey = $this->getPublicKey();
                    if ($pubKey === '' || !(new LicenseVerifier())->verify($licenseData, $pubKey)) {
                        $error = '授权签名校验失败';
                        $step = 2;
                    } else {
                        $cgStatus = $this->clayguardStatus();
                        if (!empty($cgStatus['required']) && empty($cgStatus['loaded'])) {
                            $error = '请先启用运行组件，完成后点击重新检测。';
                            $step = 2;
                        } else {
                            $clayLicense = $this->requestClayGuardLicense($licenseKey, $licenseDomain, $error);
                            if ($clayLicense === null || !$this->writeClayGuardLicense($clayLicense)) {
                                $error = $error !== '' ? $error : '授权文件保存失败，请检查 storage/license 目录权限';
                                $step = 2;
                            } else {
                                $_SESSION['install_license_key'] = $licenseKey;
                                $_SESSION['install_license_domain'] = $licenseDomain;
                                $_SESSION['install_license_data'] = $licenseData;
                                $_SESSION['install_clayguard_license'] = $clayLicense;
                                $step = 3;
                            }
                        }
                    }
                } else {
                    $step = 2;
                }
            }
        }

        if ($submittedStep === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $cfg = $this->postConfig();
            $this->ensureDatabase($cfg, $error);
            $pdo = $error === '' ? $this->tryConnect($cfg, $error) : null;
            if ($pdo) {
                $this->writeConfig($cfg, (string) ($_SESSION['install_license_key'] ?? ''), (array) ($_SESSION['install_license_data'] ?? []));
                
                $existingTables = $this->getExistingTables($pdo);
                if (!empty($existingTables)) {
                    $_SESSION['install_existing_tables'] = $existingTables;
                    $step = 35; 
                } else {
                    $step = 4;
                }
            } else {
                $step = 3;
            }
        }

        
        if ($submittedStep === 35 && $_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $mode = $_POST['db_mode'] ?? '';
            if ($mode === 'fresh') {
                
                $cfg = $this->loadConfig();
                $pdo = $this->tryConnect($cfg, $error);
                if ($pdo) {
                    $this->dropAllTables($pdo);
                    $step = 4;
                } else {
                    $step = 3;
                }
            } elseif ($mode === 'upgrade') {
                
                $step = 4;
            } else {
                $error = '请选择安装模式';
                $step = 35;
            }
            unset($_SESSION['install_existing_tables']);
        }

        if ($submittedStep === 4 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
            csrf_verify();
            $cfg = $this->loadConfig();
            $pdo = $this->tryConnect($cfg, $error);
            if ($pdo) {
                $result = $this->runMigrations($pdo, $error);
                if ($result) {
                    $step = 5;
                }
            } else {
                $step = 4;
            }
        }

        if ($submittedStep === 5 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_username'])) {
            csrf_verify();
            $cfg = $this->loadConfig();
            $pdo = $this->tryConnect($cfg, $error);
            if ($pdo) {
                $done = $this->createAdmin($pdo, $error);
                if ($done) {
                    file_put_contents($this->lockFile, date('Y-m-d H:i:s'));
                    unset($_SESSION['install_license_key'], $_SESSION['install_license_domain'], $_SESSION['install_license_data'], $_SESSION['install_clayguard_license']);
                    $step = 6;
                }
            } else {
                $step = 5;
            }
        }

        $this->render($step, $error, $info);
    }

    private function postConfig(): array
    {
        return [
            'host'     => trim($_POST['db_host'] ?? 'localhost'),
            'port'     => (int) ($_POST['db_port'] ?? 3306),
            'database' => trim($_POST['db_name'] ?? ''),
            'username' => trim($_POST['db_user'] ?? 'root'),
            'password' => $_POST['db_pass'] ?? '',
        ];
    }

    private function getExistingTables(\PDO $pdo): array
    {
        try {
            $stmt = $pdo->query('SHOW TABLES');
            return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function dropAllTables(\PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $stmt = $pdo->query('SHOW TABLES');
        $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }


    private function ensureDatabase(array $cfg, string &$error): void
    {
        if (($cfg['database'] ?? '') === '') {
            $error = '请填写数据库名';
            return;
        }
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $cfg['host'], $cfg['port']);
            $pdo = new \PDO($dsn, $cfg['username'], $cfg['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $db = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $cfg['database']);
            if ($db === '') {
                $error = '数据库名不合法';
                return;
            }
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Throwable $e) {
            $error = '创建数据库失败：' . $e->getMessage();
        }
    }

    private function tryConnect(array $cfg, string &$error): ?\PDO
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $cfg['host'],
                $cfg['port'],
                $cfg['database']
            );
            $pdo = new \PDO($dsn, $cfg['username'], $cfg['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            return $pdo;
        } catch (\Throwable $e) {
            $error = '数据库连接失败：' . $e->getMessage();
            return null;
        }
    }

    private function writeConfig(array $cfg, string $licenseKey = '', array $licenseData = []): void
    {
        $content  = "<?php\n\nreturn [\n";
        $content .= "    'driver'    => 'mysql',\n";
        $content .= "    'host'      => '" . addslashes($cfg['host']) . "',\n";
        $content .= "    'port'      => " . (int) $cfg['port'] . ",\n";
        $content .= "    'database'  => '" . addslashes($cfg['database']) . "',\n";
        $content .= "    'username'  => '" . addslashes($cfg['username']) . "',\n";
        $content .= "    'password'  => '" . addslashes($cfg['password']) . "',\n";
        $content .= "    'charset'   => 'utf8mb4',\n";
        $content .= "    'collation' => 'utf8mb4_unicode_ci',\n";
        $content .= "];\n";
        file_put_contents($this->configFile, $content);

        if ($licenseKey !== '') {
            $this->writeLicenseConfig($licenseKey, $licenseData);
        }
    }

    private function getPublicKey(): string
    {
        $path = dirname(__DIR__, 3) . '/config/update-center.php';
        $config = file_exists($path) ? (include $path) : [];
        $key = is_array($config) ? trim((string) ($config['public_key'] ?? '')) : '';
        if ($key !== '') {
            return $key;
        }

        $base = rtrim((string) ($config['url'] ?? 'https://www.claybbs.com'), '/');
        $fetched = $this->fetchOfficialPublicKey($base);
        if ($fetched !== '') {
            if (!is_array($config)) {
                $config = [];
            }
            $config['url'] = $base;
            $config['public_key'] = $fetched;
            $this->saveUpdateCenterConfig($config);
            return $fetched;
        }

        return '';
    }

    private function fetchOfficialPublicKey(string $base): string
    {
        $url = rtrim($base ?: 'https://www.claybbs.com', '/') . '/api.php?path=public-key';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            curl_close($ch);
            return '';
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status !== 200) {
            return '';
        }
        $data = json_decode((string) $res, true);
        $key = is_array($data) ? trim((string) ($data['public_key'] ?? '')) : '';
        if ($key === '' || !openssl_pkey_get_public($key)) {
            return '';
        }
        return $key;
    }

    private function saveUpdateCenterConfig(array $config): void
    {
        $path = dirname(__DIR__, 3) . '/config/update-center.php';
        $defaults = [
            'url' => 'https://www.claybbs.com',
            'site_id' => '',
            'token' => '',
            'project_id' => 0,
            'branch' => 'main',
            'current_version' => '0.0.0',
            'public_key' => '',
            'domain' => '',
            'owner' => '',
            'license_key' => '',
            'license_data' => '',
        ];
        $config = array_merge($defaults, $config);
        file_put_contents($path, "<?php

return " . var_export($config, true) . ";
");
    }

    private function writeLicenseConfig(string $licenseKey, array $licenseData): void
    {
        $path = dirname(__DIR__, 3) . '/config/update-center.php';
        $config = file_exists($path) ? (include $path) : [];
        if (!is_array($config)) {
            $config = [];
        }
        $config['license_key'] = $licenseKey;
        $config['license_data'] = json_encode($licenseData, JSON_UNESCAPED_UNICODE);
        if (!empty($licenseData['payload']['domain'])) {
            $config['domain'] = (string) $licenseData['payload']['domain'];
        }
        if (!empty($licenseData['payload']['owner'])) {
            $config['owner'] = (string) $licenseData['payload']['owner'];
        }
        if (!empty($licenseData['payload']['site_id'])) {
            $config['site_id'] = (string) $licenseData['payload']['site_id'];
        }
        $payloadToken = trim((string)($licenseData['payload']['token'] ?? ''));
        if ($payloadToken !== '') {
            $config['token'] = $payloadToken;
        }
        $this->saveUpdateCenterConfig($config);

        $licensePath = dirname(__DIR__, 3) . '/storage/license.dat';
        @file_put_contents($licensePath, json_encode($licenseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }


    private function clayguardStatus(): array
    {
        $root = dirname(__DIR__, 3);
        $hasDist = is_file($root . '/CLAYGUARD_DIST.json') || is_dir($root . '/loaders');
        $loaded = extension_loaded('clayguard') && function_exists('clayguard_require');
        $version = $loaded && function_exists('clayguard_version') ? (string)clayguard_version() : '';
        
        // 如果扩展已加载，直接返回已启用状态
        if ($loaded) {
            return [
                'required' => true,
                'loaded' => true,
                'version' => $version,
                'install_command' => 'bash tools/install-clayguard-loader.sh',
                'check_command' => 'php tools/clayguard-check.php',
            ];
        }
        
        // 扩展未加载时，判断是否需要 ClayGuard
        // 条件1：分发标记文件存在（CLAYGUARD_DIST.json 或 loaders/ 目录）
        // 条件2：运行时目录中存在加密文件（被 ClayGuard 加密过的 PHP 文件）
        $needsClayguard = $hasDist || $this->hasClayGuardFiles($root);
        
        return [
            'required' => $needsClayguard,
            'loaded' => false,
            'version' => '',
            'install_command' => 'bash tools/install-clayguard-loader.sh',
            'check_command' => 'php tools/clayguard-check.php',
        ];
    }
    
    /**
     * 检测项目是否包含需要 ClayGuard 解密的文件
     * 加密文件特征：PHP 文件中包含 "clayguard_require(" 或 ".clay" 引用
     */
    private function hasClayGuardFiles(string $root): bool
    {
        $dirs = [$root . '/app/controllers/admin', $root . '/app/services', $root . '/app/middleware', $root . '/app/models'];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) continue;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') continue;
                $path = $file->getPathname();
                // 快速检测：文件中包含 clayguard_require 调用
                $content = @file_get_contents($path);
                if ($content !== false && str_contains($content, 'clayguard_require(')) {
                    return true;
                }
            }
        }
        return false;
    }

    private function writeClayGuardLicense(array $license): bool
    {
        if (empty($license) || empty($license['signature'])) {
            return false;
        }
        $dir = dirname(__DIR__, 3) . '/storage/license';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $path = $dir . '/clayguard.lic';
        return file_put_contents($path, json_encode($license, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) !== false;
    }

    private function requestClayGuardLicense(string $licenseKey, string $domain, string &$error): ?array
    {
        $path = dirname(__DIR__, 3) . '/config/update-center.php';
        $config = file_exists($path) ? (include $path) : [];
        $base = rtrim((string)($config['url'] ?? 'https://www.claybbs.com'), '/');
        $url = $base . '/api.php?path=clayguard/issue';
        $payload = [
            'license_key' => $licenseKey,
            'domain' => $domain,
            'install_nonce' => bin2hex(random_bytes(12)),
            'php_version' => PHP_VERSION,
            'host' => $_SERVER['HTTP_HOST'] ?? '',
        ];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            $error = '授权文件获取失败：' . curl_error($ch);
            curl_close($ch);
            return null;
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status !== 200) {
            $error = '授权文件获取失败，HTTP ' . $status;
            return null;
        }
        $data = json_decode((string)$res, true);
        if (!is_array($data) || empty($data['ok']) || empty($data['license']) || !is_array($data['license'])) {
            $error = '授权文件获取失败：' . (is_array($data) ? (string)($data['error'] ?? 'invalid_response') : 'invalid_response');
            return null;
        }
        return $data['license'];
    }

    private function activateLicense(string $licenseKey, string $domain, string &$error): ?array
    {
        $path = dirname(__DIR__, 3) . '/config/update-center.php';
        $config = file_exists($path) ? (include $path) : [];
        $base = rtrim((string) ($config['url'] ?? 'https://www.claybbs.com'), '/');
        $url = $base . '/api.php?path=license/activate';

        $payload = [
            'license_key' => $licenseKey,
            'domain' => $domain,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            $error = '授权激活失败：' . curl_error($ch);
            curl_close($ch);
            return null;
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            $error = '授权激活失败，HTTP ' . $status;
            return null;
        }

        $data = json_decode($res, true);
        if (!is_array($data) || empty($data['ok'])) {
            $error = '授权激活失败：' . (is_array($data) ? (string) ($data['error'] ?? 'unknown') : 'invalid_response');
            return null;
        }

        return $data;
    }

    private function loadConfig(): array
    {
        $c = include $this->configFile;
        return [
            'host'     => $c['host']     ?? 'localhost',
            'port'     => $c['port']     ?? 3306,
            'database' => $c['database'] ?? '',
            'username' => $c['username'] ?? 'root',
            'password' => $c['password'] ?? '',
        ];
    }

    private function runMigrations(\PDO $pdo, string &$error): bool
    {
        $file = $this->sqlDir . '/install.sql';
        if (!file_exists($file)) {
            $error = 'SQL 文件不存在，请检查 database/install.sql';
            return false;
        }
        return $this->executeSqlFile($pdo, $file, $error);
    }

    private function executeSqlFile(\PDO $pdo, string $file, string &$error): bool
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            $error = 'SQL 文件读取失败：' . basename($file);
            return false;
        }
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $parts = explode(';', $sql);
        foreach ($parts as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') {
                continue;
            }
            if (preg_match('/^(USE|CREATE DATABASE)/i', $stmt)) {
                continue;
            }
            try {
                $pdo->exec($stmt);
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                if (strpos($msg, 'Duplicate') !== false || strpos($msg, 'already exists') !== false) {
                    continue;
                }
                $error = '建表失败（' . basename($file) . '）：' . $msg;
                return false;
            }
        }
        return true;
    }

    private function generatePublicId(\PDO $pdo): string
    {
        $prefix = 'CY';
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'friend_id_prefix' LIMIT 1");
            $stmt->execute();
            $prefix = (string)($stmt->fetchColumn() ?: 'CY');
        } catch (\Throwable $e) {
            $prefix = 'CY';
        }
        $prefix = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($prefix)) ?: 'CY';
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE public_id = :public_id");
        do {
            $id = $prefix . str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            $stmt->execute([':public_id' => $id]);
        } while ((int)$stmt->fetchColumn() > 0);
        return $id;
    }

    private function createAdmin(\PDO $pdo, string &$error): bool
    {
        $username = trim($_POST['admin_username'] ?? '');
        $nickname = trim($_POST['admin_nickname'] ?? '');
        $email    = trim($_POST['admin_email'] ?? '');
        $password = $_POST['admin_password'] ?? '';

        if ($username === '' || $email === '' || strlen($password) < 6) {
            $error = '请填写完整的管理员信息，密码至少 6 位';
            return false;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $publicId = $this->generatePublicId($pdo);
            $stmt = $pdo->prepare(
                "INSERT INTO users (public_id, username, nickname, email, password, role, status, email_verified, email_verify_token, email_verify_expires_at, created_at, updated_at)
                 VALUES (:public_id, :u, :n, :e, :p, 'superadmin', 'active', 1, NULL, NULL, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    public_id = COALESCE(NULLIF(public_id, ''), VALUES(public_id)),
                    nickname = VALUES(nickname),
                    email = VALUES(email),
                    role = 'superadmin',
                    password = :p2,
                    status = 'active',
                    email_verified = 1,
                    email_verify_token = NULL,
                    email_verify_expires_at = NULL,
                    updated_at = NOW()"
            );
            $stmt->execute([
                ':public_id' => $publicId,
                ':u'  => $username,
                ':n'  => $nickname ?: $username,
                ':e'  => $email,
                ':p'  => $hash,
                ':p2' => $hash,
            ]);

            $find = $pdo->prepare("SELECT * FROM users WHERE username = :u OR email = :e LIMIT 1");
            $find->execute([
                ':u' => $username,
                ':e' => $email,
            ]);
            $user = $find->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                $error = '管理员创建成功，但读取用户信息失败';
                return false;
            }

            try {
                $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE slug = 'superadmin' LIMIT 1");
                $roleStmt->execute();
                $roleId = (int) $roleStmt->fetchColumn();

                if ($roleId > 0) {
                    $bindStmt = $pdo->prepare(
                        "INSERT IGNORE INTO user_roles (user_id, role_id, scope, scope_id, granted_by, expires_at)
                         VALUES (:uid, :rid, 'global', 0, :uid, NULL)"
                    );
                    $bindStmt->execute([
                        ':uid' => (int) $user['id'],
                        ':rid' => $roleId,
                    ]);
                }
            } catch (\Throwable $e) {
                
            }

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $_SESSION['auth_user'] = [
                'id'       => $user['id'],
                'username' => $user['username'] ?? '',
                'public_id'=> $user['public_id'] ?? '',
                'nickname' => $user['nickname'] ?? '',
                'email'    => $user['email'] ?? '',
                'role'     => 'superadmin',
                'email_verified' => 1,
            ];

            return true;
        } catch (\Throwable $e) {
            $error = '创建管理员失败：' . $e->getMessage();
            return false;
        }
    }

    private function render(int $step, string $error, string $info): void
    {
        $titles = [
            1 => '环境检测',
            2 => '验证授权',
            3 => '数据库配置',
            35 => '安装模式',
            4 => '执行迁移',
            5 => '创建管理员',
            6 => '安装完成',
        ];
        $title = $titles[$step] ?? '安装';
        $cfg   = [];
        $existingTables = $_SESSION['install_existing_tables'] ?? [];
        $clayguard = $this->clayguardStatus();
        if ($step >= 4) {
            try {
                $cfg = $this->loadConfig();
            } catch (\Throwable $e) {
                $cfg = [];
            }
        }
        require theme_view('web/install/index.php');
    }

    private function renderDone(): void
    {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="zh"><head><meta charset="UTF-8"><title>已安装</title>'
            . '<style>body{display:flex;align-items:center;justify-content:center;height:100vh;margin:0;font-family:sans-serif;background:#f5f5f5;}'
            . '.b{text-align:center;background:#fff;padding:48px 64px;border-radius:12px;box-shadow:0 2px 16px rgba(0,0,0,.08);}a{color:#1a73e8;}</style></head>'
            . '<body><div class="b"><h2>系统已安装</h2><p>如需重新安装，请删除项目根目录的 <code>install.lock</code> 文件。</p>'
            . '<p><a href="/index.php">返回首页</a></p></div></body></html>';
    }
}
