<?php
/**
 * CSV Import Script for TaskMaster
 * Upload this file and run it to import your Google Sheets data
 */

require_once 'config.php';

// Turn on error display for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>TaskMaster CSV Import Tool</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {

    try {
        $database = new Database();
        $db = $database->getConnection();

        $csvFile = $_FILES['csv_file']['tmp_name'];

        if (!is_uploaded_file($csvFile)) {
            throw new Exception('No file uploaded or upload error');
        }

        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            throw new Exception('Could not open CSV file');
        }

        // Read the header row
        $headers = fgetcsv($handle);
        echo "<p><strong>CSV Headers found:</strong> " . implode(', ', $headers) . "</p>";

        // Map common header variations to our database fields
        $headerMap = [];
        foreach ($headers as $index => $header) {
            $cleanHeader = strtolower(trim($header));

            switch ($cleanHeader) {
                case 'task id':
                case 'taskid':
                case 'task_id':
                case 'id':
                    $headerMap['task_id'] = $index;
                    break;

                case 'action item':
                case 'actionitem':
                case 'action_item':
                case 'title':
                case 'task':
                    $headerMap['action_item'] = $index;
                    break;

                case 'department':
                case 'dept':
                case 'team':
                    $headerMap['department'] = $index;
                    break;

                case 'owner(s)':
                case 'owners':
                case 'owner':
                case 'assigned to':
                case 'owners':
                    $headerMap['owners'] = $index;
                    break;

                case 'status':
                case 'task status':
                case 'current status':
                    $headerMap['status'] = $index;
                    break;

                case 'priority score':
                case 'priority':
                case 'priorityscore':
                    $headerMap['priority_score'] = $index;
                    break;

                case 'progress %':
                case 'progress':
                case 'progress percentage':
                    $headerMap['progress_percentage'] = $index;
                    break;

                case 'problem description':
                case 'problem':
                case 'issue':
                    $headerMap['problem_description'] = $index;
                    break;

                case 'proposed solution':
                case 'solution':
                case 'resolution':
                    $headerMap['proposed_solution'] = $index;
                    break;

                case 'due date':
                case 'duedate':
                case 'target date':
                    $headerMap['due_date'] = $index;
                    break;

                case 'predicted hours':
                case 'est hours':
                case 'estimated hours':
                    $headerMap['predicted_hours'] = $index;
                    break;

                case 'actual hours':
                case 'hours spent':
                case 'actual hours spent':
                    $headerMap['actual_hours_spent'] = $index;
                    break;

                case 'notes':
                case 'notes log':
                case 'comments':
                    $headerMap['notes_log'] = $index;
                    break;
            }
        }

        echo "<p><strong>Field mapping:</strong></p><ul>";
        foreach ($headerMap as $dbField => $csvIndex) {
            echo "<li>{$dbField} → Column {$csvIndex} ({$headers[$csvIndex]})</li>";
        }
        echo "</ul>";

        // Prepare the insert statement
        $stmt = $db->prepare("
            INSERT INTO tasks (
                task_id, action_item, department, owners, status,
                priority_score, progress_percentage, problem_description,
                proposed_solution, due_date, predicted_hours, actual_hours_spent,
                notes_log, last_updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $imported = 0;
        $errors = 0;

        echo "<h3>Import Progress:</h3>";
        echo "<div style='max-height: 300px; overflow-y: auto; border: 1px solid #ccc; padding: 10px;'>";

        while (($row = fgetcsv($handle)) !== false) {
            try {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Extract data using the header mapping
                $taskId = isset($headerMap['task_id']) ? trim($row[$headerMap['task_id']]) : '';
                $actionItem = isset($headerMap['action_item']) ? trim($row[$headerMap['action_item']]) : '';
                $department = isset($headerMap['department']) ? trim($row[$headerMap['department']]) : 'General';
                $owners = isset($headerMap['owners']) ? trim($row[$headerMap['owners']]) : '';
                $status = isset($headerMap['status']) ? trim($row[$headerMap['status']]) : 'Not Started';
                $priorityScore = isset($headerMap['priority_score']) ? (int)$row[$headerMap['priority_score']] : 0;
                $progressPercentage = isset($headerMap['progress_percentage']) ? (int)$row[$headerMap['progress_percentage']] : 0;
                $problemDescription = isset($headerMap['problem_description']) ? trim($row[$headerMap['problem_description']]) : '';
                $proposedSolution = isset($headerMap['proposed_solution']) ? trim($row[$headerMap['proposed_solution']]) : '';
                $dueDate = isset($headerMap['due_date']) ? trim($row[$headerMap['due_date']]) : null;
                $predictedHours = isset($headerMap['predicted_hours']) ? $row[$headerMap['predicted_hours']] : null;
                $actualHours = isset($headerMap['actual_hours_spent']) ? $row[$headerMap['actual_hours_spent']] : null;
                $notesLog = isset($headerMap['notes_log']) ? trim($row[$headerMap['notes_log']]) : '';

                // Generate task ID if missing
                if (empty($taskId)) {
                    $taskId = generateTaskId();
                }

                // Skip if no action item
                if (empty($actionItem)) {
                    echo "<p style='color: orange;'>⚠️ Skipped row - no action item</p>";
                    continue;
                }

                // Parse date
                if ($dueDate && $dueDate !== '') {
                    $date = DateTime::createFromFormat('Y-m-d', $dueDate);
                    if (!$date) {
                        $date = DateTime::createFromFormat('m/d/Y', $dueDate);
                    }
                    if (!$date) {
                        $date = DateTime::createFromFormat('d/m/Y', $dueDate);
                    }
                    $dueDate = $date ? $date->format('Y-m-d') : null;
                } else {
                    $dueDate = null;
                }

                // Parse numeric fields
                $predictedHours = is_numeric($predictedHours) ? (float)$predictedHours : null;
                $actualHours = is_numeric($actualHours) ? (float)$actualHours : null;

                // Insert into database
                $stmt->execute([
                    $taskId,
                    $actionItem,
                    $department,
                    $owners,
                    $status,
                    $priorityScore,
                    $progressPercentage,
                    $problemDescription,
                    $proposedSolution,
                    $dueDate,
                    $predictedHours,
                    $actualHours,
                    $notesLog,
                    'CSV Import'
                ]);

                $imported++;
                echo "<p style='color: green;'>✅ Imported: {$actionItem} (ID: {$taskId})</p>";

            } catch (Exception $e) {
                $errors++;
                echo "<p style='color: red;'>❌ Error importing row: " . $e->getMessage() . "</p>";
            }
        }

        echo "</div>";
        fclose($handle);

        echo "<h3>Import Summary:</h3>";
        echo "<p><strong>Successfully imported:</strong> {$imported} tasks</p>";
        echo "<p><strong>Errors:</strong> {$errors}</p>";

        if ($imported > 0) {
            echo "<p style='color: green; font-weight: bold;'>🎉 Import completed! Refresh your TaskMaster app to see the tasks.</p>";
        }

    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>Import failed:</strong> " . $e->getMessage() . "</p>";
    }

} else {
    // Show upload form
    ?>
    <form method="POST" enctype="multipart/form-data" style="margin: 20px 0;">
        <div style="margin: 10px 0;">
            <label for="csv_file"><strong>Select CSV file from Google Sheets:</strong></label><br>
            <input type="file" name="csv_file" id="csv_file" accept=".csv" required style="margin: 5px 0;">
        </div>
        <div style="margin: 10px 0;">
            <button type="submit" style="background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
                Import CSV Data
            </button>
        </div>
    </form>

    <div style="background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <h4>Instructions:</h4>
        <ol>
            <li>Open your Google Sheet</li>
            <li>Go to <strong>File → Download → Comma Separated Values (.csv)</strong></li>
            <li>Save the CSV file to your computer</li>
            <li>Upload it using the form above</li>
            <li>The script will automatically map your columns to the database fields</li>
        </ol>
    </div>
    <?php
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #333; }
p { margin: 5px 0; }
</style>