<?php
/**
 * Avantgarde HRMS - Environment-Aware Configuration
 */

// Active Environment Selection: ['local', 'hevista', 'production']
// Priority: .env > fallback
$activeEnv = getenv('ACTIVE_ENVIRONMENT') ?: 'local';
define('ACTIVE_ENVIRONMENT', $activeEnv); 

// Load Environment Profiles (Fallback logic)
$environments = require __DIR__ . '/environments.php';
$envProfile = $environments[ACTIVE_ENVIRONMENT] ?? $environments['local'];

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: $envProfile['db_host']);
define('DB_PORT', getenv('DB_PORT') ?: ($envProfile['db_port'] ?? '3306'));
define('DB_NAME', getenv('DB_NAME') ?: $envProfile['db_name']);
define('DB_USER', getenv('DB_USER') ?: $envProfile['db_user']);
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : $envProfile['db_pass']);
define('DB_CHARSET', getenv('DB_CHARSET') ?: ($envProfile['db_charset'] ?? 'utf8mb4'));

// Mail Configuration (Mailgun REST API or Log)
define('MAIL_DRIVER', getenv('MAIL_DRIVER') ?: ($envProfile['mail_driver'] ?? 'mail'));
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'hrms@anedins.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Avantgarde HRMS');

// Mailgun Specific Settings (Can also be in .env)
define('MAILGUN_API_KEY', getenv('MAILGUN_API_KEY') ?: 'df767842bf32de2299426fe598607bf2-0b5dc895-285ca3af');
define('MAILGUN_DOMAIN', getenv('MAILGUN_DOMAIN') ?: 'anedins.com');
define('MAILGUN_ENDPOINT', getenv('MAILGUN_ENDPOINT') ?: 'https://api.eu.mailgun.net/v3/anedins.com/messages');

// Application Configuration
define('APP_BASE_URL', getenv('APP_URL') ?: $envProfile['app_url']);
define('ENVIRONMENT', getenv('ENVIRONMENT') ?: $envProfile['environment']); 

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
define('MAIL_LOG_PATH', BASE_PATH . '/tmp/mail_log.txt');

// Security
define('SESSION_SECURE', getenv('SESSION_SECURE') === 'true' ?: ($envProfile['session_secure'] ?? false));
