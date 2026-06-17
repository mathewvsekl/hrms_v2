<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Inspecting employee profile paths...\n";
    $stmt = $db->query("SELECT id, first_name, last_name, profile_image_path FROM employees WHERE profile_image_path IS NOT NULL");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($employees as $emp) {
        echo "ID: {$emp['id']} | Name: {$emp['first_name']} {$emp['last_name']} | Path: {$emp['profile_image_path']}\n";
        
        // If we find the 'uploads_' pattern, let's fix it
        if (strpos($emp['profile_image_path'], 'uploads_') !== false) {
            $newPath = str_replace('uploads_', 'uploads/', $emp['profile_image_path']);
            echo "  --> CORRUPTED PATH DETECTED. Suggesting fix to: $newPath\n";
            
            // Uncomment the line below to actually apply the fix
            // $db->prepare("UPDATE employees SET profile_image_path = ? WHERE id = ?")->execute([$newPath, $emp['id']]);
        }
    }
    
    echo "\nInspection complete.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
