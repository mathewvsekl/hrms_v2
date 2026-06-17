<?php

/**
 * Avantgarde HRMS Configuration Template
 * Environment-Aware Selection System
 * 
 * INSTRUCTIONS:
 * 1. Rename this file to config.php
 * 2. Set ACTIVE_ENVIRONMENT to match your server ('hevista', 'production', or 'local')
 * 3. Update database and mail credentials below.
 */

// Active Environment Selection: ['local', 'production', 'hevista', 'remote']
define('ACTIVE_ENVIRONMENT', 'hevista'); 

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

// Web Bridge Configuration (Path C - only for remote development)
define('PROXY_URL', $config['proxy_url'] ?? '');
define('PROXY_TOKEN', $config['proxy_token'] ?? '');

// Mail Configuration (SMTP Settings)
define('MAIL_DRIVER', 'smtp'); 
define('MAIL_HOST', 'mail.anedins.com'); 
define('MAIL_PORT', 587); 
define('MAIL_ENCRYPTION', 'tls'); 
define('MAIL_USER', 'agi-hrms@anedins.com'); 
define('MAIL_PASS', ''); // UPDATE THIS on production
define('MAIL_FROM_ADDRESS', 'agi-hrms@anedins.com');
define('MAIL_FROM_NAME', 'Avantgarde HRMS');
define('MAIL_LOG_PATH', BASE_PATH . '/tmp/mail_log.txt');

// Application Configuration
define('APP_BASE_URL', $config['app_url']);
define('ENVIRONMENT', $config['environment']); 

// Date Formats
define('DISPLAY_DATE_FORMAT', 'd M Y'); 
define('INPUT_DATE_FORMAT', 'd/m/Y');   

// Security
define('SESSION_SECURE', $config['session_secure'] ?? true);
