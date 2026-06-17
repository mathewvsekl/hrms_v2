<?php
// diag_otp.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/MailHelper.php';

echo "--- OTP FLOW DIAGNOSTIC ---\n";
echo "Active Env: " . ACTIVE_ENVIRONMENT . "\n";
echo "Base Path: " . (defined('BASE_PATH') ? BASE_PATH : 'UNDEFINED') . "\n";

$testEmail = "mathew.vsekl@gmail.com";

try {
    echo "1. Connecting to DB...\n";
    $db = \Database::getInstance()->getConnection();
    
    echo "2. Finding user by email: $testEmail\n";
    $stmt = $db->prepare("SELECT id, is_active FROM users WHERE username = ? OR id IN (SELECT id FROM employees WHERE email = ?) LIMIT 1");
    $stmt->execute([$testEmail, $testEmail]);
    $user = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$user) {
        die("❌ ERROR: User not found in database. Please check the email address.\n");
    }
    echo "✅ User found: ID " . $user['id'] . "\n";

    echo "3. Testing MailHelper (Logging phase)...\n";
    $otp = "123456";
    // Check if MailHelper exists and has the method
    if (class_exists('App\Helpers\MailHelper')) {
        echo "✅ MailHelper class detected.\n";
        \App\Helpers\MailHelper::sendOTP($testEmail, $otp);
        echo "✅ MailHelper::sendOTP executed (Check tmp/mail_log.txt).\n";
    } else {
        echo "❌ ERROR: MailHelper class not found!\n";
    }

    echo "✅ SUCCESS: The OTP logic completed without crashing.\n";

} catch (\Exception $e) {
    echo "❌ CRASH DETECTED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " on line " . $e->getLine() . "\n";
}
