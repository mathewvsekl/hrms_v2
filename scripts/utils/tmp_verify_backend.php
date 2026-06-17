<?php
require_once 'config/database.php';
require_once 'app/Controllers/AppraisalController.php';

// Mock the Auth Middleware to return a Super Admin
class MockAuth {
    public static function getUser() {
        return [
            'id' => 1,
            'employee_id' => 1,
            'role' => 'Super Admin'
        ];
    }
}

// Simple mock for MailHelper
namespace App\Helpers;
class MailHelper {
    public static function sendNotification($to, $subject, $body) {
        echo "Mock Email sent to $to: $subject\n";
    }
}

namespace App\Middleware;
class AuthMiddleware {
    public static function getUser() {
        return \MockAuth::getUser();
    }
}

namespace {
    use App\Controllers\AppraisalController;

    $controller = new AppraisalController();

    echo "--- Testing getSystemSettings ---\n";
    ob_start();
    $controller->getSystemSettings();
    $output = ob_get_clean();
    echo $output . "\n\n";

    echo "--- Testing getAppraisal (ID 31) ---\n";
    ob_start();
    $controller->getAppraisal(31);
    $output = ob_get_clean();
    echo $output . "\n\n";

    echo "--- Testing getTemplate (ID 1) ---\n";
    ob_start();
    $controller->getTemplate(1);
    $output = ob_get_clean();
    echo $output . "\n\n";
}
