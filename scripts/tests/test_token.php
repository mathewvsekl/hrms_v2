<?php
require 'app/Core/Env.php';
\App\Core\Env::load(__DIR__ . '/.env');
require 'config/database.php';
$db = Database::getInstance()->getConnection();
echo $db->query("SELECT api_token FROM users WHERE username = 'aneesh.mathew@visionscientificafrica.com'")->fetchColumn();
