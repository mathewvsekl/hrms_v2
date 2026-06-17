<?php
require 'vendor/autoload.php';
$db = \App\Config\Database::getConnection();
try {
    $db->exec("ALTER TABLE employee_appraisals ADD COLUMN is_active TINYINT(1) DEFAULT 1");
    echo "Column added\n";
} catch (\PDOException $e) {
    echo "Already exists or error: " . $e->getMessage() . "\n";
}
