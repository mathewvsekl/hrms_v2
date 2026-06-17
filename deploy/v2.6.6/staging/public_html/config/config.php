<?php
/**
 * Avantgarde HRMS - Environment-Aware Configuration
 * Selects profile from environments.php based on ACTIVE_ENVIRONMENT.
 */

// Active Environment Selection: ['local', 'production', 'hevista', 'remote', 'local_tunnel']
define('ACTIVE_ENVIRONMENT', 'local'); 

// Load Environment Profiles
$environments = require __DIR__ . '/environments.php';
$config = $environments[ACTIVE_ENVIRONMENT] ?? $environments['local'];

// Base Directory Mapping
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Database Configuration
define('DB_HOST', $config['db_host']);
define('DB_PORT', $config['db_port'] ?? '3306');
define('DB_NAME', $config['db_name']);
define('DB_USER', $config['db_user']);
define('DB_PASS', $config['db_pass']);
define('DB_CHARSET', $config['db_charset'] ?? 'utf8mb4');

// Mail Configuration (SMTP Settings)
define('MAIL_DRIVER', 'smtp');
define('MAIL_HOST', 'mail.hrms.anedins.com');
define('MAIL_PORT', 465);
define('MAIL_ENCRYPTION', 'ssl');
define('MAIL_USER', 'info@hrms.anedins.com');
define('MAIL_PASS', 'Vision@2030');
define('MAIL_FROM_ADDRESS', 'info@hrms.anedins.com');
define('MAIL_FROM_NAME', 'Avantgarde HRMS');
define('MAIL_LOG_PATH', BASE_PATH . '/tmp/mail_log.txt');

// Application Configuration
define('APP_BASE_URL', $config['app_url']);
define('ENVIRONMENT', $config['environment']); 

// Security
define('SESSION_SECURE', $config['session_secure'] ?? false);
