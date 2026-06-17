<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Notification;

class NotificationController extends Controller
{
    private $model;

    public function __construct()
    {
        $this->model = new Notification();
    }

    /**
     * Fetch user notifications (or all if Admin)
     */
    public function index($requestData)
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $this->jsonResponse(null, 401, 'Unauthorized');
        }

        $isAdmin = $this->hasAdminPrivileges();
        $limit = (int)($requestData['limit'] ?? 50);
        $offset = (int)($requestData['offset'] ?? 0);

        if ($isAdmin) {
            $excludeSuperAdmin = !$this->isSuperAdmin();
            $notifications = $this->model->getAll($limit, $offset, $excludeSuperAdmin);
            $unreadCount = $this->model->getAllUnreadCount($excludeSuperAdmin);
        } else {
            $notifications = $this->model->getByUserId($userId, $limit, $offset);
            $unreadCount = $this->model->getUnreadCount($userId);
        }


        return $this->jsonResponse([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'is_admin_view' => $isAdmin
        ]);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead($requestData)
    {
        $userId = $_SESSION['user_id'] ?? null;
        $id = $requestData['id'] ?? null;
        
        if (!$userId || !$id) {
            return $this->jsonResponse(null, 400, 'Missing ID');
        }

        $isAdmin = $this->hasAdminPrivileges();
        $this->model->markRead($id, $userId, $isAdmin);
        return $this->jsonResponse(['message' => 'Notification marked as read']);
    }

    /**
     * Mark all as read
     */
    public function markAllAsRead()
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $this->jsonResponse(null, 401, 'Unauthorized');
        }

        $isAdmin = $this->hasAdminPrivileges();
        $this->model->markAllRead($userId, $isAdmin);
        return $this->jsonResponse(['message' => 'All notifications marked as read']);
    }

    /**
     * Delete a notification
     */
    public function delete($id)
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId || !$id) {
            return $this->jsonResponse(null, 400, 'Missing ID');
        }

        $isAdmin = $this->hasAdminPrivileges();
        $this->model->delete($id, $userId, $isAdmin);
        return $this->jsonResponse(['message' => 'Notification deleted']);
    }

    /**
     * Helper to check for HR Admin or Super Admin privileges
     */
    private function hasAdminPrivileges()
    {
        return $this->hasAnyRole(['SUPERADMIN', 'SUPER_ADMIN', 'ADMIN', 'HRMANAGER', 'HR_MANAGER']);
    }
}
