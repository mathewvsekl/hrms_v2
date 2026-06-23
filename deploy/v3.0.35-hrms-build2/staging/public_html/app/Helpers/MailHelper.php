<?php

namespace App\Helpers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailHelper
{
    public static function sendOTP(string $email, string $otp, ?string $userName = null, ?string $referenceCode = null): bool
    {
        // 1. Resolve User Name dynamically if not supplied
        if (empty($userName)) {
            try {
                $db = \Database::getInstance()->getConnection();
                $stmt = $db->prepare("
                    SELECT e.first_name, e.last_name 
                    FROM users u
                    LEFT JOIN employees e ON u.employee_id = e.id
                    WHERE e.email = :email OR u.username = :email
                    LIMIT 1
                ");
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch(\PDO::FETCH_ASSOC);
                $userName = $user ? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) : '';
            } catch (\Throwable $e) {
                $userName = '';
            }
        }
        if (empty($userName)) {
            $userName = 'User';
        }

        // 2. Generate Reference Code if not provided
        if (empty($referenceCode)) {
            $referenceCode = substr(strtoupper(hash('sha256', $otp . $email)), 0, 4);
        }

        // 3. Security context information (IP Address & Device detection)
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $platform = 'Unknown OS';
        $browser = 'Unknown Browser';
        
        if (preg_match('/android/i', $ua)) {
            $platform = 'Android';
        } elseif (preg_match('/iphone|ipad|ipod/i', $ua)) {
            $platform = 'iOS';
        } elseif (preg_match('/macintosh|mac os x/i', $ua)) {
            $platform = 'macOS';
        } elseif (preg_match('/windows|win32/i', $ua)) {
            $platform = 'Windows';
        } elseif (preg_match('/linux/i', $ua)) {
            $platform = 'Linux';
        }
        
        if (preg_match('/chrome/i', $ua)) {
            $browser = 'Google Chrome';
        } elseif (preg_match('/safari/i', $ua)) {
            $browser = 'Apple Safari';
        } elseif (preg_match('/firefox/i', $ua)) {
            $browser = 'Mozilla Firefox';
        } elseif (preg_match('/edge/i', $ua)) {
            $browser = 'Microsoft Edge';
        } elseif (preg_match('/opera|opr/i', $ua)) {
            $browser = 'Opera';
        }
        
        $deviceStr = !empty($ua) ? "$platform ($browser)" : 'Unknown Device';

        // 4. Time and Expiration details
        $expiryTime = time() + 600;
        $expiresAt = date('d M Y H:i:s', $expiryTime) . ' UTC';
        $timeStr = date('d M Y H:i:s') . ' UTC';

        $compactMetadata = "Ref: $referenceCode &nbsp;|&nbsp; IP: $ip &nbsp;|&nbsp; Device/OS: $deviceStr &nbsp;|&nbsp; Requested At: $timeStr";

        $subject = "$otp is your Avantgarde HRMS verification code";
        
        // Brand Design System Colors
        $brandPrimary = "#286B3E";      // Premium Green --color-rose-gold
        $brandPrimaryLight = "#3A8B54"; // --color-rose-gold-light
        $brandDark = "#1A1C20";         // Charcoal --color-charcoal
        $brandBg = "#FAF9F6";           // Ivory --color-ivory
        $brandGold = "#D4AF37";         // Muted Gold --color-muted-gold
        $brandBorder = "#E5E5E5";
        
        // Premium Responsive HTML Template with Google Fonts (Outfit & Inter)
        $html = "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Avantgarde HRMS Security Verification</title>
    <!--[if !mso]><!-->
    <link href='https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap' rel='stylesheet' type='text/css'>
    <!--<![endif]-->
    <style type='text/css'>
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            background-color: $brandBg;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: $brandDark;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
            border-collapse: collapse;
        }
        img {
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
        }
        
        @media only screen and (max-width: 620px) {
            .email-container {
                width: 100% !important;
                padding: 15px !important;
            }
            .card-body {
                padding: 30px 20px !important;
            }
            .otp-code {
                font-size: 32px !important;
                letter-spacing: 6px !important;
            }
            .security-table {
                padding: 12px 14px !important;
            }
        }
    </style>
</head>
<body style='background-color: $brandBg; padding: 40px 0; margin: 0; width: 100% !important;'>

    <table border='0' cellpadding='0' cellspacing='0' width='100%' style='background-color: $brandBg;'>
        <tr>
            <td align='center' style='padding: 0 10px;'>
                
                <table class='email-container' border='0' cellpadding='0' cellspacing='0' width='550' style='max-width: 550px; margin: 0 auto;'>
                    
                    <!-- Header -->
                    <tr>
                        <td align='center' style='padding-bottom: 25px;'>
                            <h1 style='font-family: \"Outfit\", sans-serif; color: $brandPrimary; margin: 0; font-size: 26px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase;'>Avantgarde HRMS</h1>
                            <p style='font-family: \"Outfit\", sans-serif; font-size: 10px; font-weight: 600; color: $brandGold; letter-spacing: 3px; text-transform: uppercase; margin: 5px 0 0 0;'>Excellence in Human Capital</p>
                        </td>
                    </tr>
                    
                    <!-- Card Body -->
                    <tr>
                        <td class='card-body' style='background-color: #ffffff; padding: 40px 35px; border-radius: 16px; border: 1px solid $brandBorder; box-shadow: 0 10px 15px -3px rgba(26, 28, 32, 0.04), 0 4px 6px -2px rgba(26, 28, 32, 0.02);'>
                            
                            <div align='center' style='margin-bottom: 25px;'>
                                <span style='display: inline-block; background-color: rgba(40, 107, 62, 0.08); border: 1px solid rgba(40, 107, 62, 0.2); color: $brandPrimary; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; font-family: \"Outfit\", sans-serif;'>Security Verification</span>
                            </div>
                            
                            <h2 style='font-family: \"Outfit\", sans-serif; font-size: 18px; font-weight: 700; color: $brandDark; margin: 0 0 16px 0;'>Hello " . htmlspecialchars($userName) . ",</h2>
                            
                            <p style='font-size: 14px; color: #4B5563; line-height: 1.6; margin: 0 0 25px 0;'>
                                A request was made to sign in to your Avantgarde HRMS account. Please use the secure one-time password (OTP) below to authorize this session:
                            </p>
                            
                            <!-- OTP Box -->
                            <div align='center' style='margin-bottom: 25px;'>
                                <div style='font-family: \"Outfit\", sans-serif; font-size: 13px; font-weight: 700; color: #4B5563; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;'>
                                    One-Time Password (OTP)
                                </div>
                                <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                                    <tr>
                                        <td align='center' style='background-color: $brandBg; border: 2px dashed $brandPrimary; border-radius: 12px; padding: 22px; text-align: center;'>
                                            <div class='otp-code' style='font-family: \"Outfit\", monospace; font-size: 38px; font-weight: 800; color: $brandPrimary; letter-spacing: 8px; line-height: 1.2; margin: 0;'>$otp</div>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <p style='font-size: 13px; color: #6B7280; text-align: center; margin: 0 0 25px 0;'>
                                This code is valid for the next <strong style='color: $brandDark;'>10 minutes</strong> (expires at $expiresAt).
                            </p>
                            
                            
                            
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style='padding: 20px 10px 0 10px; border-top: 1px solid $brandBorder;'>
                            <p style='font-family: \"Inter\", sans-serif; font-size: 10px; color: #9CA3AF; text-align: center; margin: 0 0 15px 0;'>
                                $compactMetadata
                            </p>
                            <div style='font-size: 11px; color: #9CA3AF; text-align: center; line-height: 1.8;'>
                                <p style='margin: 0 0 8px 0;'>&copy; " . date('Y') . " Avantgarde HRMS Team. All rights reserved.</p>
                                <p style='margin: 0;'>
                                    <a href='" . (defined('APP_BASE_URL') ? APP_BASE_URL : 'https://emm.anedins.com') . "' style='color: $brandPrimary; text-decoration: none; font-weight: 600;'>Support Center</a> &nbsp;•&nbsp; 
                                    <a href='" . (defined('APP_BASE_URL') ? APP_BASE_URL : 'https://emm.anedins.com') . "' style='color: $brandPrimary; text-decoration: none; font-weight: 600;'>Privacy Policy</a>
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                </table>
                
            </td>
        </tr>
    </table>

</body>
</html>";

        // Plain Text Fallback matching the Security Logic
        $text = "AVANTGARDE HRMS SECURITY VERIFICATION\n";
        $text .= "==================================================\n";
        $text .= "Hello $userName,\n\n";
        $text .= "A request was made to sign in to your Avantgarde HRMS account.\n";
        $text .= "Please use the secure one-time password (OTP) below to authorize this session:\n\n";
        $text .= "OTP CODE: $otp\n";
        $text .= "Expires at: $expiresAt\n\n";
        $text .= "--- System Metadata ---\n";
        $text .= "Ref: $referenceCode | IP: $ip | Device/OS: $deviceStr | Requested At: $timeStr\n\n";

        $text .= "==================================================\n";
        $text .= "© " . date('Y') . " Avantgarde HRMS Team.";

        return self::send($email, $subject, $text, true, $html);
    }

    public static function sendWelcomeEmail(string $email, string $userName): bool
    {
        if (empty($userName)) {
            $userName = 'User';
        }

        $subject = "Welcome to Avantgarde HRMS!";
        
        $brandPrimary = "#286B3E";      
        $brandDark = "#1A1C20";         
        $brandBg = "#FAF9F6";           
        $brandGold = "#D4AF37";         
        $brandBorder = "#E5E5E5";

        $html = "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Welcome to Avantgarde HRMS</title>
    <style type='text/css'>
        body { margin: 0; padding: 0; width: 100% !important; background-color: $brandBg; font-family: 'Inter', sans-serif; color: $brandDark; }
    </style>
</head>
<body style='background-color: $brandBg; padding: 40px 0; margin: 0; width: 100% !important;'>
    <div style='max-width: 550px; margin: 0 auto; background-color: #ffffff; padding: 40px 35px; border-radius: 16px; border: 1px solid $brandBorder;'>
        <p style='font-size: 16px; color: $brandDark; margin: 0 0 16px 0;'>Hi " . htmlspecialchars($userName) . ",</p>
        <p style='font-size: 16px; color: $brandDark; margin: 0 0 20px 0;'>Welcome to Avantgarde HRMS!</p>
        <p style='font-size: 14px; line-height: 1.6; margin: 0 0 20px 0;'>
            Your account has been successfully created, and you can now access the system using your registered email address along with a secure One-Time Password (OTP).
        </p>
        <p style='font-size: 14px; margin: 0 0 16px 0;'>
            To get started, please use the link below:
        </p>
        <div style='text-align: center; margin-bottom: 25px;'>
            <a href='" . (defined('APP_BASE_URL') ? APP_BASE_URL : 'https://emm.anedins.com/') . "' style='display: inline-block; background-color: $brandPrimary; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 14px;'>Login to HRMS</a>
        </div>
        
        <p style='margin: 0; color: #4B5563; font-size: 14px;'>If the button doesn't work, copy and paste this link into your browser:</p>
        <a href='" . (defined('APP_BASE_URL') ? APP_BASE_URL : 'https://emm.anedins.com/') . "' style='color: $brandPrimary;'>" . (defined('APP_BASE_URL') ? APP_BASE_URL : 'https://emm.anedins.com/') . "</a>
        </p>
        <p style='font-size: 14px; color: #4B5563; margin: 0 0 20px 0;'>
            If you have any questions or need assistance, feel free to reach out to our support team.
        </p>
        <p style='font-size: 14px; color: #4B5563; margin: 0 0 25px 0;'>
            We're glad to have you on board.
        </p>
        <p style='font-size: 14px; color: #4B5563; margin: 0 0 5px 0;'>Best regards,</p>
        <p style='font-size: 14px; font-weight: bold; color: $brandDark; margin: 0 0 30px 0;'>Avantgarde HRMS Team</p>
        <div style='border-top: 1px solid $brandBorder; padding-top: 15px;'>
            <p style='font-size: 12px; color: #9CA3AF; margin: 0; text-align: center;'>&copy; " . date('Y') . " Avantgarde HRMS. All rights reserved.</p>
        </div>
    </div>
</body>
</html>";

        $text = "Hi $userName,\n\n";
        $text .= "Welcome to Avantgarde HRMS!\n\n";
        $text .= "Your account has been successfully created, and you can now access the system using your registered email address along with a secure One-Time Password (OTP).\n\n";
        $text .= "--------------------------------------------------\n";
        $text .= "Please login to your account to view this message:\n";
        $text .= (defined('APP_BASE_URL') ? APP_BASE_URL : 'https://emm.anedins.com/') . "\n\n";
        $text .= "Or check your notifications dashboard:\n";
        $text .= (defined('APP_BASE_URL') ? APP_BASE_URL : 'https://emm.anedins.com/') . "\n\n";
        $text .= "If you have any questions or need assistance, feel free to reach out to our support team.\n\n";
        $text .= "We're glad to have you on board.\n\n";
        $text .= "Best regards,\n";
        $text .= "Avantgarde HRMS Team\n\n";
        $text .= "© " . date('Y') . " Avantgarde HRMS. All rights reserved.";

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
        $tmpPath = defined('TMP_PATH') ? TMP_PATH : (defined('ROOT_PATH') ? ROOT_PATH : BASE_PATH) . '/tmp';
        $logFile = defined('MAIL_LOG_PATH') ? \MAIL_LOG_PATH : $tmpPath . '/mail_log.txt';
        $logEntry = "[" . date('Y-m-d H:i:s') . "] TO: $email | SUBJECT: $subject | MESSAGE: " . str_replace("\n", " ", $message) . "\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}
