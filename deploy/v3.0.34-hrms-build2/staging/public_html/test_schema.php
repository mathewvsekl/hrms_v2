<?php
require __DIR__ . '/../vendor/autoload.php';
$db = \App\Config\Database::getConnection();
try {
    $stmt = $db->prepare("SHOW COLUMNS FROM employee_appraisals WHERE Field = 'status'");
    $stmt->execute();
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    echo json_encode($result);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
