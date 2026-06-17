<?php
require_once 'config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Get first company and country
    $stmt = $db->query("SELECT id, country_id FROM companies LIMIT 1");
    $comp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$comp) {
        die("No company found in database.\n");
    }
    
    echo "Using Company ID: " . $comp['id'] . ", Country ID: " . $comp['country_id'] . "\n";
    
    // 2. Ensure Angela is assigned to this company as primary
    $stmt = $db->prepare("SELECT * FROM employee_companies WHERE employee_id = 301 AND company_id = :comp_id");
    $stmt->execute(['comp_id' => $comp['id']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        $stmt = $db->prepare("UPDATE employee_companies SET is_primary = 1, is_active = 1 WHERE employee_id = 301 AND company_id = :comp_id");
        $stmt->execute(['comp_id' => $comp['id']]);
        echo "Updated existing relationship.\n";
    } else {
        $stmt = $db->prepare("INSERT INTO employee_companies (employee_id, company_id, is_primary, is_active) VALUES (301, :comp_id, 1, 1)");
        $stmt->execute(['comp_id' => $comp['id']]);
        echo "Inserted new relationship.\n";
    }
    
    // 3. Update employee nationality (optional, but keep it consistent)
    $stmt = $db->prepare("UPDATE employees SET nationality = 'Kenya' WHERE id = 301");
    $stmt->execute();
    echo "Updated employee nationality.\n";
    
    // 4. Update the test holiday to match the company's country_id if different
    $stmt = $db->prepare("UPDATE public_holidays SET country_id = :cid WHERE name = 'Test Holiday' AND holiday_date = '2026-03-30'");
    $stmt->execute(['cid' => $comp['country_id']]);
    echo "Updated test holiday country_id to " . $comp['country_id'] . "\n";

    echo "Data fix complete.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
