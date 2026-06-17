<?php

/**
 * HRMS Database Connection Diagnostic
 * Verifies connectivity to the active environment.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "--- HRMS DATABASE DIAGNOSTIC ---\n";
echo "Active Environment: " . ACTIVE_ENVIRONMENT . "\n";
echo "Connecting to: " . DB_USER . "@" . DB_HOST . " (DB: " . DB_NAME . ")\n";
echo "-------------------------------\n";

// Connection Parameters
$host = DB_HOST;
$port = DB_PORT;
$user = DB_USER;
$pass = DB_PASS;
$dbName = DB_NAME;
$charset = DB_CHARSET;

echo "--- HRMS DATABASE DIAGNOSTIC ---\n";
echo "Active Environment: " . ACTIVE_ENVIRONMENT . "\n";
echo "Connecting to: $user@$host:$port (DB: $dbName)\n";
echo "-------------------------------\n";

try {
    // USE THE SINGLETON (which handles Path C automatically)
    $conn = Database::getInstance()->getConnection();
    
    // Run a simple query to verify
    $stmt = $conn->query("SELECT VERSION() as version");
    $row = $stmt->fetch();
    
    echo "✅ SUCCESS: Connected successfully!\n";
    echo "Server Version: " . $row['version'] . "\n";
    
} catch (PDOException $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    
    if (ACTIVE_ENVIRONMENT === 'remote') {
        echo "\n[TROUBLESHOOTING WEB BRIDGE (Path C)]\n";
        
        $proxyUrl = defined('PROXY_URL') ? PROXY_URL : '';
        $proxyToken = defined('PROXY_TOKEN') ? PROXY_TOKEN : '';

        echo "1. Checking Proxy URL: $proxyUrl\n";
        
        $options = [
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ];
        
        $context = stream_context_create($options);
        $response = @file_get_contents($proxyUrl, false, $context);
        
        $headers = [];
        if (function_exists('http_get_last_response_headers')) {
            $headers = http_get_last_response_headers();
        } elseif (isset($http_response_header)) {
            $headers = $http_response_header;
        }

        $httpStatus = 0;
        if (!empty($headers) && isset($headers[0])) {
            preg_match('{HTTP\/\S+\s+(\d+)}', $headers[0], $matches);
            $httpStatus = (int)$matches[1];
        }

        if ($response === false) {
            $error = error_get_last();
            echo "❌ ERROR: Local PHP failed to connect to the Proxy.\n";
            echo "   Reason: " . ($error['message'] ?? 'Unknown Error') . "\n";
            echo "   Action: If the reason includes 'SSL', ensure 'extension=openssl' is enabled in your php.ini.\n";
        } elseif ($httpStatus === 401) {
            echo "✅ REACHABLE: Proxy is active but requires correct token (Normal).\n";
        } else {
            echo "✅ REACHABLE: HTTP Code $httpStatus\n";
        }
    }
    
    if (ACTIVE_ENVIRONMENT === 'local') {
        echo "\n[TROUBLESHOOTING LOCAL]\n";
        echo "Ensure your local MySQL server (XAMPP/WAMP/MariaDB) is running.\n";
    }

    exit(1);
}
