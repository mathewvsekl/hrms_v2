<?php
require_once 'config/config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = file_get_contents('appraisal_refinements.sql');
// Split by semicolon and execute each statement
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
