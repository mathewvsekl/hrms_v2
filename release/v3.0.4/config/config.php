<?php
/**
 * Avantgarde HRMS - Environment-Aware Configuration
 */

// Active Environment Selection: ['local', 'hevista', 'production']
// Priority: .env > fallback
$activeEnv = getenv('ACTIVE_ENVIRONMENT') ?: 'local';
if (!defined('ACTIVE_ENVIRONMENT')) { define('ACTIVE_ENVIRONMENT', $activeEnv); } 

// Load Environment Profiles (Fallback logic)
$environments = require __DIR__ . '/environments.php';
$envProfile = $environments[ACTIVE_ENVIRONMENT] ?? $environments['local'];

// Database Configuration
if (!defined('DB_HOST')) { define('DB_HOST', getenv('DB_HOST') ?: $envProfile['db_host']); }
if (!defined('DB_PORT')) { define('DB_PORT', getenv('DB_PORT') ?: ($envProfile['db_port'] ?? '3306')); }
if (!defined('DB_NAME')) { define('DB_NAME', getenv('DB_NAME') ?: $envProfile['db_name']); }
if (!defined('DB_USER')) { define('DB_USER', getenv('DB_USER') ?: $envProfile['db_user']); }
if (!defined('DB_PASS')) { define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : $envProfile['db_pass']); }
if (!defined('DB_CHARSET')) { define('DB_CHARSET', getenv('DB_CHARSET') ?: ($envProfile['db_charset'] ?? 'utf8mb4')); }

// Mail Configuration (Mailgun REST API or Log)
if (!defined('MAIL_DRIVER')) { define('MAIL_DRIVER', getenv('MAIL_DRIVER') ?: ($envProfile['mail_driver'] ?? 'mail')); }
if (!defined('MAIL_FROM_ADDRESS')) { define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'hrms@anedins.com'); }
if (!defined('MAIL_FROM_NAME')) { define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Avantgarde HRMS'); }

// Mailgun Specific Settings (Can also be in .env)
if (!defined('MAILGUN_API_KEY')) { define('MAILGUN_API_KEY', getenv('MAILGUN_API_KEY') ?: 'df767842bf32de2299426fe598607bf2-0b5dc895-285ca3af'); }
if (!defined('MAILGUN_DOMAIN')) { define('MAILGUN_DOMAIN', getenv('MAILGUN_DOMAIN') ?: 'anedins.com'); }
if (!defined('MAILGUN_ENDPOINT')) { define('MAILGUN_ENDPOINT', getenv('MAILGUN_ENDPOINT') ?: 'https://api.eu.mailgun.net/v3/anedins.com/messages'); }

// Application Configuration
if (!defined('APP_BASE_URL')) { define('APP_BASE_URL', getenv('APP_URL') ?: $envProfile['app_url']); }
if (!defined('ENVIRONMENT')) { define('ENVIRONMENT', getenv('ENVIRONMENT') ?: $envProfile['environment']); }

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', BASE_PATH);
}
if (!defined('TMP_PATH')) {
    define('TMP_PATH', ROOT_PATH . '/tmp');
}

if (!defined('MAIL_LOG_PATH')) { define('MAIL_LOG_PATH', TMP_PATH . '/mail_log.txt'); }

// Security
if (!defined('SESSION_SECURE')) { define('SESSION_SECURE', getenv('SESSION_SECURE') === 'true' ?: ($envProfile['session_secure'] ?? false)); }
