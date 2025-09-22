<?php
/**
 * Setup SQLite Database with Tables
 */

require_once 'config.php';

echo "🚀 Setting up SQLite database...\n\n";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Create tasks table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            task_id VARCHAR(50) UNIQUE NOT NULL,
            action_item TEXT NOT NULL,
            department VARCHAR(100) NOT NULL,
            owners VARCHAR(500),
            status VARCHAR(50) NOT NULL DEFAULT 'Not Started',
            priority_score INTEGER DEFAULT 0,
            progress_percentage INTEGER DEFAULT 0,
            problem_description TEXT,
            proposed_solution TEXT,
            due_date DATE,
            predicted_hours DECIMAL(8,2),
            actual_hours_spent DECIMAL(8,2),
            notes_log TEXT,
            date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_updated_by VARCHAR(100),
            plan_json TEXT,
            plan_last_updated TIMESTAMP NULL,
            subtasks_json TEXT,
            subtasks_last_updated TIMESTAMP NULL,
            task_category VARCHAR(100),
            milestone_group VARCHAR(100),
            production_stage VARCHAR(100),
            time_savings_impact TEXT,
            is_leadership BOOLEAN DEFAULT 0,
            transcript_raw TEXT,
            transcript_summary TEXT,
            transcript_mentions TEXT,
            transcript_resources TEXT,
            task_log TEXT
        )
    ");
    
    // Create departments table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS departments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL,
            color VARCHAR(7) DEFAULT '#3B82F6',
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create team table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS team (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            department VARCHAR(100) NOT NULL,
            role VARCHAR(100) DEFAULT 'member',
            avatar_url TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create comments table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS comments (
            id VARCHAR(50) PRIMARY KEY,
            task_id VARCHAR(50) NOT NULL,
            parent_comment_id VARCHAR(50) NULL,
            author_id VARCHAR(100) NOT NULL,
            author_name VARCHAR(255) NOT NULL,
            author_email VARCHAR(255) NOT NULL,
            author_avatar TEXT NULL,
            content TEXT NOT NULL,
            content_type VARCHAR(20) DEFAULT 'plain',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            edited_at TIMESTAMP NULL,
            is_deleted BOOLEAN DEFAULT 0,
            is_edited BOOLEAN DEFAULT 0,
            flagged BOOLEAN DEFAULT 0
        )
    ");
    
    // Create comment_attachments table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS comment_attachments (
            id VARCHAR(50) PRIMARY KEY,
            comment_id VARCHAR(50) NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            file_size INTEGER NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            uploaded_by VARCHAR(100) NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            virus_scanned BOOLEAN DEFAULT 0,
            scan_result VARCHAR(50) DEFAULT 'pending'
        )
    ");
    
    // Insert sample data
    $conn->exec("
        INSERT OR IGNORE INTO departments (name, color) VALUES
        ('Technology', '#3B82F6'),
        ('Marketing', '#10B981'),
        ('Operations', '#F59E0B'),
        ('Sales', '#EF4444')
    ");
    
    $conn->exec("
        INSERT OR IGNORE INTO team (name, email, department, role) VALUES
        ('Alex Mazzei', 'amazzei@luvbuds.co', 'Technology', 'leadership'),
        ('John Doe', 'john@luvbuds.co', 'Technology', 'member'),
        ('Jane Smith', 'jane@luvbuds.co', 'Marketing', 'member')
    ");
    
    $conn->exec("
        INSERT OR IGNORE INTO tasks (task_id, action_item, department, owners, status, priority_score, progress_percentage, due_date) VALUES
        ('IT-002a', 'Fix CORS Issues', 'Technology', 'Alex Mazzei', 'In Progress', 5, 75, '2025-09-25'),
        ('IT-038', 'Update Task System', 'Technology', 'John Doe', 'Completed', 3, 100, '2025-09-20'),
        ('MK-001', 'Marketing Campaign', 'Marketing', 'Jane Smith', 'Not Started', 5, 0, '2025-10-01')
    ");
    
    echo "✅ Database setup completed successfully!\n";
    echo "📊 Tables created: tasks, departments, team, comments, comment_attachments\n";
    echo "📝 Sample data inserted\n";
    
    // Test the setup
    $stmt = $conn->query('SELECT COUNT(*) as count FROM tasks');
    $result = $stmt->fetch();
    echo "📋 Tasks in database: " . $result['count'] . "\n";
    
    $stmt = $conn->query('SELECT COUNT(*) as count FROM departments');
    $result = $stmt->fetch();
    echo "🏢 Departments in database: " . $result['count'] . "\n";
    
    $stmt = $conn->query('SELECT COUNT(*) as count FROM team');
    $result = $stmt->fetch();
    echo "👥 Team members in database: " . $result['count'] . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
