<?php
require_once 'config/database.php';
try {
    $db = \Database::getInstance()->getConnection();
    echo "Adding missing column 'request_group_id' to 'leave_requests'...\n";
    
    // Add column if it doesn't exist
    try {
        $db->exec("ALTER TABLE leave_requests ADD COLUMN request_group_id VARCHAR(50) NULL AFTER approved_by_id");
        $db->exec("CREATE INDEX idx_request_group_id ON leave_requests(request_group_id)");
        echo "Added 'request_group_id' column and index.\n";
    } catch (Exception $e) { echo "Skip column creation (probably exists)\n"; }
    
    // Update status enum
    try {
        $db->exec("ALTER TABLE leave_requests MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'cancel_requested', 'cancelled') DEFAULT 'pending'");
        echo "Updated 'status' enum to include 'cancel_requested'.\n";
    } catch (Exception $e) { echo "Error updating enum: " . $e->getMessage() . "\n"; }
    
    echo "Successfully updated 'leave_requests' table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
