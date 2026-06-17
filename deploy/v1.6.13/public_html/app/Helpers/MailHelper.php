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
        $subject = "Your HRMS Login OTP";
        $message = "Your one-time password for HRMS login is: " . $otpCode . "\n\nThis code will expire in 10 minutes.";
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
        // return mail($email, $subject, $message, "From: no-reply@hrms-v2.com");
        
        return true; 
    }
}
