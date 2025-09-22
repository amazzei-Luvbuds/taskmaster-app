<?php
/**
 * Global Pin Management API
 * Dedicated endpoints for managing global pins with role-based authorization
 */

require_once 'config.php';
require_once 'auth/middleware.php';

// Enable CORS
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-User-ID');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Initialize authentication middleware
$auth = new AuthMiddleware($db);

function sendError($message, $code = 400, $details = null) {
    http_response_code($code);
    $response = [
        'success' => false,
        'error' => $message,
        'code' => $code
    ];

    if ($details) {
        $response['details'] = $details;
    }

    echo json_encode($response);
    exit();
}

function sendSuccess($data = null, $message = null, $code = 200) {
    http_response_code($code);
    $response = [
        'success' => true
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    if ($message) {
        $response['message'] = $message;
    }

    echo json_encode($response);
    exit();
}

function logAuditEvent($db, $user, $action, $taskId, $details = []) {
    try {
        // Create audit_logs table if it doesn't exist
        $db->exec("
            CREATE TABLE IF NOT EXISTS audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(255) NOT NULL,
                user_name VARCHAR(255) NOT NULL,
                action VARCHAR(50) NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id VARCHAR(255) NOT NULL,
                details JSON,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                INDEX idx_user_id (user_id),
                INDEX idx_action (action),
                INDEX idx_entity (entity_type, entity_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB
        ");

        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, user_name, action, entity_type, entity_id, details, ip_address, user_agent)
            VALUES (?, ?, ?, 'global_pin', ?, ?, ?, ?)
        ");

        $stmt->execute([
            $user['userId'],
            $user['name'],
            $action,
            $taskId,
            json_encode($details),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        // Log audit failure but don't break the main operation
        error_log("Audit logging failed: " . $e->getMessage());
    }
}

function validatePinData($data, $isUpdate = false) {
    $errors = [];

    if (!$isUpdate && (!isset($data['taskId']) || empty(trim($data['taskId'])))) {
        $errors[] = 'taskId is required and cannot be empty';
    }

    if (isset($data['priority'])) {
        if (!is_numeric($data['priority']) || $data['priority'] < 1 || $data['priority'] > 10) {
            $errors[] = 'priority must be between 1 and 10';
        }
    } else if (!$isUpdate) {
        $errors[] = 'priority is required for global pins';
    }

    if (isset($data['reason']) && strlen($data['reason']) > 1000) {
        $errors[] = 'reason must be 1000 characters or less';
    }

    return $errors;
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Extract taskId from URL if present
// More robust routing: look for 'global' keyword and get the next part as taskId
$taskId = null;
$globalIndex = array_search('global', $pathParts);
if ($globalIndex !== false && isset($pathParts[$globalIndex + 1])) {
    $taskId = $pathParts[$globalIndex + 1];
}

// Authenticate user (requireAuth will call authenticate internally if needed)
$currentUser = $auth->authenticate();
$auth->requireAuth();

try {
    if ($method === 'GET' && !$taskId) {
        // GET /api/pins/global - List all global pins
        $auth->requirePermission('view');

        // Get query parameters for filtering
        $userId = $_GET['user'] ?? null;
        $priority = $_GET['priority'] ?? null;
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        $limit = min(max((int)($_GET['limit'] ?? 100), 1), 500); // Between 1 and 500 results
        $offset = max((int)($_GET['offset'] ?? 0), 0); // Non-negative offset

        // Build query with filters
        $whereConditions = ["t.pin_type = 'global'"];
        $params = [];

        if ($userId) {
            $whereConditions[] = "t.pinned_by = ?";
            $params[] = $userId;
        }

        if ($priority) {
            $whereConditions[] = "t.pin_priority = ?";
            $params[] = $priority;
        }

        if ($dateFrom) {
            $whereConditions[] = "t.pinned_at >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $whereConditions[] = "t.pinned_at <= ?";
            $params[] = $dateTo;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $stmt = $db->prepare("
            SELECT
                t.task_id,
                t.action_item,
                t.department,
                t.status,
                t.pin_type,
                t.pinned_by,
                t.pinned_at,
                t.pin_priority,
                t.pin_reason,
                d.color as department_color
            FROM tasks t
            LEFT JOIN departments d ON t.department = d.name
            WHERE {$whereClause}
            ORDER BY t.pin_priority ASC, t.pinned_at DESC
            LIMIT ? OFFSET ?
        ");

        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        $globalPins = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format response
        $formattedPins = array_map(function($pin) {
            return [
                'taskId' => $pin['task_id'],
                'actionItem' => $pin['action_item'],
                'department' => $pin['department'],
                'departmentColor' => $pin['department_color'],
                'status' => $pin['status'],
                'pinType' => $pin['pin_type'],
                'pinnedBy' => $pin['pinned_by'],
                'pinnedAt' => $pin['pinned_at'],
                'priority' => $pin['pin_priority'] ? (int)$pin['pin_priority'] : null,
                'reason' => $pin['pin_reason']
            ];
        }, $globalPins);

        // Get total count for pagination
        $countStmt = $db->prepare("
            SELECT COUNT(*) as total
            FROM tasks t
            WHERE " . implode(' AND ', $whereConditions)
        );
        $countStmt->execute(array_slice($params, 0, -2)); // Remove limit and offset
        $totalCount = $countStmt->fetch()['total'];

        sendSuccess([
            'pins' => $formattedPins,
            'pagination' => [
                'total' => (int)$totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'hasMore' => ($offset + $limit) < $totalCount
            ]
        ]);

    } elseif ($method === 'GET' && $taskId) {
        // GET /api/pins/global/{taskId} - Get specific global pin
        $auth->requirePermission('view');

        $stmt = $db->prepare("
            SELECT
                t.task_id,
                t.action_item,
                t.department,
                t.status,
                t.pin_type,
                t.pinned_by,
                t.pinned_at,
                t.pin_priority,
                t.pin_reason,
                d.color as department_color
            FROM tasks t
            LEFT JOIN departments d ON t.department = d.name
            WHERE t.task_id = ? AND t.pin_type = 'global'
        ");
        $stmt->execute([$taskId]);
        $pin = $stmt->fetch();

        if (!$pin) {
            sendError('Global pin not found', 404);
        }

        $formattedPin = [
            'taskId' => $pin['task_id'],
            'actionItem' => $pin['action_item'],
            'department' => $pin['department'],
            'departmentColor' => $pin['department_color'],
            'status' => $pin['status'],
            'pinType' => $pin['pin_type'],
            'pinnedBy' => $pin['pinned_by'],
            'pinnedAt' => $pin['pinned_at'],
            'priority' => $pin['pin_priority'] ? (int)$pin['pin_priority'] : null,
            'reason' => $pin['pin_reason']
        ];

        sendSuccess($formattedPin);

    } elseif ($method === 'POST') {
        // POST /api/pins/global - Create global pin
        $auth->requirePermission('create');

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            sendError('Invalid JSON data', 400);
        }

        // Validate input
        $validationErrors = validatePinData($data);
        if (!empty($validationErrors)) {
            sendError('Validation failed', 400, $validationErrors);
        }

        // Check if task exists
        $stmt = $db->prepare("SELECT task_id, pin_type FROM tasks WHERE task_id = ?");
        $stmt->execute([$data['taskId']]);
        $existingTask = $stmt->fetch();

        if (!$existingTask) {
            sendError('Task not found', 404);
        }

        // Check if task is already globally pinned
        if ($existingTask['pin_type'] === 'global') {
            sendError('Task is already globally pinned', 409);
        }

        // Create global pin
        $stmt = $db->prepare("
            UPDATE tasks
            SET pin_type = 'global',
                pinned_by = ?,
                pinned_at = NOW(),
                pin_priority = ?,
                pin_reason = ?,
                last_updated = NOW()
            WHERE task_id = ?
        ");

        $stmt->execute([
            $currentUser['userId'],
            $data['priority'],
            $data['reason'] ?? null,
            $data['taskId']
        ]);

        if ($stmt->rowCount() === 0) {
            sendError('Failed to create global pin', 500);
        }

        // Log audit event
        logAuditEvent($db, $currentUser, 'global_pin_created', $data['taskId'], [
            'priority' => $data['priority'],
            'reason' => $data['reason'] ?? null,
            'previous_pin_type' => $existingTask['pin_type']
        ]);

        // Return created pin data
        $stmt = $db->prepare("
            SELECT task_id, pin_type, pinned_by, pinned_at, pin_priority, pin_reason
            FROM tasks WHERE task_id = ?
        ");
        $stmt->execute([$data['taskId']]);
        $createdPin = $stmt->fetch();

        sendSuccess([
            'taskId' => $createdPin['task_id'],
            'type' => $createdPin['pin_type'],
            'pinnedBy' => $createdPin['pinned_by'],
            'pinnedAt' => $createdPin['pinned_at'],
            'priority' => $createdPin['pin_priority'] ? (int)$createdPin['pin_priority'] : null,
            'reason' => $createdPin['pin_reason']
        ], 'Global pin created successfully', 201);

    } elseif ($method === 'PUT' && $taskId) {
        // PUT /api/pins/global/{taskId} - Update global pin
        $auth->requirePermission('update');

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            sendError('Invalid JSON data', 400);
        }

        // Validate input
        $validationErrors = validatePinData($data, true);
        if (!empty($validationErrors)) {
            sendError('Validation failed', 400, $validationErrors);
        }

        // Check if task exists and is globally pinned
        $stmt = $db->prepare("
            SELECT task_id, pin_type, pinned_by, pin_priority, pin_reason
            FROM tasks WHERE task_id = ?
        ");
        $stmt->execute([$taskId]);
        $existingTask = $stmt->fetch();

        if (!$existingTask) {
            sendError('Task not found', 404);
        }

        if ($existingTask['pin_type'] !== 'global') {
            sendError('Task is not globally pinned', 400);
        }

        // Check if user can update this pin (leadership can update any, others only their own)
        if (!$auth->canModifyPin($existingTask['pinned_by'])) {
            sendError('Can only update your own global pins', 403);
        }

        // Build update query
        $updateFields = [];
        $updateValues = [];

        if (isset($data['priority'])) {
            $updateFields[] = 'pin_priority = ?';
            $updateValues[] = $data['priority'];
        }

        if (isset($data['reason'])) {
            $updateFields[] = 'pin_reason = ?';
            $updateValues[] = $data['reason'];
        }

        if (empty($updateFields)) {
            sendError('No valid fields to update', 400);
        }

        $updateFields[] = 'last_updated = NOW()';
        $updateValues[] = $taskId;

        $stmt = $db->prepare("
            UPDATE tasks SET " . implode(', ', $updateFields) . " WHERE task_id = ?
        ");
        $stmt->execute($updateValues);

        // Log audit event
        logAuditEvent($db, $currentUser, 'global_pin_updated', $taskId, [
            'previous_priority' => $existingTask['pin_priority'],
            'new_priority' => $data['priority'] ?? $existingTask['pin_priority'],
            'previous_reason' => $existingTask['pin_reason'],
            'new_reason' => $data['reason'] ?? $existingTask['pin_reason']
        ]);

        // Return updated pin data
        $stmt = $db->prepare("
            SELECT task_id, pin_type, pinned_by, pinned_at, pin_priority, pin_reason
            FROM tasks WHERE task_id = ?
        ");
        $stmt->execute([$taskId]);
        $updatedPin = $stmt->fetch();

        sendSuccess([
            'taskId' => $updatedPin['task_id'],
            'type' => $updatedPin['pin_type'],
            'pinnedBy' => $updatedPin['pinned_by'],
            'pinnedAt' => $updatedPin['pinned_at'],
            'priority' => $updatedPin['pin_priority'] ? (int)$updatedPin['pin_priority'] : null,
            'reason' => $updatedPin['pin_reason']
        ], 'Global pin updated successfully');

    } elseif ($method === 'DELETE' && $taskId) {
        // DELETE /api/pins/global/{taskId} - Remove global pin
        $auth->requirePermission('delete');

        // Check if task exists and is globally pinned
        $stmt = $db->prepare("
            SELECT task_id, pin_type, pinned_by, pin_priority, pin_reason
            FROM tasks WHERE task_id = ?
        ");
        $stmt->execute([$taskId]);
        $existingTask = $stmt->fetch();

        if (!$existingTask) {
            sendError('Task not found', 404);
        }

        if ($existingTask['pin_type'] !== 'global') {
            sendError('Task is not globally pinned', 400);
        }

        // Check if user can delete this pin (leadership can delete any, others only their own)
        if (!$auth->canModifyPin($existingTask['pinned_by'])) {
            sendError('Can only delete your own global pins', 403);
        }

        // Remove global pin
        $stmt = $db->prepare("
            UPDATE tasks
            SET pin_type = NULL,
                pinned_by = NULL,
                pinned_at = NULL,
                pin_priority = NULL,
                pin_reason = NULL,
                last_updated = NOW()
            WHERE task_id = ?
        ");
        $stmt->execute([$taskId]);

        if ($stmt->rowCount() === 0) {
            sendError('Failed to remove global pin', 500);
        }

        // Log audit event
        logAuditEvent($db, $currentUser, 'global_pin_deleted', $taskId, [
            'previous_priority' => $existingTask['pin_priority'],
            'previous_reason' => $existingTask['pin_reason'],
            'previous_pinned_by' => $existingTask['pinned_by']
        ]);

        sendSuccess(null, 'Global pin removed successfully');

    } else {
        sendError('Invalid request method or endpoint', 405);
    }

} catch (PDOException $e) {
    error_log("Database error in pins.php: " . $e->getMessage());
    sendError('Database error occurred', 500);
} catch (Exception $e) {
    error_log("General error in pins.php: " . $e->getMessage());
    sendError('An error occurred', 500);
}
?>