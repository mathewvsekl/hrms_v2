<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$container = $app->getContainer();
$db = $container->get('db');
$service = new App\Services\PayrollService($db);
print_r($service->previewPayroll(5, 2026, 1));
