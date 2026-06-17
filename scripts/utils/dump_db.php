<?php
require_once 'config/database.php';
$db = \Database::getInstance()->getConnection();

function dumpTable($db, $table) {
    echo "--- TABLE: $table ---\n";
    $stmt = $db->query("SELECT * FROM $table");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        print_r($row);
    }
    echo "\n";
}

ob_start();
dumpTable($db, 'leave_types');
dumpTable($db, 'office_attendance_status_definitions');
$output = ob_get_clean();
file_put_contents('db_dump.txt', $output);
echo "Dumped to db_dump.txt\n";
