<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=hrms_v2', 'root', '');
$pdo->exec("ALTER TABLE assets ADD COLUMN base_currency_cost DECIMAL(10,2) NULL AFTER purchase_cost");
$pdo->exec("ALTER TABLE asset_allocations ADD COLUMN attachment VARCHAR(255) NULL AFTER remarks");
echo "Done";
