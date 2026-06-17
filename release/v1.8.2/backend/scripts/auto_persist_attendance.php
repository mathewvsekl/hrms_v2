<?php
/**
 * Standalone script for Attendance Auto-Persistence.
 * This should be scheduled to run at the end of every day (e.g., 23:55).
 * 
 * Usage: php scripts/auto_persist_attendance.php [YYYY-MM-DD]
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Core/Controller.php';
require_once __DIR__ . '/../app/Controllers/AttendanceController.php';

use App\Controllers\AttendanceController;

$date = $argv[1] ?? date('Y-m-d');

echo "--- Attendance Auto-Persistence Started for $date ---\n";

$attendance = new AttendanceController();
$result = $attendance->autoPersistDefaults(['date' => $date]);

$data = json_decode($result->getContent(), true);

if ($result->getStatusCode() === 200) {
    echo "SUCCESS: " . ($data['message'] ?? 'Done') . "\n";
} else {
    echo "ERROR: " . ($data['error'] ?? 'Unknown error') . "\n";
}

echo "--- Process Finished at " . date('Y-m-d H:i:s') . " ---\n";
