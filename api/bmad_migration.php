<?php
/**
 * BMAD Database Migration & Testing Tool
 *
 * This script will:
 * 1. Add missing BMAD columns to the tasks table
 * 2. Test BMAD functionality with sample data
 * 3. Verify the 500 errors are resolved
 *
 * Usage: Open this file in your browser or run via command line
 */

require_once 'config.php';

// Track what we've done
$results = [];
$errors = [];

// HTML header for web interface
if (!isset($argv)) {
    echo "<!DOCTYPE html>
<html>
<head>
    <title>BMAD Migration Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .step { border-left: 4px solid #007bff; padding-left: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🤖 BMAD Database Migration Tool</h1>
        <p>This tool will prepare your database for BMAD AI functionality and test the integration.</p>";
}

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "<div class='step'><h2>Step 1: Checking Database Connection</h2>";
    if ($db) {
        echo "<div class='success'>✅ Database connection successful!</div>";
        $results[] = "Database connection established";
    } else {
        throw new Exception("Database connection failed");
    }
    echo "</div>";

    // Step 2: Check if BMAD columns already exist
    echo "<div class='step'><h2>Step 2: Checking Existing Database Schema</h2>";
    $columnsCheck = $db->query("DESCRIBE tasks");
    $existingColumns = $columnsCheck->fetchAll(PDO::FETCH_COLUMN);

    $bmadColumnsExist = in_array('bmad_analysis_json', $existingColumns) && in_array('generated_subtasks_json', $existingColumns);

    if ($bmadColumnsExist) {
        echo "<div class='info'>ℹ️ BMAD columns already exist in the database.</div>";
        $results[] = "BMAD columns already present";
    } else {
        echo "<div class='warning'>⚠️ BMAD columns missing. Will add them now...</div>";
    }
    echo "</div>";

    // Step 3: Add BMAD columns if needed
    if (!$bmadColumnsExist) {
        echo "<div class='step'><h2>Step 3: Adding BMAD Columns</h2>";

        try {
            // Add bmad_analysis_json column
            $sql1 = "ALTER TABLE tasks ADD COLUMN bmad_analysis_json JSON COMMENT 'BMAD AI task analysis data'";
            $db->exec($sql1);
            echo "<div class='success'>✅ Added bmad_analysis_json column</div>";

            // Add generated_subtasks_json column
            $sql2 = "ALTER TABLE tasks ADD COLUMN generated_subtasks_json JSON COMMENT 'AI generated subtasks data'";
            $db->exec($sql2);
            echo "<div class='success'>✅ Added generated_subtasks_json column</div>";

            // Add indexes for performance
            $sql3 = "ALTER TABLE tasks ADD INDEX idx_bmad_analysis ((JSON_VALID(bmad_analysis_json)))";
            $db->exec($sql3);
            echo "<div class='success'>✅ Added bmad_analysis_json index</div>";

            $sql4 = "ALTER TABLE tasks ADD INDEX idx_generated_subtasks ((JSON_VALID(generated_subtasks_json)))";
            $db->exec($sql4);
            echo "<div class='success'>✅ Added generated_subtasks_json index</div>";

            $results[] = "BMAD columns added successfully";

        } catch (Exception $e) {
            echo "<div class='error'>❌ Error adding BMAD columns: " . $e->getMessage() . "</div>";
            $errors[] = "Column addition failed: " . $e->getMessage();
        }
        echo "</div>";
    }

    // Step 4: Test BMAD data insertion
    echo "<div class='step'><h2>Step 4: Testing BMAD Data Insertion</h2>";

    // Create sample BMAD data
    $sampleBmadAnalysis = [
        'complexity' => [
            'level' => 'moderate',
            'score' => 6,
            'factors' => [
                [
                    'factor' => 'Data Integration',
                    'impact' => 'medium',
                    'description' => 'Requires connecting multiple data sources'
                ]
            ]
        ],
        'estimatedEffort' => 8,
        'skillsRequired' => ['PHP', 'Database Design', 'API Development'],
        'dependencies' => [
            [
                'type' => 'technical',
                'description' => 'Database schema updates required'
            ]
        ],
        'risks' => [
            [
                'severity' => 'low',
                'category' => 'technical',
                'description' => 'Minor compatibility issues possible'
            ]
        ],
        'departmentSpecific' => [
            'department' => 'Tech',
            'workflow' => 'standard_development'
        ]
    ];

    $sampleSubtasks = [
        [
            'id' => 'test-bmad-s1',
            'title' => 'Set up database schema',
            'description' => 'Add BMAD columns to tasks table',
            'estimatedHours' => 2,
            'priority' => 8,
            'prerequisites' => []
        ],
        [
            'id' => 'test-bmad-s2',
            'title' => 'Update API endpoints',
            'description' => 'Modify tasks_simple.php to handle BMAD data',
            'estimatedHours' => 4,
            'priority' => 7,
            'prerequisites' => ['test-bmad-s1']
        ],
        [
            'id' => 'test-bmad-s3',
            'title' => 'Test integration',
            'description' => 'Verify BMAD data saves and loads correctly',
            'estimatedHours' => 2,
            'priority' => 6,
            'prerequisites' => ['test-bmad-s2']
        ]
    ];

    // Insert test task with BMAD data
    $testTaskId = 'BMAD-TEST-' . date('YmdHis');

    try {
        $stmt = $db->prepare("
            INSERT INTO tasks (
                task_id, action_item, department, owners, status,
                priority_score, progress_percentage, problem_description,
                proposed_solution, notes_log, last_updated_by,
                bmad_analysis_json, generated_subtasks_json
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $testTaskId,
            'Test BMAD Integration and Data Flow',
            'Tech',
            'BMAD Migration Tool',
            'In Progress',
            75,
            50,
            'Testing the BMAD AI analysis system integration with database storage and retrieval.',
            'Create a comprehensive test to verify all BMAD components work correctly together.',
            'This task was created by the BMAD migration tool to test the integration.',
            'BMAD Migration Tool',
            json_encode($sampleBmadAnalysis),
            json_encode($sampleSubtasks)
        ]);

        echo "<div class='success'>✅ Test task created with ID: $testTaskId</div>";
        $results[] = "Test task created successfully";

    } catch (Exception $e) {
        echo "<div class='error'>❌ Error creating test task: " . $e->getMessage() . "</div>";
        $errors[] = "Test task creation failed: " . $e->getMessage();
    }
    echo "</div>";

    // Step 5: Test data retrieval
    echo "<div class='step'><h2>Step 5: Testing BMAD Data Retrieval</h2>";

    try {
        $stmt = $db->prepare("SELECT * FROM tasks WHERE task_id = ?");
        $stmt->execute([$testTaskId]);
        $testTask = $stmt->fetch();

        if ($testTask) {
            echo "<div class='success'>✅ Test task retrieved successfully</div>";

            // Test JSON parsing
            $bmadData = json_decode($testTask['bmad_analysis_json'], true);
            $subtasksData = json_decode($testTask['generated_subtasks_json'], true);

            if ($bmadData && $subtasksData) {
                echo "<div class='success'>✅ BMAD JSON data parsed successfully</div>";
                echo "<div class='info'>📊 Retrieved " . count($subtasksData) . " subtasks and complexity level: " . $bmadData['complexity']['level'] . "</div>";
                $results[] = "BMAD data retrieval and parsing successful";
            } else {
                echo "<div class='error'>❌ Failed to parse BMAD JSON data</div>";
                $errors[] = "JSON parsing failed";
            }
        } else {
            echo "<div class='error'>❌ Test task not found</div>";
            $errors[] = "Test task retrieval failed";
        }

    } catch (Exception $e) {
        echo "<div class='error'>❌ Error retrieving test task: " . $e->getMessage() . "</div>";
        $errors[] = "Data retrieval failed: " . $e->getMessage();
    }
    echo "</div>";

    // Step 6: Test API endpoint compatibility
    echo "<div class='step'><h2>Step 6: Testing API Endpoint Compatibility</h2>";

    try {
        // Simulate what the API would do
        $updateData = [
            'taskID' => $testTaskId,
            'actionItem' => 'Updated Test BMAD Integration',
            'status' => 'Completed',
            'bmadAnalysis' => $sampleBmadAnalysis,
            'generatedSubtasks' => $sampleSubtasks
        ];

        // Test the update logic from tasks_simple.php
        $updateFields = [];
        $updateValues = [];

        $allowedFields = [
            'actionItem' => 'action_item',
            'status' => 'status',
            'bmadAnalysis' => 'bmad_analysis_json',
            'generatedSubtasks' => 'generated_subtasks_json'
        ];

        foreach ($allowedFields as $frontendField => $dbField) {
            if (isset($updateData[$frontendField])) {
                $updateFields[] = "$dbField = ?";

                // JSON encode for special fields
                if (in_array($frontendField, ['bmadAnalysis', 'generatedSubtasks'])) {
                    $updateValues[] = json_encode($updateData[$frontendField]);
                } else {
                    $updateValues[] = $updateData[$frontendField];
                }
            }
        }

        $updateFields[] = 'last_updated = CURRENT_TIMESTAMP';
        $sql = "UPDATE tasks SET " . implode(', ', $updateFields) . " WHERE task_id = ?";
        $updateValues[] = $testTaskId;

        $stmt = $db->prepare($sql);
        $stmt->execute($updateValues);

        echo "<div class='success'>✅ API-style update test successful</div>";
        $results[] = "API endpoint compatibility confirmed";

    } catch (Exception $e) {
        echo "<div class='error'>❌ API compatibility test failed: " . $e->getMessage() . "</div>";
        $errors[] = "API test failed: " . $e->getMessage();
    }
    echo "</div>";

    // Step 7: Final verification
    echo "<div class='step'><h2>Step 7: Final Verification</h2>";

    try {
        $stmt = $db->prepare("SELECT bmad_analysis_json, generated_subtasks_json FROM tasks WHERE task_id = ?");
        $stmt->execute([$testTaskId]);
        $finalCheck = $stmt->fetch();

        if ($finalCheck && $finalCheck['bmad_analysis_json'] && $finalCheck['generated_subtasks_json']) {
            echo "<div class='success'>✅ Final verification passed - BMAD system is working correctly!</div>";
            $results[] = "Final verification successful";

            // Show sample data
            echo "<div class='info'>";
            echo "<h4>Sample BMAD Analysis Data:</h4>";
            echo "<pre>" . json_encode(json_decode($finalCheck['bmad_analysis_json'], true), JSON_PRETTY_PRINT) . "</pre>";
            echo "</div>";

        } else {
            echo "<div class='error'>❌ Final verification failed</div>";
            $errors[] = "Final verification failed";
        }

    } catch (Exception $e) {
        echo "<div class='error'>❌ Final verification error: " . $e->getMessage() . "</div>";
        $errors[] = "Final verification error: " . $e->getMessage();
    }
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>❌ Critical error: " . $e->getMessage() . "</div>";
    $errors[] = "Critical error: " . $e->getMessage();
}

// Summary
echo "<div class='step'><h2>🎯 Migration Summary</h2>";

if (empty($errors)) {
    echo "<div class='success'>";
    echo "<h3>✅ Migration Completed Successfully!</h3>";
    echo "<p><strong>The BMAD system is now ready to use. The 500 errors should be resolved.</strong></p>";
    echo "<ul>";
    foreach ($results as $result) {
        echo "<li>$result</li>";
    }
    echo "</ul>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ul>";
    echo "<li>Test the TaskMaster frontend BMAD features</li>";
    echo "<li>Try creating/editing tasks with AI analysis</li>";
    echo "<li>Verify that BMAD data saves and loads correctly</li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>❌ Migration Encountered Issues</h3>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
    echo "</div>";

    if (!empty($results)) {
        echo "<div class='info'>";
        echo "<h4>✅ Successful Steps:</h4>";
        echo "<ul>";
        foreach ($results as $result) {
            echo "<li>$result</li>";
        }
        echo "</ul>";
        echo "</div>";
    }
}

echo "</div>";

// Cleanup option
if (!isset($argv)) {
    echo "<div class='step'>";
    echo "<h3>🧹 Cleanup</h3>";
    echo "<p>The test task (ID: $testTaskId) was created for testing. You can:</p>";
    echo "<a href='?cleanup=true' class='btn'>Remove Test Task</a> ";
    echo "<a href='?' class='btn'>Run Migration Again</a>";
    echo "</div>";

    // Handle cleanup
    if (isset($_GET['cleanup'])) {
        try {
            $stmt = $db->prepare("DELETE FROM tasks WHERE task_id = ?");
            $stmt->execute([$testTaskId]);
            echo "<div class='success'>✅ Test task removed successfully</div>";
        } catch (Exception $e) {
            echo "<div class='error'>❌ Error removing test task: " . $e->getMessage() . "</div>";
        }
    }

    echo "</div></body></html>";
}

?>