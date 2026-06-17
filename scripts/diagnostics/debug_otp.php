<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "--- HRMS V2 Diagnostic Tool ---\n";

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'app/Core/Controller.php';
require_once 'app/Controllers/AuthController.php';

echo "Active Environment: " . ACTIVE_ENVIRONMENT . "\n";
echo "ENVIRONMENT: " . ENVIRONMENT . "\n";

try {
    echo "Attempting Database Connection...\n";
    $db = Database::getInstance()->getConnection();
    echo "Connection Successful (Type: " . get_class($db) . ")\n";

    echo "\nAttempting OTP Request for a test email...\n";
    $email = 'aneesh.mathew@visionscientificafrica.com'; // Known user email from logs

    // We can't easily mock the request object, so we'll just try the DB logic first
    $stmt = $db->prepare("SELECT id, username, is_active FROM users WHERE username = ? OR id = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        echo "Found User: " . $user['id'] . " (" . $user['username'] . ")\n";
    } else {
        echo "User not found.\n";
    }

} catch (Throwable $e) {
    echo "\n!!! CRITICAL ERROR !!!\n";
    echo $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
