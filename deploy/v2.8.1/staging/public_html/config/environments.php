<?php

/**
 * HRMS V2 Environment Profiles
 * Defines configuration for different runtime environments.
 */

return [
    'local' => [
        'name' => 'Local Development',
        'db_host' => '127.0.0.1',
        'db_port' => '3306',
        'db_name' => 'hrms_v2',
        'db_user' => 'root',
        'db_pass' => '',
        'db_charset' => 'utf8mb4',
        'app_url' => 'http://localhost:5173',
        'environment' => 'development',
        'session_secure' => false,
        'mail_driver' => 'log',
    ],

    'remote' => [
        'name' => 'Direct Remote (DirectAdmin Live)',
        'db_host' => '188.40.91.234',
        'db_port' => '3306',
        'db_name' => 'glowlady_avantgarde',
        'db_user' => 'glowlady_avantgarde',
        'db_pass' => 'USsBTCeXy9rrsCnKuNR9',
        'db_charset' => 'utf8mb4',
        'proxy_url' => 'https://avantgarde.glowlady.in/db_proxy.php',
        'proxy_token' => 'HRMS_LOCAL_DEV_SECURE_TOKEN_55',
        'app_url' => 'http://localhost/HRMS%20V2',
        'environment' => 'development',
        'session_secure' => false,
    ],

    'production' => [
        'name' => 'Glow Lady Production',
        'db_host' => 'localhost',
        'db_port' => '3306',
        'db_name' => 'glowlady_avantgarde',
        'db_user' => 'glowlady_avantgarde',
        'db_pass' => 'USsBTCeXy9rrsCnKuNR9',
        'db_charset' => 'utf8mb4',
        'app_url' => 'http://avantgarde.glowlady.in/',
        'environment' => 'production',
        'session_secure' => false,
    ],

    'hevista' => [
        'name' => 'Hevista Production',
        'db_host' => 'localhost',
        'db_port' => '3306',
        'db_name' => 'Admin_anedins_hrms_agi',
        'db_user' => 'Admin_admin_anedins_hrms_agi',
        'db_pass' => 'dxzWW?EAYaC9gE|o',
        'db_charset' => 'utf8mb4',
        'app_url' => 'https://hrms.anedins.com',
        'environment' => 'production',
        'session_secure' => true,
        'mail_driver' => 'mailgun',
    ],

    'local_tunnel' => [
        'name' => 'Local via SSH Tunnel',
        'db_host' => '127.0.0.1',
        'db_port' => '3307',
        'db_name' => 'Admin_anedins_hrms_agi',
        'db_user' => 'Admin_admin_anedins_hrms_agi',
        'db_pass' => 'HRMS_anedins_2026',
        'db_charset' => 'utf8mb4',
        'app_url' => 'http://127.0.0.1:8000',
        'environment' => 'development',
        'session_secure' => false,
        'mail_driver' => 'log',
    ],
];
