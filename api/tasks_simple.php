<?php
/**
 * Simple tasks endpoint - works without routing
 */

require_once 'config.php';
require_once 'notifications.php';

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

// Analytics functions
function generateExecutiveAnalytics($db, $timeRange = '30d', $departments = []) {
    // Convert time range to date filter
    $dateFilter = getDateFilter($timeRange);

    // Build department filter
    $deptFilter = '';
    $params = [$dateFilter];
    if (!empty($departments)) {
        $placeholders = str_repeat('?,', count($departments) - 1) . '?';
        $deptFilter = " AND department IN ($placeholders)";
        $params = array_merge($params, $departments);
    }

    // Get executive metrics
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total_tasks,
            COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed_tasks,
            COUNT(CASE WHEN status IN ('In Progress', 'Started') THEN 1 END) as in_progress_tasks,
            COUNT(CASE WHEN due_date < CURDATE() AND status != 'Completed' THEN 1 END) as overdue_tasks,
            AVG(CASE WHEN progress_percentage IS NOT NULL THEN progress_percentage ELSE 0 END) as avg_progress,
            AVG(CASE WHEN actual_hours_spent IS NOT NULL AND actual_hours_spent > 0 THEN actual_hours_spent ELSE NULL END) as avg_hours_spent
        FROM tasks
        WHERE last_updated >= ? AND status != 'Deleted' $deptFilter
    ");

    $stmt->execute($params);
    $metrics = $stmt->fetch();

    // Calculate completion rate
    $completionRate = $metrics['total_tasks'] > 0
        ? ($metrics['completed_tasks'] / $metrics['total_tasks']) * 100
        : 0;

    // Get department breakdown
    $deptStmt = $db->prepare("
        SELECT
            department,
            COUNT(*) as task_count,
            COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed_count,
            AVG(CASE WHEN progress_percentage IS NOT NULL THEN progress_percentage ELSE 0 END) as avg_progress
        FROM tasks
        WHERE last_updated >= ? AND status != 'Deleted' $deptFilter
        GROUP BY department
        ORDER BY task_count DESC
    ");

    $deptStmt->execute($params);
    $departmentBreakdown = $deptStmt->fetchAll();

    return [
        'totalTasks' => (int)$metrics['total_tasks'],
        'completedTasks' => (int)$metrics['completed_tasks'],
        'inProgressTasks' => (int)$metrics['in_progress_tasks'],
        'overdueTasks' => (int)$metrics['overdue_tasks'],
        'completionRate' => round($completionRate, 1),
        'averageProgress' => round($metrics['avg_progress'], 1),
        'averageHoursSpent' => $metrics['avg_hours_spent'] ? round($metrics['avg_hours_spent'], 1) : null,
        'departmentBreakdown' => $departmentBreakdown,
        'lastUpdated' => date('c'),
        'timeRange' => $timeRange,
        'departments' => $departments
    ];
}

function generateDepartmentAnalytics($db, $department, $timeRange = '30d') {
    $dateFilter = getDateFilter($timeRange);

    // Get department metrics
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as task_count,
            COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed_count,
            COUNT(CASE WHEN due_date < CURDATE() AND status != 'Completed' THEN 1 END) as overdue_count,
            AVG(CASE WHEN progress_percentage IS NOT NULL THEN progress_percentage ELSE 0 END) as avg_progress,
            AVG(CASE WHEN actual_hours_spent IS NOT NULL AND actual_hours_spent > 0 THEN actual_hours_spent ELSE NULL END) as avg_hours_spent,
            AVG(DATEDIFF(CURDATE(), date_created)) as avg_task_age
        FROM tasks
        WHERE department = ? AND last_updated >= ? AND status != 'Deleted'
    ");

    $stmt->execute([$department, $dateFilter]);
    $metrics = $stmt->fetch();

    // Calculate completion rate
    $completionRate = $metrics['task_count'] > 0
        ? ($metrics['completed_count'] / $metrics['task_count']) * 100
        : 0;

    // Get priority distribution
    $priorityStmt = $db->prepare("
        SELECT
            CASE
                WHEN priority_score >= 8 THEN 'High'
                WHEN priority_score >= 5 THEN 'Medium'
                ELSE 'Low'
            END as priority_level,
            COUNT(*) as count
        FROM tasks
        WHERE department = ? AND last_updated >= ? AND status != 'Deleted'
        GROUP BY priority_level
    ");

    $priorityStmt->execute([$department, $dateFilter]);
    $priorityDistribution = $priorityStmt->fetchAll();

    // Get status distribution
    $statusStmt = $db->prepare("
        SELECT status, COUNT(*) as count
        FROM tasks
        WHERE department = ? AND last_updated >= ? AND status != 'Deleted'
        GROUP BY status
    ");

    $statusStmt->execute([$department, $dateFilter]);
    $statusDistribution = $statusStmt->fetchAll();

    return [
        'department' => $department,
        'taskCount' => (int)$metrics['task_count'],
        'completionRate' => round($completionRate, 1),
        'overdueCount' => (int)$metrics['overdue_count'],
        'averageProgress' => round($metrics['avg_progress'], 1),
        'averageTaskAge' => $metrics['avg_task_age'] ? round($metrics['avg_task_age'], 1) : null,
        'averageHoursSpent' => $metrics['avg_hours_spent'] ? round($metrics['avg_hours_spent'], 1) : null,
        'priorityDistribution' => $priorityDistribution,
        'statusDistribution' => $statusDistribution,
        'lastUpdated' => date('c'),
        'timeRange' => $timeRange
    ];
}

function generateTrendAnalytics($db, $metric, $timeRange = '30d', $granularity = 'daily', $departments = []) {
    $dateFilter = getDateFilter($timeRange);

    // Build department filter
    $deptFilter = '';
    $params = [$dateFilter];
    if (!empty($departments)) {
        $placeholders = str_repeat('?,', count($departments) - 1) . '?';
        $deptFilter = " AND department IN ($placeholders)";
        $params = array_merge($params, $departments);
    }


    // Determine grouping based on granularity
    switch ($granularity) {
        case 'weekly':
            $dateGrouping = 'DATE(DATE_SUB(last_updated, INTERVAL WEEKDAY(last_updated) DAY))';
            break;
        case 'monthly':
            $dateGrouping = 'DATE_FORMAT(last_updated, "%Y-%m-01")';
            break;
        default:
            $dateGrouping = 'DATE(last_updated)';
            break;
    }

    // Build metric calculation based on requested metric
    switch ($metric) {
        case 'completionRate':
            $metricCalculation = 'ROUND((COUNT(CASE WHEN status = "Completed" THEN 1 END) / COUNT(*)) * 100, 1)';
            break;
        case 'averageProgress':
            $metricCalculation = 'ROUND(AVG(CASE WHEN progress_percentage IS NOT NULL THEN progress_percentage ELSE 0 END), 1)';
            break;
        case 'overdueCount':
            $metricCalculation = 'COUNT(CASE WHEN due_date < CURDATE() AND status != "Completed" THEN 1 END)';
            break;
        default:
            $metricCalculation = 'COUNT(*)';
            break;
    }

    // Build SQL with proper concatenation
    $sql = "SELECT " . $dateGrouping . " as date, " . $metricCalculation . " as value " .
           "FROM tasks " .
           "WHERE last_updated >= ? AND status != 'Deleted'" . $deptFilter . " " .
           "GROUP BY " . $dateGrouping . " " .
           "ORDER BY date ASC";

    $stmt = $db->prepare($sql);

    $stmt->execute($params);
    $trendData = $stmt->fetchAll();

    // Format the data
    $formattedData = [];
    foreach ($trendData as $row) {
        $formattedData[] = [
            'date' => $row['date'],
            'value' => (float)$row['value'],
            'metric' => $metric
        ];
    }

    return $formattedData;
}

function getDateFilter($timeRange) {
    switch ($timeRange) {
        case '7d':
            return date('Y-m-d', strtotime('-7 days'));
        case '30d':
            return date('Y-m-d', strtotime('-30 days'));
        case '90d':
            return date('Y-m-d', strtotime('-90 days'));
        case '1y':
            return date('Y-m-d', strtotime('-1 year'));
        default:
            return date('Y-m-d', strtotime('-30 days'));
    }
}

// Report generation function
function generateCustomReport($db, $reportConfig) {
    try {
        // Extract report configuration
        $reportName = $reportConfig['reportName'] ?? 'Custom Report';
        $filters = $reportConfig['filters'] ?? [];
        $metrics = $reportConfig['metrics'] ?? [];
        $exportFormats = $reportConfig['exportFormats'] ?? ['pdf'];

        // Build department filter
        $deptFilter = '';
        $params = [];
        if (!empty($filters['departments']) && !in_array('All', $filters['departments'])) {
            $placeholders = str_repeat('?,', count($filters['departments']) - 1) . '?';
            $deptFilter = " AND department IN ($placeholders)";
            $params = array_merge($params, $filters['departments']);
        }

        // Build status filter
        $statusFilter = '';
        if (!empty($filters['taskStatuses']) && !in_array('All', $filters['taskStatuses'])) {
            $placeholders = str_repeat('?,', count($filters['taskStatuses']) - 1) . '?';
            $statusFilter = " AND status IN ($placeholders)";
            $params = array_merge($params, $filters['taskStatuses']);
        }

        // Get filtered tasks data
        $sql = "SELECT * FROM tasks WHERE status != 'Deleted'" . $deptFilter . $statusFilter;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();

        // Calculate metrics from filtered data
        $totalTasks = count($tasks);
        $completedTasks = array_filter($tasks, function($task) {
            return $task['status'] === 'Completed';
        });
        $inProgressTasks = array_filter($tasks, function($task) {
            return in_array($task['status'], ['In Progress', 'Started']);
        });
        $overdueTasks = array_filter($tasks, function($task) {
            return $task['due_date'] && $task['due_date'] < date('Y-m-d') && $task['status'] !== 'Completed';
        });

        $completionRate = $totalTasks > 0 ? round((count($completedTasks) / $totalTasks) * 100, 1) : 0;
        $avgProgress = $totalTasks > 0 ? round(array_sum(array_column($tasks, 'progress_percentage')) / $totalTasks, 1) : 0;

        // Generate report data
        $reportData = [
            'reportName' => $reportName,
            'generatedAt' => date('Y-m-d H:i:s'),
            'filters' => $filters,
            'summary' => [
                'totalTasks' => $totalTasks,
                'completionRate' => $completionRate,
                'averageProgress' => $avgProgress,
                'completedTasks' => count($completedTasks),
                'inProgressTasks' => count($inProgressTasks),
                'overdueTasks' => count($overdueTasks)
            ],
            'tasks' => $tasks
        ];

        // Generate export files based on requested formats
        $downloads = [];
        $baseUrl = "https://" . $_SERVER['HTTP_HOST'] . "/api";

        foreach ($exportFormats as $format) {
            switch ($format) {
                case 'csv':
                    $csvData = generateCSVReport($reportData);
                    $downloads[] = [
                        'format' => 'csv',
                        'url' => $baseUrl . '/download_report.php?type=csv&data=' . urlencode(base64_encode($csvData)),
                        'filename' => sanitizeFilename($reportName) . '_' . date('Y-m-d') . '.csv'
                    ];
                    break;

                case 'pdf':
                    // For PDF, return a placeholder URL (would need PDF library for real implementation)
                    $downloads[] = [
                        'format' => 'pdf',
                        'url' => $baseUrl . '/download_report.php?type=pdf&report=' . urlencode(base64_encode(json_encode($reportData))),
                        'filename' => sanitizeFilename($reportName) . '_' . date('Y-m-d') . '.pdf'
                    ];
                    break;

                case 'excel':
                    // For Excel, return CSV format with .xlsx extension (basic implementation)
                    $csvData = generateCSVReport($reportData);
                    $downloads[] = [
                        'format' => 'excel',
                        'url' => $baseUrl . '/download_report.php?type=excel&data=' . urlencode(base64_encode($csvData)),
                        'filename' => sanitizeFilename($reportName) . '_' . date('Y-m-d') . '.xlsx'
                    ];
                    break;
            }
        }

        return [
            'success' => true,
            'reportId' => $reportConfig['reportId'],
            'reportName' => $reportName,
            'downloads' => $downloads,
            'previewData' => $reportData['summary'],
            'generatedAt' => $reportData['generatedAt']
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Report generation failed: ' . $e->getMessage()
        ];
    }
}

// Helper function to generate CSV data
function generateCSVReport($reportData) {
    $output = "TaskMaster Analytics Report: " . $reportData['reportName'] . "\n";
    $output .= "Generated: " . $reportData['generatedAt'] . "\n\n";

    // Summary section
    $output .= "SUMMARY METRICS\n";
    $output .= "Total Tasks," . $reportData['summary']['totalTasks'] . "\n";
    $output .= "Completion Rate," . $reportData['summary']['completionRate'] . "%\n";
    $output .= "Average Progress," . $reportData['summary']['averageProgress'] . "%\n";
    $output .= "Completed Tasks," . $reportData['summary']['completedTasks'] . "\n";
    $output .= "In Progress Tasks," . $reportData['summary']['inProgressTasks'] . "\n";
    $output .= "Overdue Tasks," . $reportData['summary']['overdueTasks'] . "\n\n";

    // Tasks detail section
    $output .= "TASK DETAILS\n";
    $output .= "Task ID,Title,Department,Status,Priority,Progress,Due Date,Created\n";

    foreach ($reportData['tasks'] as $task) {
        $output .= sprintf(
            '"%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
            $task['task_id'] ?? '',
            str_replace('"', '""', $task['title'] ?? ''),
            $task['department'] ?? '',
            $task['status'] ?? '',
            $task['priority_score'] ?? '',
            $task['progress_percentage'] ?? '0',
            $task['due_date'] ?? '',
            $task['created_at'] ?? ''
        );
    }

    return $output;
}

// Helper function to sanitize filenames
function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
}

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? 'tasks';

// Debug logging for DELETE
if ($method === 'DELETE') {
    error_log("DELETE Debug: method=$method, endpoint='$endpoint', endpoint param=" . ($_GET['endpoint'] ?? 'not set'));
}

try {
    // Create database connection with override values if needed
    if (defined('DB_HOST_OVERRIDE')) {
        $dsn = "mysql:host=" . DB_HOST_OVERRIDE . ";dbname=" . DB_NAME_OVERRIDE . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];
        $db = new PDO($dsn, DB_USER_OVERRIDE, DB_PASS_OVERRIDE, $options);
    } else {
        $database = new Database();
        $db = $database->getConnection();
    }

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

    } elseif ($method === 'POST' && $endpoint === 'debug-comment') {
        // Debug endpoint to see what data we receive
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        sendJSON([
            'debug' => true,
            'raw_input' => $input,
            'parsed_data' => $data,
            'has_authorAvatar' => isset($data['authorAvatar']),
            'authorAvatar_value' => $data['authorAvatar'] ?? 'NOT_SET',
            'all_keys' => array_keys($data ?? [])
        ]);

    } elseif ($method === 'POST' && $endpoint === 'migrate-avatar') {
        // Migration endpoint to add author_avatar column
        try {
            // Check if column exists
            $check = $db->query("SHOW COLUMNS FROM comments LIKE 'author_avatar'");
            $columnExists = $check->rowCount() > 0;

            if ($columnExists) {
                sendJSON([
                    'status' => 'already_exists',
                    'message' => 'Column author_avatar already exists',
                    'timestamp' => date('c')
                ]);
            } else {
                // Add the column
                $db->exec("ALTER TABLE comments ADD COLUMN author_avatar TEXT NULL AFTER author_email");

                sendJSON([
                    'status' => 'success',
                    'message' => 'Column author_avatar added successfully',
                    'timestamp' => date('c')
                ]);
            }
        } catch (Exception $e) {
            sendError('Migration failed: ' . $e->getMessage(), 500);
        }

    } elseif ($method === 'POST' && $endpoint === 'migrate-attachments') {
        // Migration endpoint to add attachments column
        try {
            // Check if column exists
            $check = $db->query("SHOW COLUMNS FROM comments LIKE 'attachments'");
            $columnExists = $check->rowCount() > 0;
            if ($columnExists) {
                sendJSON([
                    'status' => 'already_exists',
                    'message' => 'Column attachments already exists',
                    'timestamp' => date('c')
                ]);
            } else {
                // Add the column
                $db->exec("ALTER TABLE comments ADD COLUMN attachments TEXT NULL AFTER author_avatar");
                sendJSON([
                    'status' => 'success',
                    'message' => 'Column attachments added successfully',
                    'timestamp' => date('c')
                ]);
            }
        } catch (Exception $e) {
            sendError('Migration failed: ' . $e->getMessage(), 500);
        }

    } elseif ($method === 'GET' && $endpoint === 'debug-schema') {
        // Debug endpoint to check table schema
        try {
            $stmt = $db->query("DESCRIBE comments");
            $columns = $stmt->fetchAll();
            sendJSON([
                'table' => 'comments',
                'columns' => $columns,
                'timestamp' => date('c')
            ]);
        } catch (Exception $e) {
            sendError('Schema check failed: ' . $e->getMessage(), 500);
        }

    } elseif ($method === 'GET' && $endpoint === 'notifications') {
        // Get notifications for a user
        $userId = $_GET['userId'] ?? '';
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = (int)($_GET['offset'] ?? 0);

        if (empty($userId)) {
            sendError('Missing userId parameter', 400);
        }

        // For now, return a simple structure that matches what real-time service expects
        // In the future, this would connect to a real notifications table
        sendJSON([
            'notifications' => [], // Empty for now - would contain real notifications from database
            'total' => 0,
            'unread' => 0,
            'hasMore' => false,
            'lastPollTime' => date('c')
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
        $task['bmadAnalysis'] = $task['bmad_analysis_json'] ? json_decode($task['bmad_analysis_json'], true) : null;
        $task['generatedSubtasks'] = $task['generated_subtasks_json'] ? json_decode($task['generated_subtasks_json'], true) : null;

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
            $task['bmadAnalysis'] = $task['bmad_analysis_json'] ? json_decode($task['bmad_analysis_json'], true) : null;
            $task['generatedSubtasks'] = $task['generated_subtasks_json'] ? json_decode($task['generated_subtasks_json'], true) : null;

            // Pin fields for dual-tier pin system
            $task['pinType'] = $task['pin_type'] ?? null;
            $task['pinnedBy'] = $task['pinned_by'] ?? null;
            $task['pinnedAt'] = $task['pinned_at'] ?? null;
            $task['pinPriority'] = isset($task['pin_priority']) ? (int)$task['pin_priority'] : null;
            $task['pinReason'] = $task['pin_reason'] ?? null;
        }

        sendJSON($tasks);

    } elseif ($method === 'POST' && !$endpoint) {
        // Create new task
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data || !isset($data['actionItem']) || !isset($data['department'])) {
            sendError('Missing required fields: actionItem, department', 400);
        }

        $taskId = generateTaskId();

        // Validate pin fields for task creation
        if (isset($data['pinType']) || isset($data['pinPriority']) || isset($data['pinnedBy'])) {
            // Validate pin type
            if (isset($data['pinType']) && !in_array($data['pinType'], ['personal', 'global', null])) {
                sendError('Invalid pinType. Must be "personal", "global", or null', 400);
            }

            // Validate pin priority (only for global pins)
            if (isset($data['pinPriority']) && $data['pinPriority'] !== null) {
                if (!is_numeric($data['pinPriority']) || $data['pinPriority'] < 1 || $data['pinPriority'] > 10) {
                    sendError('pinPriority must be between 1 and 10', 400);
                }
                if (!isset($data['pinType']) || $data['pinType'] !== 'global') {
                    sendError('pinPriority can only be set for global pins', 400);
                }
            }

            // Validate pinnedBy is provided when creating a pin
            if (isset($data['pinType']) && $data['pinType'] !== null) {
                if (!isset($data['pinnedBy']) || empty($data['pinnedBy'])) {
                    sendError('pinnedBy is required when creating a pin', 400);
                }
                // Set pinnedAt timestamp if not provided
                if (!isset($data['pinnedAt'])) {
                    $data['pinnedAt'] = date('Y-m-d H:i:s');
                }
            }
        }

        $stmt = $db->prepare("
            INSERT INTO tasks (
                task_id, action_item, department, owners, status,
                priority_score, progress_percentage, problem_description,
                proposed_solution, due_date, predicted_hours, notes_log,
                last_updated_by, plan_json, bmad_analysis_json, generated_subtasks_json,
                pin_type, pinned_by, pinned_at, pin_priority, pin_reason
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            isset($data['aiProjectPlan']) ? json_encode($data['aiProjectPlan']) : null,
            isset($data['bmadAnalysis']) ? json_encode($data['bmadAnalysis']) : null,
            isset($data['generatedSubtasks']) ? json_encode($data['generatedSubtasks']) : null,
            $data['pinType'] ?? null,
            $data['pinnedBy'] ?? null,
            $data['pinnedAt'] ?? null,
            $data['pinPriority'] ?? null,
            $data['pinReason'] ?? null
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
                'aiProjectPlan' => 'plan_json',
                'bmadAnalysis' => 'bmad_analysis_json',
                'generatedSubtasks' => 'generated_subtasks_json',
                // Pin fields for dual-tier pin system
                'pinType' => 'pin_type',
                'pinnedBy' => 'pinned_by',
                'pinnedAt' => 'pinned_at',
                'pinPriority' => 'pin_priority',
                'pinReason' => 'pin_reason'
            ];

            // Validate pin fields before processing
            if (isset($data['pinType']) || isset($data['pinPriority']) || isset($data['pinnedBy'])) {
                // Get current task pin state for validation
                $currentPinType = null;
                if (isset($data['pinPriority']) && !isset($data['pinType'])) {
                    $checkStmt = $db->prepare("SELECT pin_type FROM tasks WHERE task_id = ?");
                    $checkStmt->execute([$data['taskID']]);
                    $currentTask = $checkStmt->fetch();
                    $currentPinType = $currentTask['pin_type'] ?? null;
                }

                // Validate pin type
                if (isset($data['pinType']) && !in_array($data['pinType'], ['personal', 'global', null])) {
                    sendError('Invalid pinType. Must be "personal", "global", or null', 400);
                }

                // Validate pin priority (only for global pins)
                if (isset($data['pinPriority'])) {
                    if ($data['pinPriority'] !== null) {
                        if (!is_numeric($data['pinPriority']) || $data['pinPriority'] < 1 || $data['pinPriority'] > 10) {
                            sendError('pinPriority must be between 1 and 10', 400);
                        }
                        // Check both incoming pinType and current pinType
                        $effectivePinType = isset($data['pinType']) ? $data['pinType'] : $currentPinType;
                        if ($effectivePinType !== 'global') {
                            sendError('pinPriority can only be set for global pins', 400);
                        }
                    }
                }

                // Validate pinnedBy is provided when creating a pin
                if (isset($data['pinType']) && $data['pinType'] !== null) {
                    if (!isset($data['pinnedBy']) || empty($data['pinnedBy'])) {
                        sendError('pinnedBy is required when creating a pin', 400);
                    }
                }

                // Set pinnedAt timestamp when creating a pin
                if (isset($data['pinType']) && $data['pinType'] !== null && !isset($data['pinnedAt'])) {
                    $data['pinnedAt'] = date('Y-m-d H:i:s');
                }

                // Clear pin fields when removing pin
                if (isset($data['pinType']) && $data['pinType'] === null) {
                    $data['pinnedBy'] = null;
                    $data['pinnedAt'] = null;
                    $data['pinPriority'] = null;
                    $data['pinReason'] = null;
                }
            }

            foreach ($allowedFields as $frontendField => $dbField) {
                if (isset($data[$frontendField])) {
                    $updateFields[] = "$dbField = ?";

                    // JSON encode for special fields
                    if (in_array($frontendField, ['aiProjectPlan', 'bmadAnalysis', 'generatedSubtasks'])) {
                        $updateValues[] = json_encode($data[$frontendField]);
                    } else {
                        $updateValues[] = $data[$frontendField];
                    }
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

    } elseif ($method === 'GET' && $endpoint === 'comments') {
        // Get comments for a task
        $task_id = $_GET['task_id'] ?? null;

        if (!$task_id) {
            sendError('task_id parameter is required', 400);
        }

        // Check if comments table exists first
        $tableCheck = $db->query("SHOW TABLES LIKE 'comments'");
        if ($tableCheck->rowCount() === 0) {
            // Table doesn't exist, return empty but valid response
            sendJSON([
                'comments' => [],
                'totalCount' => 0,
                'hasMore' => false,
                'nextCursor' => null
            ]);
            return;
        }

        // Check if attachments column exists for GET queries too
        $getAttachmentsExists = false;
        try {
            $checkStmt = $db->query("SHOW COLUMNS FROM comments LIKE 'attachments'");
            $getAttachmentsExists = $checkStmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log('Failed to check attachments column for GET: ' . $e->getMessage());
        }

        // Get comments for the task
        if ($getAttachmentsExists) {
            $stmt = $db->prepare("
                SELECT
                    id,
                    task_id as taskId,
                    parent_comment_id as parentCommentId,
                    author_id as authorId,
                    author_name as authorName,
                    author_email as authorEmail,
                    author_avatar as authorAvatar,
                    content,
                    content_type as contentType,
                    attachments,
                    created_at as createdAt,
                    updated_at as updatedAt,
                    edited_at as editedAt,
                    is_deleted as isDeleted,
                    is_edited as isEdited,
                    flagged
                FROM comments
                WHERE task_id = ? AND is_deleted = 0
                ORDER BY created_at DESC
                LIMIT 50
            ");
        } else {
            $stmt = $db->prepare("
                SELECT
                    id,
                    task_id as taskId,
                    parent_comment_id as parentCommentId,
                    author_id as authorId,
                    author_name as authorName,
                    author_email as authorEmail,
                    author_avatar as authorAvatar,
                    content,
                    content_type as contentType,
                    created_at as createdAt,
                    updated_at as updatedAt,
                    edited_at as editedAt,
                    is_deleted as isDeleted,
                    is_edited as isEdited,
                    flagged
                FROM comments
                WHERE task_id = ? AND is_deleted = 0
                ORDER BY created_at DESC
                LIMIT 50
            ");
        }

        $stmt->execute([$task_id]);
        $comments = $stmt->fetchAll();

        // Format comments properly
        $formatted_comments = [];
        foreach ($comments as $comment) {
            // Parse attachments JSON (if column exists)
            $attachments = [];
            if ($getAttachmentsExists && !empty($comment['attachments'])) {
                $parsedAttachments = json_decode($comment['attachments'], true);
                if (is_array($parsedAttachments)) {
                    $attachments = $parsedAttachments;
                }
            }

            $formatted_comments[] = [
                'id' => $comment['id'],
                'taskId' => $comment['taskId'],
                'parentCommentId' => $comment['parentCommentId'],
                'authorId' => $comment['authorId'],
                'authorName' => $comment['authorName'],
                'authorEmail' => $comment['authorEmail'],
                'authorAvatar' => $comment['authorAvatar'],
                'content' => $comment['content'],
                'contentType' => $comment['contentType'] ?? 'plain',
                'createdAt' => $comment['createdAt'],
                'updatedAt' => $comment['updatedAt'],
                'editedAt' => $comment['editedAt'],
                'isDeleted' => (bool)$comment['isDeleted'],
                'isEdited' => (bool)$comment['isEdited'],
                'mentions' => [],
                'attachments' => $attachments,
                'reactions' => [],
                'metadata' => [
                    'flagged' => (bool)$comment['flagged'],
                    'editHistory' => [],
                    'moderatorActions' => []
                ]
            ];
        }

        sendJSON([
            'comments' => $formatted_comments,
            'totalCount' => count($formatted_comments),
            'hasMore' => false,
            'nextCursor' => null
        ]);

    } elseif ($method === 'POST' && $endpoint === 'comments') {
        // Create a new comment
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data || !isset($data['taskId']) || !isset($data['content'])) {
            sendError('Missing required fields: taskId, content', 400);
        }

        // Debug: Show what data we received
        if (isset($_GET['debug'])) {
            sendJSON([
                'debug' => true,
                'received_data' => $data,
                'has_authorAvatar' => isset($data['authorAvatar']),
                'authorAvatar_value' => $data['authorAvatar'] ?? 'NOT_SET'
            ]);
            return;
        }

        // Check if comments table exists
        $tableCheck = $db->query("SHOW TABLES LIKE 'comments'");
        if ($tableCheck->rowCount() === 0) {
            sendError('Comments system not set up yet', 500);
        }

        $comment_id = 'comment_' . uniqid();

        // Handle attachments if provided
        $attachmentsJson = null;
        if (isset($data['attachments']) && is_array($data['attachments']) && !empty($data['attachments'])) {
            $attachmentsJson = json_encode($data['attachments']);
        }

        // Check if attachments column exists
        $attachmentsColumnExists = false;
        try {
            $checkStmt = $db->query("SHOW COLUMNS FROM comments LIKE 'attachments'");
            $attachmentsColumnExists = $checkStmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log('Failed to check attachments column: ' . $e->getMessage());
        }

        if ($attachmentsColumnExists) {
            $stmt = $db->prepare("
                INSERT INTO comments (
                    id, task_id, content, author_id, author_name, author_email, author_avatar,
                    attachments, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
        } else {
            $stmt = $db->prepare("
                INSERT INTO comments (
                    id, task_id, content, author_id, author_name, author_email, author_avatar,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
        }

        // AVATAR SOLUTION: Look up avatar from team data based on email
        $avatarUrl = null;
        $authorEmail = $data['authorEmail'] ?? 'anonymous@example.com';

        // Debug log: Show what we received
        error_log('AVATAR DEBUG - Raw data: ' . json_encode([
            'authorAvatar' => $data['authorAvatar'] ?? 'NOT_SET',
            'authorEmail' => $authorEmail,
            'authorName' => $data['authorName'] ?? 'NOT_SET'
        ]));

        // Strategy 1: Direct field access (original method)
        if (isset($data['authorAvatar']) && !empty($data['authorAvatar'])) {
            $avatarUrl = $data['authorAvatar'];
            error_log('AVATAR DEBUG - Strategy 1 used: ' . $avatarUrl);
        }

        // Strategy 2: Email-based avatar lookup from team database
        if (!$avatarUrl && $authorEmail !== 'anonymous@example.com') {
            try {
                error_log('AVATAR DEBUG - Attempting email lookup for: ' . $authorEmail);
                $avatarLookupStmt = $db->prepare("SELECT avatar_url FROM avatar_profiles WHERE email = ?");
                $avatarLookupStmt->execute([$authorEmail]);
                $teamMember = $avatarLookupStmt->fetch();

                error_log('AVATAR DEBUG - Database lookup result: ' . json_encode($teamMember));

                if ($teamMember && !empty($teamMember['avatar_url'])) {
                    $avatarUrl = $teamMember['avatar_url'];
                    error_log('AVATAR DEBUG - Strategy 2 success: ' . $avatarUrl);
                } else {
                    error_log('AVATAR DEBUG - Strategy 2 failed: No team member found or empty avatar_url');
                }
            } catch (Exception $e) {
                // Log the error but continue with comment creation
                error_log('AVATAR DEBUG - Strategy 2 exception: ' . $e->getMessage());
            }
        }

        // Strategy 3: Fallback to generated avatar if no match found
        if (!$avatarUrl) {
            $authorName = $data['authorName'] ?? 'Anonymous';
            $avatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode($authorName) . '&background=4f46e5&color=fff&size=64';
            error_log('AVATAR DEBUG - Strategy 3 fallback: ' . $avatarUrl);
        }

        error_log('AVATAR DEBUG - Final avatar URL: ' . $avatarUrl);

        // Debug: Log the data being inserted
        if ($attachmentsColumnExists) {
            $insertData = [
                $comment_id,
                $data['taskId'],
                $data['content'],
                $data['authorId'] ?? 'anonymous',
                $data['authorName'] ?? 'Anonymous',
                $data['authorEmail'] ?? 'anonymous@example.com',
                $avatarUrl,
                $attachmentsJson
            ];
        } else {
            $insertData = [
                $comment_id,
                $data['taskId'],
                $data['content'],
                $data['authorId'] ?? 'anonymous',
                $data['authorName'] ?? 'Anonymous',
                $data['authorEmail'] ?? 'anonymous@example.com',
                $avatarUrl
            ];
        }

        $result = $stmt->execute($insertData);

        // Debug: Log the insert result
        error_log('Comment Insert Debug: ' . json_encode([
            'comment_id' => $comment_id,
            'insert_success' => $result,
            'attachments_column_exists' => $attachmentsColumnExists,
            'insert_data_count' => count($insertData)
        ]));

        // ENHANCED AVATAR FIX: Always apply avatar, log the process, and verify
        error_log('Avatar Processing Debug: ' . json_encode([
            'step' => 'before_update',
            'comment_id' => $comment_id,
            'avatar_url' => $avatarUrl,
            'author_email' => $authorEmail,
            'insert_result' => $result
        ]));

        if ($result && $avatarUrl) {
            $avatarUpdateStmt = $db->prepare("UPDATE comments SET author_avatar = ? WHERE id = ?");
            $avatarUpdateResult = $avatarUpdateStmt->execute([$avatarUrl, $comment_id]);

            // Verify the update worked
            $verifyStmt = $db->prepare("SELECT author_avatar FROM comments WHERE id = ?");
            $verifyStmt->execute([$comment_id]);
            $finalAvatar = $verifyStmt->fetch();

            error_log('Avatar Fix Complete: ' . json_encode([
                'comment_id' => $comment_id,
                'attempted_avatar' => $avatarUrl,
                'update_success' => $avatarUpdateResult,
                'final_avatar' => $finalAvatar['author_avatar'] ?? 'NOT_FOUND'
            ]));
        } else {
            error_log('Avatar Fix Skipped: ' . json_encode([
                'comment_id' => $comment_id,
                'avatar_url' => $avatarUrl,
                'insert_result' => $result,
                'reason' => !$result ? 'Insert failed' : 'No avatar URL'
            ]));
        }

        // Get the created comment with all fields to return to frontend
        if ($attachmentsColumnExists) {
            $stmt = $db->prepare("
                SELECT
                    id,
                    task_id as taskId,
                    content,
                    content_type as contentType,
                    author_id as authorId,
                    author_name as authorName,
                    author_email as authorEmail,
                    author_avatar as authorAvatar,
                    attachments,
                    created_at as createdAt,
                    updated_at as updatedAt,
                    edited_at as editedAt,
                    is_deleted as isDeleted,
                    is_edited as isEdited,
                    flagged
                FROM comments
                WHERE id = ?
            ");
        } else {
            $stmt = $db->prepare("
                SELECT
                    id,
                    task_id as taskId,
                    content,
                    content_type as contentType,
                    author_id as authorId,
                    author_name as authorName,
                    author_email as authorEmail,
                    author_avatar as authorAvatar,
                    created_at as createdAt,
                    updated_at as updatedAt,
                    edited_at as editedAt,
                    is_deleted as isDeleted,
                    is_edited as isEdited,
                    flagged
                FROM comments
                WHERE id = ?
            ");
        }
        try {
            $stmt->execute([$comment_id]);
            $createdComment = $stmt->fetch();
        } catch (Exception $e) {
            error_log('Comment Fetch Error: ' . $e->getMessage());
            $createdComment = false;
        }

        // Debug: Log the fetch result
        error_log('Comment Fetch Debug: ' . json_encode([
            'comment_id' => $comment_id,
            'fetch_success' => !!$createdComment,
            'fetched_fields' => $createdComment ? array_keys($createdComment) : 'NO_DATA'
        ]));

        if ($createdComment) {
            // Parse attachments JSON (if column exists)
            $attachments = [];
            if ($attachmentsColumnExists && !empty($createdComment['attachments'])) {
                $parsedAttachments = json_decode($createdComment['attachments'], true);
                if (is_array($parsedAttachments)) {
                    $attachments = $parsedAttachments;
                }
            }

            // Return the full comment object like the GET endpoint does
            sendJSON([
                'success' => true,
                'data' => [
                    'id' => $createdComment['id'],
                    'taskId' => $createdComment['taskId'],
                    'content' => $createdComment['content'],
                    'contentType' => $createdComment['contentType'] ?? 'plain',
                    'authorId' => $createdComment['authorId'],
                    'authorName' => $createdComment['authorName'],
                    'authorEmail' => $createdComment['authorEmail'],
                    'authorAvatar' => $createdComment['authorAvatar'],
                    'createdAt' => $createdComment['createdAt'],
                    'updatedAt' => $createdComment['updatedAt'],
                    'editedAt' => $createdComment['editedAt'],
                    'isDeleted' => (bool)$createdComment['isDeleted'],
                    'isEdited' => (bool)$createdComment['isEdited'],
                    'mentions' => [],
                    'attachments' => $attachments,
                    'reactions' => [],
                    'metadata' => [
                        'flagged' => (bool)$createdComment['flagged'],
                        'editHistory' => [],
                        'source' => 'web'
                    ]
                ],
                'status' => 200,
                'timestamp' => date('c')
            ]);
        } else {
            // Fallback: Create response from the insert data
            sendJSON([
                'success' => true,
                'data' => [
                    'id' => $comment_id,
                    'taskId' => $data['taskId'],
                    'content' => $data['content'],
                    'contentType' => $data['contentType'] ?? 'plain',
                    'authorId' => $data['authorId'] ?? 'anonymous',
                    'authorName' => $data['authorName'] ?? 'Anonymous',
                    'authorEmail' => $data['authorEmail'] ?? 'anonymous@example.com',
                    'authorAvatar' => $avatarUrl,
                    'createdAt' => date('Y-m-d H:i:s'),
                    'updatedAt' => date('Y-m-d H:i:s'),
                    'editedAt' => null,
                    'isDeleted' => false,
                    'isEdited' => false,
                    'mentions' => [],
                    'attachments' => $attachmentsColumnExists && $attachmentsJson ? json_decode($attachmentsJson, true) : [],
                    'reactions' => [],
                    'metadata' => [
                        'flagged' => false,
                        'editHistory' => [],
                        'source' => 'web'
                    ]
                ],
                'status' => 200,
                'timestamp' => date('c')
            ]);
        }

    } elseif ($method === 'GET' && $endpoint === 'analytics') {
        // Analytics endpoint
        $type = $_GET['type'] ?? 'executive';
        $timeRange = $_GET['timeRange'] ?? '30d';
        $departments = isset($_GET['departments']) ? explode(',', $_GET['departments']) : [];

        if ($type === 'executive') {
            // Executive analytics
            $analytics = generateExecutiveAnalytics($db, $timeRange, $departments);
            sendJSON($analytics);

        } elseif ($type === 'department') {
            $department = $_GET['department'] ?? '';
            if (empty($department)) {
                sendError('Department parameter required for department analytics', 400);
            }
            $analytics = generateDepartmentAnalytics($db, $department, $timeRange);
            sendJSON($analytics);

        } elseif ($type === 'trends') {
            $metric = $_GET['metric'] ?? 'taskCount';
            $granularity = $_GET['granularity'] ?? 'daily';

            // Parse departments parameter if provided
            $departments = [];
            if (isset($_GET['departments']) && !empty($_GET['departments'])) {
                $departments = array_filter(array_map('trim', explode(',', $_GET['departments'])));
            }

            $analytics = generateTrendAnalytics($db, $metric, $timeRange, $granularity, $departments);
            sendJSON($analytics);

        } else {
            sendError('Invalid analytics type. Use: executive, department, or trends', 400);
        }

    } elseif ($method === 'POST' && $endpoint === 'analytics') {
        // Handle report generation
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['reportConfig'])) {
            sendError('Report configuration required', 400);
            return;
        }

        $reportConfig = $input['reportConfig'];
        $report = generateCustomReport($db, $reportConfig);

        if ($report['success']) {
            sendJSON($report);
        } else {
            sendError($report['error'], 500);
        }

    } else {
        sendError('Method not allowed', 405);
    }

} catch (Exception $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
?>