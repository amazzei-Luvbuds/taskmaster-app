<?php
/**
 * TaskMaster API - Simple Comments Endpoint
 * Simplified version that works with our current database structure
 */

require_once 'config.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-ID-Token, X-User-ID, X-User-Email');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class SimpleCommentsAPI {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];
        
        // Parse the path
        $path = parse_url($uri, PHP_URL_PATH);
        $pathParts = explode('/', trim($path, '/'));
        
        // Remove the script name from path parts
        $scriptName = basename($_SERVER['SCRIPT_NAME']);
        $pathParts = array_filter($pathParts, function($part) use ($scriptName) {
            return $part !== $scriptName;
        });
        $pathParts = array_values($pathParts);
        
        switch ($method) {
            case 'GET':
                if (isset($pathParts[0]) && $pathParts[0] === 'task' && isset($pathParts[1])) {
                    $this->getTaskComments($pathParts[1]);
                } elseif (isset($pathParts[0])) {
                    $this->getComment($pathParts[0]);
                } else {
                    $this->sendError('Invalid comment endpoint', 400);
                }
                break;
                
            case 'POST':
                $this->createComment();
                break;
                
            case 'PUT':
                if (isset($pathParts[0])) {
                    $this->updateComment($pathParts[0]);
                } else {
                    $this->sendError('Comment ID required for update', 400);
                }
                break;
                
            case 'DELETE':
                if (isset($pathParts[0])) {
                    $this->deleteComment($pathParts[0]);
                } else {
                    $this->sendError('Comment ID required for deletion', 400);
                }
                break;
                
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    private function getTaskComments($taskId) {
        try {
            $limit = $_GET['limit'] ?? 20;
            $offset = $_GET['offset'] ?? 0;
            
            // Simple query for comments
            $query = "
                SELECT 
                    id,
                    task_id as taskId,
                    content,
                    content_type as contentType,
                    author_name as author,
                    author_email as authorEmail,
                    created_at as createdAt,
                    updated_at as updatedAt,
                    is_deleted as isDeleted,
                    is_edited as isEdited
                FROM comments 
                WHERE task_id = :task_id AND is_deleted = 0
                ORDER BY created_at ASC
                LIMIT :limit OFFSET :offset
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':task_id', $taskId, PDO::PARAM_STR);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $comments = $stmt->fetchAll();
            
            // Format the response
            $formattedComments = [];
            foreach ($comments as $comment) {
                $formattedComments[] = [
                    'id' => $comment['id'],
                    'taskId' => $comment['taskId'],
                    'content' => $comment['content'],
                    'contentType' => $comment['contentType'] ?: 'text',
                    'author' => $comment['author'],
                    'authorEmail' => $comment['authorEmail'],
                    'createdAt' => $this->formatTimestampForAPI($comment['createdAt']),
                    'updatedAt' => $this->formatTimestampForAPI($comment['updatedAt']),
                    'attachments' => [], // Empty for now
                    'reactions' => [], // Empty for now
                    'replies' => [] // Empty for now
                ];
            }
            
            $this->sendSuccess([
                'comments' => $formattedComments,
                'totalCount' => count($formattedComments),
                'hasMore' => count($formattedComments) >= $limit
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Failed to fetch comments: ' . $e->getMessage(), 500);
        }
    }
    
    private function getComment($commentId) {
        try {
            $query = "SELECT * FROM comments WHERE id = :id AND is_deleted = 0";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $commentId, PDO::PARAM_STR);
            $stmt->execute();
            
            $comment = $stmt->fetch();
            
            if (!$comment) {
                $this->sendError('Comment not found', 404);
                return;
            }
            
            $this->sendSuccess($comment);
            
        } catch (Exception $e) {
            $this->sendError('Failed to fetch comment: ' . $e->getMessage(), 500);
        }
    }
    
    private function createComment() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['taskId']) || !isset($input['content'])) {
                $this->sendError('Missing required fields: taskId and content', 400);
                return;
            }
            
            $commentId = 'comment_' . uniqid();
            
            $query = "
                INSERT INTO comments (
                    id, task_id, content, content_type, author_id, 
                    author_name, author_email, created_at, updated_at
                ) VALUES (
                    :id, :task_id, :content, :content_type, :author_id,
                    :author_name, :author_email, :created_at, :updated_at
                )
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $commentId, PDO::PARAM_STR);
            $stmt->bindValue(':task_id', $input['taskId'], PDO::PARAM_STR);
            $stmt->bindValue(':content', $input['content'], PDO::PARAM_STR);
            $stmt->bindValue(':content_type', $input['contentType'] ?? 'text', PDO::PARAM_STR);
            $stmt->bindValue(':author_id', $input['authorId'] ?? 'current-user', PDO::PARAM_STR);
            $stmt->bindValue(':author_name', $input['authorName'] ?? 'Current User', PDO::PARAM_STR);
            $stmt->bindValue(':author_email', $input['authorEmail'] ?? 'user@example.com', PDO::PARAM_STR);
            $stmt->bindValue(':created_at', date('Y-m-d H:i:s'), PDO::PARAM_STR);
            $stmt->bindValue(':updated_at', date('Y-m-d H:i:s'), PDO::PARAM_STR);
            
            $stmt->execute();
            
            $this->sendSuccess([
                'id' => $commentId,
                'message' => 'Comment created successfully'
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Failed to create comment: ' . $e->getMessage(), 500);
        }
    }
    
    private function updateComment($commentId) {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['content'])) {
                $this->sendError('Missing required field: content', 400);
                return;
            }
            
            $query = "
                UPDATE comments 
                SET content = :content, updated_at = :updated_at, is_edited = 1
                WHERE id = :id AND is_deleted = 0
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $commentId, PDO::PARAM_STR);
            $stmt->bindValue(':content', $input['content'], PDO::PARAM_STR);
            $stmt->bindValue(':updated_at', date('Y-m-d H:i:s'), PDO::PARAM_STR);
            
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                $this->sendError('Comment not found', 404);
                return;
            }
            
            $this->sendSuccess([
                'id' => $commentId,
                'message' => 'Comment updated successfully'
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Failed to update comment: ' . $e->getMessage(), 500);
        }
    }
    
    private function deleteComment($commentId) {
        try {
            $query = "UPDATE comments SET is_deleted = 1, updated_at = :updated_at WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $commentId, PDO::PARAM_STR);
            $stmt->bindValue(':updated_at', date('Y-m-d H:i:s'), PDO::PARAM_STR);
            
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                $this->sendError('Comment not found', 404);
                return;
            }
            
            $this->sendSuccess([
                'id' => $commentId,
                'message' => 'Comment deleted successfully'
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Failed to delete comment: ' . $e->getMessage(), 500);
        }
    }
    
    private function formatTimestampForAPI($timestamp) {
        if (!$timestamp) return null;
        
        // Convert to ISO 8601 format
        $date = new DateTime($timestamp);
        return $date->format('c');
    }
    
    private function sendSuccess($data, $status = 200) {
        http_response_code($status);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'status' => $status,
            'timestamp' => date('c')
        ]);
        exit;
    }
    
    private function sendError($message, $status = 500) {
        http_response_code($status);
        echo json_encode([
            'error' => $message,
            'status' => $status,
            'timestamp' => date('c')
        ]);
        exit;
    }
}

// Handle the request
$api = new SimpleCommentsAPI();
$api->handleRequest();
?>
