<?php
define('BASE_PATH', 'c:\Users\AneeshMathew\HRMS V2\backend');
require 'c:\Users\AneeshMathew\HRMS V2\backend\app\Core\Autoloader.php';
\App\Core\Autoloader::register();
require 'c:\Users\AneeshMathew\HRMS V2\backend\config\database.php';

$authService = new \App\Services\AuthService();
$email = 'aneesh.mathew@visionscientificafrica.com';
$user = $authService->findUserByIdentifier($email);

if (!$user) {
    echo "User not found\n";
    exit;
}
echo "User found: " . print_r($user, true) . "\n";

try {
    $otp = "123456";
    $authService->createOTP((int)$user['id'], $otp);
    echo "OTP Created successfully\n";
    
    $userName = 'Aneesh';
    $referenceCode = 'ABCD';
    $res = \App\Helpers\MailHelper::sendOTP($email, $otp, $userName, $referenceCode);
    echo "MailHelper::sendOTP result: " . var_export($res, true) . "\n";
} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
