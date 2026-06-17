<?php

namespace App\Helpers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailHelper
{
    public static function sendOTP(string $email, string $otp): bool
    {
        $subject = "Your HRMS Access Code";
        $message = "Your 6-digit access code for Avantgarde HRMS is: " . $otp . "\n\nThis code will expire in 10 minutes.";
        
        return self::send($email, $subject, $message);
    }

    public static function send(string $email, string $subject, string $message): bool
    {
        // 1. Mandatory logging for audit trail
        self::log($email, $subject, $message);

        // 2. Determine driver
        $driver = defined('MAIL_DRIVER') ? \MAIL_DRIVER : 'mail';

        // 3. Safety check: If PHPMailer class is missing
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return true; 
        }

        $mail = new PHPMailer(true);

        try {
            // Server settings
            if ($driver === 'smtp') {
                $mail->isSMTP();
                $mail->Host       = \MAIL_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = \MAIL_USER;
                $mail->Password   = \MAIL_PASS;
                $mail->SMTPSecure = \MAIL_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = \MAIL_PORT;
            } else {
                $mail->isMail();
            }

            // Recipients
            $mail->setFrom(\MAIL_FROM_ADDRESS, \MAIL_FROM_NAME);
            $mail->addAddress($email);

            // Content
            $mail->isHTML(false);
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
        $logFile = defined('MAIL_LOG_PATH') ? \MAIL_LOG_PATH : BASE_PATH . '/tmp/mail_log.txt';
        
        $logEntry = "[" . date('Y-m-d H:i:s') . "] TO: $email | SUBJECT: $subject | MESSAGE: " . str_replace("\n", " ", $message) . "\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}
