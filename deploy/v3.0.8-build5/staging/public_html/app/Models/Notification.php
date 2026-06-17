<?php

namespace App\Models;

class Notification
{
    private $db;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
    }

    public function create($userId, $type, $title, $message, $data = null)
    {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, type, title, message, data)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $userId,
            $type,
            $title,
            $message,
            $data ? json_encode($data) : null
        ]);
    }

    public function getByUserId($userId, $limit = 50, $offset = 0)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at_utc DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $userId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Fetch all notifications (for Admin/HR)
     */
    public function getAll($limit = 50, $offset = 0, $excludeSuperAdmin = false)
    {
        $query = "
            SELECT n.*, u.username as recipient_name 
            FROM notifications n
            JOIN users u ON n.user_id = u.id
            WHERE 1=1
        ";

        if ($excludeSuperAdmin) {
            $query .= " AND NOT EXISTS (
                SELECT 1 FROM user_roles ur2 
                WHERE ur2.user_id = u.id AND ur2.role_id = 1
            )";
        }

        $query .= " ORDER BY n.created_at_utc DESC LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }


    public function getUnreadCount($userId)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get global unread count (for Admin/HR)
     */
    public function getAllUnreadCount($excludeSuperAdmin = false)
    {
        $query = "SELECT COUNT(*) FROM notifications n JOIN users u ON n.user_id = u.id WHERE n.is_read = 0";
        if ($excludeSuperAdmin) {
            $query .= " AND NOT EXISTS (
                SELECT 1 FROM user_roles ur2 
                WHERE ur2.user_id = u.id AND ur2.role_id = 1
            )";
        }
        $stmt = $this->db->query($query);
        return (int)$stmt->fetchColumn();
    }


    public function markRead($id, $userId, $bypassCheck = false)
    {
        $query = "UPDATE notifications SET is_read = 1, read_at_utc = CURRENT_TIMESTAMP WHERE id = ?";
        $params = [$id];

        if (!$bypassCheck) {
            $query .= " AND user_id = ?";
            $params[] = $userId;
        }

        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    public function markAllRead($userId, $bypassCheck = false)
    {
        $query = "UPDATE notifications SET is_read = 1, read_at_utc = CURRENT_TIMESTAMP WHERE is_read = 0";
        $params = [];

        if (!$bypassCheck) {
            $query .= " AND user_id = ?";
            $params[] = $userId;
        }

        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    public function delete($id, $userId, $bypassCheck = false)
    {
        $query = "DELETE FROM notifications WHERE id = ?";
        $params = [$id];

        if (!$bypassCheck) {
            $query .= " AND user_id = ?";
            $params[] = $userId;
        }

        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }
}
