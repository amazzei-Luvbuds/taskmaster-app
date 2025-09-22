<?php
/**
 * Fixed Comments API - Simplified for quick deployment
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://taskmaster-react.vercel.app');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-ID-Token, X-User-ID, X-User-Email');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

// Performance monitoring
$request_start = microtime(true);

/**
 * Get comments for a task
 */
function getTaskComments($task_id, $limit = 20) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();

        $stmt = $pdo->prepare("
            SELECT
                id,
                task_id,
                parent_comment_id,
                author_id,
                author_name,
                author_email,
                author_avatar,
                content,
                content_type,
                created_at,
                updated_at,
                edited_at,
                is_deleted,
                is_edited,
                flagged
            FROM comments
            WHERE task_id = ? AND is_deleted = 0
            ORDER BY created_at DESC
            LIMIT ?
        ");

        $stmt->execute([$task_id, $limit]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format comments for frontend
        $formatted_comments = [];
        foreach ($comments as $comment) {
            $formatted_comments[] = [
                'id' => $comment['id'],
                'taskId' => $comment['task_id'],
                'parentCommentId' => $comment['parent_comment_id'],
                'authorId' => $comment['author_id'],
                'authorName' => $comment['author_name'],
                'authorEmail' => $comment['author_email'],
                'authorAvatar' => $comment['author_avatar'],
                'content' => $comment['content'],
                'contentType' => $comment['content_type'],
                'createdAt' => $comment['created_at'],
                'updatedAt' => $comment['updated_at'],
                'editedAt' => $comment['edited_at'],
                'isDeleted' => (bool)$comment['is_deleted'],
                'isEdited' => (bool)$comment['is_edited'],
                'mentions' => [],
                'attachments' => [],
                'reactions' => [],
                'metadata' => [
                    'flagged' => (bool)$comment['flagged'],
                    'editHistory' => [],
                    'moderatorActions' => []
                ]
            ];
        }

        // Get total count
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE task_id = ? AND is_deleted = 0");
        $count_stmt->execute([$task_id]);
        $total_count = $count_stmt->fetchColumn();

        return [
            'comments' => $formatted_comments,
            'totalCount' => (int)$total_count,
            'hasMore' => count($formatted_comments) >= $limit,
            'nextCursor' => null
        ];

    } catch (Exception $e) {
        error_log('Comments API Error: ' . $e->getMessage());
        throw new Exception('Failed to fetch comments');
    }
}

/**
 * Create a new comment
 */
function createComment($data) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();

        $comment_id = 'comment_' . uniqid();

        $stmt = $pdo->prepare("
            INSERT INTO comments (id, task_id, content, author_id, author_name, author_email, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $comment_id,
            $data['taskId'],
            $data['content'],
            $data['authorId'] ?? 'anonymous',
            $data['authorName'] ?? 'Anonymous',
            $data['authorEmail'] ?? 'anonymous@example.com'
        ]);

        return [
            'id' => $comment_id,
            'message' => 'Comment created successfully'
        ];

    } catch (Exception $e) {
        error_log('Comment creation error: ' . $e->getMessage());
        throw new Exception('Failed to create comment');
    }
}

// Route requests
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET' && isset($_GET['task_id'])) {
        $task_id = $_GET['task_id'];
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

        $result = getTaskComments($task_id, $limit);

        // Add performance info
        $result['performance'] = [
            'queryTime' => round((microtime(true) - $request_start) * 1000, 2),
            'cacheStatus' => 'MISS'
        ];

        echo json_encode($result);

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['taskId']) || !isset($input['content'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input data']);
            exit;
        }

        $result = createComment($input);
        echo json_encode($result);

    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'performance' => [
            'queryTime' => round((microtime(true) - $request_start) * 1000, 2),
            'error' => true
        ]
    ]);
}
?>