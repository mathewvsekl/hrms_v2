<?php
$_SERVER['REQUEST_URI'] = '/api/employees';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_X_VIEW_MODE'] = 'employee';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = 443;
$_SERVER['HTTPS'] = 'on';

session_start();
$_SESSION['user_id'] = 10;
$_SESSION['user_role'] = 'Employee';
$_SESSION['scope_employee_id'] = 10;
$_SESSION['scope_company_id'] = 1;
$_SESSION['associated_company_ids'] = [1];

ob_start();
require __DIR__ . '/index.php';
$json = ob_get_clean();

$data = json_decode($json, true);
echo "Total employees returned: " . count($data['data']) . "\n";
