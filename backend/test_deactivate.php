<?php
require __DIR__ . '/vendor/autoload.php';
$db = \App\Config\Database::getConnection();
try {
    $stmt = $db->prepare("UPDATE employee_appraisals SET status = 'withdrawn' WHERE status != 'finalized'");
    $stmt->execute();
    echo "Success";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
