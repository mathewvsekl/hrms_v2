<?php
require __DIR__ . '/backend/config/database.php';
require __DIR__ . '/backend/app/Helpers/StringNormalizer.php';

use App\Helpers\StringNormalizer;

try {
    $db = Database::getInstance()->getConnection();
    
    // Begin transaction for safety
    $db->beginTransaction();
    echo "Starting Database Normalization...\n";

    // 1. Add normalized_name column to roles table if it doesn't exist
    $stmt = $db->query("SHOW COLUMNS FROM roles LIKE 'normalized_name'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE roles ADD COLUMN normalized_name VARCHAR(100) AFTER name");
        echo "- Added 'normalized_name' column to 'roles' table.\n";
    }

    // 2. Fetch all roles and backfill the normalized name
    $stmt = $db->query("SELECT id, name FROM roles");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updateStmt = $db->prepare("UPDATE roles SET normalized_name = :norm WHERE id = :id");
    
    foreach ($roles as $role) {
        $normalized = StringNormalizer::normalizeRole($role['name']);
        $updateStmt->execute([
            'norm' => $normalized,
            'id' => $role['id']
        ]);
        echo "- Standardized Role ID {$role['id']} ('{$role['name']}') -> '$normalized'\n";
    }

    // 3. Update active sessions in memory/DB if they store raw roles (Skipping complex session parsing for now)

    $db->commit();
    echo "Migration completed successfully.\n";

} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    echo "Migration failed: " . $e->getMessage() . "\n";
}
