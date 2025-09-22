<?php
/**
 * Quick Comments System Setup
 * Simple script to create comment tables
 */

require_once '../api/config.php';

echo "<h2>🚀 TaskMaster Comments Quick Setup</h2>\n";

try {
    // Connect to database using the Database class
    $database = new Database();
    $pdo = $database->getConnection();
    echo "<p>✅ Database connection established</p>\n";

    // Define the SQL statements one by one
    $statements = [
        // Create comments table
        "CREATE TABLE IF NOT EXISTS comments (
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
        )",

        // Create comment_mentions table
        "CREATE TABLE IF NOT EXISTS comment_mentions (
            id VARCHAR(50) PRIMARY KEY,
            comment_id VARCHAR(50) NOT NULL,
            user_id VARCHAR(100) NOT NULL,
            user_name VARCHAR(255) NOT NULL,
            user_email VARCHAR(255) NOT NULL,
            start_index INT NOT NULL,
            end_index INT NOT NULL,
            display_name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // Create comment_attachments table
        "CREATE TABLE IF NOT EXISTS comment_attachments (
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
        )",

        // Create comment_reactions table
        "CREATE TABLE IF NOT EXISTS comment_reactions (
            id VARCHAR(50) PRIMARY KEY,
            comment_id VARCHAR(50) NOT NULL,
            user_id VARCHAR(100) NOT NULL,
            user_name VARCHAR(255) NOT NULL,
            emoji VARCHAR(10) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // Add indexes
        "ALTER TABLE comments ADD INDEX idx_task_comments (task_id, created_at)",
        "ALTER TABLE comments ADD INDEX idx_parent_comments (parent_comment_id)",
        "ALTER TABLE comment_mentions ADD INDEX idx_comment_mentions (comment_id)",
        "ALTER TABLE comment_attachments ADD INDEX idx_comment_attachments (comment_id)",
        "ALTER TABLE comment_reactions ADD INDEX idx_comment_reactions (comment_id)",

        // Add unique constraint for reactions
        "ALTER TABLE comment_reactions ADD UNIQUE KEY unique_user_reaction (comment_id, user_id, emoji)",

        // Insert test data
        "INSERT IGNORE INTO comments (id, task_id, author_id, author_name, author_email, content, created_at) VALUES
        ('demo_comment_1', 'FIN-006', 'user_demo', 'Demo User', 'demo@example.com', 'This is a test comment for the new comment system. It supports threading, mentions, and file attachments!', NOW() - INTERVAL 1 HOUR)",

        "INSERT IGNORE INTO comments (id, task_id, author_id, author_name, author_email, content, created_at) VALUES
        ('demo_comment_2', 'FIN-006', 'user_demo2', 'Another User', 'user2@example.com', 'Great feature! Looking forward to using this in our project discussions.', NOW() - INTERVAL 30 MINUTE)",

        "INSERT IGNORE INTO comments (id, task_id, author_id, author_name, author_email, content, parent_comment_id, created_at) VALUES
        ('demo_reply_1', 'FIN-006', 'user_demo', 'Demo User', 'demo@example.com', 'Thanks! The system also includes real-time updates and performance optimization.', 'demo_comment_2', NOW() - INTERVAL 15 MINUTE)",

        "INSERT IGNORE INTO comment_reactions (id, comment_id, user_id, user_name, emoji) VALUES
        ('react_1', 'demo_comment_1', 'user_demo2', 'Another User', '👍')",

        "INSERT IGNORE INTO comment_reactions (id, comment_id, user_id, user_name, emoji) VALUES
        ('react_2', 'demo_comment_2', 'user_demo', 'Demo User', '❤️')"
    ];

    $success_count = 0;
    $error_count = 0;

    foreach ($statements as $i => $sql) {
        try {
            $pdo->exec($sql);
            $success_count++;
            echo "<p>✅ Statement " . ($i + 1) . " executed successfully</p>\n";
        } catch (PDOException $e) {
            // Ignore errors for indexes that already exist
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "<p>⚪ Statement " . ($i + 1) . " skipped (already exists)</p>\n";
            } else {
                $error_count++;
                echo "<p>❌ Error in statement " . ($i + 1) . ": " . $e->getMessage() . "</p>\n";
            }
        }
    }

    echo "<h3>📊 Migration Summary</h3>\n";
    echo "<p>✅ Successful: $success_count</p>\n";
    echo "<p>❌ Errors: $error_count</p>\n";

    // Verify tables exist
    echo "<h3>🔍 Verifying Tables</h3>\n";
    $tables = ['comments', 'comment_mentions', 'comment_attachments', 'comment_reactions'];

    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $count_stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $count_stmt->fetch()['count'];
            echo "<p>✅ Table '$table' exists ($count rows)</p>\n";
        } else {
            echo "<p>❌ Table '$table' NOT found</p>\n";
        }
    }

    echo "<h3>🎯 Test API Endpoint</h3>\n";
    echo "<p><a href='/api/comments_optimized.php?task_id=FIN-006' target='_blank'>Test Comments API</a></p>\n";

    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>🎉 Setup Complete!</h4>";
    echo "<p>Your comment system is now ready. The API should return JSON data with test comments.</p>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<p>❌ Database connection failed: " . $e->getMessage() . "</p>\n";
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>\n";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #007bff; }
h3 { color: #28a745; }
p { margin: 5px 0; }
</style>