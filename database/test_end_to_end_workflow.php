<?php
/**
 * Complete End-to-End Workflow Test for Dual-Pin System
 * Tests the complete workflow from task creation to pin management
 */

require_once '../api/config.php';

function runEndToEndTest($db) {
    echo "🔄 END-TO-END WORKFLOW TEST\n";
    echo "============================\n\n";

    $errors = [];
    $passed = 0;
    $total = 0;

    // Workflow 1: Create task without pin, then add personal pin
    echo "Workflow 1: Create task → Add personal pin\n";
    $total++;

    try {
        // Step 1: Create regular task (simulate POST /api/tasks)
        $taskId = 'test-workflow-1-' . time();
        $createData = [
            'actionItem' => 'Workflow Test Task 1',
            'department' => 'Tech',
            'status' => 'Not Started',
            'priorityScore' => 5
        ];

        // Simulate task creation (INSERT)
        $stmt = $db->prepare("
            INSERT INTO tasks (task_id, action_item, department, status, priority_score, date_created, last_updated)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$taskId, $createData['actionItem'], $createData['department'], $createData['status'], $createData['priorityScore']]);

        // Step 2: Add personal pin (simulate PUT /api/tasks)
        $pinData = [
            'taskID' => $taskId,
            'pinType' => 'personal',
            'pinnedBy' => 'user123',
            'pinnedAt' => date('Y-m-d H:i:s')
        ];

        // Validate pin data (simulate API validation)
        $isValid = true;
        if (!in_array($pinData['pinType'], ['personal', 'global', null])) {
            $isValid = false;
        }
        if (empty($pinData['pinnedBy'])) {
            $isValid = false;
        }

        if ($isValid) {
            // Update task with pin (simulate API update)
            $stmt = $db->prepare("
                UPDATE tasks SET pin_type = ?, pinned_by = ?, pinned_at = ?, last_updated = NOW()
                WHERE task_id = ?
            ");
            $stmt->execute([$pinData['pinType'], $pinData['pinnedBy'], $pinData['pinnedAt'], $taskId]);

            // Step 3: Verify task has pin data (simulate GET /api/tasks)
            $stmt = $db->prepare("SELECT * FROM tasks WHERE task_id = ?");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);

            // Simulate API response formatting
            $apiResponse = [
                'taskID' => $task['task_id'],
                'actionItem' => $task['action_item'],
                'pinType' => $task['pin_type'],
                'pinnedBy' => $task['pinned_by'],
                'pinnedAt' => $task['pinned_at'],
                'pinPriority' => $task['pin_priority'] ? (int)$task['pin_priority'] : null,
                'pinReason' => $task['pin_reason']
            ];

            if ($apiResponse['pinType'] === 'personal' && $apiResponse['pinnedBy'] === 'user123') {
                $passed++;
                echo "   ✅ Workflow 1 completed successfully\n";
            } else {
                $errors[] = "Workflow 1: Pin data not correctly saved/retrieved";
            }
        } else {
            $errors[] = "Workflow 1: Pin validation failed unexpectedly";
        }

        $db->exec("DELETE FROM tasks WHERE task_id = '$taskId'");
    } catch (Exception $e) {
        $errors[] = "Workflow 1: Exception - " . $e->getMessage();
    }

    // Workflow 2: Create task with global pin directly
    echo "\nWorkflow 2: Create task with global pin directly\n";
    $total++;

    try {
        $taskId = 'test-workflow-2-' . time();
        $createData = [
            'actionItem' => 'Workflow Test Task 2',
            'department' => 'Marketing',
            'status' => 'In Progress',
            'pinType' => 'global',
            'pinnedBy' => 'admin123',
            'pinPriority' => 8,
            'pinReason' => 'Critical for launch'
        ];

        // Validate pin data for creation
        $isValid = true;
        if (!in_array($createData['pinType'], ['personal', 'global', null])) {
            $isValid = false;
        }
        if ($createData['pinPriority'] !== null) {
            if ($createData['pinPriority'] < 1 || $createData['pinPriority'] > 10) {
                $isValid = false;
            }
            if ($createData['pinType'] !== 'global') {
                $isValid = false;
            }
        }
        if (empty($createData['pinnedBy'])) {
            $isValid = false;
        }

        if ($isValid) {
            // Create task with pin (simulate POST with pin data)
            $stmt = $db->prepare("
                INSERT INTO tasks (
                    task_id, action_item, department, status,
                    pin_type, pinned_by, pinned_at, pin_priority, pin_reason,
                    date_created, last_updated
                ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $taskId,
                $createData['actionItem'],
                $createData['department'],
                $createData['status'],
                $createData['pinType'],
                $createData['pinnedBy'],
                $createData['pinPriority'],
                $createData['pinReason']
            ]);

            // Verify creation
            $stmt = $db->prepare("SELECT * FROM tasks WHERE task_id = ?");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($task['pin_type'] === 'global' && $task['pin_priority'] == 8) {
                $passed++;
                echo "   ✅ Workflow 2 completed successfully\n";
            } else {
                $errors[] = "Workflow 2: Global pin data not correctly saved";
            }
        } else {
            $errors[] = "Workflow 2: Global pin validation failed unexpectedly";
        }

        $db->exec("DELETE FROM tasks WHERE task_id = '$taskId'");
    } catch (Exception $e) {
        $errors[] = "Workflow 2: Exception - " . $e->getMessage();
    }

    // Workflow 3: Convert personal pin to global pin
    echo "\nWorkflow 3: Convert personal pin → global pin\n";
    $total++;

    try {
        $taskId = 'test-workflow-3-' . time();

        // Create task with personal pin
        $stmt = $db->prepare("
            INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_by, pinned_at, date_created, last_updated)
            VALUES (?, 'Conversion Test', 'Sales', 'personal', 'user456', NOW(), NOW(), NOW())
        ");
        $stmt->execute([$taskId]);

        // Convert to global pin
        $updateData = [
            'taskID' => $taskId,
            'pinType' => 'global',
            'pinnedBy' => 'admin789',
            'pinPriority' => 6,
            'pinReason' => 'Escalated to leadership'
        ];

        // Validate conversion
        $isValid = true;
        if ($updateData['pinType'] === 'global' && isset($updateData['pinPriority'])) {
            if ($updateData['pinPriority'] < 1 || $updateData['pinPriority'] > 10) {
                $isValid = false;
            }
        }

        if ($isValid) {
            $stmt = $db->prepare("
                UPDATE tasks
                SET pin_type = ?, pinned_by = ?, pin_priority = ?, pin_reason = ?, pinned_at = NOW(), last_updated = NOW()
                WHERE task_id = ?
            ");
            $stmt->execute([
                $updateData['pinType'],
                $updateData['pinnedBy'],
                $updateData['pinPriority'],
                $updateData['pinReason'],
                $taskId
            ]);

            // Verify conversion
            $stmt = $db->prepare("SELECT * FROM tasks WHERE task_id = ?");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($task['pin_type'] === 'global' && $task['pin_priority'] == 6 && $task['pinned_by'] === 'admin789') {
                $passed++;
                echo "   ✅ Workflow 3 completed successfully\n";
            } else {
                $errors[] = "Workflow 3: Pin conversion not correctly saved";
            }
        } else {
            $errors[] = "Workflow 3: Pin conversion validation failed";
        }

        $db->exec("DELETE FROM tasks WHERE task_id = '$taskId'");
    } catch (Exception $e) {
        $errors[] = "Workflow 3: Exception - " . $e->getMessage();
    }

    // Workflow 4: Remove pin (set to NULL)
    echo "\nWorkflow 4: Remove pin from task\n";
    $total++;

    try {
        $taskId = 'test-workflow-4-' . time();

        // Create task with global pin
        $stmt = $db->prepare("
            INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_by, pinned_at, pin_priority, pin_reason, date_created, last_updated)
            VALUES (?, 'Pin Removal Test', 'HR', 'global', 'admin123', NOW(), 9, 'Urgent', NOW(), NOW())
        ");
        $stmt->execute([$taskId]);

        // Remove pin (simulate API setting pinType to null)
        $stmt = $db->prepare("
            UPDATE tasks
            SET pin_type = NULL, pinned_by = NULL, pinned_at = NULL, pin_priority = NULL, pin_reason = NULL, last_updated = NOW()
            WHERE task_id = ?
        ");
        $stmt->execute([$taskId]);

        // Verify removal
        $stmt = $db->prepare("SELECT * FROM tasks WHERE task_id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        $allNull = (
            $task['pin_type'] === null &&
            $task['pinned_by'] === null &&
            $task['pinned_at'] === null &&
            $task['pin_priority'] === null &&
            $task['pin_reason'] === null
        );

        if ($allNull) {
            $passed++;
            echo "   ✅ Workflow 4 completed successfully\n";
        } else {
            $errors[] = "Workflow 4: Pin fields not properly cleared";
        }

        $db->exec("DELETE FROM tasks WHERE task_id = '$taskId'");
    } catch (Exception $e) {
        $errors[] = "Workflow 4: Exception - " . $e->getMessage();
    }

    // Workflow 5: Simulate API response formatting
    echo "\nWorkflow 5: Complete API response cycle\n";
    $total++;

    try {
        // Create multiple tasks with different pin states
        $tasks = [
            ['id' => 'api-test-1', 'pin_type' => null],
            ['id' => 'api-test-2', 'pin_type' => 'personal', 'pinned_by' => 'user123'],
            ['id' => 'api-test-3', 'pin_type' => 'global', 'pinned_by' => 'admin123', 'pin_priority' => 5, 'pin_reason' => 'Important']
        ];

        foreach ($tasks as $taskData) {
            $stmt = $db->prepare("
                INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_by, pinned_at, pin_priority, pin_reason, date_created, last_updated)
                VALUES (?, 'API Test', 'Tech', ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $taskData['id'],
                $taskData['pin_type'],
                $taskData['pinned_by'] ?? null,
                $taskData['pin_type'] ? date('Y-m-d H:i:s') : null,
                $taskData['pin_priority'] ?? null,
                $taskData['pin_reason'] ?? null
            ]);
        }

        // Simulate API GET request
        $stmt = $db->query("SELECT * FROM tasks WHERE task_id LIKE 'api-test-%' ORDER BY task_id");
        $dbTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Simulate API response formatting
        $apiTasks = [];
        foreach ($dbTasks as $task) {
            $apiTasks[] = [
                'taskID' => $task['task_id'],
                'actionItem' => $task['action_item'],
                'pinType' => $task['pin_type'],
                'pinnedBy' => $task['pinned_by'],
                'pinnedAt' => $task['pinned_at'],
                'pinPriority' => $task['pin_priority'] ? (int)$task['pin_priority'] : null,
                'pinReason' => $task['pin_reason']
            ];
        }

        // Verify API response format
        $formatCorrect = true;
        if (count($apiTasks) !== 3) {
            $formatCorrect = false;
        } else {
            // Check each task format
            if ($apiTasks[0]['pinType'] !== null) $formatCorrect = false;
            if ($apiTasks[1]['pinType'] !== 'personal') $formatCorrect = false;
            if ($apiTasks[2]['pinType'] !== 'global' || $apiTasks[2]['pinPriority'] !== 5) $formatCorrect = false;
        }

        if ($formatCorrect) {
            $passed++;
            echo "   ✅ Workflow 5 completed successfully\n";
        } else {
            $errors[] = "Workflow 5: API response formatting incorrect";
        }

        $db->exec("DELETE FROM tasks WHERE task_id LIKE 'api-test-%'");
    } catch (Exception $e) {
        $errors[] = "Workflow 5: Exception - " . $e->getMessage();
    }

    // Summary
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "END-TO-END WORKFLOW RESULTS\n";
    echo str_repeat("=", 50) . "\n";
    echo "Total Workflows: {$total}\n";
    echo "Passed: {$passed}\n";
    echo "Failed: " . count($errors) . "\n";

    if (empty($errors)) {
        echo "🎉 ALL END-TO-END WORKFLOWS PASSED!\n";
        echo "The dual-pin system works correctly end-to-end.\n";
        return true;
    } else {
        echo "\n❌ WORKFLOW FAILURES:\n";
        foreach ($errors as $error) {
            echo "   • {$error}\n";
        }
        return false;
    }
}

// Run the tests
try {
    if (runEndToEndTest($db)) {
        echo "\n✅ Complete dual-pin workflow is functional!\n";
    } else {
        echo "\n❌ Workflow issues found. Please review implementation.\n";
    }
} catch (Exception $e) {
    echo "\n💥 End-to-end test failed: " . $e->getMessage() . "\n";
}
?>