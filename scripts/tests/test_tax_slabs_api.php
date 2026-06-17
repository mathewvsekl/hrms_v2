<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/tax-slabs?company_id=1';
$_GET['company_id'] = 1;
$_SESSION['user_id'] = 2; // mimic login
require 'index.php';
