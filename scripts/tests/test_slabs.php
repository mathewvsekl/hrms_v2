<?php
require 'config/database.php';
require 'app/Services/PayrollService.php';

$service = new \App\Services\PayrollService();
echo "PAYE for 235,000: " . $service->calculatePAYE(235000) . "\n";
echo "PAYE for 300,000: " . $service->calculatePAYE(300000) . "\n";
echo "PAYE for 400,000: " . $service->calculatePAYE(400000) . "\n";
echo "PAYE for 500,000: " . $service->calculatePAYE(500000) . "\n";
echo "PAYE for 11,000,000: " . $service->calculatePAYE(11000000) . "\n";
