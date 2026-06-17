<?php
/**
 * Avantgarde HRMS - Production Configuration (Hevista)
 */

define('ACTIVE_ENVIRONMENT', 'hevista');
define('ENVIRONMENT', 'production');

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'Admin_anedins_hrms_agi');
define('DB_USER', 'Admin_anedins_hrms_agi');
define('DB_PASS', 'dxzWW?EAYaC9gE|o');
define('DB_CHARSET', 'utf8mb4');

define('MAIL_DRIVER', 'smtp');
define('MAIL_HOST', 'mail.hrms.anedins.com');
define('MAIL_PORT', 465);
define('MAIL_ENCRYPTION', 'ssl');
define('MAIL_USER', 'info@hrms.anedins.com');
define('MAIL_PASS', 'Vision@2030');
define('MAIL_FROM_ADDRESS', 'info@hrms.anedins.com');
define('MAIL_FROM_NAME', 'Avantgarde HRMS');

define('APP_BASE_URL', 'https://hrms.anedins.com');
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
define('MAIL_LOG_PATH', BASE_PATH . '/tmp/mail_log.txt');

define('SESSION_SECURE', true);
