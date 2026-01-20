<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Core/Autoload.php';

use Core\Router;
use Core\CORS;

CORS::handle();

$router = new Router();

// Health route
$router->get('/api/health', 'HealthController@index');

// Load app routes
require __DIR__ . '/api.php';

// --- FIX: normalize URI so "/health/public" doesn’t break routes ---
$basePath = '/health/public'; // adjust if your folder name is different
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace($basePath, '', $uri);

$router->dispatch($_SERVER['REQUEST_METHOD'], $uri);
