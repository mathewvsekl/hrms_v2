<?php

namespace App\Helpers;

use App\Models\Notification;
use App\Helpers\MailHelper;

class NotificationHelper
{
    /**
     * Send a notification to a specific user
     * 
     * @param int $userId
     * @param string $type
     * @param string $title
     * @param string $message
     * @param mixed $data
     * @param bool $emailNotify Whether to also send an email
     */
    public static function send($userId, $type, $title, $message, $data = null, $emailNotify = false)
    {
        $model = new Notification();
        $saved = $model->create($userId, $type, $title, $message, $data);

        if ($saved && $emailNotify) {
            self::sendEmailToUser($userId, $title, $message);
        }

        return $saved;
    }

    /**
     * Broadcast a notification to all users with a specific role
     */
    public static function broadcast($roleName, $type, $title, $message, $data = null, $emailNotify = false)
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
            $saved = $model->create($userId, $type, $title, $message, $data);
            if ($saved && $emailNotify) {
                self::sendEmailToUser($userId, $title, $message);
            }
            $results[] = $saved;
        }

        return $results;
    }

    /**
     * Notify an employee's reporting manager
     */
    public static function notifyManager($employeeId, $type, $title, $message, $data = null, $emailNotify = false)
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
            return self::send($managerId, $type, $title, $message, $data, $emailNotify);
        }

        return false;
    }

    /**
     * Internal helper to fetch user email and trigger MailHelper
     */
    private static function sendEmailToUser($userId, $subject, $message)
    {
        $db = \Database::getInstance()->getConnection();
        
        // Get user's email from employees table
        $stmt = $db->prepare("
            SELECT e.email 
            FROM employees e
            JOIN users u ON e.id = u.employee_id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $email = $stmt->fetchColumn();

        if ($email) {
            return MailHelper::send($email, $subject, $message);
        }
        
        return false;
    }
}
