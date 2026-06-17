<?php
require_once 'config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM designations LIMIT 1");
    $dg = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Designation Columns: " . implode(", ", array_keys($dg)) . "\n";
    echo "Designation Data: " . json_encode($dg) . "\n";
} catch (Exception $e) {
    echo $e->getMessage();
}
