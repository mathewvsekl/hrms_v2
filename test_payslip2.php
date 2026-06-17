<?php
require 'c:/Users/AneeshMathew/HRMS V2/backend/config/database.php';
$db = Database::getInstance()->getConnection();

$stmt = $db->query("SELECT id FROM payroll_records WHERE employee_id = 1 AND company_id = 4 ORDER BY id DESC LIMIT 1");
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if ($record) {
    require 'c:/Users/AneeshMathew/HRMS V2/backend/app/Services/PayrollService.php';
    $service = new App\Services\PayrollService($db);
    $data = $service->getPayslipData($record['id']);
    print_r([
        'employee_currency' => $data['employee_currency'] ?? null,
        'company_currency' => $data['company_currency'] ?? null,
        'payslip_currency' => $data['payslip_currency'] ?? null,
        'net_pay' => $data['net_pay'] ?? null,
    ]);
} else {
    echo "No UAE record found for employee 1";
}
