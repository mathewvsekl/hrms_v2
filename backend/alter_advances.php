<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2', 'root', '');
$db->exec('ALTER TABLE salary_advances 
    ADD COLUMN reason TEXT DEFAULT NULL, 
    ADD COLUMN reviewed_by INT DEFAULT NULL, 
    ADD COLUMN reviewed_at TIMESTAMP NULL DEFAULT NULL, 
    ADD COLUMN approved_by INT DEFAULT NULL, 
    ADD COLUMN approved_at TIMESTAMP NULL DEFAULT NULL');
echo "Schema updated.\n";
