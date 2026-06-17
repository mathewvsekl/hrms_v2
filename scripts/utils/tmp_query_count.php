<?php
require 'config/database.php';
$db = Database::getInstance()->getConnection();

$whereClause = "WHERE 1=1 AND e.status NOT IN ('onboarding', 'pending_approval') AND NOT EXISTS (
                    SELECT 1 FROM user_roles ur2 
                    WHERE ur2.user_id = u.id AND ur2.role_id = 1
                )";

$sql = "
    SELECT COUNT(DISTINCT e.id) 
    FROM employees e
    LEFT JOIN users u ON e.id = u.employee_id
    $whereClause
";

echo "Expected count from API: " . $db->query($sql)->fetchColumn() . "\n";
