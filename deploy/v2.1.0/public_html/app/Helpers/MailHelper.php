<?php

namespace App\Helpers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Require PHPMailer classes manually as no autoloader is used
if (file_exists(BASE_PATH . '/vendor/phpmailer/Exception.php')) {
    require_once BASE_PATH . '/vendor/phpmailer/Exception.php';
    require_once BASE_PATH . '/vendor/phpmailer/PHPMailer.php';
    require_once BASE_PATH . '/vendor/phpmailer/SMTP.php';
}

/**
 * MailHelper
 * 
 * Securely handles email delivery for OTP and notifications using PHPMailer.
 */
class MailHelper
{
    /**
     * Send OTP to a user's email
     */
    public static function sendOTP(string $email, string $otpCode): bool
    {
        $subject = "Your Avantgarde HRMS Login OTP";
        $loginUrl = APP_BASE_URL . "/#/login";
        $message = "Your one-time password for Avantgarde HRMS login is: " . $otpCode . "\n\n" .
                   "Login here: " . $loginUrl . "\n\n" .
                   "This code will expire in 10 minutes.";
        return self::send($email, $subject, $message);
    }

    /**
     * Send a general notification email
     */
    public static function sendNotification(string $email, string $subject, string $message): bool
    {
        $loginUrl = APP_BASE_URL . "/#/login";
        $message .= "\n\nLogin to access your Avantgarde HRMS dashboard: " . $loginUrl;
        return self::send($email, $subject, $message);
    }

    /**
     * Core method to send email via SMTP or native mail
     */
    public static function send(string $email, string $subject, string $message): bool
    {
        // 1. Mandatory logging for audit trail
        self::log($email, $subject, $message);

        // 2. Determine driver
        $driver = defined('MAIL_DRIVER') ? MAIL_DRIVER : 'mail';

        // If in development and no mailer configured, just return true after logging
        if (ENVIRONMENT === 'development' && empty(MAIL_USER) && $driver === 'smtp') {
            return true;
        }

        $mail = new PHPMailer(true);

        try {
            // Server settings
            if ($driver === 'smtp') {
                $mail->isSMTP();
                $mail->Host       = MAIL_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = MAIL_USER;
                $mail->Password   = MAIL_PASS;
                $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = MAIL_PORT;
            } else {
                $mail->isMail();
            }

            // Recipients
            $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
            $mail->addAddress($email);

            // Content
            $mail->isHTML(false); // Set to true if sending HTML
            $mail->Subject = $subject;
            $mail->Body    = $message;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mail Error: {$mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * Internal method to log attempts
     */
    private static function log(string $email, string $subject, string $message): void
    {
        // Use production path if defined, otherwise fallback to local project path
        $logFile = defined('MAIL_LOG_PATH') ? MAIL_LOG_PATH : BASE_PATH . '/tmp/mail_log.txt';
        
        // Ensure directory exists for local fallback (might fail for absolute external paths if no permissions)
        if (!defined('MAIL_LOG_PATH') && !is_dir(BASE_PATH . '/tmp')) {
            @mkdir(BASE_PATH . '/tmp', 0777, true);
        }

        $logEntry = "[" . date('Y-m-d H:i:s') . "] TO: $email | SUBJECT: $subject | MESSAGE: " . str_replace("\n", " ", $message) . "\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}
