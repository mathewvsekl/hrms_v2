<?php
require_once 'config/database.php';
$db = \Database::getInstance()->getConnection();

$tables = [];
$stmt = $db->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

foreach ($tables as $table) {
    if (strpos($table, 'appraisal') !== false || $table === 'template_questions') {
        $stmt = $db->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        echo $row[1] . ";\n\n";
    }
}
