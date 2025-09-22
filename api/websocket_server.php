<?php
/**
 * Real-time WebSocket Server for TaskMaster
 * Handles real-time notifications using ReactPHP/Ratchet
 */

require_once __DIR__ . '/config.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

class NotificationServer implements MessageComponentInterface {
    protected $clients;
    protected $rooms;
    protected $userConnections;
    protected $db;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->rooms = [];
        $this->userConnections = [];

        // Initialize database connection
        $database = new Database();
        $this->db = $database->getConnection();

        echo "🚀 TaskMaster WebSocket Server Started\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "📱 New connection! ({$conn->resourceId})\n";

        // Send welcome message
        $conn->send(json_encode([
            'type' => 'system',
            'message' => 'Connected to TaskMaster real-time server',
            'timestamp' => date('c')
        ]));
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        try {
            $data = json_decode($msg, true);

            if (!$data || !isset($data['type'])) {
                $this->sendError($from, 'Invalid message format');
                return;
            }

            echo "📨 Message received: {$data['type']}\n";

            switch ($data['type']) {
                case 'user:authenticate':
                    $this->handleUserAuthentication($from, $data);
                    break;

                case 'task:join_room':
                    $this->handleJoinTaskRoom($from, $data);
                    break;

                case 'task:leave_room':
                    $this->handleLeaveTaskRoom($from, $data);
                    break;

                case 'task:update':
                    $this->handleTaskUpdate($from, $data);
                    break;

                case 'notification:send':
                    $this->handleSendNotification($from, $data);
                    break;

                case 'comment:new':
                    $this->handleNewComment($from, $data);
                    break;

                case 'user:typing':
                    $this->handleUserTyping($from, $data);
                    break;

                default:
                    $this->sendError($from, 'Unknown message type');
            }
        } catch (Exception $e) {
            echo "❌ Error processing message: " . $e->getMessage() . "\n";
            $this->sendError($from, 'Server error processing message');
        }
    }

    public function onClose(ConnectionInterface $conn) {
        // Remove user from all rooms
        $this->removeUserFromAllRooms($conn);

        // Remove from clients
        $this->clients->detach($conn);

        echo "🔌 Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "❌ An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    // Authentication handling
    private function handleUserAuthentication($conn, $data) {
        if (!isset($data['userId']) || !isset($data['email'])) {
            $this->sendError($conn, 'Missing user credentials');
            return;
        }

        // Store user connection mapping
        $this->userConnections[$data['userId']] = $conn;
        $conn->userId = $data['userId'];
        $conn->userEmail = $data['email'];

        // Send confirmation
        $conn->send(json_encode([
            'type' => 'user:authenticated',
            'userId' => $data['userId'],
            'timestamp' => date('c')
        ]));

        // Broadcast user online status
        $this->broadcastToAll([
            'type' => 'user:status_changed',
            'userId' => $data['userId'],
            'status' => 'online',
            'timestamp' => date('c')
        ], $conn);

        echo "✅ User authenticated: {$data['email']}\n";
    }

    // Task room management
    private function handleJoinTaskRoom($conn, $data) {
        if (!isset($data['taskId']) || !isset($conn->userId)) {
            $this->sendError($conn, 'Missing taskId or user not authenticated');
            return;
        }

        $taskId = $data['taskId'];

        // Create room if it doesn't exist
        if (!isset($this->rooms[$taskId])) {
            $this->rooms[$taskId] = [];
        }

        // Add user to room
        $this->rooms[$taskId][$conn->userId] = $conn;

        // Notify room members
        $this->broadcastToRoom($taskId, [
            'type' => 'user:joined_task',
            'taskId' => $taskId,
            'userId' => $conn->userId,
            'userEmail' => $conn->userEmail,
            'timestamp' => date('c')
        ], $conn);

        echo "🏠 User {$conn->userId} joined task room: {$taskId}\n";
    }

    private function handleLeaveTaskRoom($conn, $data) {
        if (!isset($data['taskId']) || !isset($conn->userId)) {
            return;
        }

        $taskId = $data['taskId'];

        if (isset($this->rooms[$taskId][$conn->userId])) {
            unset($this->rooms[$taskId][$conn->userId]);

            // Notify remaining room members
            $this->broadcastToRoom($taskId, [
                'type' => 'user:left_task',
                'taskId' => $taskId,
                'userId' => $conn->userId,
                'timestamp' => date('c')
            ]);
        }

        echo "🚪 User {$conn->userId} left task room: {$taskId}\n";
    }

    // Task update handling
    private function handleTaskUpdate($conn, $data) {
        if (!isset($data['task']) || !isset($conn->userId)) {
            $this->sendError($conn, 'Missing task data or user not authenticated');
            return;
        }

        $task = $data['task'];
        $taskId = $task['task_id'] ?? $task['id'] ?? null;

        if (!$taskId) {
            $this->sendError($conn, 'Missing task ID');
            return;
        }

        // Store task update in database
        $this->logTaskUpdate($taskId, $conn->userId, $task);

        // Broadcast to task room members
        $this->broadcastToRoom($taskId, [
            'type' => 'task:updated',
            'task' => $task,
            'updatedBy' => $conn->userId,
            'timestamp' => date('c')
        ], $conn);

        // Send notification to task assignees
        $this->sendTaskUpdateNotifications($task, $conn->userId);

        echo "📝 Task updated: {$taskId} by {$conn->userId}\n";
    }

    // Comment handling
    private function handleNewComment($conn, $data) {
        if (!isset($data['comment']) || !isset($conn->userId)) {
            $this->sendError($conn, 'Missing comment data or user not authenticated');
            return;
        }

        $comment = $data['comment'];
        $taskId = $comment['taskId'];

        // Store comment in database
        $commentId = $this->storeComment($comment);
        if (!$commentId) {
            $this->sendError($conn, 'Failed to store comment');
            return;
        }

        $comment['id'] = $commentId;

        // Broadcast to task room
        $this->broadcastToRoom($taskId, [
            'type' => 'comment:new',
            'comment' => $comment,
            'timestamp' => date('c')
        ]);

        // Handle mentions and send notifications
        $this->processMentions($comment, $conn->userId);

        echo "💬 New comment on task {$taskId} by {$conn->userId}\n";
    }

    // Notification sending
    private function handleSendNotification($conn, $data) {
        if (!isset($data['notification']) || !isset($conn->userId)) {
            $this->sendError($conn, 'Missing notification data');
            return;
        }

        $notification = $data['notification'];

        // Send to specific user if userId provided
        if (isset($notification['userId'])) {
            $this->sendNotificationToUser($notification['userId'], $notification);
        } else {
            // Broadcast to all connected users
            $this->broadcastNotification($notification);
        }

        echo "🔔 Notification sent by {$conn->userId}\n";
    }

    // User typing indicators
    private function handleUserTyping($conn, $data) {
        if (!isset($data['taskId']) || !isset($conn->userId)) {
            return;
        }

        $this->broadcastToRoom($data['taskId'], [
            'type' => 'user:typing',
            'taskId' => $data['taskId'],
            'userId' => $conn->userId,
            'isTyping' => $data['isTyping'] ?? true,
            'timestamp' => date('c')
        ], $conn);
    }

    // Helper methods
    private function broadcastToRoom($taskId, $message, $excludeConn = null) {
        if (!isset($this->rooms[$taskId])) {
            return;
        }

        foreach ($this->rooms[$taskId] as $conn) {
            if ($conn !== $excludeConn) {
                $conn->send(json_encode($message));
            }
        }
    }

    private function broadcastToAll($message, $excludeConn = null) {
        foreach ($this->clients as $conn) {
            if ($conn !== $excludeConn) {
                $conn->send(json_encode($message));
            }
        }
    }

    private function sendNotificationToUser($userId, $notification) {
        if (isset($this->userConnections[$userId])) {
            $this->userConnections[$userId]->send(json_encode([
                'type' => 'notification:received',
                'notification' => $notification,
                'timestamp' => date('c')
            ]));
        }
    }

    private function broadcastNotification($notification) {
        $message = [
            'type' => 'notification:broadcast',
            'notification' => $notification,
            'timestamp' => date('c')
        ];

        foreach ($this->clients as $conn) {
            $conn->send(json_encode($message));
        }
    }

    private function sendError($conn, $message) {
        $conn->send(json_encode([
            'type' => 'error',
            'message' => $message,
            'timestamp' => date('c')
        ]));
    }

    private function removeUserFromAllRooms($conn) {
        if (!isset($conn->userId)) {
            return;
        }

        foreach ($this->rooms as $taskId => $roomUsers) {
            if (isset($roomUsers[$conn->userId])) {
                unset($this->rooms[$taskId][$conn->userId]);

                // Notify remaining room members
                $this->broadcastToRoom($taskId, [
                    'type' => 'user:left_task',
                    'taskId' => $taskId,
                    'userId' => $conn->userId,
                    'timestamp' => date('c')
                ]);
            }
        }

        // Remove from user connections
        if (isset($this->userConnections[$conn->userId])) {
            unset($this->userConnections[$conn->userId]);
        }

        // Broadcast user offline status
        $this->broadcastToAll([
            'type' => 'user:status_changed',
            'userId' => $conn->userId,
            'status' => 'offline',
            'timestamp' => date('c')
        ]);
    }

    // Database operations
    private function logTaskUpdate($taskId, $userId, $taskData) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO task_updates (task_id, updated_by, update_data, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$taskId, $userId, json_encode($taskData)]);
        } catch (PDOException $e) {
            echo "❌ Failed to log task update: " . $e->getMessage() . "\n";
        }
    }

    private function storeComment($comment) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO comments (task_id, author_id, author_name, author_email, content, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $comment['taskId'],
                $comment['authorId'],
                $comment['authorName'],
                $comment['authorEmail'],
                $comment['content']
            ]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            echo "❌ Failed to store comment: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function processMentions($comment, $authorId) {
        // Extract mentions from comment content
        preg_match_all('/@(\w+)/', $comment['content'], $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $username) {
                // Send mention notification
                $this->sendMentionNotification($comment, $username, $authorId);
            }
        }
    }

    private function sendMentionNotification($comment, $username, $authorId) {
        // Find user by username/email
        try {
            $stmt = $this->db->prepare("
                SELECT id, name, email FROM avatar_profiles
                WHERE name LIKE ? OR email LIKE ?
                LIMIT 1
            ");
            $stmt->execute(["%{$username}%", "%{$username}%"]);
            $user = $stmt->fetch();

            if ($user) {
                $notification = [
                    'type' => 'mention',
                    'taskId' => $comment['taskId'],
                    'commentId' => $comment['id'],
                    'mentionedUser' => $user,
                    'authorId' => $authorId,
                    'content' => $comment['content'],
                    'timestamp' => date('c')
                ];

                $this->sendNotificationToUser($user['id'], $notification);
            }
        } catch (PDOException $e) {
            echo "❌ Failed to process mention: " . $e->getMessage() . "\n";
        }
    }

    private function sendTaskUpdateNotifications($task, $updatedBy) {
        // Get task assignees
        $owners = $task['current_owners'] ?? '';
        if (empty($owners)) {
            return;
        }

        $ownerList = array_map('trim', explode(',', $owners));

        foreach ($ownerList as $owner) {
            if (empty($owner) || $owner === $updatedBy) {
                continue;
            }

            $notification = [
                'type' => 'task_update',
                'taskId' => $task['task_id'],
                'taskTitle' => $task['action_item'],
                'updatedBy' => $updatedBy,
                'timestamp' => date('c')
            ];

            $this->sendNotificationToUser($owner, $notification);
        }
    }
}

// CLI script to run the WebSocket server
if (php_sapi_name() === 'cli') {
    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new NotificationServer()
            )
        ),
        8080
    );

    echo "🚀 TaskMaster WebSocket Server running on port 8080\n";
    echo "📱 Waiting for connections...\n";

    $server->run();
}
?>