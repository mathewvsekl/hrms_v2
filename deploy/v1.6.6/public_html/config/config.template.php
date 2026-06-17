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

// Application Configuration
define('APP_BASE_URL', ''); // e.g., 'https://domain.com' or '/HRMS%20V2'
define('ENVIRONMENT', 'production'); // 'development' or 'production'

// Security
define('SESSION_SECURE', true);
