<?php
/**
 * Test Database Connection Script
 * This script tests the database connection with different configurations
 */

// Test configurations
$configs = [
    'localhost' => [
        'host' => 'localhost',
        'dbname' => 'cosmichq_luvbudstaskmaster',
        'user' => 'cosmichq_luvbudstaskmaster',
        'pass' => 'gyjnix-dumpik-tobHi9'
    ],
    'luvbudstv.com' => [
        'host' => 'luvbudstv.com',
        'dbname' => 'cosmichq_luvbudstaskmaster',
        'user' => 'cosmichq_luvbudstaskmaster',
        'pass' => 'gyjnix-dumpik-tobHi9'
    ],
    '127.0.0.1' => [
        'host' => '127.0.0.1',
        'dbname' => 'cosmichq_luvbudstaskmaster',
        'user' => 'cosmichq_luvbudstaskmaster',
        'pass' => 'gyjnix-dumpik-tobHi9'
    ]
];

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$results = [];

foreach ($configs as $configName => $config) {
    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];
        
        $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
        
        // Test query
        $stmt = $pdo->query("SELECT COUNT(*) as task_count FROM tasks");
        $result = $stmt->fetch();
        
        $results[$configName] = [
            'status' => 'success',
            'message' => 'Connection successful',
            'task_count' => $result['task_count']
        ];
        
    } catch (PDOException $e) {
        $results[$configName] = [
            'status' => 'error',
            'message' => $e->getMessage(),
            'error_code' => $e->getCode()
        ];
    }
}

echo json_encode($results, JSON_PRETTY_PRINT);
?>
