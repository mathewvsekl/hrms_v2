<?php
$token = 'YOUR_DB_TOKEN'; // We'll just run it internally
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8', 'root', '');
// Wait, we can test it directly!
$controller = new \App\Controllers\AppraisalTemplateController();
// oh we need to require autoload. Let's do an internal curl to localhost:8000
