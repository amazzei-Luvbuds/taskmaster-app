<?php
/**
 * Real-time Notifications API Endpoint
 * Handles notification CRUD operations and integrates with WebSocket server
 */

require_once __DIR__ . '/config.php';

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit;
}

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Initialize database
$database = new Database();
$db = $database->getConnection();

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];
$pathParts = explode('/', trim(parse_url($path, PHP_URL_PATH), '/'));

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($db, $pathParts);
            break;
        case 'POST':
            handlePostRequest($db, $pathParts);
            break;
        case 'PUT':
            handlePutRequest($db, $pathParts);
            break;
        case 'DELETE':
            handleDeleteRequest($db, $pathParts);
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Notifications API Error: " . $e->getMessage());
    sendError('Internal server error', 500);
}

/**
 * Handle GET requests
 */
function handleGetRequest($db, $pathParts) {
    $endpoint = $pathParts[1] ?? '';

    switch ($endpoint) {
        case 'notifications':
            getUserNotifications($db);
            break;
        case 'unread-count':
            getUnreadCount($db);
            break;
        case 'preferences':
            getNotificationPreferences($db);
            break;
        case 'online-users':
            getOnlineUsers($db);
            break;
        default:
            sendError('Invalid endpoint', 404);
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($db, $pathParts) {
    $endpoint = $pathParts[1] ?? '';
    $data = getRequestData();

    switch ($endpoint) {
        case 'send':
            sendNotification($db, $data);
            break;
        case 'comment':
            handleCommentNotification($db, $data);
            break;
        case 'mention':
            handleMentionNotification($db, $data);
            break;
        case 'task-update':
            handleTaskUpdateNotification($db, $data);
            break;
        default:
            sendError('Invalid endpoint', 404);
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($db, $pathParts) {
    $endpoint = $pathParts[1] ?? '';
    $data = getRequestData();

    switch ($endpoint) {
        case 'mark-read':
            markNotificationsAsRead($db, $data);
            break;
        case 'preferences':
            updateNotificationPreferences($db, $data);
            break;
        case 'presence':
            updateUserPresence($db, $data);
            break;
        default:
            sendError('Invalid endpoint', 404);
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($db, $pathParts) {
    $endpoint = $pathParts[1] ?? '';
    $id = $pathParts[2] ?? '';

    switch ($endpoint) {
        case 'notification':
            deleteNotification($db, $id);
            break;
        default:
            sendError('Invalid endpoint', 404);
    }
}

/**
 * Get user notifications
 */
function getUserNotifications($db) {
    $userId = $_GET['userId'] ?? '';
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = (int)($_GET['offset'] ?? 0);

    if (empty($userId)) {
        sendError('Missing userId parameter', 400);
    }

    try {
        $stmt = $db->prepare("
            SELECT
                n.*,
                CASE
                    WHEN n.comment_id IS NOT NULL THEN c.content
                    ELSE NULL
                END as comment_content
            FROM notifications n
            LEFT JOIN comments c ON n.comment_id = c.id
            WHERE n.recipient_id = ?
            ORDER BY n.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        $notifications = $stmt->fetchAll();

        sendJSON([
            'notifications' => $notifications,
            'total' => getNotificationCount($db, $userId),
            'unread' => getUnreadCount($db, $userId)
        ]);
    } catch (PDOException $e) {
        error_log("Error fetching notifications: " . $e->getMessage());
        sendError('Failed to fetch notifications', 500);
    }
}

/**
 * Get unread notification count
 */
function getUnreadCount($db, $userId = null) {
    $userId = $userId ?? $_GET['userId'] ?? '';

    if (empty($userId)) {
        sendError('Missing userId parameter', 400);
    }

    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM notifications
            WHERE recipient_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();

        sendJSON(['unread_count' => (int)$result['count']]);
    } catch (PDOException $e) {
        error_log("Error fetching unread count: " . $e->getMessage());
        sendError('Failed to fetch unread count', 500);
    }
}

/**
 * Get notification preferences
 */
function getNotificationPreferences($db) {
    $userId = $_GET['userId'] ?? '';

    if (empty($userId)) {
        sendError('Missing userId parameter', 400);
    }

    try {
        $stmt = $db->prepare("
            SELECT * FROM notification_preferences
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $preferences = $stmt->fetch();

        if (!$preferences) {
            // Create default preferences
            $defaultPrefs = [
                'user_id' => $userId,
                'user_email' => $userId, // Assume userId is email for now
                'email_notifications' => true,
                'push_notifications' => true,
                'mention_notifications' => true,
                'comment_notifications' => true,
                'task_update_notifications' => true,
                'deadline_notifications' => true,
                'digest_frequency' => 'immediate',
                'timezone' => 'UTC'
            ];

            $stmt = $db->prepare("
                INSERT INTO notification_preferences
                (user_id, user_email, email_notifications, push_notifications,
                 mention_notifications, comment_notifications, task_update_notifications,
                 deadline_notifications, digest_frequency, timezone)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $defaultPrefs['user_id'],
                $defaultPrefs['user_email'],
                $defaultPrefs['email_notifications'],
                $defaultPrefs['push_notifications'],
                $defaultPrefs['mention_notifications'],
                $defaultPrefs['comment_notifications'],
                $defaultPrefs['task_update_notifications'],
                $defaultPrefs['deadline_notifications'],
                $defaultPrefs['digest_frequency'],
                $defaultPrefs['timezone']
            ]);

            $preferences = $defaultPrefs;
        }

        sendJSON($preferences);
    } catch (PDOException $e) {
        error_log("Error fetching preferences: " . $e->getMessage());
        sendError('Failed to fetch preferences', 500);
    }
}

/**
 * Get online users
 */
function getOnlineUsers($db) {
    try {
        $stmt = $db->prepare("
            SELECT user_id, user_email, user_name, status, current_task_id, is_typing, typing_task_id
            FROM user_presence
            WHERE status IN ('online', 'away')
            AND last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ORDER BY last_seen DESC
        ");
        $stmt->execute();
        $users = $stmt->fetchAll();

        sendJSON(['online_users' => $users]);
    } catch (PDOException $e) {
        error_log("Error fetching online users: " . $e->getMessage());
        sendError('Failed to fetch online users', 500);
    }
}

/**
 * Send a notification
 */
function sendNotification($db, $data) {
    validateRequired($data, ['type', 'recipient_id', 'sender_id', 'title', 'message']);

    try {
        $stmt = $db->prepare("
            INSERT INTO notifications
            (type, recipient_id, recipient_email, sender_id, sender_name, sender_email,
             task_id, comment_id, title, message, action_url, metadata)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['type'],
            $data['recipient_id'],
            $data['recipient_email'] ?? $data['recipient_id'],
            $data['sender_id'],
            $data['sender_name'] ?? '',
            $data['sender_email'] ?? $data['sender_id'],
            $data['task_id'] ?? null,
            $data['comment_id'] ?? null,
            $data['title'],
            $data['message'],
            $data['action_url'] ?? null,
            isset($data['metadata']) ? json_encode($data['metadata']) : null
        ]);

        $notificationId = $db->lastInsertId();

        // Send real-time notification via WebSocket
        triggerWebSocketNotification($data);

        // Send email if preferences allow
        if ($data['send_email'] ?? true) {
            sendEmailNotification($data);
        }

        sendJSON(['notification_id' => $notificationId, 'status' => 'sent']);
    } catch (PDOException $e) {
        error_log("Error sending notification: " . $e->getMessage());
        sendError('Failed to send notification', 500);
    }
}

/**
 * Handle comment notifications
 */
function handleCommentNotification($db, $data) {
    validateRequired($data, ['comment', 'task_title']);

    $comment = $data['comment'];
    $taskTitle = $data['task_title'];

    // Create notification for comment
    $notification = [
        'type' => 'comment',
        'recipient_id' => $data['recipient_id'] ?? 'task_assignee',
        'recipient_email' => $data['recipient_email'] ?? '',
        'sender_id' => $comment['authorId'],
        'sender_name' => $comment['authorName'],
        'sender_email' => $comment['authorEmail'],
        'task_id' => $comment['taskId'],
        'comment_id' => $comment['id'] ?? null,
        'title' => "New comment in \"{$taskTitle}\"",
        'message' => truncateContent($comment['content']),
        'action_url' => "/tasks/{$comment['taskId']}#comment-{$comment['id']}",
        'metadata' => ['comment' => $comment]
    ];

    sendNotification($db, $notification);
}

/**
 * Handle mention notifications
 */
function handleMentionNotification($db, $data) {
    validateRequired($data, ['comment', 'task_title', 'mentions']);

    $comment = $data['comment'];
    $taskTitle = $data['task_title'];
    $mentions = $data['mentions'];

    foreach ($mentions as $mention) {
        $notification = [
            'type' => 'mention',
            'recipient_id' => $mention['userId'],
            'recipient_email' => $mention['userEmail'],
            'sender_id' => $comment['authorId'],
            'sender_name' => $comment['authorName'],
            'sender_email' => $comment['authorEmail'],
            'task_id' => $comment['taskId'],
            'comment_id' => $comment['id'] ?? null,
            'title' => "{$comment['authorName']} mentioned you in \"{$taskTitle}\"",
            'message' => truncateContent($comment['content']),
            'action_url' => "/tasks/{$comment['taskId']}#comment-{$comment['id']}",
            'metadata' => ['mention' => $mention, 'comment' => $comment]
        ];

        sendNotification($db, $notification);
    }
}

/**
 * Handle task update notifications
 */
function handleTaskUpdateNotification($db, $data) {
    validateRequired($data, ['task', 'updated_by']);

    $task = $data['task'];
    $updatedBy = $data['updated_by'];

    // Get task assignees
    $owners = $task['current_owners'] ?? '';
    if (empty($owners)) {
        sendJSON(['message' => 'No assignees to notify']);
        return;
    }

    $ownerList = array_map('trim', explode(',', $owners));

    foreach ($ownerList as $owner) {
        if (empty($owner) || $owner === $updatedBy) {
            continue;
        }

        $notification = [
            'type' => 'task_update',
            'recipient_id' => $owner,
            'recipient_email' => $owner,
            'sender_id' => $updatedBy,
            'sender_name' => $updatedBy,
            'sender_email' => $updatedBy,
            'task_id' => $task['task_id'],
            'title' => "Task updated: {$task['action_item']}",
            'message' => "Task \"{$task['action_item']}\" has been updated",
            'action_url' => "/tasks/{$task['task_id']}",
            'metadata' => ['task' => $task, 'changes' => $data['changes'] ?? []]
        ];

        sendNotification($db, $notification);
    }

    sendJSON(['message' => 'Task update notifications sent']);
}

/**
 * Mark notifications as read
 */
function markNotificationsAsRead($db, $data) {
    validateRequired($data, ['user_id', 'notification_ids']);

    try {
        $placeholders = str_repeat('?,', count($data['notification_ids']) - 1) . '?';
        $stmt = $db->prepare("
            UPDATE notifications
            SET is_read = TRUE, read_at = NOW()
            WHERE recipient_id = ? AND id IN ($placeholders)
        ");

        $params = array_merge([$data['user_id']], $data['notification_ids']);
        $stmt->execute($params);

        sendJSON(['updated' => $stmt->rowCount()]);
    } catch (PDOException $e) {
        error_log("Error marking notifications as read: " . $e->getMessage());
        sendError('Failed to update notifications', 500);
    }
}

/**
 * Update notification preferences
 */
function updateNotificationPreferences($db, $data) {
    validateRequired($data, ['user_id']);

    try {
        $fields = [];
        $values = [];

        $allowedFields = [
            'email_notifications', 'push_notifications', 'mention_notifications',
            'comment_notifications', 'task_update_notifications', 'deadline_notifications',
            'digest_frequency', 'quiet_hours_start', 'quiet_hours_end', 'timezone'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            sendError('No valid fields to update', 400);
        }

        $values[] = $data['user_id'];
        $sql = "UPDATE notification_preferences SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE user_id = ?";

        $stmt = $db->prepare($sql);
        $stmt->execute($values);

        sendJSON(['message' => 'Preferences updated successfully']);
    } catch (PDOException $e) {
        error_log("Error updating preferences: " . $e->getMessage());
        sendError('Failed to update preferences', 500);
    }
}

/**
 * Update user presence
 */
function updateUserPresence($db, $data) {
    validateRequired($data, ['user_id', 'status']);

    try {
        $stmt = $db->prepare("
            INSERT INTO user_presence (user_id, user_email, user_name, status, current_task_id, last_seen)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                current_task_id = VALUES(current_task_id),
                last_seen = NOW(),
                updated_at = NOW()
        ");

        $stmt->execute([
            $data['user_id'],
            $data['user_email'] ?? $data['user_id'],
            $data['user_name'] ?? '',
            $data['status'],
            $data['current_task_id'] ?? null
        ]);

        // Broadcast presence update via WebSocket
        triggerWebSocketPresenceUpdate($data);

        sendJSON(['message' => 'Presence updated']);
    } catch (PDOException $e) {
        error_log("Error updating presence: " . $e->getMessage());
        sendError('Failed to update presence', 500);
    }
}

/**
 * Delete a notification
 */
function deleteNotification($db, $id) {
    if (empty($id)) {
        sendError('Missing notification ID', 400);
    }

    try {
        $stmt = $db->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([$id]);

        sendJSON(['message' => 'Notification deleted']);
    } catch (PDOException $e) {
        error_log("Error deleting notification: " . $e->getMessage());
        sendError('Failed to delete notification', 500);
    }
}

/**
 * Helper Functions
 */

function getNotificationCount($db, $userId) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE recipient_id = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetch()['count'];
}

function truncateContent($content, $maxLength = 100) {
    $plainText = strip_tags($content);
    return strlen($plainText) > $maxLength
        ? substr($plainText, 0, $maxLength) . '...'
        : $plainText;
}

function triggerWebSocketNotification($notification) {
    // In a real implementation, this would send the notification to the WebSocket server
    // For now, we'll simulate it by logging
    error_log("WebSocket notification triggered: " . json_encode($notification));
}

function triggerWebSocketPresenceUpdate($presenceData) {
    // In a real implementation, this would broadcast presence update via WebSocket
    error_log("WebSocket presence update: " . json_encode($presenceData));
}

function sendEmailNotification($notification) {
    // Email notification logic would go here
    // For now, we'll just log it
    error_log("Email notification sent: " . $notification['title']);
}

?>