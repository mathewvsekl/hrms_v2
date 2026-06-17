<?php
$db = new PDO('sqlite:database.sqlite');
$db->exec('ALTER TABLE tax_slabs ADD COLUMN personal_relief DECIMAL(10,2) DEFAULT 0;');
echo "Done";
