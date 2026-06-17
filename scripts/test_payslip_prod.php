<?php
require_once 'config/config.php';
require_once 'config/ProxyPDO.php';

try {
    $pdo = new ProxyPDO('https://hrms.anedins.com/db_proxy.php', 'HRMS_LOCAL_DEV_SECURE_TOKEN_55');

    $stmt = $pdo->prepare('
        SELECT pr.*, e.employee_code as emp_code, e.first_name, e.last_name, 
               e.tin_no, e.nssf_no, e.bank_account_no, e.bank_name,
               d.title as designation_name
        FROM payroll_records pr
        JOIN employees e ON pr.employee_id = e.id
        JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1
        JOIN companies c ON ec.company_id = c.id
        LEFT JOIN designations d ON e.designation_id = d.id
        WHERE pr.id = 2
    ');
    $stmt->execute();
    print_r($stmt->fetch(PDO::FETCH_ASSOC));
} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
