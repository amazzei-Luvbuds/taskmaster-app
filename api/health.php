<?php
/**
 * Simple health check endpoint
 */

require_once 'config.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $stmt = $db->query("SELECT COUNT(*) as task_count FROM tasks");
    $tasks = $stmt->fetch();

    $stmt = $db->query("SELECT COUNT(*) as dept_count FROM departments");
    $departments = $stmt->fetch();

    sendJSON([
        'status' => 'healthy',
        'database' => 'connected',
        'version' => API_VERSION,
        'timestamp' => date('c'),
        'stats' => [
            'tasks' => (int)$tasks['task_count'],
            'departments' => (int)$departments['dept_count']
        ],
        'endpoints' => [
            'GET /api/health.php' => 'API health check',
            'GET /api/tasks_simple.php' => 'Get all tasks',
            'GET /api/departments_simple.php' => 'Get all departments'
        ]
    ]);
} catch (Exception $e) {
    sendError('Database connection failed: ' . $e->getMessage(), 500);
}
?>