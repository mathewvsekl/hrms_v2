<?php
require_once 'config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Update Test Holiday to UAE (Country 1)
    $stmt = $db->prepare("UPDATE public_holidays SET country_id = 1 WHERE name = 'Test Holiday'");
    $stmt->execute();
    echo "Updated Test Holiday to country_id 1.\n";
    
    // 2. Insert Company Holiday for Company 1
    $stmt = $db->prepare("DELETE FROM holidays WHERE company_id = 1 AND holiday_date = '2026-03-25'");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO holidays (company_id, holiday_date, name) VALUES (1, '2026-03-25', 'Company Fun Day')");
    $stmt->execute();
    echo "Inserted Company Holiday for March 25.\n";
    
    // 3. Insert Weekly Schedule for Company 1 (Sat/Sun Off)
    $stmt = $db->prepare("DELETE FROM office_weekly_schedules WHERE company_id = 1");
    $stmt->execute();
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    foreach ($days as $day) {
        $status = ($day === 'Saturday' || $day === 'Sunday') ? 'Off' : 'Work';
        $stmt = $db->prepare("INSERT INTO office_weekly_schedules (company_id, day_of_week, status) VALUES (1, :day, :status)");
        $stmt->execute(['day' => $day, 'status' => $status]);
    }
    echo "Inserted Weekly Schedule (Sat/Sun Off).\n";

    echo "Data setup complete.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
