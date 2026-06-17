<?php
/**
 * Router script for PHP built-in development server.
 * Routes all /api/* requests to index.php, serves static files directly.
 */
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve static files directly if they exist
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false; // Let the built-in server handle static files
}

// Route everything else (especially /api/*) to index.php
require __DIR__ . '/index.php';
