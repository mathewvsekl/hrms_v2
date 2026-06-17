<?php

namespace App\Helpers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailHelper
{
    public static function sendOTP(string $email, string $otp): bool
    {
        $subject = "Your HRMS Verification Code";
        
        // Brand Colors from index.css
        $brandPrimary = "#286B3E"; // --color-rose-gold
        $brandDark = "#1A1C20";    // --color-charcoal
        $brandBg = "#FAF9F6";      // --color-ivory
        
        // Premium HTML Template matching Web App Theme
        $html = "
        <div style='font-family: \"Outfit\", \"Inter\", \"Segoe UI\", Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 40px; border: 1px solid #E5E5E5; border-radius: 12px; background-color: #ffffff; color: $brandDark;'>
            <div style='text-align: center; margin-bottom: 35px;'>
                <h1 style='color: $brandPrimary; margin: 0; font-size: 28px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;'>Avantgarde HRMS</h1>
                <p style='font-size: 10px; color: #6B7280; letter-spacing: 0.1em; text-transform: uppercase; margin-top: 4px;'>Excellence in Human Capital</p>
            </div>
            
            <div style='margin-bottom: 30px; background-color: $brandBg; padding: 30px; border-radius: 12px; border: 1px solid rgba(40, 107, 62, 0.05);'>
                <h2 style='font-size: 18px; margin-bottom: 12px; color: $brandDark; font-weight: 600;'>Security Verification</h2>
                <p style='line-height: 1.6; color: #4B5563; font-size: 15px;'>To complete your request, please use the following one-time password (OTP):</p>
                
                <div style='background-color: #ffffff; border: 2px solid $brandPrimary; border-radius: 8px; padding: 25px; text-align: center; margin: 25px 0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);'>
                    <span style='font-size: 36px; font-weight: 800; color: $brandPrimary; letter-spacing: 8px; font-family: monospace;'>$otp</span>
                </div>
                
                <p style='font-size: 13px; color: #6B7280; text-align: center;'>This code is valid for the next <b style='color: $brandDark;'>10 minutes</b>.</p>
            </div>
            
            <div style='padding: 0 10px;'>
                <p style='font-size: 12px; color: #6B7280; line-height: 1.6;'>
                    <strong style='color: $brandDark;'>Security Reminder:</strong> For your protection, do not share this code with anyone. Our team will never ask for your OTP via phone, email, or text. If you did not request this code, please ignore this email.
                </p>
            </div>

            <hr style='border: 0; border-top: 1px solid #E5E5E5; margin: 30px 0;'>
            
            <div style='font-size: 11px; color: #9CA3AF; text-align: center; line-height: 1.5;'>
                <p>&copy; " . date('Y') . " Avantgarde HRMS Team. All rights reserved.</p>
                <p style='margin-top: 10px;'>
                    <a href='https://hrms.anedins.com' style='color: $brandPrimary; text-decoration: none; font-weight: 600;'>Support Center</a> &nbsp;•&nbsp; 
                    <a href='https://hrms.anedins.com' style='color: $brandPrimary; text-decoration: none; font-weight: 600;'>Privacy Policy</a>
                </p>
            </div>
        </div>";

        // Plain text fallback
        $text = "Avantgarde HRMS Verification Code: $otp\n\nValid for 10 minutes.\n\nSecurity Reminder: Do not share this code. Avantgarde team will never ask for your OTP.";

        return self::send($email, $subject, $text, true, $html);
    }

    public static function send(string $email, string $subject, string $message, bool $isHtml = false, string $htmlBody = ''): bool
    {
        // 1. Mandatory logging for audit trail
        self::log($email, $subject, $message);

        // 2. Determine driver
        $driver = defined('MAIL_DRIVER') ? \MAIL_DRIVER : 'mail';

        // Noah Audit Fix: Skip sending if driver is 'log' or 'none' (Local Dev)
        if ($driver === 'log' || $driver === 'none') {
            return true;
        }

        // 3. Mailgun Driver
        if ($driver === 'mailgun') {
            return self::sendViaMailgun($email, $subject, $message, $isHtml ? $htmlBody : '');
        }

        // 4. Safety check: If PHPMailer class is missing
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            error_log("MailHelper: PHPMailer not found. Skipping SMTP send.");
            return true; 
        }

        $mail = new PHPMailer(true);

        try {
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

            $mail->setFrom(\MAIL_FROM_ADDRESS, \MAIL_FROM_NAME);
            $mail->addAddress($email);

            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            
            if ($isHtml && !empty($htmlBody)) {
                $mail->Body    = $htmlBody;
                $mail->AltBody = $message;
            } else {
                $mail->Body    = $message;
            }

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mail Error: {$mail->ErrorInfo}");
            return false;
        }
    }

    private static function sendViaMailgun(string $email, string $subject, string $message, string $html = ''): bool
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

        if (!empty($html)) {
            $postData['html'] = $html;
        }

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
        } else {
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

            $httpCode = 500;
            if (isset($http_response_header) && preg_match('{HTTP\/\S+\s+(\d+)}', $http_response_header[0], $matches)) {
                $httpCode = (int)$matches[1];
            }
        }

        return ($httpCode >= 200 && $httpCode < 300);
    }

    private static function log(string $email, string $subject, string $message): void
    {
        $logFile = defined('MAIL_LOG_PATH') ? \MAIL_LOG_PATH : BASE_PATH . '/tmp/mail_log.txt';
        $logEntry = "[" . date('Y-m-d H:i:s') . "] TO: $email | SUBJECT: $subject | MESSAGE: " . str_replace("\n", " ", $message) . "\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}
