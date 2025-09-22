-- TaskMaster Database Tables
-- Import this file into your existing database

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