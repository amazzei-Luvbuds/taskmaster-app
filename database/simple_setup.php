<?php
/**
 * Simple Comments Setup - Just run the SQL
 */

require_once '../api/config.php';

echo "🚀 Creating Comment Tables...\n\n";

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Just create the tables we need
    $sql = "
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

    CREATE TABLE IF NOT EXISTS comment_reactions (
        id VARCHAR(50) PRIMARY KEY,
        comment_id VARCHAR(50) NOT NULL,
        user_id VARCHAR(100) NOT NULL,
        user_name VARCHAR(255) NOT NULL,
        emoji VARCHAR(10) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    INSERT IGNORE INTO comments (id, task_id, author_id, author_name, author_email, content) VALUES
    ('demo_1', 'FIN-006', 'demo_user', 'Demo User', 'demo@example.com', 'Welcome to the new comment system! 🎉');
    ";

    $pdo->exec($sql);

    echo "✅ Tables created successfully!\n";
    echo "🔗 Test: https://luvbudstv.com/api/comments_optimized.php?task_id=FIN-006\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>