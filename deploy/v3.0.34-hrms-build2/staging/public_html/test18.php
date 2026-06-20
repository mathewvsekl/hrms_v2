<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8', 'root', '');
$db->prepare("INSERT INTO appraisal_ratings (appraisal_id, question_id, employee_rating, manager_rating) VALUES (147, 157, 8, 7)")->execute();
echo "Inserted test soft skill rating.\n";
