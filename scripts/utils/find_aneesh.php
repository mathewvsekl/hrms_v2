<?php
// Simple DB connection for diagnostics
try {
    $db = new PDO('mysql:host=localhost;dbname=hrms_v2', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "--- ANEESH MATHEW CONTEXT ---\n";
    $stmt = $db->query("
        SELECT u.id, u.username, r.name as role_name 
        FROM users u 
        LEFT JOIN user_roles ur ON u.id = ur.user_id 
        LEFT JOIN roles r ON ur.role_id = r.id 
        WHERE u.username LIKE '%aneesh%' 
        OR u.username LIKE '%mathew%'
    ");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage();
}
?>
