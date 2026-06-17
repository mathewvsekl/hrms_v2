<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/ProxyPDO.php';

try {
    $pdo = new ProxyPDO('https://hrms.anedins.com/db_proxy.php', 'HRMS_LOCAL_DEV_SECURE_TOKEN_55');

    $tablesStmt = $pdo->query("SHOW TABLES");
    $tables = $tablesStmt->fetchAll(\PDO::FETCH_COLUMN);

    $schema = [];
    foreach ($tables as $table) {
        $descStmt = $pdo->query("DESCRIBE `$table`");
        $columns = $descStmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $schema[$table] = $columns;
    }

    file_put_contents('live_schema_dump.json', json_encode($schema, JSON_PRETTY_PRINT));
    echo "Schema dumped successfully to live_schema_dump.json";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
