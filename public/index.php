<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/core/bootstrap.php';

use App\Core\Router;

$router = new Router();
require_once dirname(__DIR__) . '/routes/web.php';
require_once dirname(__DIR__) . '/routes/admin.php';

$router->dispatch($_SERVER['REQUEST_URI'] ?? '/', $_SERVER['REQUEST_METHOD'] ?? 'GET');
