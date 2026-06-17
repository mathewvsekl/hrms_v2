<?php
require 'backend/config/database.php';
$pdo = \Database::getInstance()->getConnection();
try {
    $stmt = $pdo->prepare("SELECT pr.month, pr.year, pr.company_id, c.name as company_name, COUNT(DISTINCT pr.employee_id) as total_employees, SUM(pr.net_pay) as total_net_pay, SUM(pr.gross_chargeable_income) as total_gross_pay, MAX(pr.status) as status, MAX(pr.reporting_currency) as reporting_currency, MAX(pr.exchange_rate) as exchange_rate FROM payroll_records pr JOIN companies c ON pr.company_id = c.id GROUP BY pr.month, pr.year, pr.company_id, c.name ORDER BY pr.year DESC, pr.month DESC, c.name ASC");
    $stmt->execute();
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
