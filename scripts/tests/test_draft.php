<?php
require 'app/Core/Database.php';
require 'app/Helpers/NotificationHelper.php';
require 'app/Services/LeaveService.php';

$db = Database::getInstance()->getConnection();
$leaveService = new \App\Services\LeaveService();
$count = $leaveService->generateSystemDraftLeaves();

echo "Generated drafts: $count\n";
