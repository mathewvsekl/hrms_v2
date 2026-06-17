<?php

namespace App\Helpers;

/**
 * MailHelper
 * 
 * Securely handles email delivery for OTP and notifications.
 */
class MailHelper
{
    /**
     * Send OTP to a user's email
     * 
     * @param string $email
     * @param string $otpCode
     * @return bool
     */
    public static function sendOTP(string $email, string $otpCode): bool
    {
        $subject = "Your Avantgarde HRMS Login OTP";
        $loginUrl = APP_BASE_URL . "/#/login";
        $message = "Your one-time password for Avantgarde HRMS login is: " . $otpCode . "\n\n" .
                   "Login here: " . $loginUrl . "\n\n" .
                   "This code will expire in 10 minutes.";
        return self::logAndSend($email, $subject, $message);
    }

    /**
     * Send a general notification email
     * 
     * @param string $email
     * @param string $subject
     * @param string $message
     * @return bool
     */
    public static function sendNotification(string $email, string $subject, string $message): bool
    {
        $loginUrl = APP_BASE_URL . "/#/login";
        $message .= "\n\nLogin to access your Avantgarde HRMS dashboard: " . $loginUrl;
        return self::logAndSend($email, $subject, $message);
    }

    /**
     * Internal method to log and simulate sending
     */
    private static function logAndSend(string $email, string $subject, string $message): bool
    {
        // DEVELOPMENT MODE: Log to a file for easy testing
        $logFile = BASE_PATH . '/tmp/mail_log.txt';
        if (!is_dir(BASE_PATH . '/tmp')) {
            mkdir(BASE_PATH . '/tmp', 0777, true);
        }
        $logEntry = "[" . date('Y-m-d H:i:s') . "] TO: $email | SUBJECT: $subject | MESSAGE: $message\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        // Actual mail sending can be enabled here if mail() is configured
        // return mail($email, $subject, $message, "From: no-reply@avantgarde-hrms.com");
        
        return true; 
    }
}
