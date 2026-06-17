<?php
/**
 * Test Script for HRMS V2 v1.8.7 - Leave Logic
 * 1. Multi-Type Submission
 * 2. Overlap Prevention
 * 3. Cancellation & Attendance Cleanup
 */

require_once 'config/database.php';
require_once 'app/Core/Controller.php';
require_once 'app/Helpers/NotificationHelper.php';
require_once 'app/Controllers/LeaveController.php';
require_once 'app/Controllers/AttendanceController.php';

use App\Controllers\LeaveController;

function log_test($msg, $success = true) {
    echo ($success ? "[PASS] " : "[FAIL] ") . $msg . "\n";
}

try {
    $leave = new LeaveController();
    $employeeId = 305; // Aneesh (Super Admin)

    echo "--- Testing Overlap Prevention ---\n";
    $overlapData = [
        'employee_id' => $employeeId,
        'segments' => [
            ['leave_type_id' => 1, 'start_date' => '2026-05-01', 'end_date' => '2026-05-05'],
            ['leave_type_id' => 2, 'start_date' => '2026-05-04', 'end_date' => '2026-05-10']
        ]
    ];
    $res = $leave->submitRequest($overlapData);
    $data = json_decode(json_encode($res->getData()), true);
    
    if (strpos($data['message'] ?? '', 'Overlap') !== false) {
        log_test("Internal overlap blocked within the same request.");
    } else {
        log_test("Internal overlap was NOT blocked correctly.", false);
    }

    echo "\n--- Testing Multi-Type Success ---\n";
    $validData = [
        'employee_id' => $employeeId,
        'segments' => [
            ['leave_type_id' => 1, 'start_date' => '2026-06-01', 'end_date' => '2026-06-02'],
            ['leave_type_id' => 2, 'start_date' => '2026-06-03', 'end_date' => '2026-06-04']
        ]
    ];
    $res = $leave->submitRequest($validData);
    $data = json_decode(json_encode($res->getData()), true);
    
    if (isset($data['results']) && count($data['results']) == 2) {
        log_test("Multi-segment request created successfully.");
        $requestId = $data['results'][0]['id'];
        
        echo "\n--- Testing Cancellation & Attendance Purge ---\n";
        // Manual approve logic test skip (requires many mocks), testing adminCancel directly
        $cancelRes = $leave->adminCancel(['id' => $requestId, 'comment' => 'Test Cancellation']);
        $cancelData = json_decode(json_encode($cancelRes->getData()), true);
        
        if (strpos($cancelData['message'], 'cancelled') !== false) {
            log_test("Admin cancellation logic executed without error.");
        } else {
            log_test("Admin cancellation failed: " . ($cancelData['message'] ?? 'Unknown error'), false);
        }
    } else {
        log_test("Multi-segment request failed: " . ($data['message'] ?? 'Unknown error'), false);
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
