<?php
/**
 * Fix Database Configuration Script
 * This script fixes the database configuration on the server
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Only allow this to run once for security
$lockFile = __DIR__ . '/.config_fixed';
if (file_exists($lockFile)) {
    echo json_encode(['error' => 'Configuration already fixed. Delete .config_fixed to run again.']);
    exit;
}

try {
    // Read the current config.php file
    $configPath = __DIR__ . '/config.php';
    $configContent = file_get_contents($configPath);
    
    if ($configContent === false) {
        throw new Exception('Could not read config.php file');
    }
    
    // Update the database host from luvbudstv.com to localhost
    $updatedContent = str_replace(
        "define('DB_HOST', 'luvbudstv.com');",
        "define('DB_HOST', 'localhost');",
        $configContent
    );
    
    // Also update the comment
    $updatedContent = str_replace(
        "// Database configuration - Remote MySQL server",
        "// Database configuration - Local MySQL server",
        $updatedContent
    );
    
    // Write the updated config back
    $result = file_put_contents($configPath, $updatedContent);
    
    if ($result === false) {
        throw new Exception('Could not write to config.php file');
    }
    
    // Create lock file to prevent running again
    file_put_contents($lockFile, date('Y-m-d H:i:s'));
    
    // Test the database connection
    $testResult = testDatabaseConnection();
    
    echo json_encode([
        'success' => true,
        'message' => 'Database configuration updated successfully',
        'changes' => [
            'DB_HOST' => 'luvbudstv.com -> localhost'
        ],
        'database_test' => $testResult
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

function testDatabaseConnection() {
    try {
        $dsn = "mysql:host=localhost;dbname=cosmichq_luvbudstaskmaster;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];
        
        $pdo = new PDO($dsn, 'cosmichq_luvbudstaskmaster', 'gyjnix-dumpik-tobHi9', $options);
        
        // Test query
        $stmt = $pdo->query("SELECT COUNT(*) as task_count FROM tasks");
        $result = $stmt->fetch();
        
        return [
            'status' => 'success',
            'message' => 'Database connection successful',
            'task_count' => $result['task_count']
        ];
        
    } catch (PDOException $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}
?>
