<?php

/**
 * HRMS V2 Configuration Template
 * Rename this file to config.php on your server and populate with environment-specific values.
 * This file is EXCLUDED from git/deployment to prevent credential leakage.
 */

// Database Configuration
define('DB_HOST', 'localhost'); // Use 'localhost' for Unix Sockets (DirectAdmin), '127.0.0.1' for TCP
define('DB_NAME', 'hrms_v2');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4'); // MariaDB 10.6+ supports utf8mb4

// Mail Configuration (PHPMailer / DirectAdmin)
define('MAIL_DRIVER', 'smtp'); // 'smtp' or 'mail'
define('MAIL_HOST', 'mail.anedins.com'); // Usually 'localhost' or 'mail.yourdomain.com'
define('MAIL_PORT', 587); // 587 for TLS, 465 for SSL, 25 for non-secure
define('MAIL_ENCRYPTION', 'tls'); // 'tls', 'ssl', or null
define('MAIL_USER', 'agi-hrms@anedins.com'); // Your DirectAdmin email address
define('MAIL_PASS', ''); // Your email password
define('MAIL_FROM_ADDRESS', 'agi-hrms@anedins.com');
define('MAIL_FROM_NAME', 'Avantgarde HRMS');
define('MAIL_LOG_PATH', '/domains/avantgarde.anedins.com/public_html/tmp/mail_log.txt');

// Application Configuration
define('APP_BASE_URL', ''); // e.g., 'https://domain.com' or '/HRMS%20V2'
define('ENVIRONMENT', 'production'); // 'development' or 'production'

// Security
define('SESSION_SECURE', true);
