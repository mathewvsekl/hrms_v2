<?php
$db = new PDO('sqlite:database.sqlite');
$tables = ['departments', 'employees', 'companies', 'designations'];
foreach ($tables as $table) {
    $stmt = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $row['sql'] . "\n\n";
}
