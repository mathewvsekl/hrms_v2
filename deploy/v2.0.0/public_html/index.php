<?php

/**
 * Avantgarde HRMS V2.0.0
 * Production Entry Point - DirectAdmin Optimized
 */

declare(strict_types=1);
date_default_timezone_set('UTC');

// Polyfill for getallheaders()
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

// Define explicit base paths
define('BASE_PATH', __DIR__);
define('STORAGE_PATH', dirname(BASE_PATH) . '/storage');

// Load Core Infrastructure
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Core/Controller.php';

// Autoload remaining components if they exist
foreach (glob(BASE_PATH . "/app/Models/*.php") as $filename) require_once $filename;
foreach (glob(BASE_PATH . "/app/Helpers/*.php") as $filename) require_once $filename;
foreach (glob(BASE_PATH . "/app/Middleware/*.php") as $filename) require_once $filename;
foreach (glob(BASE_PATH . "/app/Controllers/*.php") as $filename) require_once $filename;

// Load API Routes
require_once BASE_PATH . '/routes/api.php';

// Intercept Request Details
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

// CORS Handling
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($requestMethod == 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Handle Frontend vs API
 */
if (strpos($requestUri, '/api/') === 0) {
    // API Request
    dispatchRoute($requestMethod, $requestUri);
} else {
    // Serve Frontend
    if (file_exists(BASE_PATH . '/index.html')) {
        include BASE_PATH . '/index.html';
    } else {
        http_response_code(404);
        echo "Frontend assets not found. Please check public_html structure.";
    }
}
