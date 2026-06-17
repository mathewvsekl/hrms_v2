<?php
$host = '127.0.0.1';
$db   = 'hrms_v2';
$user = 'root';
$pass = '';

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$tables = [];
$result = $mysqli->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

$schema = "";
foreach ($tables as $table) {
    $res = $mysqli->query("SHOW CREATE TABLE `$table`");
    $row = $res->fetch_row();
    $schema .= $row[1] . ";\n\n";
}

file_put_contents('database/migrations/DATABASE_SCHEMA.sql', $schema);
echo "Schema dumped successfully to database/migrations/DATABASE_SCHEMA.sql.\n";
