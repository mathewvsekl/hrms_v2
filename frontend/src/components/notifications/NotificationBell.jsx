import React, { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { Bell, Check, Trash2, MailOpen, Clock } from 'lucide-react';
import useNotificationStore from '../../store/useNotificationStore';
import { formatDateTime } from '../../utils/dateUtils';
import './NotificationBell.css';

const NotificationBell = () => {
  const { notifications, unreadCount, fetchNotifications, markAsRead, markAllAsRead, clearNotification } = useNotificationStore();
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef(null);
  const navigate = useNavigate();

  useEffect(() => {
    fetchNotifications();
    // Short polling every 60 seconds
    const interval = setInterval(fetchNotifications, 120000); // Poll every 2 minutes
    return () => clearInterval(interval);
  }, [fetchNotifications]);

  useEffect(() => {
    const handleClickOutside = (event) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleNotificationClick = async (notification) => {
    if (!notification.is_read) {
      await markAsRead(notification.id);
    }
    setIsOpen(false);
    
    // Redirect if link exists
    let data = {};
    try {
      data = typeof notification.data === 'string' ? JSON.parse(notification.data) : (notification.data || {});
    } catch (e) {
      console.warn('Failed to parse notification data');
    }

    if (data.link) {
      navigate(data.link);
    }
  };

  const formatDate = (dateStr) => {
    const date = new Date(dateStr + 'Z'); // Treat as UTC
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) return 'Just now';
    if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
    if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`;
    return formatDateTime(dateStr);
  };

  return (
    <div className="notification-bell-container" ref={dropdownRef}>
      <button 
        className={`notification-trigger ${isOpen ? 'active' : ''}`}
        onClick={() => setIsOpen(!isOpen)}
        aria-label="Notifications"
      >
        <Bell size={20} />
        {unreadCount > 0 && (
          <span className="notification-badge">
            {unreadCount > 9 ? '9+' : unreadCount}
          </span>
        )}
      </button>

      {isOpen && (
        <div className="notification-dropdown">
          <div className="notification-header">
            <h3>Notifications</h3>
            {unreadCount > 0 && (
              <button 
                className="mark-all-read"
                onClick={(e) => { e.stopPropagation(); markAllAsRead(); }}
              >
                Mark all as read
              </button>
            )}
          </div>

          <div className="notification-list">
            {notifications.length > 0 ? (
              notifications.map((n) => (
                <div 
                  key={n.id} 
                  className={`notification-item ${!n.is_read ? 'unread' : ''}`}
                  onClick={() => handleNotificationClick(n)}
                >
                  <div className="notification-icon-wrapper">
                    <div className={`notification-dot ${n.type}`}></div>
                  </div>
                  <div className="notification-content">
                    <div className="notification-title">{n.title}</div>
                    <div className="notification-message">{n.message}</div>
                    <div className="notification-time">
                      <Clock size={12} />
                      {formatDate(n.created_at_utc)}
                    </div>
                  </div>
                  <div className="notification-actions">
                     <button 
                        className="delete-notification"
                        onClick={(e) => { e.stopPropagation(); clearNotification(n.id); }}
                        title="Delete"
                      >
                        <Trash2 size={14} />
                      </button>
                  </div>
                </div>
              ))
            ) : (
              <div className="notification-empty">
                No notifications found
              </div>
            )}
          </div>

          <div className="notification-footer">
            <button onClick={() => { setIsOpen(false); navigate('/notifications'); }}>
              View all notifications
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default NotificationBell;
