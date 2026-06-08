<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $root = dirname(__DIR__, 2);
        $configFile = $root . '/config/database.php';
        if (!is_file($configFile)) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
            $isInstallRequest = in_array($path, ['/install', '/install.php'], true)
                || $script === 'install.php'
                || in_array(($_GET['path'] ?? ''), ['install', 'install.php'], true);
            if (!$isInstallRequest && !headers_sent()) {
                header('Location: /install.php');
                exit;
            }
            throw new \RuntimeException('Database config file is missing. Please run installer first.');
        }

        $config = require $configFile;

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            self::$connection = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            $root = dirname(__DIR__, 2);
            $lock = $root . '/install.lock';
            $isInstallPath = in_array($path, ['/install', '/install.php'], true)
                || in_array(($_GET['path'] ?? ''), ['install', 'install.php'], true);
            if (!$isInstallPath && !file_exists($lock)) {
                header('Location: /install.php');
                exit;
            }
            http_response_code(500);
            exit('Database connection failed: ' . $e->getMessage());
        }

        return self::$connection;
    }
}
