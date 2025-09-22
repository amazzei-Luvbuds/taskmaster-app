<?php
/**
 * TaskMaster API - Comments Mock Endpoint
 * Mock implementation for local development when database is not available
 */

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Parse the path
$path = parse_url($uri, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Remove empty parts and get the actual path segments
$pathParts = array_filter($pathParts, function($part) {
    return !empty($part);
});
$pathParts = array_values($pathParts);

// Mock responses
switch ($method) {
    case 'GET':
        // For GET /comments_mock.php/task/{task_id}, the pathParts should be [0] => 'comments_mock.php', [1] => 'task', [2] => '{task_id}'
        if (isset($pathParts[1]) && $pathParts[1] === 'task' && isset($pathParts[2])) {
            // GET /comments_mock.php/task/{task_id} - Get comments for a task
            $taskId = $pathParts[2];
            
            // Mock comments with images for testing
            $mockComments = [
                [
                    'id' => 'comment_68d0abd8c342f',
                    'taskId' => $taskId,
                    'content' => 'Here\'s the taskmaster logo design',
                    'contentType' => 'text',
                    'author' => 'Alex Mazzei',
                    'authorEmail' => 'amazzei@luvbuds.co',
                    'createdAt' => '2025-09-22T03:16:34.844Z',
                    'updatedAt' => '2025-09-22T03:16:34.844Z',
                    'attachments' => [
                        [
                            'id' => 'github_1758510997212_rwx6o6gr6',
                            'fileName' => 'taskmaster.jpg',
                            'fileType' => 'image/jpeg',
                            'fileSize' => 45678,
                            'fileUrl' => 'https://raw.githubusercontent.com/amazzei-Luvbuds/taskmaster-images/main/images/2025-09-22T03-16-34-844Z-taskmaster.jpg',
                            'uploadedAt' => '2025-09-22T03:16:34.844Z'
                        ]
                    ],
                    'reactions' => [],
                    'replies' => []
                ],
                [
                    'id' => 'comment_68d0abd8c342g',
                    'taskId' => $taskId,
                    'content' => 'Here\'s the luvbuds sticker design',
                    'contentType' => 'text',
                    'author' => 'Alex Mazzei',
                    'authorEmail' => 'amazzei@luvbuds.co',
                    'createdAt' => '2025-09-22T03:02:24.627Z',
                    'updatedAt' => '2025-09-22T03:02:24.627Z',
                    'attachments' => [
                        [
                            'id' => 'github_1758510146675_dg06yc9yi',
                            'fileName' => 'luvbudsstickerforhat.jpg',
                            'fileType' => 'image/jpeg',
                            'fileSize' => 32156,
                            'fileUrl' => 'https://raw.githubusercontent.com/amazzei-Luvbuds/taskmaster-images/main/images/2025-09-22T03-02-24-627Z-luvbudsstickerforhat.jpg',
                            'uploadedAt' => '2025-09-22T03:02:24.627Z'
                        ]
                    ],
                    'reactions' => [],
                    'replies' => []
                ],
                [
                    'id' => 'comment_68d0abd8c342h',
                    'taskId' => $taskId,
                    'content' => 'Here\'s the heart logo design',
                    'contentType' => 'text',
                    'author' => 'Alex Mazzei',
                    'authorEmail' => 'amazzei@luvbuds.co',
                    'createdAt' => '2025-09-22T02:52:02.244Z',
                    'updatedAt' => '2025-09-22T02:52:02.244Z',
                    'attachments' => [
                        [
                            'id' => 'github_1758509523722_nmcbd38uw',
                            'fileName' => 'luvbudsheartlogo pr.png',
                            'fileType' => 'image/png',
                            'fileSize' => 28934,
                            'fileUrl' => 'https://raw.githubusercontent.com/amazzei-Luvbuds/taskmaster-images/main/images/2025-09-22T02-52-02-244Z-luvbudsheartlogo%20pr.png',
                            'uploadedAt' => '2025-09-22T02:52:02.244Z'
                        ]
                    ],
                    'reactions' => [],
                    'replies' => []
                ]
            ];
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => [
                    'comments' => $mockComments,
                    'totalCount' => count($mockComments),
                    'hasMore' => false
                ],
                'status' => 200,
                'timestamp' => date('c')
            ]);
        } elseif (isset($pathParts[1])) {
            // GET /comments_mock.php/{id} - Get single comment
            http_response_code(404);
            echo json_encode([
                'error' => 'Comment not found',
                'status' => 404
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'error' => 'Invalid comment endpoint',
                'status' => 400
            ]);
        }
        break;

    case 'POST':
        // POST /api/comments.php - Create new comment
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => 'mock_' . uniqid(),
                'message' => 'Comment created successfully (mock)'
            ],
            'status' => 201,
            'timestamp' => date('c')
        ]);
        break;

    case 'PUT':
        if (isset($pathParts[2])) {
            // PUT /api/comments.php/{id} - Update comment
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $pathParts[2],
                    'message' => 'Comment updated successfully (mock)'
                ],
                'status' => 200,
                'timestamp' => date('c')
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'error' => 'Comment ID required for update',
                'status' => 400
            ]);
        }
        break;

    case 'DELETE':
        // For DELETE /comments_mock.php/{id}, the pathParts should be [0] => 'comments_mock.php', [1] => '{id}'
        if (isset($pathParts[1])) {
            // DELETE /comments_mock.php/{id} - Delete comment
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $pathParts[1],
                    'message' => 'Comment deleted successfully (mock)'
                ],
                'status' => 200,
                'timestamp' => date('c')
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'error' => 'Comment ID required for deletion',
                'status' => 400
            ]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode([
            'error' => 'Method not allowed',
            'status' => 405
        ]);
        break;
}
?>
