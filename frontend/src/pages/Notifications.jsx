import React, { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { 
  Bell, 
  CheckCircle2, 
  Clock, 
  Trash2, 
  ArrowLeft,
  Filter,
  MoreVertical
} from 'lucide-react';
import useNotificationStore from '../store/useNotificationStore';
import useLayoutStore from '../store/useLayoutStore';
import { formatDateTime } from '../utils/dateUtils';
import './Notifications.css';

const NotificationsPage = () => {
  const { 
    notifications, 
    unreadCount, 
    isAdminView,
    fetchNotifications, 
    markAsRead, 
    markAllAsRead, 
    clearNotification,
    loading 
  } = useNotificationStore();
  const navigate = useNavigate();
  const { setPageTitle, setPageSubtitle, setBackPath, resetPageHeader } = useLayoutStore();

  useEffect(() => {
    setPageTitle("Notifications");
    setPageSubtitle(`${unreadCount} unread alerts`);
    setBackPath('/dashboard');
    return () => resetPageHeader();
  }, [unreadCount]);

  useEffect(() => {
    fetchNotifications();
  }, [fetchNotifications]);

  const handleNotificationClick = async (notification) => {
    if (!notification.is_read) {
      await markAsRead(notification.id);
    }
    
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

  const formatDateFull = (dateStr) => {
    return formatDateTime(dateStr);
  };

  return (
    <div className="notifications-page">
      <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: '24px' }}>
        <button className="secondary-btn" onClick={markAllAsRead}>
          <CheckCircle2 size={16} /> Mark all as read
        </button>
      </div>

      <div className="content-container">
        <div className="filters-bar">
          <div className="filter-item active">All</div>
          <div className="filter-item">Unread</div>
          <div className="filter-item">Leave</div>
          <div className="filter-item">Appraisal</div>
        </div>

        <div className="notifications-list-extensive">
          {loading && (!notifications || notifications.length === 0) ? (
            <div className="loading-state">Loading notifications...</div>
          ) : (Array.isArray(notifications) && notifications.length > 0) ? (
            notifications.map((n) => (
              <div 
                key={n.id} 
                className={`extensive-item ${!n.is_read ? 'unread' : ''}`}
                onClick={() => handleNotificationClick(n)}
              >
                <div className="item-icon">
                  <div className={`icon-circle ${n.type}`}>
                    <Bell size={20} />
                  </div>
                </div>
                <div className="item-body">
                  <div className="item-top">
                    <span className="item-type">{n.type.replace('_', ' ')}</span>
                    <span className="item-date">
                      <Clock size={12} />
                      {formatDateFull(n.created_at_utc)}
                    </span>
                  </div>
                  <h3 className="item-title">{n.title}</h3>
                  <p className="item-message">{n.message}</p>
                  {isAdminView && n.recipient_name && (
                    <div className="item-recipient" style={{ 
                      marginTop: '8px', 
                      fontSize: '0.7rem', 
                      fontWeight: '700', 
                      color: 'var(--color-rose-gold)',
                      textTransform: 'uppercase'
                    }}>
                      Recipient: {n.recipient_name}
                    </div>
                  )}
                </div>
                <div className="item-actions">
                  <button 
                    className="action-btn delete"
                    onClick={(e) => { e.stopPropagation(); clearNotification(n.id); }}
                    title="Delete"
                  >
                    <Trash2 size={18} />
                  </button>
                </div>
              </div>
            ))
          ) : (
            <div className="empty-state">
              <div className="empty-icon">
                <Bell size={48} />
              </div>
              <h3>No notifications yet</h3>
              <p>When you have alerts, they will appear here.</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default NotificationsPage;
