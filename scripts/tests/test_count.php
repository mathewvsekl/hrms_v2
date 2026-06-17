<?php
require 'app/Core/Env.php';
\App\Core\Env::load(__DIR__ . '/.env');
require 'config/database.php';
$db = Database::getInstance()->getConnection();
echo $db->query('SELECT COUNT(*) FROM payroll_records')->fetchColumn();
