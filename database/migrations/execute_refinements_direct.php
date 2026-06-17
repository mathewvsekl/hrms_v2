<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'hrms_v2');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = file_get_contents('appraisal_refinements.sql');
$statements = explode(';', $sql);
foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if (!empty($stmt)) {
        if ($conn->query($stmt) === TRUE) {
            echo "Executed: " . substr($stmt, 0, 50) . "...\n";
        } else {
            echo "Error executing stmt: " . $conn->error . "\n";
        }
    }
}
$conn->close();
?>
