<?php
/**
 * Add sample comments to the database
 */

require_once 'config.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Insert sample comments
    $conn->exec("
        INSERT OR IGNORE INTO comments (id, task_id, content, content_type, author_id, author_name, author_email, created_at, updated_at) VALUES
        ('comment_1', 'IT-002a', 'Working on fixing the CORS issues. The main problem seems to be with the API headers.', 'text', 'user1', 'Alex Mazzei', 'amazzei@luvbuds.co', '2025-09-22 03:00:00', '2025-09-22 03:00:00'),
        ('comment_2', 'IT-002a', 'Found the issue! The Access-Control-Allow-Origin header was missing.', 'text', 'user1', 'Alex Mazzei', 'amazzei@luvbuds.co', '2025-09-22 03:15:00', '2025-09-22 03:15:00'),
        ('comment_3', 'IT-038', 'Task completed successfully. All tests passing.', 'text', 'user2', 'John Doe', 'john@luvbuds.co', '2025-09-22 02:30:00', '2025-09-22 02:30:00')
    ");
    
    echo "Sample comments added successfully!\n";
    
    // Verify the comments were added
    $stmt = $conn->query('SELECT COUNT(*) as count FROM comments');
    $result = $stmt->fetch();
    echo "Total comments in database: " . $result['count'] . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
