<?php
/**
 * TaskMaster API - Main Router
 * Routes requests to appropriate endpoints
 */

require_once 'config.php';

// Simple routing
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// API health check
if ($path === '/api' || $path === '/api/') {
    sendJSON([
        'status' => 'healthy',
        'version' => API_VERSION,
        'endpoints' => [
            'GET /api/tasks' => 'Get all tasks',
            'GET /api/tasks/{id}' => 'Get single task',
            'POST /api/tasks' => 'Create new task',
            'PUT /api/tasks/{id}' => 'Update task',
            'PUT /api/tasks' => 'Update task status (for kanban)',
            'DELETE /api/tasks/{id}' => 'Delete task',
            'GET /api/departments' => 'Get all departments',
            'GET /api/comments/task/{task_id}' => 'Get comments for task',
            'GET /api/comments/{id}' => 'Get single comment',
            'POST /api/comments' => 'Create new comment',
            'PUT /api/comments/{id}' => 'Update comment',
            'DELETE /api/comments/{id}' => 'Delete comment',
            'POST /api/comments/reactions/{comment_id}' => 'Add reaction to comment',
            'DELETE /api/comments/{comment_id}/reactions/{reaction_id}' => 'Remove reaction',
            'GET /api/health' => 'API health check'
        ]
    ]);
}

// Health check endpoint
if ($path === '/api/health') {
    try {
        $database = new Database();
        $db = $database->getConnection();
        $stmt = $db->query("SELECT 1");
        $result = $stmt->fetch();

        sendJSON([
            'status' => 'healthy',
            'database' => 'connected',
            'version' => API_VERSION,
            'timestamp' => date('c')
        ]);
    } catch (Exception $e) {
        sendError('Database connection failed', 500);
    }
}

// Route to specific endpoints
if (isset($pathParts[1])) {
    switch ($pathParts[1]) {
        case 'tasks':
            require_once 'tasks.php';
            break;

        case 'departments':
            require_once 'departments.php';
            break;

        case 'comments':
            require_once 'comments.php';
            break;

        default:
            sendError('Endpoint not found', 404);
    }
} else {
    sendError('Invalid API endpoint', 400);
}
?>