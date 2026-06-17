<?php
define('BASE_PATH', __DIR__);
define('ROOT_PATH', __DIR__);
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $sql = "CREATE TABLE IF NOT EXISTS `payslips` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `employee_id` int(11) NOT NULL,
      `month` int(2) NOT NULL,
      `year` int(4) NOT NULL,
      `file_path` varchar(255) NOT NULL,
      `uploaded_by` int(11) NULL,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $db->exec($sql);
    echo "Payslips table created successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
