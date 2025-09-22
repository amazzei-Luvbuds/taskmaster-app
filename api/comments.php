<?php
/**
 * TaskMaster API - Comments Endpoint
 * Handles all comment-related operations for the Team Communication Hub
 */

require_once 'config.php';

// Override database configuration if needed
if (defined('DB_HOST') && DB_HOST === 'luvbudstv.com') {
    // Redefine the database constants with correct values
    define('DB_HOST_OVERRIDE', 'localhost');
    define('DB_NAME_OVERRIDE', 'cosmichq_luvbudstaskmaster');
    define('DB_USER_OVERRIDE', 'cosmichq_luvbudstaskmaster');
    define('DB_PASS_OVERRIDE', 'gyjnix-dumpik-tobHi9');
}

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

class CommentsAPI {
    private $db;

    public function __construct() {
        // Create database connection with override values if needed
        if (defined('DB_HOST_OVERRIDE')) {
            $dsn = "mysql:host=" . DB_HOST_OVERRIDE . ";dbname=" . DB_NAME_OVERRIDE . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            $this->db = new PDO($dsn, DB_USER_OVERRIDE, DB_PASS_OVERRIDE, $options);
        } else {
            $database = new Database();
            $this->db = $database->getConnection();
        }
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];

        // Parse the path
        $path = parse_url($uri, PHP_URL_PATH);
        $pathParts = explode('/', trim($path, '/'));

        switch ($method) {
            case 'GET':
                if (isset($pathParts[2]) && $pathParts[2] === 'task' && isset($pathParts[3])) {
                    // GET /api/comments/task/{task_id} - Get comments for a task
                    $this->getTaskComments($pathParts[3]);
                } elseif (isset($pathParts[2])) {
                    // GET /api/comments/{id} - Get single comment
                    $this->getComment($pathParts[2]);
                } else {
                    sendError('Invalid comment endpoint', 400);
                }
                break;

            case 'POST':
                if (isset($pathParts[2]) && $pathParts[2] === 'reactions' && isset($pathParts[3])) {
                    // POST /api/comments/reactions/{comment_id} - Add reaction
                    $this->addReaction($pathParts[3]);
                } else {
                    // POST /api/comments - Create new comment
                    $this->createComment();
                }
                break;

            case 'PUT':
                if (isset($pathParts[2])) {
                    // PUT /api/comments/{id} - Update comment
                    $this->updateComment($pathParts[2]);
                } else {
                    sendError('Comment ID required for update', 400);
                }
                break;

            case 'DELETE':
                if (isset($pathParts[2]) && $pathParts[3] === 'reactions' && isset($pathParts[4])) {
                    // DELETE /api/comments/{comment_id}/reactions/{reaction_id} - Remove reaction
                    $this->removeReaction($pathParts[2], $pathParts[4]);
                } elseif (isset($pathParts[2])) {
                    // DELETE /api/comments/{id} - Delete comment
                    $this->deleteComment($pathParts[2]);
                } else {
                    sendError('Comment ID required for deletion', 400);
                }
                break;

            default:
                sendError('Method not allowed', 405);
        }
    }

    /**
     * Get comments for a specific task with threading and pagination
     */
    private function getTaskComments($taskId) {
        try {
            $limit = $_GET['limit'] ?? 20;
            $offset = $_GET['offset'] ?? 0;
            $cursor = $_GET['cursor'] ?? null;

            // Build the query with proper joins
            $query = "
                SELECT
                    c.*,
                    cm.metadata,
                    GROUP_CONCAT(
                        DISTINCT JSON_OBJECT(
                            'id', m.id,
                            'userId', m.user_id,
                            'userName', m.user_name,
                            'userEmail', m.user_email,
                            'startIndex', m.start_index,
                            'endIndex', m.end_index,
                            'displayName', m.display_name,
                            'type', m.mention_type
                        )
                    ) as mentions,
                    GROUP_CONCAT(
                        DISTINCT JSON_OBJECT(
                            'id', r.id,
                            'userId', r.user_id,
                            'userName', r.user_name,
                            'emoji', r.emoji,
                            'createdAt', r.created_at
                        )
                    ) as reactions,
                    GROUP_CONCAT(
                        DISTINCT JSON_OBJECT(
                            'id', a.id,
                            'fileName', a.file_name,
                            'fileUrl', a.file_url,
                            'fileType', a.file_type,
                            'fileSize', a.file_size,
                            'uploadedBy', a.uploaded_by,
                            'createdAt', a.created_at
                        )
                    ) as attachments
                FROM comments c
                LEFT JOIN comment_metadata cm ON c.id = cm.comment_id
                LEFT JOIN comment_mentions m ON c.id = m.comment_id
                LEFT JOIN comment_reactions r ON c.id = r.comment_id
                LEFT JOIN comment_attachments a ON c.id = a.comment_id
                WHERE c.task_id = :task_id AND c.is_deleted = FALSE
                GROUP BY c.id
                ORDER BY c.created_at ASC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':task_id', $taskId, PDO::PARAM_STR);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();

            $comments = $stmt->fetchAll();

            // Process the results to format JSON fields properly
            $formattedComments = [];
            foreach ($comments as $comment) {
                $formattedComment = [
                    'id' => $comment['id'],
                    'taskId' => $comment['task_id'],
                    'authorId' => $comment['author_id'],
                    'authorName' => $comment['author_name'],
                    'authorEmail' => $comment['author_email'],
                    'authorAvatar' => $comment['author_avatar'],
                    'content' => $comment['content'],
                    'contentType' => $comment['content_type'],
                    'parentCommentId' => $comment['parent_comment_id'],
                    'mentions' => $comment['mentions'] ? json_decode('[' . $comment['mentions'] . ']', true) : [],
                    'attachments' => $comment['attachments'] ? json_decode('[' . $comment['attachments'] . ']', true) : [],
                    'reactions' => $comment['reactions'] ? json_decode('[' . $comment['reactions'] . ']', true) : [],
                    'createdAt' => $this->formatTimestampForAPI($comment['created_at']),
                    'updatedAt' => $this->formatTimestampForAPI($comment['updated_at']),
                    'editedAt' => $comment['edited_at'] ? $this->formatTimestampForAPI($comment['edited_at']) : null,
                    'isDeleted' => (bool)$comment['is_deleted'],
                    'isEdited' => (bool)$comment['is_edited'],
                    'metadata' => $comment['metadata'] ? json_decode($comment['metadata'], true) : [
                        'editCount' => 0,
                        'viewCount' => 0,
                        'isPinned' => false,
                        'isResolved' => false,
                        'priority' => 'normal',
                        'tags' => [],
                        'version' => 1
                    ]
                ];

                $formattedComments[] = $formattedComment;
            }

            // Get total count for pagination
            $countQuery = "SELECT COUNT(*) as total FROM comments WHERE task_id = :task_id AND is_deleted = FALSE";
            $countStmt = $this->db->prepare($countQuery);
            $countStmt->bindValue(':task_id', $taskId, PDO::PARAM_STR);
            $countStmt->execute();
            $totalCount = $countStmt->fetch()['total'];

            $hasMore = ($offset + $limit) < $totalCount;
            $nextCursor = $hasMore ? ($offset + $limit) : null;

            sendJSON([
                'comments' => $formattedComments,
                'totalCount' => (int)$totalCount,
                'hasMore' => $hasMore,
                'nextCursor' => $nextCursor
            ]);

        } catch (Exception $e) {
            error_log("Error fetching task comments: " . $e->getMessage());
            sendError('Failed to fetch comments', 500);
        }
    }

    /**
     * Get a single comment by ID
     */
    private function getComment($commentId) {
        try {
            $query = "
                SELECT
                    c.*,
                    cm.metadata,
                    GROUP_CONCAT(DISTINCT JSON_OBJECT('id', m.id, 'userId', m.user_id, 'userName', m.user_name, 'userEmail', m.user_email, 'startIndex', m.start_index, 'endIndex', m.end_index, 'displayName', m.display_name, 'type', m.mention_type)) as mentions,
                    GROUP_CONCAT(DISTINCT JSON_OBJECT('id', r.id, 'userId', r.user_id, 'userName', r.user_name, 'emoji', r.emoji, 'createdAt', r.created_at)) as reactions,
                    GROUP_CONCAT(DISTINCT JSON_OBJECT('id', a.id, 'fileName', a.file_name, 'fileUrl', a.file_url, 'fileType', a.file_type, 'fileSize', a.file_size, 'uploadedBy', a.uploaded_by, 'createdAt', a.created_at)) as attachments
                FROM comments c
                LEFT JOIN comment_metadata cm ON c.id = cm.comment_id
                LEFT JOIN comment_mentions m ON c.id = m.comment_id
                LEFT JOIN comment_reactions r ON c.id = r.comment_id
                LEFT JOIN comment_attachments a ON c.id = a.comment_id
                WHERE c.id = :comment_id AND c.is_deleted = FALSE
                GROUP BY c.id
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':comment_id', $commentId, PDO::PARAM_STR);
            $stmt->execute();

            $comment = $stmt->fetch();

            if (!$comment) {
                sendError('Comment not found', 404);
            }

            $formattedComment = [
                'id' => $comment['id'],
                'taskId' => $comment['task_id'],
                'authorId' => $comment['author_id'],
                'authorName' => $comment['author_name'],
                'authorEmail' => $comment['author_email'],
                'authorAvatar' => $comment['author_avatar'],
                'content' => $comment['content'],
                'contentType' => $comment['content_type'],
                'parentCommentId' => $comment['parent_comment_id'],
                'mentions' => $comment['mentions'] ? json_decode('[' . $comment['mentions'] . ']', true) : [],
                'attachments' => $comment['attachments'] ? json_decode('[' . $comment['attachments'] . ']', true) : [],
                'reactions' => $comment['reactions'] ? json_decode('[' . $comment['reactions'] . ']', true) : [],
                'createdAt' => $this->formatTimestampForAPI($comment['created_at']),
                'updatedAt' => $this->formatTimestampForAPI($comment['updated_at']),
                'editedAt' => $comment['edited_at'] ? $this->formatTimestampForAPI($comment['edited_at']) : null,
                'isDeleted' => (bool)$comment['is_deleted'],
                'isEdited' => (bool)$comment['is_edited'],
                'metadata' => $comment['metadata'] ? json_decode($comment['metadata'], true) : []
            ];

            sendJSON($formattedComment);

        } catch (Exception $e) {
            error_log("Error fetching comment: " . $e->getMessage());
            sendError('Failed to fetch comment', 500);
        }
    }

    /**
     * Create a new comment
     */
    private function createComment() {
        try {
            $data = getRequestData();

            // Validate required fields
            validateRequired($data, ['taskId', 'content', 'authorId', 'authorName', 'authorEmail']);

            // Sanitize and validate content
            $content = $this->sanitizeContent($data['content'], $data['contentType'] ?? 'plain');
            if (empty(trim(strip_tags($content)))) {
                sendError('Comment content cannot be empty', 400);
            }

            // Generate UUID for comment
            $commentId = $this->generateUUID();

            $this->db->beginTransaction();

            try {
                // Insert comment
                $query = "
                    INSERT INTO comments (
                        id, task_id, author_id, author_name, author_email, author_avatar,
                        content, content_type, parent_comment_id, created_at, updated_at
                    ) VALUES (
                        :id, :task_id, :author_id, :author_name, :author_email, :author_avatar,
                        :content, :content_type, :parent_comment_id, NOW(), NOW()
                    )
                ";

                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':id', $commentId, PDO::PARAM_STR);
                $stmt->bindValue(':task_id', $data['taskId'], PDO::PARAM_STR);
                $stmt->bindValue(':author_id', $data['authorId'], PDO::PARAM_STR);
                $stmt->bindValue(':author_name', $data['authorName'], PDO::PARAM_STR);
                $stmt->bindValue(':author_email', $data['authorEmail'], PDO::PARAM_STR);
                $stmt->bindValue(':author_avatar', $data['authorAvatar'] ?? null, PDO::PARAM_STR);
                $stmt->bindValue(':content', $content, PDO::PARAM_STR);
                $stmt->bindValue(':content_type', $data['contentType'] ?? 'plain', PDO::PARAM_STR);
                $stmt->bindValue(':parent_comment_id', $data['parentCommentId'] ?? null, PDO::PARAM_STR);
                $stmt->execute();

                // Insert mentions if any
                if (!empty($data['mentions'])) {
                    $this->insertMentions($commentId, $data['mentions']);
                }

                // Insert comment metadata
                $this->insertCommentMetadata($commentId);

                // Insert into search index
                $this->updateSearchIndex($commentId, $data['taskId'], $data['authorName'], $content, $data['mentions'] ?? []);

                // Log activity
                $this->logActivity($commentId, 'created', $data['authorId'], $data['authorName']);

                $this->db->commit();

                // Fetch the complete comment to return
                $this->getComment($commentId);

            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }

        } catch (Exception $e) {
            error_log("Error creating comment: " . $e->getMessage());
            sendError('Failed to create comment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update an existing comment
     */
    private function updateComment($commentId) {
        try {
            $data = getRequestData();

            // Validate required fields
            validateRequired($data, ['content']);

            // Sanitize content
            $content = $this->sanitizeContent($data['content'], $data['contentType'] ?? 'plain');
            if (empty(trim(strip_tags($content)))) {
                sendError('Comment content cannot be empty', 400);
            }

            $this->db->beginTransaction();

            try {
                // Update comment
                $query = "
                    UPDATE comments
                    SET content = :content,
                        content_type = :content_type,
                        is_edited = TRUE,
                        edited_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :comment_id AND is_deleted = FALSE
                ";

                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':content', $content, PDO::PARAM_STR);
                $stmt->bindValue(':content_type', $data['contentType'] ?? 'plain', PDO::PARAM_STR);
                $stmt->bindValue(':comment_id', $commentId, PDO::PARAM_STR);
                $stmt->execute();

                if ($stmt->rowCount() === 0) {
                    $this->db->rollback();
                    sendError('Comment not found or cannot be updated', 404);
                }

                // Update mentions
                if (isset($data['mentions'])) {
                    // Delete existing mentions
                    $deleteMentionsQuery = "DELETE FROM comment_mentions WHERE comment_id = :comment_id";
                    $deleteMentionsStmt = $this->db->prepare($deleteMentionsQuery);
                    $deleteMentionsStmt->bindValue(':comment_id', $commentId, PDO::PARAM_STR);
                    $deleteMentionsStmt->execute();

                    // Insert new mentions
                    if (!empty($data['mentions'])) {
                        $this->insertMentions($commentId, $data['mentions']);
                    }
                }

                // Update metadata edit count
                $updateMetadataQuery = "
                    UPDATE comment_metadata
                    SET edit_count = edit_count + 1, updated_at = NOW()
                    WHERE comment_id = :comment_id
                ";
                $updateMetadataStmt = $this->db->prepare($updateMetadataQuery);
                $updateMetadataStmt->bindValue(':comment_id', $commentId, PDO::PARAM_STR);
                $updateMetadataStmt->execute();

                // Update search index
                $commentQuery = "SELECT task_id, author_name FROM comments WHERE id = :comment_id";
                $commentStmt = $this->db->prepare($commentQuery);
                $commentStmt->bindValue(':comment_id', $commentId, PDO::PARAM_STR);
                $commentStmt->execute();
                $commentData = $commentStmt->fetch();

                if ($commentData) {
                    $this->updateSearchIndex($commentId, $commentData['task_id'], $commentData['author_name'], $content, $data['mentions'] ?? []);
                }

                // Log activity
                $this->logActivity($commentId, 'updated', $data['authorId'] ?? 'system', $data['authorName'] ?? 'System');

                $this->db->commit();

                // Return updated comment
                $this->getComment($commentId);

            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }

        } catch (Exception $e) {
            error_log("Error updating comment: " . $e->getMessage());
            sendError('Failed to update comment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Soft delete a comment
     */
    private function deleteComment($commentId) {
        try {
            $data = getRequestData();

            $this->db->beginTransaction();

            try {
                // Soft delete the comment
                $query = "
                    UPDATE comments
                    SET is_deleted = TRUE, updated_at = NOW()
                    WHERE id = :comment_id AND is_deleted = FALSE
                ";

                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':comment_id', $commentId, PDO::PARAM_STR);
                $stmt->execute();

                if ($stmt->rowCount() === 0) {
                    $this->db->rollback();
                    sendError('Comment not found or already deleted', 404);
                }

                // Log activity
                $this->logActivity($commentId, 'deleted', $data['deletedBy'] ?? 'system', $data['deletedByName'] ?? 'System');

                $this->db->commit();

                sendJSON(['message' => 'Comment deleted successfully']);

            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }

        } catch (Exception $e) {
            error_log("Error deleting comment: " . $e->getMessage());
            sendError('Failed to delete comment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Add a reaction to a comment
     */
    private function addReaction($commentId) {
        try {
            $data = getRequestData();
            validateRequired($data, ['emoji', 'userId', 'userName']);

            $reactionId = $this->generateUUID();

            $query = "
                INSERT INTO comment_reactions (id, comment_id, user_id, user_name, emoji, created_at)
                VALUES (:id, :comment_id, :user_id, :user_name, :emoji, NOW())
                ON DUPLICATE KEY UPDATE created_at = NOW()
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $reactionId, PDO::PARAM_STR);
            $stmt->bindValue(':comment_id', $commentId, PDO::PARAM_STR);
            $stmt->bindValue(':user_id', $data['userId'], PDO::PARAM_STR);
            $stmt->bindValue(':user_name', $data['userName'], PDO::PARAM_STR);
            $stmt->bindValue(':emoji', $data['emoji'], PDO::PARAM_STR);
            $stmt->execute();

            sendJSON([
                'id' => $reactionId,
                'commentId' => $commentId,
                'userId' => $data['userId'],
                'userName' => $data['userName'],
                'emoji' => $data['emoji'],
                'createdAt' => $this->formatTimestampForAPI(date('Y-m-d H:i:s'))
            ]);

        } catch (Exception $e) {
            error_log("Error adding reaction: " . $e->getMessage());
            sendError('Failed to add reaction', 500);
        }
    }

    /**
     * Remove a reaction from a comment
     */
    private function removeReaction($commentId, $reactionId) {
        try {
            $query = "DELETE FROM comment_reactions WHERE id = :reaction_id AND comment_id = :comment_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':reaction_id', $reactionId, PDO::PARAM_STR);
            $stmt->bindValue(':comment_id', $commentId, PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                sendError('Reaction not found', 404);
            }

            sendJSON(['message' => 'Reaction removed successfully']);

        } catch (Exception $e) {
            error_log("Error removing reaction: " . $e->getMessage());
            sendError('Failed to remove reaction', 500);
        }
    }

    /**
     * Helper: Insert mentions for a comment
     */
    private function insertMentions($commentId, $mentions) {
        if (empty($mentions)) return;

        $query = "
            INSERT INTO comment_mentions (
                id, comment_id, user_id, user_name, user_email,
                start_index, end_index, display_name, mention_type, created_at
            ) VALUES (
                :id, :comment_id, :user_id, :user_name, :user_email,
                :start_index, :end_index, :display_name, :mention_type, NOW()
            )
        ";

        $stmt = $this->db->prepare($query);

        foreach ($mentions as $mention) {
            $mentionId = $this->generateUUID();
            $stmt->bindValue(':id', $mentionId, PDO::PARAM_STR);
            $stmt->bindValue(':comment_id', $commentId, PDO::PARAM_STR);
            $stmt->bindValue(':user_id', $mention['userId'], PDO::PARAM_STR);
            $stmt->bindValue(':user_name', $mention['userName'], PDO::PARAM_STR);
            $stmt->bindValue(':user_email', $mention['userEmail'], PDO::PARAM_STR);
            $stmt->bindValue(':start_index', $mention['startIndex'], PDO::PARAM_INT);
            $stmt->bindValue(':end_index', $mention['endIndex'], PDO::PARAM_INT);
            $stmt->bindValue(':display_name', $mention['displayName'] ?? $mention['userName'], PDO::PARAM_STR);
            $stmt->bindValue(':mention_type', $mention['type'] ?? 'user', PDO::PARAM_STR);
            $stmt->execute();
        }
    }

    /**
     * Helper: Insert default comment metadata
     */
    private function insertCommentMetadata($commentId) {
        $query = "
            INSERT INTO comment_metadata (
                comment_id, edit_count, view_count, is_pinned, is_resolved,
                priority, version, created_at, updated_at
            ) VALUES (
                :comment_id, 0, 0, FALSE, FALSE, 'normal', 1, NOW(), NOW()
            )
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':comment_id', $commentId, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * Helper: Update search index
     */
    private function updateSearchIndex($commentId, $taskId, $authorName, $content, $mentions) {
        $contentText = strip_tags($content);
        $mentionedUsers = '';
        $tags = '';

        if (!empty($mentions)) {
            $mentionNames = array_column($mentions, 'userName');
            $mentionedUsers = implode(' ', $mentionNames);
        }

        $query = "
            INSERT INTO comment_search_index (
                comment_id, task_id, author_name, content_text, mentioned_users, tags, created_at, updated_at
            ) VALUES (
                :comment_id, :task_id, :author_name, :content_text, :mentioned_users, :tags, NOW(), NOW()
            ) ON DUPLICATE KEY UPDATE
                content_text = VALUES(content_text),
                mentioned_users = VALUES(mentioned_users),
                tags = VALUES(tags),
                updated_at = NOW()
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':comment_id', $commentId, PDO::PARAM_STR);
        $stmt->bindValue(':task_id', $taskId, PDO::PARAM_STR);
        $stmt->bindValue(':author_name', $authorName, PDO::PARAM_STR);
        $stmt->bindValue(':content_text', $contentText, PDO::PARAM_STR);
        $stmt->bindValue(':mentioned_users', $mentionedUsers, PDO::PARAM_STR);
        $stmt->bindValue(':tags', $tags, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * Helper: Log activity for audit trail
     */
    private function logActivity($commentId, $actionType, $performedBy, $performedByName, $oldValues = null, $newValues = null) {
        $activityId = $this->generateUUID();

        $query = "
            INSERT INTO comment_activity_log (
                id, comment_id, action_type, performed_by, performed_by_name,
                old_values, new_values, ip_address, user_agent, created_at
            ) VALUES (
                :id, :comment_id, :action_type, :performed_by, :performed_by_name,
                :old_values, :new_values, :ip_address, :user_agent, NOW()
            )
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $activityId, PDO::PARAM_STR);
        $stmt->bindValue(':comment_id', $commentId, PDO::PARAM_STR);
        $stmt->bindValue(':action_type', $actionType, PDO::PARAM_STR);
        $stmt->bindValue(':performed_by', $performedBy, PDO::PARAM_STR);
        $stmt->bindValue(':performed_by_name', $performedByName, PDO::PARAM_STR);
        $stmt->bindValue(':old_values', $oldValues ? json_encode($oldValues) : null, PDO::PARAM_STR);
        $stmt->bindValue(':new_values', $newValues ? json_encode($newValues) : null, PDO::PARAM_STR);
        $stmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? null, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * Helper: Sanitize content based on content type
     */
    private function sanitizeContent($content, $contentType) {
        switch ($contentType) {
            case 'rich':
                // For rich content, allow specific HTML tags and sanitize properly
                $allowedTags = '<b><i><u><s><strong><em><ul><ol><li><a><code><blockquote><br><span>';
                $content = strip_tags($content, $allowedTags);

                // Additional sanitization for attributes
                $content = preg_replace('/(<a[^>]*?)(?:(?!href|class|data-mention)[a-zA-Z-]+="[^"]*")/i', '$1', $content);
                break;

            case 'markdown':
                // For markdown, escape HTML but allow markdown syntax
                $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
                break;

            case 'plain':
            default:
                // For plain text, escape all HTML
                $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
                break;
        }

        return trim($content);
    }

    /**
     * Helper: Format MySQL timestamp to ISO 8601 format for API responses
     */
    private function formatTimestampForAPI($timestamp) {
        if (!$timestamp) {
            return null;
        }
        
        // Convert MySQL timestamp to ISO 8601 format
        $date = new DateTime($timestamp);
        return $date->format('c'); // 'c' format gives ISO 8601 with timezone
    }

    /**
     * Helper: Generate UUID v4
     */
    private function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

// Initialize and handle the request
$api = new CommentsAPI();
$api->handleRequest();
?>