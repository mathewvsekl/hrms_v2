<?php
require 'app/Core/Env.php';
\App\Core\Env::load(__DIR__ . '/.env');
require 'config/database.php';
$db = Database::getInstance()->getConnection();
$db->exec("ALTER TABLE salary_structures ADD COLUMN commissions DECIMAL(12,2) DEFAULT 0.00 AFTER base_salary");
$db->exec("ALTER TABLE salary_structures ADD COLUMN other_earnings DECIMAL(12,2) DEFAULT 0.00 AFTER commissions");
echo "Done";
