<?php

namespace App\Middleware;

/**
 * SecurityMiddleware
 * Handles application-level security enforcement including HTTPS redirects
 * and strict path-based access control for system configurations.
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
            if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
                header("Content-Type: application/json; charset=UTF-8");
                http_response_code(403);
                echo json_encode([
                    "status" => "error", 
                    "message" => "SSL Required: This endpoint must be accessed via HTTPS."
                ]);
                exit();
            } else {
                $redirectUrl = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                header("Location: " . $redirectUrl, true, 301);
                exit();
            }
        }
    }

    /**
     * Hard-block access to sensitive system paths regardless of RBAC level
     * if the request originates from a non-administrative context.
     */
    public static function interceptRestrictedPaths()
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // 1. HARD DENY: Direct access to system files on disk (redundant with .htaccess but good for defense-in-depth)
        $forbiddenPatterns = [
            '/\.env$/',
            '/config\.php$/',
            '/dump_db\.php$/',
            '/fix_db\.php$/',
            '/inspect_db\.php$/',
            '/\.sql$/',
            '/\.sh$/',
            '/\.bat$/'
        ];

        foreach ($forbiddenPatterns as $pattern) {
            if (preg_match($pattern, $uri)) {
                self::denyAccess("System Restriction: Direct access to configuration files is strictly forbidden via web interface.");
            }
        }

        // 2. CONTEXT AWARE: Block sensitive API endpoints if in a restricted session or view mode
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Detect current view mode from request header (sent by frontend api.js)
        $viewMode = $_SERVER['HTTP_X_VIEW_MODE'] ?? 'admin';
        $isRestrictedContext = ($viewMode === 'employee');

        $restrictedEndpoints = [
            '/api/organization/settings',
            '/api/rbac',
            '/api/leave/recalculate',
            '/api/leave/types',
            '/api/leave/policies',
            '/api/holidays',
            '/api/appraisals/settings'
        ];

        foreach ($restrictedEndpoints as $endpoint) {
            if (strpos($uri, $endpoint) === 0) {
                // Noah Audit Fix: Allow GET requests for informational endpoints even in restricted context
                // These are needed for standard employee features (e.g. leave application categories)
                $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
                $isInformational = ($method === 'GET' && in_array($endpoint, ['/api/leave/types', '/api/holidays', '/api/leave/policies']));
                
                if ($isInformational) continue;

                $role = $_SESSION['user_role'] ?? 'GUEST';
                $isImpersonating = $_SESSION['is_impersonating'] ?? false;
                $normRole = strtoupper($role);
                $isAdministrativeRole = in_array($normRole, ['SUPER_ADMIN', 'SUPERADMIN', 'ADMIN']);
                
                if (!$isAdministrativeRole || $isRestrictedContext || $isImpersonating) {
                    $reason = $isRestrictedContext ? "Access restricted while in Employee View mode." : "Insufficient permissions for system configurations.";
                    self::denyAccess("Security Violation: $reason");
                }
            }
        }
    }

    private static function denyAccess($message)
    {
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "code" => 403,
            "message" => $message
        ]);
        exit();
    }
}
