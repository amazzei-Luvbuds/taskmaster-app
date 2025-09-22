-- TaskMaster Comments System Database Schema
-- Creates tables for comments, mentions, reactions, and attachments

-- Comments table - Main comment storage
CREATE TABLE IF NOT EXISTS comments (
    id VARCHAR(36) PRIMARY KEY,
    task_id VARCHAR(50) NOT NULL,
    author_id VARCHAR(36) NOT NULL,
    author_name VARCHAR(255) NOT NULL,
    author_email VARCHAR(255) NOT NULL,
    author_avatar VARCHAR(500),
    content TEXT NOT NULL,
    content_type ENUM('plain', 'rich', 'markdown') DEFAULT 'plain',
    parent_comment_id VARCHAR(36) NULL,
    is_deleted BOOLEAN DEFAULT FALSE,
    is_edited BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    edited_at TIMESTAMP NULL,
    INDEX idx_task_id (task_id),
    INDEX idx_author_id (author_id),
    INDEX idx_parent_comment (parent_comment_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (parent_comment_id) REFERENCES comments(id) ON DELETE SET NULL
);

-- Comment mentions table - Stores @mentions within comments
CREATE TABLE IF NOT EXISTS comment_mentions (
    id VARCHAR(36) PRIMARY KEY,
    comment_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    start_index INT NOT NULL,
    end_index INT NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    mention_type ENUM('user', 'team', 'role') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_comment_id (comment_id),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
);

-- Comment reactions table - Stores emoji reactions to comments
CREATE TABLE IF NOT EXISTS comment_reactions (
    id VARCHAR(36) PRIMARY KEY,
    comment_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    emoji VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_comment_emoji (comment_id, user_id, emoji),
    INDEX idx_comment_id (comment_id),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
);

-- Comment attachments table - Stores file attachments for comments
CREATE TABLE IF NOT EXISTS comment_attachments (
    id VARCHAR(36) PRIMARY KEY,
    comment_id VARCHAR(36) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_url VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    uploaded_by VARCHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_comment_id (comment_id),
    INDEX idx_uploaded_by (uploaded_by),
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
);

-- Comment metadata table - Stores additional metadata for analytics and features
CREATE TABLE IF NOT EXISTS comment_metadata (
    comment_id VARCHAR(36) PRIMARY KEY,
    edit_count INT DEFAULT 0,
    view_count INT DEFAULT 0,
    last_viewed_at TIMESTAMP NULL,
    is_pinned BOOLEAN DEFAULT FALSE,
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_by VARCHAR(36) NULL,
    resolved_at TIMESTAMP NULL,
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    tags JSON NULL,
    version INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
);

-- Comment notifications table - Stores notification preferences and delivery status
CREATE TABLE IF NOT EXISTS comment_notifications (
    id VARCHAR(36) PRIMARY KEY,
    comment_id VARCHAR(36) NOT NULL,
    notification_type ENUM('comment', 'mention', 'reply', 'reaction') NOT NULL,
    recipient_user_id VARCHAR(36) NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    triggered_by_user_id VARCHAR(36) NOT NULL,
    triggered_by_name VARCHAR(255) NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    is_sent BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP NULL,
    email_sent BOOLEAN DEFAULT FALSE,
    email_sent_at TIMESTAMP NULL,
    push_sent BOOLEAN DEFAULT FALSE,
    push_sent_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_comment_id (comment_id),
    INDEX idx_recipient (recipient_user_id),
    INDEX idx_triggered_by (triggered_by_user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
);

-- Comment search index table - For full-text search capabilities
CREATE TABLE IF NOT EXISTS comment_search_index (
    comment_id VARCHAR(36) PRIMARY KEY,
    task_id VARCHAR(50) NOT NULL,
    author_name VARCHAR(255) NOT NULL,
    content_text TEXT NOT NULL,
    mentioned_users TEXT NULL,
    tags TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FULLTEXT idx_content_search (content_text, author_name, mentioned_users, tags),
    INDEX idx_task_id (task_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
);

-- User notification preferences table - Controls how users receive notifications
CREATE TABLE IF NOT EXISTS user_notification_preferences (
    user_id VARCHAR(36) PRIMARY KEY,
    email_notifications BOOLEAN DEFAULT TRUE,
    push_notifications BOOLEAN DEFAULT TRUE,
    mention_notifications BOOLEAN DEFAULT TRUE,
    comment_notifications BOOLEAN DEFAULT TRUE,
    reply_notifications BOOLEAN DEFAULT TRUE,
    reaction_notifications BOOLEAN DEFAULT FALSE,
    digest_frequency ENUM('immediate', 'hourly', 'daily', 'weekly', 'never') DEFAULT 'immediate',
    quiet_hours_start TIME NULL,
    quiet_hours_end TIME NULL,
    timezone VARCHAR(50) DEFAULT 'UTC',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Comment activity log table - Audit trail for comment changes
CREATE TABLE IF NOT EXISTS comment_activity_log (
    id VARCHAR(36) PRIMARY KEY,
    comment_id VARCHAR(36) NOT NULL,
    action_type ENUM('created', 'updated', 'deleted', 'undeleted', 'pinned', 'unpinned', 'resolved', 'unresolve') NOT NULL,
    performed_by VARCHAR(36) NOT NULL,
    performed_by_name VARCHAR(255) NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_comment_id (comment_id),
    INDEX idx_performed_by (performed_by),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
);

-- Function to generate UUID v4
DELIMITER $$
CREATE FUNCTION IF NOT EXISTS UUID_v4()
RETURNS VARCHAR(36)
READS SQL DATA
DETERMINISTIC
BEGIN
    RETURN LOWER(CONCAT(
        HEX(RANDOM_BYTES(4)),
        '-', HEX(RANDOM_BYTES(2)),
        '-4', SUBSTR(HEX(RANDOM_BYTES(2)), 2, 3),
        '-', CONV((CONV(SUBSTR(HEX(RANDOM_BYTES(2)), 1, 2), 16, 10) & 63) | 128, 10, 16),
        SUBSTR(HEX(RANDOM_BYTES(2)), 2, 1),
        '-', HEX(RANDOM_BYTES(6))
    ));
END$$
DELIMITER ;

-- Indexes for performance optimization
CREATE INDEX IF NOT EXISTS idx_comments_task_created ON comments(task_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_comments_parent_created ON comments(parent_comment_id, created_at ASC);
CREATE INDEX IF NOT EXISTS idx_notifications_recipient_unread ON comment_notifications(recipient_user_id, is_read, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_activity_log_comment_created ON comment_activity_log(comment_id, created_at DESC);