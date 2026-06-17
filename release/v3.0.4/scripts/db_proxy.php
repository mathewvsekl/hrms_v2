<?php
/**
 * HRMS V2 Database Proxy (The Web Bridge)
 * Upload this file to your live server's public_html/ folder.
 * Use it to securely execute queries from your local development environment.
 */

// --- CONFIGURATION ---
// IMPORTANT: This token must match the one in your local config/environments.php
define('PROXY_AUTH_TOKEN', 'HRMS_LOCAL_DEV_SECURE_TOKEN_55');

// Live Credentials (on the server, it's usually localhost)
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'Admin_anedins_hrms_agi');
define('DB_USER', 'Admin_admin_anedins_hrms_agi');
define('DB_PASS', 'dxzWW?EAYaC9gE|o');
define('DB_CHARSET', 'utf8mb4');

// --- PROXY LOGIC ---

header('Content-Type: application/json');

// 1. Verify Authorization
$headers = getallheaders();
$providedToken = isset($headers['X-Hrms-Proxy-Token']) ? $headers['X-Hrms-Proxy-Token'] : '';

// Handle case-insensitivity in headers (important for some server configs)
if (empty($providedToken)) {
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'x-hrms-proxy-token') {
            $providedToken = $value;
            break;
        }
    }
}

if ($providedToken !== PROXY_AUTH_TOKEN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: Invalid Proxy Token']);
    exit;
}

// 2. Parse Incoming Request
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['sql'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request: SQL and parameters required']);
    exit;
}

$sql = $input['sql'];
$params = isset($input['params']) ? $input['params'] : [];

// 3. Execute Query
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $results = [];
    $rowCount = $stmt->rowCount();
    $lastInsertId = null;
    
    // If it's a SELECT, SHOW, DESCRIBE, etc.
    if (preg_match('/^\s*(SELECT|SHOW|DESC|DESCRIBE|EXPLAIN)\s/i', $sql)) {
        $results = $stmt->fetchAll();
    } elseif (stripos($sql, 'INSERT') === 0) {
        $lastInsertId = $pdo->lastInsertId();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $results,
        'rowCount' => $rowCount,
        'lastInsertId' => $lastInsertId
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
