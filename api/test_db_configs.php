<?php
/**
 * Test Database Configurations
 * This script tests different database configurations to find the working one
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Test different database configurations
$configs = [
    'localhost_cosmichq' => [
        'host' => 'localhost',
        'dbname' => 'cosmichq_luvbudstaskmaster',
        'user' => 'cosmichq_luvbudstaskmaster',
        'pass' => 'gyjnix-dumpik-tobHi9'
    ],
    'localhost_root' => [
        'host' => 'localhost',
        'dbname' => 'cosmichq_luvbudstaskmaster',
        'user' => 'root',
        'pass' => ''
    ],
    'localhost_cpanel' => [
        'host' => 'localhost',
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
        
        // Test query to get task count
        $stmt = $pdo->query("SELECT COUNT(*) as task_count FROM tasks");
        $result = $stmt->fetch();
        
        $results[$configName] = [
            'status' => 'success',
            'message' => 'Connection successful',
            'task_count' => $result['task_count'],
            'host' => $config['host'],
            'dbname' => $config['dbname'],
            'user' => $config['user']
        ];
        
    } catch (PDOException $e) {
        $results[$configName] = [
            'status' => 'error',
            'message' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'host' => $config['host'],
            'dbname' => $config['dbname'],
            'user' => $config['user']
        ];
    }
}

echo json_encode($results, JSON_PRETTY_PRINT);
?>
