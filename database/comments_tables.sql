-- Comments System Database Tables
-- Run this SQL script in your MySQL database to enable the comment system

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
    flagged BOOLEAN DEFAULT FALSE,

    -- Indexes for performance
    INDEX idx_task_comments (task_id, created_at),
    INDEX idx_parent_comments (parent_comment_id),
    INDEX idx_author_comments (author_id),
    INDEX idx_created_at (created_at),

    -- Foreign key constraint for parent comments
    FOREIGN KEY (parent_comment_id) REFERENCES comments(id) ON DELETE CASCADE
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_comment_mentions (comment_id),
    INDEX idx_user_mentions (user_id),

    -- Foreign key
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
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
    scan_result VARCHAR(50) DEFAULT 'pending',

    -- Indexes
    INDEX idx_comment_attachments (comment_id),
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_upload_date (uploaded_at),

    -- Foreign key (can be NULL for orphaned files)
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE SET NULL
);

-- Comment reactions table (for emoji reactions)
CREATE TABLE IF NOT EXISTS comment_reactions (
    id VARCHAR(50) PRIMARY KEY,
    comment_id VARCHAR(50) NOT NULL,
    user_id VARCHAR(100) NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    emoji VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_comment_reactions (comment_id),
    INDEX idx_user_reactions (user_id),

    -- Unique constraint (one reaction per user per comment per emoji)
    UNIQUE KEY unique_user_reaction (comment_id, user_id, emoji),

    -- Foreign key
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
);

-- Create optimized indexes for pagination
CREATE INDEX IF NOT EXISTS idx_comments_pagination ON comments(task_id, created_at DESC, id);
CREATE INDEX IF NOT EXISTS idx_comments_replies ON comments(parent_comment_id, created_at ASC);

-- Sample data for testing (optional)
INSERT IGNORE INTO comments (id, task_id, author_id, author_name, author_email, content, created_at) VALUES
('demo_comment_1', 'FIN-006', 'user_demo', 'Demo User', 'demo@example.com', 'This is a test comment for the new comment system. It supports threading, mentions, and file attachments!', NOW() - INTERVAL 1 HOUR),
('demo_comment_2', 'FIN-006', 'user_demo2', 'Another User', 'user2@example.com', 'Great feature! Looking forward to using this in our project discussions.', NOW() - INTERVAL 30 MINUTE),
('demo_reply_1', 'FIN-006', 'user_demo', 'Demo User', 'demo@example.com', 'Thanks! The system also includes real-time updates and performance optimization.', NOW() - INTERVAL 15 MINUTE);

-- Update reply to have parent
UPDATE comments SET parent_comment_id = 'demo_comment_2' WHERE id = 'demo_reply_1';

-- Create sample reactions
INSERT IGNORE INTO comment_reactions (id, comment_id, user_id, user_name, emoji) VALUES
('react_1', 'demo_comment_1', 'user_demo2', 'Another User', '👍'),
('react_2', 'demo_comment_2', 'user_demo', 'Demo User', '❤️'),
('react_3', 'demo_comment_1', 'user_demo3', 'Third User', '🚀');

-- Performance optimization queries
ANALYZE TABLE comments;
ANALYZE TABLE comment_mentions;
ANALYZE TABLE comment_attachments;
ANALYZE TABLE comment_reactions;

-- Show table creation summary
SELECT
    'Comments System Setup Complete!' as status,
    (SELECT COUNT(*) FROM comments) as sample_comments,
    (SELECT COUNT(*) FROM comment_reactions) as sample_reactions,
    NOW() as created_at;