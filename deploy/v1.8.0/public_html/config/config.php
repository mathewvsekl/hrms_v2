<?php

/**
 * HRMS V2 Configuration
 */

// Database Configuration
define('DB_HOST', '127.0.0.1'); // Fixed host for explicit TCP connection
define('DB_NAME', 'hrms_v2');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4'); // MariaDB 10.6+ supports utf8mb4

// Application Configuration
define('APP_BASE_URL', ''); // e.g., 'https://domain.com' or '/HRMS%20V2'
define('ENVIRONMENT', 'development'); // 'development' or 'production'

// Security
define('SESSION_SECURE', false); // Set to false for local dev on HTTP
