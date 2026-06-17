<?php
require_once 'config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM designations LIMIT 1");
    $dg = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($dg) {
        foreach ($dg as $key => $val) {
            echo "Key: $key | Value: $val\n";
        }
    } else {
        echo "No designations found.\n";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
