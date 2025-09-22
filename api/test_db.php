<?php
/**
 * Test database connection
 */

require_once 'config.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    echo "Database connection successful!\n";
    
    // Test a simple query
    $stmt = $conn->query('SELECT COUNT(*) as count FROM tasks');
    $result = $stmt->fetch();
    echo "Tasks in database: " . $result['count'] . "\n";
    
    // Test departments
    $stmt = $conn->query('SELECT COUNT(*) as count FROM departments');
    $result = $stmt->fetch();
    echo "Departments in database: " . $result['count'] . "\n";
    
    // Test team
    $stmt = $conn->query('SELECT COUNT(*) as count FROM team');
    $result = $stmt->fetch();
    echo "Team members in database: " . $result['count'] . "\n";
    
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
}
?>
