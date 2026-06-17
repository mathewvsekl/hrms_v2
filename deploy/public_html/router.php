<?php
// PHP Built-in Server Router script
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

if (is_file($file)) {
    return false; // serve the requested resource as-is.
}

// Otherwise, route to index.php
require_once 'index.php';
