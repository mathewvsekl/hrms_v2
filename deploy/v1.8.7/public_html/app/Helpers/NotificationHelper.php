<?php

namespace App\Helpers;

use App\Models\Notification;

class NotificationHelper
{
    /**
     * Send a notification to a specific user
     */
    public static function send($userId, $type, $title, $message, $data = null)
    {
        $model = new Notification();
        return $model->create($userId, $type, $title, $message, $data);
    }

    /**
     * Broadcast a notification to all users with a specific role
     */
    public static function broadcast($roleName, $type, $title, $message, $data = null)
    {
        $db = \Database::getInstance()->getConnection();
        
        // Find users with this role
        $stmt = $db->prepare("
            SELECT u.id 
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            WHERE r.name = ?
        ");
        $stmt->execute([$roleName]);
        $userIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $model = new Notification();
        $results = [];
        foreach ($userIds as $userId) {
            $results[] = $model->create($userId, $type, $title, $message, $data);
        }

        return $results;
    }

    /**
     * Notify an employee's reporting manager
     */
    public static function notifyManager($employeeId, $type, $title, $message, $data = null)
    {
        $db = \Database::getInstance()->getConnection();
        
        // Find manager's user ID
        $stmt = $db->prepare("
            SELECT u.id 
            FROM users u
            JOIN employees e ON u.employee_id = e.reporting_manager_id
            WHERE e.id = ?
        ");
        $stmt->execute([$employeeId]);
        $managerId = $stmt->fetchColumn();

        if ($managerId) {
            return self::send($managerId, $type, $title, $message, $data);
        }

        return false;
    }
}
