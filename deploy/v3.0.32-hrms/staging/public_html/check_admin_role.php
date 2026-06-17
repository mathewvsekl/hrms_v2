<?php
require_once __DIR__ . '/../app/Core/Autoloader.php'; \App\Core\Autoloader::register();
require_once __DIR__ . '/../config/database.php';

$db = \Database::getInstance()->getConnection();
$stmt = $db->query("
    SELECT u.id, u.username, u.employee_id, e.first_name, e.last_name, dg.title as designation
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    LEFT JOIN designations dg ON e.designation_id = dg.id
    WHERE 1=1
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$authService = new \App\Services\AuthService();

foreach ($users as $user) {
    echo "--- User: {$user['username']} ({$user['first_name']} {$user['last_name']}) ---\n";
    $role = $authService->getUserRole((int)$user['id']);
    echo "Role: {$role}\n";
    
    // Mimic the query from getUserPermissions to see EXACTLY what roles are fetched
    $stmt2 = $db->prepare("
        SELECT r.name as role_name, p.module, p.action 
        FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        LEFT JOIN role_permissions rp ON r.id = rp.role_id
        LEFT JOIN permissions p ON rp.permission_id = p.id
        WHERE ur.user_id = :user_id
    ");
    $stmt2->execute(['user_id' => $user['id']]);
    $userPrivileges = $stmt2->fetchAll(\PDO::FETCH_ASSOC);
    
    $roles = array_map('strtoupper', array_column($userPrivileges, 'role_name'));
    echo "Assigned Roles (from DB): " . implode(", ", $roles) . "\n";
    
    $isSuperAdmin = in_array('SUPERADMIN', $roles) || in_array('SUPER_ADMIN', $roles);
    $isAdmin = in_array('ADMIN', $roles);
    $isCountryManager = in_array('COUNTRYMANAGER', $roles) || in_array('COUNTRY_MANAGER', $roles);
    
    echo "isSuperAdmin: " . ($isSuperAdmin ? 'true' : 'false') . "\n";
    echo "isAdmin: " . ($isAdmin ? 'true' : 'false') . "\n";
    echo "isCountryManager: " . ($isCountryManager ? 'true' : 'false') . "\n";
    
    $perms = $authService->getUserPermissions((int)$user['id']);
    echo "Final Permissions Array:\n";
    print_r($perms);
    echo "\n\n";
}
