-- Fixed Comments System Database Tables
-- Compatible with all MySQL versions

-- Comments table (main comment storage)
CREATE TABLE IF NOT EXISTS comments (
    id VARCHAR(50) PRIMARY KEY,
    task_id VARCHAR(50) NOT NULL,
    parent_comment_id VARCHAR(50) NULL,
    author_id VARCHAR(100) NOT NULL,
    author_name VARCHAR(255) NOT NULL,
    author_email VARCHAR(255) NOT NULL,
    author_avatar TEXT NULL,
    content TEXT NOT NULL,
    content_type ENUM('plain', 'markdown') DEFAULT 'plain',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    edited_at TIMESTAMP NULL,
    is_deleted BOOLEAN DEFAULT FALSE,
    is_edited BOOLEAN DEFAULT FALSE,
    flagged BOOLEAN DEFAULT FALSE
);

-- Comment mentions table (for @mentions)
CREATE TABLE IF NOT EXISTS comment_mentions (
    id VARCHAR(50) PRIMARY KEY,
    comment_id VARCHAR(50) NOT NULL,
    user_id VARCHAR(100) NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    start_index INT NOT NULL,
    end_index INT NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Comment attachments table (for file uploads)
CREATE TABLE IF NOT EXISTS comment_attachments (
    id VARCHAR(50) PRIMARY KEY,
    comment_id VARCHAR(50) NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    uploaded_by VARCHAR(100) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    virus_scanned BOOLEAN DEFAULT FALSE,
    scan_result VARCHAR(50) DEFAULT 'pending'
);

-- Comment reactions table (for emoji reactions)
CREATE TABLE IF NOT EXISTS comment_reactions (
    id VARCHAR(50) PRIMARY KEY,
    comment_id VARCHAR(50) NOT NULL,
    user_id VARCHAR(100) NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    emoji VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add indexes separately (compatible with older MySQL)
ALTER TABLE comments ADD INDEX idx_task_comments (task_id, created_at);
ALTER TABLE comments ADD INDEX idx_parent_comments (parent_comment_id);
ALTER TABLE comments ADD INDEX idx_author_comments (author_id);

ALTER TABLE comment_mentions ADD INDEX idx_comment_mentions (comment_id);
ALTER TABLE comment_mentions ADD INDEX idx_user_mentions (user_id);

ALTER TABLE comment_attachments ADD INDEX idx_comment_attachments (comment_id);
ALTER TABLE comment_attachments ADD INDEX idx_uploaded_by (uploaded_by);

ALTER TABLE comment_reactions ADD INDEX idx_comment_reactions (comment_id);
ALTER TABLE comment_reactions ADD INDEX idx_user_reactions (user_id);

-- Add unique constraint for reactions (one reaction per user per comment per emoji)
ALTER TABLE comment_reactions ADD UNIQUE KEY unique_user_reaction (comment_id, user_id, emoji);

-- Insert test data
INSERT INTO comments (id, task_id, author_id, author_name, author_email, content, created_at) VALUES
('demo_comment_1', 'FIN-006', 'user_demo', 'Demo User', 'demo@example.com', 'This is a test comment for the new comment system. It supports threading, mentions, and file attachments!', NOW() - INTERVAL 1 HOUR),
('demo_comment_2', 'FIN-006', 'user_demo2', 'Another User', 'user2@example.com', 'Great feature! Looking forward to using this in our project discussions.', NOW() - INTERVAL 30 MINUTE),
('demo_reply_1', 'FIN-006', 'user_demo', 'Demo User', 'demo@example.com', 'Thanks! The system also includes real-time updates and performance optimization.', NOW() - INTERVAL 15 MINUTE);

-- Update reply to have parent
UPDATE comments SET parent_comment_id = 'demo_comment_2' WHERE id = 'demo_reply_1';

-- Create sample reactions
INSERT INTO comment_reactions (id, comment_id, user_id, user_name, emoji) VALUES
('react_1', 'demo_comment_1', 'user_demo2', 'Another User', '👍'),
('react_2', 'demo_comment_2', 'user_demo', 'Demo User', '❤️'),
('react_3', 'demo_comment_1', 'user_demo3', 'Third User', '🚀');