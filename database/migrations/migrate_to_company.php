<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    // MySQL DDL statements cause implicit commits, so we don't use transactions here.

    function tableExists($db, $table)
    {
        try {
            $db->query("SELECT 1 FROM $table LIMIT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    function columnExists($db, $table, $column)
    {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM $table LIKE '$column'");
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    // 1. Rename core tables
    if (tableExists($db, 'offices') && !tableExists($db, 'companies')) {
        echo "Renaming offices to companies...\n";
        $db->exec("RENAME TABLE offices TO companies");
    }
    if (tableExists($db, 'office_custom_fields') && !tableExists($db, 'company_custom_fields')) {
        echo "Renaming office_custom_fields to company_custom_fields...\n";
        $db->exec("RENAME TABLE office_custom_fields TO company_custom_fields");
    }
    if (tableExists($db, 'office_leave_policies') && !tableExists($db, 'company_leave_policies')) {
        echo "Renaming office_leave_policies to company_leave_policies...\n";
        $db->exec("RENAME TABLE office_leave_policies TO company_leave_policies");
    }

    // 2. Rename columns
    $tablesToUpdate = [
        'departments' => 'office_id',
        'company_custom_fields' => 'office_id',
        'company_leave_policies' => 'office_id',
        'payroll_runs' => 'office_id',
        'attendance_policies' => 'office_id',
        'holidays' => 'office_id'
    ];

    foreach ($tablesToUpdate as $table => $oldCol) {
        if (tableExists($db, $table) && columnExists($db, $table, $oldCol)) {
            echo "Updating $table: renaming $oldCol to company_id...\n";
            $db->exec("ALTER TABLE $table RENAME COLUMN $oldCol TO company_id");
        }
    }

    // 3. Create join table
    if (!tableExists($db, 'employee_companies')) {
        echo "Creating employee_companies table...\n";
        $db->exec("CREATE TABLE employee_companies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            company_id INT NOT NULL,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // 4. Migrate data
    if (columnExists($db, 'employees', 'office_id')) {
        echo "Migrating data to employee_companies...\n";
        $db->exec("INSERT INTO employee_companies (employee_id, company_id) SELECT id, office_id FROM employees WHERE office_id IS NOT NULL");
    }

    // 5. Remove single office link
    if (columnExists($db, 'employees', 'office_id')) {
        echo "Dropping foreign key employees_ibfk_1...\n";
        try {
            $db->exec("ALTER TABLE employees DROP FOREIGN KEY employees_ibfk_1");
        } catch (Exception $e) {
            echo "Warning: Could not drop FK (might already be gone or different name): " . $e->getMessage() . "\n";
        }
        echo "Dropping office_id column from employees...\n";
        $db->exec("ALTER TABLE employees DROP COLUMN office_id");
    }

    echo "Migration successful: Office -> Company transition complete.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
