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

        // 3. Mailgun Driver (Preferred for API integration)
        if ($driver === 'mailgun') {
            return self::sendViaMailgun($email, $subject, $message);
        }

        // 4. Safety check: If PHPMailer class is missing for SMTP/Mail drivers
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            // If we are in local development without PHPMailer, we just log it and return true
            error_log("MailHelper: PHPMailer not found. Skipping SMTP send.");
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
     * Send email via Mailgun REST API
     */
    private static function sendViaMailgun(string $email, string $subject, string $message): bool
    {
        $apiKey = defined('MAILGUN_API_KEY') ? \MAILGUN_API_KEY : '';
        $domain = defined('MAILGUN_DOMAIN') ? \MAILGUN_DOMAIN : '';
        $endpoint = defined('MAILGUN_ENDPOINT') ? \MAILGUN_ENDPOINT : "https://api.mailgun.net/v3/{$domain}/messages";

        if (empty($apiKey) || $apiKey === 'YOUR_MAILGUN_API_KEY') {
            error_log("Mailgun Error: API Key not configured.");
            return false;
        }

        $postData = [
            'from'    => \MAIL_FROM_NAME . ' <' . \MAIL_FROM_ADDRESS . '>',
            'to'      => $email,
            'subject' => $subject,
            'text'    => $message
        ];

        // Preference 1: CURL (Most robust)
        if (function_exists('curl_init')) {
            $ch = \curl_init();
            \curl_setopt($ch, CURLOPT_URL, $endpoint);
            \curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            \curl_setopt($ch, CURLOPT_USERPWD, "api:{$apiKey}");
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            \curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            \curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            \curl_setopt($ch, CURLOPT_POST, 1);
            \curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

            $result = \curl_exec($ch);
            $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = \curl_error($ch);
            \curl_close($ch);

            if ($error) {
                error_log("Mailgun Curl Error: " . $error);
                return false;
            }
        } 
        // Preference 2: Streams (Fallback if CURL is missing)
        else {
            error_log("MailHelper: curl_init not found. Using file_get_contents fallback.");
            $auth = base64_encode("api:{$apiKey}");
            $options = [
                'http' => [
                    'header'  => "Authorization: Basic $auth\r\n" .
                                 "Content-Type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($postData),
                    'ignore_errors' => true,
                    'timeout' => 30
                ]
            ];
            $context  = stream_context_create($options);
            $result = @file_get_contents($endpoint, false, $context);
            
            if ($result === false) {
                $error = error_get_last();
                error_log("Mailgun Fallback Error: " . ($error['message'] ?? 'Unknown error'));
                return false;
            }

            // Extract HTTP code from response headers
            $httpCode = 500;
            if (isset($http_response_header) && preg_match('{HTTP\/\S+\s+(\d+)}', $http_response_header[0], $matches)) {
                $httpCode = (int)$matches[1];
            }
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        } else {
            error_log("Mailgun API Error (HTTP {$httpCode}): " . $result);
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
