<?php require 'config/database.php'; $db = Database::getInstance()->getConnection(); print_r($db->query('SELECT name, color_code FROM leave_types')->fetchAll(PDO::FETCH_ASSOC));
