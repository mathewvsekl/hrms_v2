<?php
define('ROOT_PATH', __DIR__);
require ROOT_PATH . '/database/db.php';

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare('SELECT pr.*, e.employee_code as emp_code, e.first_name, e.last_name, d.title as designation_name FROM payroll_records pr JOIN employees e ON pr.employee_id = e.id JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 LEFT JOIN designations d ON e.designation_id = d.id WHERE pr.month = ? AND pr.year = ? AND ec.company_id = ? ORDER BY e.first_name ASC');
    $stmt->execute([5, 2026, 5]);
    echo "Success\n";
} catch(Exception $e) {
    echo $e->getMessage();
}
