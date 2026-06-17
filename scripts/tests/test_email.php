<?php

/**
 * Avantgarde HRMS - E-mail Troubleshooting & Diagnostic Tool
 * This script bypasses the helpers to test the vendor/phpmailer integration directly.
 */

declare(strict_types=1);

// 1. Bootstrap Minimal Environment
define('BASE_PATH', __DIR__);
require_once __DIR__ . '/config/config.php';

// 2. Manual PHPMailer Loading
require_once __DIR__ . '/vendor/phpmailer/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 3. User Interface (CLI/Web)
$targetEmail = $_GET['email'] ?? ($argv[1] ?? null);

if (!$targetEmail) {
    echo "Usage (CLI): php test_email.php recipient@example.com\n";
    echo "Usage (Web): test_email.php?email=recipient@example.com\n";
    exit(1);
}

echo "--- Avantgarde HRMS Email Diagnostic ---\n";
echo "Driver: " . MAIL_DRIVER . "\n";
echo "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_ADDRESS . ">\n";

if (MAIL_DRIVER === 'mailgun') {
    echo "Mailgun Domain: " . (defined('MAILGUN_DOMAIN') ? MAILGUN_DOMAIN : 'NOT SET') . "\n";
    echo "Mailgun Endpoint: " . (defined('MAILGUN_ENDPOINT') ? MAILGUN_ENDPOINT : 'NOT SET') . "\n";
    $key = defined('MAILGUN_API_KEY') ? MAILGUN_API_KEY : 'NOT SET';
    echo "Mailgun API Key: " . ($key !== 'YOUR_MAILGUN_API_KEY' && $key !== 'NOT SET' ? 'SET (Hidden)' : 'NOT CONFIGURED') . "\n";
} else {
    echo "SMTP Host: " . MAIL_HOST . "\n";
    echo "SMTP Port: " . MAIL_PORT . "\n";
    echo "SMTP User: " . MAIL_USER . "\n";
    echo "Encryption: " . (MAIL_ENCRYPTION ?? 'none') . "\n";
}

echo "Target Recipient: $targetEmail\n";
echo "----------------------------------------\n";

try {
    if (MAIL_DRIVER === 'mailgun') {
        echo "Attempting to send via Mailgun API helper...\n";
        require_once __DIR__ . '/app/Helpers/MailHelper.php';
        $success = \App\Helpers\MailHelper::send($targetEmail, 'Avantgarde HRMS - Mailgun API Test', 'System Check: If you see this, the Mailgun API integration is working correctly.');
        if ($success) {
            echo "\n[SUCCESS] Message has been sent via Mailgun API to $targetEmail\n";
        } else {
            echo "\n[ERROR] Message could not be sent via Mailgun. Check tmp/php_errors.log for details.\n";
        }
        exit;
    }

    $mail = new PHPMailer(true);
    // Server settings
    if (MAIL_DRIVER === 'smtp') {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        
        // Debugging (Disable in production Helper but useful here)
        $mail->SMTPDebug  = 2; 
    } else {
        $mail->isMail();
    }

    // Recipients
    $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
    $mail->addAddress($targetEmail);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Avantgarde HRMS - SMTP Connectivity Test';
    $mail->Body    = '<h1>System Check</h1><p>If you see this, the <b>PHPMailer implementation</b> for DirectAdmin is working correctly.</p>';
    $mail->AltBody = 'System Check: The PHPMailer implementation for DirectAdmin is working correctly.';

    echo "Attempting to send via PHPMailer...\n";
    $mail->send();
    echo "\n[SUCCESS] Message has been sent to $targetEmail\n";

} catch (Exception $e) {
    echo "\n[ERROR] Message could not be sent.\n";
    if (isset($mail)) echo "Mailer Error: {$mail->ErrorInfo}\n";
    echo "Exception: " . $e->getMessage() . "\n";
}
