<?php
/**
 * TaskMaster Data Migration Script
 * Migrates data from Google Sheets to MySQL database
 */

require_once '../api/config.php';

// Configuration
$GOOGLE_SHEETS_CSV_URL = 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/export?format=csv&gid=0';
$DRY_RUN = true; // Set to false to actually insert data

echo "TaskMaster Data Migration Script\n";
echo "================================\n\n";

if ($DRY_RUN) {
    echo "*** DRY RUN MODE - No data will be inserted ***\n\n";
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Method 1: Load from CSV file
    echo "Choose migration method:\n";
    echo "1. Load from local CSV file\n";
    echo "2. Load from Google Sheets URL\n";
    echo "3. Load sample data for testing\n";

    $method = readline("Enter choice (1-3): ");

    switch ($method) {
        case '1':
            $csvFile = readline("Enter CSV file path: ");
            if (!file_exists($csvFile)) {
                throw new Exception("CSV file not found: $csvFile");
            }
            $data = loadFromCSV($csvFile);
            break;

        case '2':
            $url = readline("Enter Google Sheets CSV URL: ");
            $data = loadFromURL($url);
            break;

        case '3':
            $data = generateSampleData();
            break;

        default:
            throw new Exception("Invalid choice");
    }

    echo "\nLoaded " . count($data) . " rows\n";

    // Process and insert data
    $inserted = 0;
    $errors = 0;

    foreach ($data as $index => $row) {
        try {
            $taskData = mapRowToTask($row, $index);

            if (!$DRY_RUN) {
                insertTask($db, $taskData);
            }

            $inserted++;
            echo "✓ Processed: " . ($taskData['action_item'] ?? "Row $index") . "\n";

        } catch (Exception $e) {
            $errors++;
            echo "✗ Error on row $index: " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== Migration Summary ===\n";
    echo "Total rows: " . count($data) . "\n";
    echo "Successfully processed: $inserted\n";
    echo "Errors: $errors\n";

    if ($DRY_RUN) {
        echo "\n*** This was a dry run. Set \$DRY_RUN = false to actually insert data ***\n";
    } else {
        echo "\n✅ Migration completed!\n";
    }

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}

/**
 * Load data from CSV file
 */
function loadFromCSV($file) {
    $data = [];
    $handle = fopen($file, 'r');

    if ($handle === false) {
        throw new Exception("Could not open CSV file");
    }

    $headers = fgetcsv($handle); // Skip header row

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) === count($headers)) {
            $data[] = array_combine($headers, $row);
        }
    }

    fclose($handle);
    return $data;
}

/**
 * Load data from URL
 */
function loadFromURL($url) {
    $csvData = file_get_contents($url);
    if ($csvData === false) {
        throw new Exception("Could not fetch data from URL");
    }

    $lines = explode("\n", $csvData);
    $headers = str_getcsv(array_shift($lines));
    $data = [];

    foreach ($lines as $line) {
        if (trim($line)) {
            $row = str_getcsv($line);
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
        }
    }

    return $data;
}

/**
 * Generate sample data for testing
 */
function generateSampleData() {
    return [
        [
            'Task ID' => 'NT-123456-ABC',
            'Action Item' => 'Set up new project dashboard',
            'Department' => 'Tech',
            'Owner(s)' => 'John Doe, Jane Smith',
            'Status' => 'In Progress',
            'Priority Score' => '75',
            'Progress %' => '50',
            'Problem Description' => 'Need centralized project tracking',
            'Proposed Solution' => 'Implement dashboard with real-time updates',
            'Due Date' => '2024-12-31',
            'Predicted Hours' => '40',
            'Notes' => 'Initial setup completed'
        ],
        [
            'Task ID' => 'NT-123457-DEF',
            'Action Item' => 'Review Q4 marketing campaigns',
            'Department' => 'Marketing',
            'Owner(s)' => 'Marketing Team',
            'Status' => 'Pending Review',
            'Priority Score' => '60',
            'Progress %' => '80',
            'Problem Description' => '',
            'Proposed Solution' => '',
            'Due Date' => '2024-11-15',
            'Predicted Hours' => '20',
            'Notes' => 'Campaign performance analysis needed'
        ]
    ];
}

/**
 * Map CSV row to task structure
 */
function mapRowToTask($row, $index) {
    // Common header variations
    $headerMap = [
        'task_id' => ['Task ID', 'TaskID', 'Task_ID', 'ID'],
        'action_item' => ['Action Item', 'ActionItem', 'Action_Item', 'Title', 'Task'],
        'department' => ['Department', 'Dept', 'Team'],
        'owners' => ['Owner(s)', 'Owners', 'Owner', 'Assigned To', 'ownerS'],
        'status' => ['Status', 'Task Status', 'Current Status'],
        'priority_score' => ['Priority Score', 'Priority', 'PriorityScore'],
        'progress_percentage' => ['Progress %', 'Progress', 'Progress Percentage'],
        'problem_description' => ['Problem Description', 'Problem', 'Issue'],
        'proposed_solution' => ['Proposed Solution', 'Solution', 'Resolution'],
        'due_date' => ['Due Date', 'DueDate', 'Target Date'],
        'predicted_hours' => ['Predicted Hours', 'Est Hours', 'Estimated Hours'],
        'actual_hours_spent' => ['Actual Hours', 'Hours Spent', 'Actual Hours Spent'],
        'notes_log' => ['Notes', 'Notes Log', 'Comments']
    ];

    $task = [];

    foreach ($headerMap as $dbField => $possibleHeaders) {
        $value = null;
        foreach ($possibleHeaders as $header) {
            if (isset($row[$header]) && $row[$header] !== '') {
                $value = trim($row[$header]);
                break;
            }
        }
        $task[$dbField] = $value;
    }

    // Generate task ID if missing
    if (empty($task['task_id'])) {
        $task['task_id'] = generateTaskId();
    }

    // Set defaults
    $task['action_item'] = $task['action_item'] ?: "Task from migration (Row $index)";
    $task['department'] = $task['department'] ?: 'General';
    $task['status'] = $task['status'] ?: 'Not Started';
    $task['priority_score'] = is_numeric($task['priority_score']) ? (int)$task['priority_score'] : 0;
    $task['progress_percentage'] = is_numeric($task['progress_percentage']) ? (int)$task['progress_percentage'] : 0;

    // Parse date
    if ($task['due_date']) {
        $date = DateTime::createFromFormat('Y-m-d', $task['due_date']);
        if (!$date) {
            $date = DateTime::createFromFormat('m/d/Y', $task['due_date']);
        }
        if (!$date) {
            $date = DateTime::createFromFormat('d/m/Y', $task['due_date']);
        }
        $task['due_date'] = $date ? $date->format('Y-m-d') : null;
    }

    return $task;
}

/**
 * Insert task into database
 */
function insertTask($db, $task) {
    $stmt = $db->prepare("
        INSERT INTO tasks (
            task_id, action_item, department, owners, status,
            priority_score, progress_percentage, problem_description,
            proposed_solution, due_date, predicted_hours, actual_hours_spent,
            notes_log, last_updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $task['task_id'],
        $task['action_item'],
        $task['department'],
        $task['owners'],
        $task['status'],
        $task['priority_score'],
        $task['progress_percentage'],
        $task['problem_description'],
        $task['proposed_solution'],
        $task['due_date'],
        $task['predicted_hours'],
        $task['actual_hours_spent'],
        $task['notes_log'],
        'Migration Script'
    ]);
}
?>