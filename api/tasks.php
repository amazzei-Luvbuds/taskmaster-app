<?php
/**
 * TaskMaster API - Tasks Endpoint
 * Handles all task-related operations
 */

require_once 'config.php';

class TasksAPI {
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

        switch ($method) {
            case 'GET':
                if (isset($pathParts[2])) {
                    // GET /api/tasks/{id}
                    $this->getTask($pathParts[2]);
                } else {
                    // GET /api/tasks
                    $this->getTasks();
                }
                break;

            case 'POST':
                // POST /api/tasks
                $this->createTask();
                break;

            case 'PUT':
                if (isset($pathParts[2])) {
                    // PUT /api/tasks/{id}
                    $this->updateTask($pathParts[2]);
                } else {
                    $this->updateTaskStatus();
                }
                break;

            case 'DELETE':
                if (isset($pathParts[2])) {
                    // DELETE /api/tasks/{id}
                    $this->deleteTask($pathParts[2]);
                }
                break;

            default:
                sendError('Method not allowed', 405);
        }
    }

    /**
     * Get all tasks
     */
    public function getTasks() {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    t.*,
                    GROUP_CONCAT(
                        JSON_OBJECT(
                            'name', od.name,
                            'email', od.email,
                            'avatar_url', od.avatar_url,
                            'role', od.role,
                            'department', od.department
                        )
                    ) as owner_details
                FROM tasks t
                LEFT JOIN owner_details od ON t.task_id = od.task_id
                GROUP BY t.id
                ORDER BY t.last_updated DESC
            ");

            $stmt->execute();
            $tasks = $stmt->fetchAll();

            // Process the results
            foreach ($tasks as &$task) {
                // Parse JSON fields
                $task['plan_json'] = $task['plan_json'] ? json_decode($task['plan_json'], true) : null;
                $task['subtasks_json'] = $task['subtasks_json'] ? json_decode($task['subtasks_json'], true) : null;

                // Parse owner details
                if ($task['owner_details']) {
                    $ownerData = explode(',', $task['owner_details']);
                    $task['owner_details'] = array_map(function($item) {
                        return json_decode($item, true);
                    }, $ownerData);
                } else {
                    $task['owner_details'] = [];
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
            }

            sendJSON($tasks);
        } catch (Exception $e) {
            sendError('Failed to fetch tasks: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get single task by ID
     */
    public function getTask($taskId) {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    t.*,
                    GROUP_CONCAT(
                        JSON_OBJECT(
                            'name', od.name,
                            'email', od.email,
                            'avatar_url', od.avatar_url,
                            'role', od.role,
                            'department', od.department
                        )
                    ) as owner_details
                FROM tasks t
                LEFT JOIN owner_details od ON t.task_id = od.task_id
                WHERE t.task_id = ?
                GROUP BY t.id
            ");

            $stmt->execute([$taskId]);
            $task = $stmt->fetch();

            if (!$task) {
                sendError('Task not found', 404);
            }

            // Process the result (same as getTasks)
            $task['plan_json'] = $task['plan_json'] ? json_decode($task['plan_json'], true) : null;
            $task['subtasks_json'] = $task['subtasks_json'] ? json_decode($task['subtasks_json'], true) : null;

            if ($task['owner_details']) {
                $ownerData = explode(',', $task['owner_details']);
                $task['owner_details'] = array_map(function($item) {
                    return json_decode($item, true);
                }, $ownerData);
            } else {
                $task['owner_details'] = [];
            }

            sendJSON($task);
        } catch (Exception $e) {
            sendError('Failed to fetch task: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create new task
     */
    public function createTask() {
        $data = getRequestData();

        validateRequired($data, ['actionItem', 'department']);

        try {
            $taskId = generateTaskId();

            $stmt = $this->db->prepare("
                INSERT INTO tasks (
                    task_id, action_item, department, owners, status,
                    priority_score, progress_percentage, problem_description,
                    proposed_solution, due_date, predicted_hours, notes_log,
                    last_updated_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                $data['lastUpdatedBy'] ?? 'API'
            ]);

            // Handle owner details if provided
            if (isset($data['ownerDetails']) && is_array($data['ownerDetails'])) {
                $this->updateOwnerDetails($taskId, $data['ownerDetails']);
            }

            sendJSON(['taskID' => $taskId, 'message' => 'Task created successfully'], 201);
        } catch (Exception $e) {
            sendError('Failed to create task: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update task status (for kanban drag & drop)
     */
    public function updateTaskStatus() {
        $data = getRequestData();

        validateRequired($data, ['taskID', 'status']);

        try {
            $stmt = $this->db->prepare("
                UPDATE tasks
                SET status = ?, last_updated = CURRENT_TIMESTAMP
                WHERE task_id = ?
            ");

            $stmt->execute([$data['status'], $data['taskID']]);

            if ($stmt->rowCount() === 0) {
                sendError('Task not found', 404);
            }

            sendJSON([
                'task' => [
                    'taskID' => $data['taskID'],
                    'status' => $data['status']
                ],
                'message' => 'Task status updated successfully'
            ]);
        } catch (Exception $e) {
            sendError('Failed to update task status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update entire task
     */
    public function updateTask($taskId) {
        $data = getRequestData();

        try {
            $fields = [];
            $values = [];

            // Build dynamic update query
            $allowedFields = [
                'action_item' => 'actionItem',
                'department' => 'department',
                'owners' => 'owners',
                'status' => 'status',
                'priority_score' => 'priorityScore',
                'progress_percentage' => 'progressPercentage',
                'problem_description' => 'problemDescription',
                'proposed_solution' => 'proposedSolution',
                'due_date' => 'dueDate',
                'predicted_hours' => 'predictedHours',
                'actual_hours_spent' => 'actualHoursSpent',
                'notes_log' => 'notesLog',
                'last_updated_by' => 'lastUpdatedBy'
            ];

            foreach ($allowedFields as $dbField => $dataKey) {
                if (isset($data[$dataKey])) {
                    $fields[] = "$dbField = ?";
                    $values[] = $data[$dataKey];
                }
            }

            if (empty($fields)) {
                sendError('No valid fields to update', 400);
            }

            $fields[] = "last_updated = CURRENT_TIMESTAMP";
            $values[] = $taskId;

            $sql = "UPDATE tasks SET " . implode(', ', $fields) . " WHERE task_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            if ($stmt->rowCount() === 0) {
                sendError('Task not found', 404);
            }

            // Update owner details if provided
            if (isset($data['ownerDetails']) && is_array($data['ownerDetails'])) {
                $this->updateOwnerDetails($taskId, $data['ownerDetails']);
            }

            sendJSON(['message' => 'Task updated successfully']);
        } catch (Exception $e) {
            sendError('Failed to update task: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete task
     */
    public function deleteTask($taskId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM tasks WHERE task_id = ?");
            $stmt->execute([$taskId]);

            if ($stmt->rowCount() === 0) {
                sendError('Task not found', 404);
            }

            sendJSON(['message' => 'Task deleted successfully']);
        } catch (Exception $e) {
            sendError('Failed to delete task: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update owner details for a task
     */
    private function updateOwnerDetails($taskId, $ownerDetails) {
        // Delete existing owner details
        $stmt = $this->db->prepare("DELETE FROM owner_details WHERE task_id = ?");
        $stmt->execute([$taskId]);

        // Insert new owner details
        if (!empty($ownerDetails)) {
            $stmt = $this->db->prepare("
                INSERT INTO owner_details (task_id, name, email, avatar_url, role, department)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($ownerDetails as $owner) {
                $stmt->execute([
                    $taskId,
                    $owner['name'] ?? '',
                    $owner['email'] ?? '',
                    $owner['avatar_url'] ?? '',
                    $owner['role'] ?? '',
                    $owner['department'] ?? ''
                ]);
            }
        }
    }
}

// Initialize and handle the request
$api = new TasksAPI();
$api->handleRequest();
?>