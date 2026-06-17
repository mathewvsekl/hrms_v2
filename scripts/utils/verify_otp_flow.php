<?php
require_once __DIR__ . '/index.php'; // Load infrastructure

function testOTP() {
    $email = 'mathew.vsekl@gmail.com'; // Known emergency admin
    
    echo "--- Testing OTP Request ---\n";
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/api/auth/request-otp';
    $payload = json_encode(['email' => $email]);
    
    // We need to capture the output since jsonResponse calls exit()
    // For testing, we might want to modify AuthController to return instead of exit, 
    // but we'll try to just check the DB and log file.
    
    // Manual call to controller method to avoid exit() if possible, or just run as separate process
}

// Let's just use a simpler script that tests the logic directly via PDO
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Core/Controller.php';
require_once __DIR__ . '/app/Helpers/MailHelper.php';
require_once __DIR__ . '/app/Controllers/AuthController.php';

use App\Controllers\AuthController;

class TestAuthController extends AuthController {
    public function jsonResponse($data, $httpStatus = 200, $message = '') {
        echo "Response ($httpStatus): $message\n";
        print_r($data);
        return; // Don't exit
    }
    protected function getJsonPayload() {
        global $testPayload;
        return $testPayload;
    }
}

$testAuth = new TestAuthController();
$email = 'mathew.vsekl@gmail.com';

echo "1. Requesting OTP for $email...\n";
$testPayload = ['email' => $email];
$testAuth->requestOTP();

$logFile = __DIR__ . '/tmp/mail_log.txt';
if (file_exists($logFile)) {
    $log = file_get_contents($logFile);
    echo "2. Mail Log Content:\n$log\n";
    
    // Extract OTP from log
    if (preg_match('/is: (\d{6})/', $log, $matches)) {
        $otp = $matches[1];
        echo "3. Extracted OTP: $otp\n";
        
        echo "4. Verifying OTP...\n";
        $testPayload = ['email' => $email, 'otp' => $otp];
        $testAuth->verifyOTP();
    } else {
        echo "Failed to extract OTP from log.\n";
    }
} else {
    echo "Mail log not found.\n";
}
