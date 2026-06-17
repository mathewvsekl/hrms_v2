<?php
require_once 'config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Ensure Country 1 exists (or get first)
    $stmt = $db->query("SELECT id FROM countries LIMIT 1");
    $country = $stmt->fetch(PDO::FETCH_ASSOC);
    $countryId = $country['id'] ?? 1;

    // 2. Public Holiday
    $stmt = $db->prepare("SELECT id FROM public_holidays WHERE name = 'Test Holiday' AND holiday_date = '2026-03-30'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $stmt = $db->prepare("INSERT INTO public_holidays (country_id, name, holiday_date, year) VALUES (:cid, 'Test Holiday', '2026-03-30', 2026)");
        $stmt->execute(['cid' => $countryId]);
        echo "Inserted Public Holiday.\n";
    } else {
        echo "Public Holiday already exists.\n";
    }

    // 3. Company Holiday
    $stmt = $db->prepare("SELECT id FROM holidays WHERE name = 'Company Fun Day' AND holiday_date = '2026-03-25'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $stmt = $db->prepare("INSERT INTO holidays (company_id, holiday_date, name) VALUES (1, '2026-03-25', 'Company Fun Day')");
        $stmt->execute();
        echo "Inserted Company Holiday.\n";
    } else {
        echo "Company Holiday already exists.\n";
    }

    // 4. Verify counts
    $stmt = $db->query("SELECT COUNT(*) FROM public_holidays");
    echo "Total Public Holidays: " . $stmt->fetchColumn() . "\n";
    $stmt = $db->query("SELECT COUNT(*) FROM holidays");
    echo "Total Company Holidays: " . $stmt->fetchColumn() . "\n";
    
    // 5. Check Angela's Primary Company
    $stmt = $db->prepare("SELECT company_id FROM employee_companies WHERE employee_id = 301 AND is_primary = 1");
    $stmt->execute();
    $companyId = $stmt->fetchColumn();
    echo "Angela's Primary Company ID: " . ($companyId ?: 'NONE') . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
