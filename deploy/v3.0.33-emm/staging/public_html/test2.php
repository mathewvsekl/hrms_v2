<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
    $db->exec("ALTER TABLE employee_appraisals MODIFY COLUMN status ENUM('draft', 'manager_review', 'hr_review', 'l1_review', 'l2_review', 'l3_review', 'hr_calibration', 'finalized', 'withdrawn', 'rejected') DEFAULT 'draft'");
    echo "SUCCESS";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
