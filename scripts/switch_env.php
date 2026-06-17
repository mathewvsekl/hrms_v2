<?php

/**
 * HRMS Environment Switcher
 * Usage: php scripts/switch_env.php [local|tunnel|production]
 */

if ($argc < 2) {
    die("Usage: php scripts/switch_env.php [local|tunnel|production]\n");
}

$newEnv = strtolower($argv[1]);
$allowedEnvs = ['local', 'remote', 'production'];

if (!in_array($newEnv, $allowedEnvs)) {
    die("Usage: php scripts/switch_env.php [local|remote|production]\n");
}

$configFile = __DIR__ . '/../config/config.php';
$content = file_get_contents($configFile);

$pattern = "/define\('ACTIVE_ENVIRONMENT', '.*?'\);/";
$replacement = "define('ACTIVE_ENVIRONMENT', '$newEnv');";

if (preg_match($pattern, $content)) {
    $newContent = preg_replace($pattern, $replacement, $content);
    file_put_contents($configFile, $newContent);
    echo "Successfully switched to environment: $newEnv\n";
    
    if ($newEnv === 'remote') {
        echo "\n[INFO] Path B Active: Direct connection to 188.40.91.234:3306.\n";
        echo "Ensure you have added '%' or your IP to 'Allowed Hosts' in DirectAdmin.\n";
    }
} else {
    die("Error: Could not find ACTIVE_ENVIRONMENT definition in config/config.php\n");
}
