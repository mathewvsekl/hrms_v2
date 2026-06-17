<?php
try {
    $db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8mb4', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Update all existing records
    $updateQuery = "
        UPDATE users u
        INNER JOIN employees e ON u.employee_id = e.id
        SET u.username = e.email
        WHERE e.email IS NOT NULL AND e.email != '' AND u.username COLLATE utf8mb4_general_ci != e.email COLLATE utf8mb4_general_ci
    ";
    $affected = $db->exec($updateQuery);
    echo "Updated $affected existing users.\n";

    // 2. Drop existing triggers
    $db->exec("DROP TRIGGER IF EXISTS sync_email_to_username_update");

    // 3. Create trigger
    $triggerUpdate = "
        CREATE TRIGGER sync_email_to_username_update
        AFTER UPDATE ON employees
        FOR EACH ROW
        BEGIN
            IF NEW.email COLLATE utf8mb4_general_ci != OLD.email COLLATE utf8mb4_general_ci AND NEW.email IS NOT NULL AND NEW.email != '' THEN
                UPDATE users SET username = NEW.email WHERE employee_id = NEW.id;
            END IF;
        END;
    ";
    $db->exec($triggerUpdate);
    echo "Successfully created triggers.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
