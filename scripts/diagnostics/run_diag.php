<?php
require_once 'config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Get Angela's basic info and company/country links
    $stmt = $db->prepare("
        SELECT e.id, e.nationality, ec.company_id, c.id as country_id, c.name as country_name, c.iso_code
        FROM employees e
        LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1
        LEFT JOIN companies c_comp ON ec.company_id = c_comp.id
        LEFT JOIN countries c ON c_comp.country_id = c.id
        WHERE e.id = 301
    ");
    $stmt->execute();
    $angela = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. Get all public holidays
    $stmt = $db->query("SELECT * FROM public_holidays");
    $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Get all weekly schedules
    $stmt = $db->query("SELECT * FROM office_weekly_schedules");
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = [
        'angela' => $angela,
        'public_holidays' => $holidays,
        'schedules' => $schedules
    ];
    
    file_put_contents('diag_final.json', json_encode($result, JSON_PRETTY_PRINT));
    echo "Diagnostic complete. Saved to diag_final.json\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
