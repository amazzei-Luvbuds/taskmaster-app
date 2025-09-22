-- TaskMaster Basic Schema - Compatible with all MySQL versions

-- Tasks table - Main table for all task data
CREATE TABLE IF NOT EXISTS tasks (
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
    task_category VARCHAR(100),
    milestone_group VARCHAR(100),
    production_stage VARCHAR(100),
    time_savings_impact TEXT,
    is_leadership BOOLEAN DEFAULT FALSE,
    transcript_raw LONGTEXT,
    transcript_summary TEXT,
    transcript_mentions TEXT,
    transcript_resources TEXT,
    task_log TEXT
);

-- Owner details table
CREATE TABLE IF NOT EXISTS owner_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255),
    avatar_url VARCHAR(500),
    role VARCHAR(100),
    department VARCHAR(100)
);

-- Departments table
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    color VARCHAR(20) DEFAULT 'blue',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default departments
INSERT IGNORE INTO departments (name, color, description) VALUES
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

-- Insert some sample data for testing
INSERT IGNORE INTO tasks (task_id, action_item, department, owners, status, priority_score, progress_percentage) VALUES
('NT-123456-ABC', 'Set up database migration', 'Tech', 'Development Team', 'In Progress', 80, 90),
('NT-123457-DEF', 'Review Q4 marketing campaigns', 'Marketing', 'Marketing Team', 'Pending Review', 60, 75),
('NT-123458-GHI', 'Process expense reports', 'Accounting', 'Finance Team', 'Not Started', 40, 0),
('NT-123459-JKL', 'Update employee handbook', 'HR', 'HR Team', 'Requires Approval', 70, 85),
('NT-123460-MNO', 'Launch new product campaign', 'Marketing', 'Creative Team', 'Completed', 90, 100);