<?php

namespace App\Middleware;

/**
 * SecurityMiddleware
 * Handles application-level security enforcement including HTTPS redirects.
 */
class SecurityMiddleware
{
    /**
     * Enforce HTTPS for all API requests in non-local environments.
     */
    public static function enforceSSL()
    {
        // Skip if on localhost
        $isLocal = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || 
                   (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false);
        
        if ($isLocal) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                   ($_SERVER['SERVER_PORT'] == 443) ||
                   (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        if (!$isHttps) {
            // If it's an API call, return 403. Otherwise, redirect.
            if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
                header("Content-Type: application/json; charset=UTF-8");
                http_response_code(403);
                echo json_encode([
                    "status" => "error", 
                    "message" => "SSL Required: This endpoint must be accessed via HTTPS for mobile readiness compliance."
                ]);
                exit();
            } else {
                $redirectUrl = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                header("Location: " . $redirectUrl, true, 301);
                exit();
            }
        }
    }
}
