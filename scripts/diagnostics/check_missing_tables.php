<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance()->getConnection();

// Get all tables
$stmt = $db->query("SHOW TABLES");
$dbTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Convert to lower case for comparison
$dbTablesLower = array_map('strtolower', $dbTables);

function searchForTables($dir, &$foundTables) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            searchForTables($path, $foundTables);
        } elseif (preg_match('/\.php$/', $file)) {
            $content = file_get_contents($path);
            
            // Regex to find table names in FROM, JOIN, INTO, UPDATE
            preg_match_all('/(?:FROM|JOIN|INTO|UPDATE)\s+`?([a-zA-Z0-9_]+)`?/i', $content, $matches);
            
            foreach ($matches[1] as $match) {
                $tableName = strtolower($match);
                // Exclude some common non-table keywords or aliases
                if (!in_array($tableName, ['user', 'admin', 'where', 'select', 'join', 'left', 'right', 'inner'])) {
                    $foundTables[$tableName] = true;
                }
            }
        }
    }
}

$foundTables = [];
searchForTables(__DIR__ . '/app', $foundTables);

$missingTables = [];
foreach (array_keys($foundTables) as $table) {
    if (!in_array($table, $dbTablesLower)) {
        $missingTables[] = $table;
    }
}

echo "Tables mentioned in code but missing from DB:\n";
print_r($missingTables);

// Also compare with DATABASE_SCHEMA.sql
$schema = file_get_contents(__DIR__ . '/DATABASE_SCHEMA.sql');
preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?`?([a-zA-Z0-9_]+)`?/i', $schema, $schemaMatches);
$schemaTables = array_map('strtolower', $schemaMatches[1]);

$missingFromSchema = [];
foreach ($dbTablesLower as $table) {
    if (!in_array($table, $schemaTables)) {
        $missingFromSchema[] = $table;
    }
}

echo "\nTables in DB but missing from DATABASE_SCHEMA.sql:\n";
print_r($missingFromSchema);

