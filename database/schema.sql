-- TaskMaster Database Schema
-- MySQL Database Structure for Task Management

-- Create database
CREATE DATABASE IF NOT EXISTS taskmaster_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE taskmaster_db;

-- Tasks table - Main table for all task data
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id VARCHAR(50) UNIQUE NOT NULL,
    action_item TEXT NOT NULL,
    department VARCHAR(100) NOT NULL,
    owners VARCHAR(500),
    status VARCHAR(50) NOT NULL DEFAULT 'Not Started',
    priority_score INT DEFAULT 0,
    progress_percentage INT DEFAULT 0,
    problem_description TEXT,
    proposed_solution TEXT,
    due_date DATE,
    predicted_hours DECIMAL(8,2),
    actual_hours_spent DECIMAL(8,2),
    notes_log TEXT,
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_updated_by VARCHAR(100),

    -- BMAD/AI fields
    plan_json JSON,
    plan_last_updated TIMESTAMP NULL,
    subtasks_json JSON,
    subtasks_last_updated TIMESTAMP NULL,

    -- Advanced fields
    task_category VARCHAR(100),
    milestone_group VARCHAR(100),
    production_stage VARCHAR(100),
    time_savings_impact TEXT,
    is_leadership BOOLEAN DEFAULT FALSE,

    -- Transcript and AI analysis
    transcript_raw LONGTEXT,
    transcript_summary TEXT,
    transcript_mentions TEXT,
    transcript_resources TEXT,

    -- Task log for history
    task_log TEXT,

    -- Indexes for performance
    INDEX idx_task_id (task_id),
    INDEX idx_department (department),
    INDEX idx_status (status),
    INDEX idx_date_created (date_created),
    INDEX idx_due_date (due_date),
    INDEX idx_last_updated (last_updated)
) ENGINE=InnoDB;

-- Owner details table for team member information
CREATE TABLE owner_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255),
    avatar_url VARCHAR(500),
    role VARCHAR(100),
    department VARCHAR(100),

    FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
    INDEX idx_task_id (task_id),
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- Departments table for configuration
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    color VARCHAR(20) DEFAULT 'blue',
    description TEXT,
    workflow_config JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_name (name)
) ENGINE=InnoDB;

-- Task status definitions
CREATE TABLE task_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT,
    status_name VARCHAR(50) NOT NULL,
    status_order INT NOT NULL,
    status_color VARCHAR(20) DEFAULT 'gray',
    description TEXT,

    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_department_order (department_id, status_order)
) ENGINE=InnoDB;

-- API keys and configuration
CREATE TABLE api_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT,
    is_encrypted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Insert default departments
INSERT INTO departments (name, color, description) VALUES
('General', 'gray', 'General tasks and miscellaneous items'),
('Sales', 'blue', 'Sales department tasks and leads'),
('Accounting', 'green', 'Financial and accounting operations'),
('Marketing', 'orange', 'Marketing campaigns and content'),
('Tech', 'purple', 'Technical development and IT'),
('HR', 'red', 'Human resources and team management'),
('Ideas', 'yellow', 'Innovation and idea development'),
('Purchasing', 'purple', 'Procurement and vendor management'),
('Swag', 'green', 'Merchandise and promotional items'),
('Customer Retention', 'teal', 'Customer success and retention');

-- Insert default task statuses for general workflow
INSERT INTO task_statuses (department_id, status_name, status_order, status_color) VALUES
(1, 'Not Started', 1, 'gray'),
(1, 'Pending Review', 2, 'yellow'),
(1, 'In Progress', 3, 'blue'),
(1, 'Requires Approval', 4, 'orange'),
(1, 'Completed', 5, 'green');

-- Comments table - For task discussions and collaboration
CREATE TABLE comments (
    id VARCHAR(255) PRIMARY KEY,
    task_id VARCHAR(50) NOT NULL,
    parent_comment_id VARCHAR(255) NULL,
    author_id VARCHAR(255) NOT NULL,
    author_name VARCHAR(255) NOT NULL,
    author_email VARCHAR(320) NOT NULL,
    author_avatar VARCHAR(500),
    content LONGTEXT NOT NULL,
    content_type ENUM('plain', 'rich') DEFAULT 'plain',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    edited_at TIMESTAMP NULL,
    is_deleted BOOLEAN DEFAULT FALSE,
    is_edited BOOLEAN DEFAULT FALSE,
    flagged BOOLEAN DEFAULT FALSE,

    -- Indexes for performance
    INDEX idx_task_id (task_id),
    INDEX idx_parent_comment (parent_comment_id),
    INDEX idx_author (author_id),
    INDEX idx_created_at (created_at),

    -- Foreign key constraints
    FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
    FOREIGN KEY (parent_comment_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Comment mentions table - For @mentions in comments
CREATE TABLE comment_mentions (
    id VARCHAR(255) PRIMARY KEY,
    comment_id VARCHAR(255) NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    user_email VARCHAR(320) NOT NULL,
    start_index INT NOT NULL,
    end_index INT NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_comment_id (comment_id),
    INDEX idx_user_id (user_id),

    -- Foreign keys
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Comment attachments table - For secure file uploads
CREATE TABLE comment_attachments (
    id VARCHAR(255) PRIMARY KEY,
    comment_id VARCHAR(255) NULL, -- NULL initially, set when comment is created
    original_name VARCHAR(500) NOT NULL,
    stored_name VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    mime_type VARCHAR(255) NOT NULL,
    file_path VARCHAR(1000) NOT NULL,
    uploaded_by VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    virus_scanned BOOLEAN DEFAULT FALSE,
    scan_result ENUM('clean', 'infected', 'pending', 'error') DEFAULT 'pending',
    scan_date TIMESTAMP NULL,
    download_count INT DEFAULT 0,
    last_accessed TIMESTAMP NULL,

    -- Indexes
    INDEX idx_comment_id (comment_id),
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_uploaded_at (uploaded_at),
    INDEX idx_scan_result (scan_result),

    -- Foreign keys
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Comment reactions table - For emoji reactions
CREATE TABLE comment_reactions (
    id VARCHAR(255) PRIMARY KEY,
    comment_id VARCHAR(255) NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    emoji VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Ensure one reaction per user per comment per emoji
    UNIQUE KEY unique_reaction (comment_id, user_id, emoji),

    -- Indexes
    INDEX idx_comment_id (comment_id),
    INDEX idx_user_id (user_id),

    -- Foreign keys
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Comment edit history table - Track comment modifications
CREATE TABLE comment_edit_history (
    id VARCHAR(255) PRIMARY KEY,
    comment_id VARCHAR(255) NOT NULL,
    previous_content LONGTEXT NOT NULL,
    previous_content_type ENUM('plain', 'rich') DEFAULT 'plain',
    edited_by VARCHAR(255) NOT NULL,
    edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    edit_reason VARCHAR(500),

    -- Indexes
    INDEX idx_comment_id (comment_id),
    INDEX idx_edited_at (edited_at),

    -- Foreign keys
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Comment notifications table - Track notification delivery
CREATE TABLE comment_notifications (
    id VARCHAR(255) PRIMARY KEY,
    comment_id VARCHAR(255) NOT NULL,
    recipient_user_id VARCHAR(255) NOT NULL,
    recipient_email VARCHAR(320) NOT NULL,
    notification_type ENUM('mention', 'reply', 'comment') NOT NULL,
    sent_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    delivery_status ENUM('pending', 'sent', 'failed', 'bounced') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_comment_id (comment_id),
    INDEX idx_recipient (recipient_user_id),
    INDEX idx_delivery_status (delivery_status),
    INDEX idx_sent_at (sent_at),

    -- Foreign keys
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user for API access (adjust credentials as needed)
-- CREATE USER 'taskmaster_api'@'localhost' IDENTIFIED BY 'your_secure_password_here';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON taskmaster_db.* TO 'taskmaster_api'@'localhost';
-- FLUSH PRIVILEGES;