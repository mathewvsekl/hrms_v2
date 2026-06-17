<?php
require 'config/database.php';
$db = Database::getInstance()->getConnection();

try {
    $db->exec("ALTER TABLE tax_slabs ADD COLUMN component_id INT NULL DEFAULT NULL AFTER id");
    $db->exec("ALTER TABLE tax_slabs ADD CONSTRAINT fk_tax_slabs_comp FOREIGN KEY (component_id) REFERENCES payroll_components(id) ON DELETE CASCADE");
    echo "Added component_id to tax_slabs successfully\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
