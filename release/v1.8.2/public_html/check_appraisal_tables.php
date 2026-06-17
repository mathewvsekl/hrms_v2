<?php
require_once 'config/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$tables = ['appraisal_cycles', 'employee_appraisals', 'appraisal_approvals'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "$table exists\n";
    } else {
        echo "$table NOT exists\n";
    }
}
$conn->close();
?>
