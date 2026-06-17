<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Core/Controller.php';
require_once __DIR__ . '/app/Controllers/PayrollController.php';
require_once __DIR__ . '/app/Services/PayrollService.php';

$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT * FROM payroll_records ORDER BY id DESC LIMIT 1");
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if ($record) {
    $service = new \App\Services\PayrollService();
    $data = $service->getPayslipData($record['id']);
    print_r($data);
} else {
    echo "No records found.";
}
