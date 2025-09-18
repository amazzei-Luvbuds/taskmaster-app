<?php
/**
 * Simple tasks endpoint - works without routing
 */

require_once 'config.php';
require_once 'notifications.php';

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? 'tasks';

// Debug logging for DELETE
if ($method === 'DELETE') {
    error_log("DELETE Debug: method=$method, endpoint='$endpoint', endpoint param=" . ($_GET['endpoint'] ?? 'not set'));
}

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($method === 'GET' && $endpoint === 'departments') {
        // Get departments
        $stmt = $db->prepare("SELECT name, color, description FROM departments ORDER BY name");
        $stmt->execute();
        $departments = $stmt->fetchAll();

        sendJSON($departments);

    } elseif ($method === 'GET' && $endpoint === 'avatars') {
        // Get avatar mappings (return empty for now since we use static mapping)
        sendJSON([]);

    } elseif ($method === 'GET' && $endpoint === 'health') {
        // Health check
        sendJSON([
            'status' => 'ok',
            'version' => '1.0.0',
            'timestamp' => date('c')
        ]);

    } elseif ($method === 'GET' && $endpoint === 'team') {
        // Get team members
        $stmt = $db->prepare("
            SELECT
                ap.name,
                ap.email,
                ap.department,
                ap.avatar_url,
                d.color as department_color
            FROM avatar_profiles ap
            LEFT JOIN departments d ON ap.department = d.name
            ORDER BY ap.name
        ");

        $stmt->execute();
        $teamMembers = $stmt->fetchAll();

        // Format for frontend
        $team = [];
        foreach ($teamMembers as $member) {
            $team[] = [
                'fullName' => $member['name'],
                'email' => $member['email'],
                'department' => $member['department'],
                'avatarUrl' => $member['avatar_url'],
                'department_color' => $member['department_color']
            ];
        }

        sendJSON(['team' => $team]);

    } elseif ($method === 'GET' && isset($_GET['id'])) {
        // Get specific task by ID
        $taskId = $_GET['id'];
        $stmt = $db->prepare("
            SELECT
                t.*,
                d.color as department_color
            FROM tasks t
            LEFT JOIN departments d ON t.department = d.name
            WHERE t.task_id = ?
        ");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();

        if (!$task) {
            sendError('Task not found', 404);
        }

        // Get owner details for this specific task
        $ownerDetails = [];
        if ($task['owners']) {
            $ownerStmt = $db->prepare("
                SELECT name, email, avatar_url, department
                FROM avatar_profiles
                WHERE FIND_IN_SET(name, REPLACE(?, ';', ',')) > 0
                   OR FIND_IN_SET(email, REPLACE(?, ';', ',')) > 0
            ");
            $ownerStmt->execute([$task['owners'], $task['owners']]);
            $ownerDetails = $ownerStmt->fetchAll();
        }
        $task['owner_details'] = $ownerDetails;

        // Convert to camelCase for frontend compatibility
        $task['taskID'] = $task['task_id'];
        $task['actionItem'] = $task['action_item'];
        $task['priorityScore'] = (int)$task['priority_score'];
        $task['progressPercentage'] = (int)$task['progress_percentage'];
        $task['problemDescription'] = $task['problem_description'];
        $task['proposedSolution'] = $task['proposed_solution'];
        $task['dueDate'] = $task['due_date'];
        $task['predictedHours'] = $task['predicted_hours'];
        $task['actualHoursSpent'] = $task['actual_hours_spent'];
        $task['notesLog'] = $task['notes_log'];
        $task['dateCreated'] = $task['date_created'];
        $task['lastUpdated'] = $task['last_updated'];
        $task['lastUpdatedBy'] = $task['last_updated_by'];
        $task['ownerS'] = $task['owners'];
        $task['departmentColor'] = $task['department_color'];
        $task['aiProjectPlan'] = $task['plan_json'] ? json_decode($task['plan_json'], true) : null;

        sendJSON(['task' => $task]);

    } elseif ($method === 'GET' && $endpoint === 'tasks') {
        // Get all tasks with optional pagination
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100; // Default to 100 tasks
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        // Limit maximum results to prevent performance issues
        $limit = min($limit, 500);

        $stmt = $db->prepare("
            SELECT
                t.*,
                d.color as department_color
            FROM tasks t
            LEFT JOIN departments d ON t.department = d.name
            WHERE t.status != 'Deleted'
                AND t.status IS NOT NULL
                AND TRIM(t.status) != ''
                AND TRIM(t.status) != 'Deleted'
            ORDER BY t.last_updated DESC
            LIMIT ? OFFSET ?
        ");

        $stmt->execute([$limit, $offset]);
        $tasks = $stmt->fetchAll();

        // Debug logging - check for any deleted tasks that made it through
        foreach ($tasks as $task) {
            if (strtolower(trim($task['status'])) === 'deleted') {
                error_log("WARNING: Deleted task found in results: " . $task['task_id'] . " - Status: '" . $task['status'] . "'");
            }
        }

        // Process the results
        foreach ($tasks as &$task) {
            // Set owner_details to empty array for now (avatars handled client-side)
            $task['owner_details'] = [];

            // Clean out any screenshot paths from task data
            foreach ($task as $key => &$value) {
                if (is_string($value) && (
                    strpos($value, 'SCREENSHOTS') !== false ||
                    strpos($value, 'Screenshot') !== false ||
                    strpos($value, '/Users/alexandermazzei2020/Documents/SCREENSHOTS') !== false ||
                    strpos($value, '/var/folders/') !== false ||
                    strpos($value, 'TemporaryItems') !== false ||
                    strpos($value, 'screencaptureui') !== false
                )) {
                    error_log("Cleaning screenshot path from task field '$key': $value");
                    $value = ''; // Clear the field if it contains screenshot paths
                }
            }

            // Convert to camelCase for frontend compatibility
            $task['taskID'] = $task['task_id'];
            $task['actionItem'] = $task['action_item'];
            $task['priorityScore'] = (int)$task['priority_score'];
            $task['progressPercentage'] = (int)$task['progress_percentage'];
            $task['problemDescription'] = $task['problem_description'];
            $task['proposedSolution'] = $task['proposed_solution'];
            $task['dueDate'] = $task['due_date'];
            $task['predictedHours'] = $task['predicted_hours'];
            $task['actualHoursSpent'] = $task['actual_hours_spent'];
            $task['notesLog'] = $task['notes_log'];
            $task['dateCreated'] = $task['date_created'];
            $task['lastUpdated'] = $task['last_updated'];
            $task['lastUpdatedBy'] = $task['last_updated_by'];
            $task['ownerS'] = $task['owners']; // Legacy field name
            $task['departmentColor'] = $task['department_color']; // Department color
            $task['aiProjectPlan'] = $task['plan_json'] ? json_decode($task['plan_json'], true) : null;
        }

        sendJSON($tasks);

    } elseif ($method === 'POST') {
        // Create new task
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data || !isset($data['actionItem']) || !isset($data['department'])) {
            sendError('Missing required fields: actionItem, department', 400);
        }

        $taskId = generateTaskId();

        $stmt = $db->prepare("
            INSERT INTO tasks (
                task_id, action_item, department, owners, status,
                priority_score, progress_percentage, problem_description,
                proposed_solution, due_date, predicted_hours, notes_log,
                last_updated_by, plan_json
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $taskId,
            $data['actionItem'],
            $data['department'],
            $data['owners'] ?? '',
            $data['status'] ?? 'Not Started',
            $data['priorityScore'] ?? 0,
            $data['progressPercentage'] ?? 0,
            $data['problemDescription'] ?? '',
            $data['proposedSolution'] ?? '',
            $data['dueDate'] ?? null,
            $data['predictedHours'] ?? null,
            $data['notesLog'] ?? '',
            $data['lastUpdatedBy'] ?? 'API',
            isset($data['aiProjectPlan']) ? json_encode($data['aiProjectPlan']) : null
        ]);

        sendJSON(['taskID' => $taskId, 'message' => 'Task created successfully'], 201);

    } elseif ($method === 'PUT') {
        // Update task (status only or full task)
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data || !isset($data['taskID'])) {
            sendError('Missing required field: taskID', 400);
        }

        // Check if this is just a status update (kanban) or full task update
        $isStatusOnly = isset($data['status']) && count($data) == 2; // Only taskID and status

        // Debug log
        error_log("PUT request data: " . json_encode($data));
        error_log("Data count: " . count($data));
        error_log("Is status only: " . ($isStatusOnly ? 'true' : 'false'));

        if ($isStatusOnly) {
            // Simple status update for kanban
            $stmt = $db->prepare("
                UPDATE tasks
                SET status = ?, last_updated = CURRENT_TIMESTAMP
                WHERE task_id = ?
            ");
            $stmt->execute([$data['status'], $data['taskID']]);
        } else {
            // Full task update - build dynamic query based on provided fields
            $updateFields = [];
            $updateValues = [];

            $allowedFields = [
                'actionItem' => 'action_item',
                'department' => 'department',
                'owners' => 'owners',
                'status' => 'status',
                'priorityScore' => 'priority_score',
                'progressPercentage' => 'progress_percentage',
                'problemDescription' => 'problem_description',
                'proposedSolution' => 'proposed_solution',
                'dueDate' => 'due_date',
                'predictedHours' => 'predicted_hours',
                'actualHoursSpent' => 'actual_hours_spent',
                'notesLog' => 'notes_log',
                'lastUpdatedBy' => 'last_updated_by',
                'aiProjectPlan' => 'plan_json'
            ];

            foreach ($allowedFields as $frontendField => $dbField) {
                if (isset($data[$frontendField])) {
                    $updateFields[] = "$dbField = ?";
                    $updateValues[] = $data[$frontendField];
                }
            }

            if (empty($updateFields)) {
                sendError('No valid fields to update', 400);
            }

            // Always update the timestamp
            $updateFields[] = 'last_updated = CURRENT_TIMESTAMP';

            $sql = "UPDATE tasks SET " . implode(', ', $updateFields) . " WHERE task_id = ?";
            $updateValues[] = $data['taskID'];

            $stmt = $db->prepare($sql);
            $stmt->execute($updateValues);
        }

        if ($stmt->rowCount() === 0) {
            sendError('Task not found', 404);
        }

        // Handle notifications for ownership changes
        if (!$isStatusOnly && isset($data['owners'])) {
            try {
                // Get the current task data for notifications
                $taskStmt = $db->prepare("SELECT * FROM tasks WHERE task_id = ?");
                $taskStmt->execute([$data['taskID']]);
                $currentTask = $taskStmt->fetch();

                if ($currentTask) {
                    // Get previous owners before update (if any)
                    $previousOwners = $currentTask['owners'] ?? '';

                    // Send notifications for newly assigned team members
                    sendTaskNotifications($data['taskID'], $data['owners'], $previousOwners, $currentTask, $db);
                }
            } catch (Exception $e) {
                // Log notification error but don't fail the update
                error_log("Notification error for task {$data['taskID']}: " . $e->getMessage());
            }
        }

        sendJSON([
            'task' => [
                'taskID' => $data['taskID'],
                'status' => $data['status'] ?? 'Updated'
            ],
            'message' => $isStatusOnly ? 'Task status updated successfully' : 'Task updated successfully'
        ]);

    } elseif ($method === 'DELETE' && $endpoint === 'tasks') {
        // Delete task - get ID from body or query param
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $taskId = $data['taskID'] ?? $_GET['id'] ?? null;

        if (!$taskId) {
            sendError('Missing task ID', 400);
        }

        $stmt = $db->prepare("DELETE FROM tasks WHERE task_id = ?");
        $stmt->execute([$taskId]);

        if ($stmt->rowCount() === 0) {
            sendError('Task not found', 404);
        }

        sendJSON(['message' => 'Task deleted successfully']);

    } else {
        sendError('Method not allowed', 405);
    }

} catch (Exception $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
?>