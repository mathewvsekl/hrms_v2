<?php
require 'config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT ts.*, pc.name as component_name FROM tax_slabs ts LEFT JOIN payroll_components pc ON ts.component_id = pc.id WHERE 1=1 ORDER BY ts.component_id ASC, ts.min_amount ASC");
$stmt->execute();
$slabs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
echo json_encode(['status' => 'success', 'data' => $slabs]);
