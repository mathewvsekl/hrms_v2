<?php
/**
 * Avantgarde HRMS - Environment-Aware Configuration
 */

// Active Environment Selection: ['local', 'hevista', 'production']
// For local development, use 'local'.
define('ACTIVE_ENVIRONMENT', 'local'); 

// Load Environment Profiles
$environments = require __DIR__ . '/environments.php';
$config = $environments[ACTIVE_ENVIRONMENT] ?? $environments['local'];

// Database Configuration
define('DB_HOST', $config['db_host']);
define('DB_PORT', $config['db_port'] ?? '3306');
define('DB_NAME', $config['db_name']);
define('DB_USER', $config['db_user']);
define('DB_PASS', $config['db_pass']);
define('DB_CHARSET', $config['db_charset'] ?? 'utf8mb4');

// Mail Configuration (Mailgun REST API or Log)
define('MAIL_DRIVER', $config['mail_driver'] ?? 'mail');
define('MAIL_FROM_ADDRESS', 'hrms@anedins.com');
define('MAIL_FROM_NAME', 'Avantgarde HRMS');

// Mailgun Specific Settings
define('MAILGUN_API_KEY', 'df767842bf32de2299426fe598607bf2-0b5dc895-285ca3af');
define('MAILGUN_DOMAIN', 'anedins.com');
define('MAILGUN_ENDPOINT', 'https://api.eu.mailgun.net/v3/anedins.com/messages');

// Application Configuration
define('APP_BASE_URL', $config['app_url']);
define('ENVIRONMENT', $config['environment']); 

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
define('MAIL_LOG_PATH', BASE_PATH . '/tmp/mail_log.txt');

// Security
define('SESSION_SECURE', $config['session_secure'] ?? false);
