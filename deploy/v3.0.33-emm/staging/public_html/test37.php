<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8', 'root', '');
$db->exec("ALTER TABLE template_questions ADD COLUMN description TEXT DEFAULT NULL");
echo "Added description column.";
