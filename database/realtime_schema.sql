-- Real-time Notification System Database Schema
-- Additional tables for TaskMaster real-time features

-- Table for tracking real-time task updates
CREATE TABLE IF NOT EXISTS task_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id VARCHAR(50) NOT NULL,
    updated_by VARCHAR(255) NOT NULL,
    update_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_task_updates_task_id (task_id),
    INDEX idx_task_updates_created_at (created_at)
);

-- Table for comments system
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id VARCHAR(50) NOT NULL,
    parent_id INT NULL, -- For threaded comments
    author_id VARCHAR(255) NOT NULL,
    author_name VARCHAR(255) NOT NULL,
    author_email VARCHAR(255) NOT NULL,
    author_avatar TEXT NULL,
    content TEXT NOT NULL,
    mentions JSON NULL, -- Store mentioned users
    attachments JSON NULL, -- Store file attachments
    reactions JSON NULL, -- Store reactions/emojis
    is_edited BOOLEAN DEFAULT FALSE,
    edited_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_comments_task_id (task_id),
    INDEX idx_comments_author_id (author_id),
    INDEX idx_comments_created_at (created_at),
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
);

-- Table for real-time notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('mention', 'comment', 'reply', 'task_update', 'assignment', 'deadline') NOT NULL,
    recipient_id VARCHAR(255) NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    sender_id VARCHAR(255) NOT NULL,
    sender_name VARCHAR(255) NOT NULL,
    sender_email VARCHAR(255) NOT NULL,
    task_id VARCHAR(50) NULL,
    comment_id INT NULL,
    title VARCHAR(500) NOT NULL,
    message TEXT NOT NULL,
    action_url VARCHAR(1000) NULL,
    is_read BOOLEAN DEFAULT FALSE,
    is_email_sent BOOLEAN DEFAULT FALSE,
    is_push_sent BOOLEAN DEFAULT FALSE,
    metadata JSON NULL, -- Additional notification data
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    INDEX idx_notifications_recipient (recipient_id),
    INDEX idx_notifications_type (type),
    INDEX idx_notifications_created_at (created_at),
    INDEX idx_notifications_unread (recipient_id, is_read),
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
);

-- Table for user presence tracking
CREATE TABLE IF NOT EXISTS user_presence (
    user_id VARCHAR(255) PRIMARY KEY,
    user_email VARCHAR(255) NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    status ENUM('online', 'away', 'offline') DEFAULT 'offline',
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    current_task_id VARCHAR(50) NULL,
    is_typing BOOLEAN DEFAULT FALSE,
    typing_task_id VARCHAR(50) NULL,
    connection_id VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_presence_status (status),
    INDEX idx_user_presence_last_seen (last_seen),
    INDEX idx_user_presence_current_task (current_task_id)
);

-- Table for notification preferences
CREATE TABLE IF NOT EXISTS notification_preferences (
    user_id VARCHAR(255) PRIMARY KEY,
    user_email VARCHAR(255) NOT NULL,
    email_notifications BOOLEAN DEFAULT TRUE,
    push_notifications BOOLEAN DEFAULT TRUE,
    mention_notifications BOOLEAN DEFAULT TRUE,
    comment_notifications BOOLEAN DEFAULT TRUE,
    task_update_notifications BOOLEAN DEFAULT TRUE,
    deadline_notifications BOOLEAN DEFAULT TRUE,
    digest_frequency ENUM('immediate', 'hourly', 'daily', 'weekly', 'disabled') DEFAULT 'immediate',
    quiet_hours_start TIME NULL,
    quiet_hours_end TIME NULL,
    timezone VARCHAR(50) DEFAULT 'UTC',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_notification_prefs_email (user_email)
);

-- Table for real-time collaboration conflicts
CREATE TABLE IF NOT EXISTS collaboration_conflicts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id VARCHAR(50) NOT NULL,
    conflict_type ENUM('simultaneous_edit', 'version_mismatch', 'concurrent_update') NOT NULL,
    user1_id VARCHAR(255) NOT NULL,
    user2_id VARCHAR(255) NOT NULL,
    field_name VARCHAR(100) NULL,
    original_value TEXT NULL,
    user1_value TEXT NULL,
    user2_value TEXT NULL,
    resolved_value TEXT NULL,
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_by VARCHAR(255) NULL,
    resolution_method VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    INDEX idx_conflicts_task_id (task_id),
    INDEX idx_conflicts_status (is_resolved),
    INDEX idx_conflicts_created_at (created_at)
);

-- Table for file attachments in comments
CREATE TABLE IF NOT EXISTS comment_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comment_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(1000) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    uploaded_by VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attachments_comment_id (comment_id),
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
);

-- Table for notification delivery tracking
CREATE TABLE IF NOT EXISTS notification_delivery_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    delivery_method ENUM('email', 'push', 'websocket', 'sms') NOT NULL,
    delivery_status ENUM('pending', 'sent', 'delivered', 'failed', 'bounced') NOT NULL,
    delivery_provider VARCHAR(100) NULL, -- e.g., 'sendgrid', 'firebase', 'twilio'
    external_id VARCHAR(255) NULL, -- Provider's message ID
    error_message TEXT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivered_at TIMESTAMP NULL,
    INDEX idx_delivery_log_notification (notification_id),
    INDEX idx_delivery_log_status (delivery_status),
    INDEX idx_delivery_log_method (delivery_method),
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE
);

-- Table for real-time event streaming
CREATE TABLE IF NOT EXISTS realtime_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    task_id VARCHAR(50) NULL,
    user_id VARCHAR(255) NOT NULL,
    room_id VARCHAR(255) NULL,
    event_data JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL, -- For cleanup of old events
    INDEX idx_realtime_events_type (event_type),
    INDEX idx_realtime_events_task_id (task_id),
    INDEX idx_realtime_events_user_id (user_id),
    INDEX idx_realtime_events_created_at (created_at),
    INDEX idx_realtime_events_expires_at (expires_at)
);

-- Views for common queries

-- Unread notifications count per user
CREATE OR REPLACE VIEW user_unread_notifications AS
SELECT
    recipient_id,
    recipient_email,
    COUNT(*) as unread_count,
    MAX(created_at) as latest_notification
FROM notifications
WHERE is_read = FALSE
GROUP BY recipient_id, recipient_email;

-- Recent task activity view
CREATE OR REPLACE VIEW recent_task_activity AS
SELECT
    t.task_id,
    t.updated_by,
    t.created_at as update_time,
    'task_update' as activity_type,
    JSON_EXTRACT(t.update_data, '$.action_item') as activity_summary
FROM task_updates t
UNION ALL
SELECT
    c.task_id,
    c.author_id as updated_by,
    c.created_at as update_time,
    'comment' as activity_type,
    LEFT(c.content, 100) as activity_summary
FROM comments c
ORDER BY update_time DESC;

-- Online users view
CREATE OR REPLACE VIEW online_users AS
SELECT
    user_id,
    user_email,
    user_name,
    status,
    last_seen,
    current_task_id,
    is_typing,
    typing_task_id
FROM user_presence
WHERE status IN ('online', 'away')
AND last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE);

-- Stored procedures for common operations

DELIMITER //

-- Mark notifications as read
CREATE PROCEDURE MarkNotificationsAsRead(
    IN p_recipient_id VARCHAR(255),
    IN p_notification_ids TEXT
)
BEGIN
    SET @sql = CONCAT('UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE recipient_id = "', p_recipient_id, '" AND id IN (', p_notification_ids, ')');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END//

-- Get user notification digest
CREATE PROCEDURE GetUserNotificationDigest(
    IN p_user_id VARCHAR(255),
    IN p_limit INT DEFAULT 20,
    IN p_offset INT DEFAULT 0
)
BEGIN
    SELECT
        n.*,
        CASE
            WHEN n.comment_id IS NOT NULL THEN c.content
            ELSE NULL
        END as comment_content
    FROM notifications n
    LEFT JOIN comments c ON n.comment_id = c.id
    WHERE n.recipient_id = p_user_id
    ORDER BY n.created_at DESC
    LIMIT p_limit OFFSET p_offset;
END//

-- Update user presence
CREATE PROCEDURE UpdateUserPresence(
    IN p_user_id VARCHAR(255),
    IN p_user_email VARCHAR(255),
    IN p_user_name VARCHAR(255),
    IN p_status ENUM('online', 'away', 'offline'),
    IN p_current_task_id VARCHAR(50)
)
BEGIN
    INSERT INTO user_presence (user_id, user_email, user_name, status, current_task_id, last_seen)
    VALUES (p_user_id, p_user_email, p_user_name, p_status, p_current_task_id, NOW())
    ON DUPLICATE KEY UPDATE
        status = p_status,
        current_task_id = p_current_task_id,
        last_seen = NOW(),
        updated_at = NOW();
END//

DELIMITER ;

-- Cleanup old events (recommended to run via cron)
-- DELETE FROM realtime_events WHERE expires_at < NOW();
-- DELETE FROM task_updates WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Indexes for performance optimization
CREATE INDEX idx_notifications_composite ON notifications(recipient_id, is_read, created_at);
CREATE INDEX idx_comments_task_created ON comments(task_id, created_at);
CREATE INDEX idx_realtime_events_composite ON realtime_events(event_type, task_id, created_at);

-- Initial notification preferences for existing users
INSERT IGNORE INTO notification_preferences (user_id, user_email)
SELECT DISTINCT current_owners as user_id, current_owners as user_email
FROM luv_task_master_data
WHERE current_owners IS NOT NULL AND current_owners != '';