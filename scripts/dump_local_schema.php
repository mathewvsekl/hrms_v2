<?php
require_once 'config/database.php';
$db = \Database::getInstance()->getConnection();

$tables = [];
$stmt = $db->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

$schema = "";
foreach ($tables as $table) {
    $stmt = $db->query("SHOW CREATE TABLE `$table`");
    $row = $stmt->fetch(PDO::FETCH_NUM);
    $schema .= $row[1] . ";\n\n";
}
file_put_contents('DATABASE_SCHEMA.sql', $schema);
echo "Local schema dumped to DATABASE_SCHEMA.sql";
