<?php
// Mock Mailgun for direct test
define('MAIL_DRIVER', 'mailgun');
require_once 'config/config.php';
require_once 'app/Helpers/MailHelper.php';

echo "Testing Mailgun Connectivity...\n";
echo "Endpoint: " . MAILGUN_ENDPOINT . "\n";
echo "Domain: " . MAILGUN_DOMAIN . "\n";

$testEmail = 'aneesh.mathew@visionscientificafrica.com';
// Force driver if config.php already defined it (though define() won't work if already defined)
// Let's use a more robust way to test the private method or just the send logic.
$result = \App\Helpers\MailHelper::send($testEmail, "Mailgun Test", "This is a test message from HRMS V2 Diagnostic Tool.");

if ($result) {
    echo "SUCCESS: Mail sent via Mailgun.\n";
} else {
    echo "FAILURE: Mail could not be sent. Check php_errors.log.\n";
}
