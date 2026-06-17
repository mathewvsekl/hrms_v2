<?php
require_once __DIR__ . '/backend/public/index.php'; // or something to bootstrap
$auth = new \App\Services\AuthService();
$perms = $auth->getUserPermissions(3); // whatever user ID
print_r($perms);
