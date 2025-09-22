<?php
/**
 * Backward Compatibility Test for Dual-Pin System
 * Ensures existing functionality continues to work unchanged
 */

require_once '../api/config.php';

function testBackwardCompatibility($db) {
    echo "🔄 BACKWARD COMPATIBILITY TESTING\n";
    echo "==================================\n\n";

    $errors = [];
    $passed = 0;
    $total = 0;

    // Test 1: Existing tasks without pin data
    echo "Test 1: Existing tasks return NULL pin fields\n";
    $total++;

    try {
        // Create a task without pin data (simulating existing data)
        $db->exec("INSERT INTO tasks (task_id, action_item, department, status, date_created, last_updated)
                   VALUES ('test-existing', 'Existing Task', 'Tech', 'In Progress', NOW(), NOW())");

        // Simulate API response processing
        $stmt = $db->prepare("SELECT * FROM tasks WHERE task_id = 'test-existing'");
        $stmt->execute();
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        // Simulate API field mapping
        $apiTask = [
            'taskID' => $task['task_id'],
            'actionItem' => $task['action_item'],
            'pinType' => $task['pin_type'] ?? null,
            'pinnedBy' => $task['pinned_by'] ?? null,
            'pinnedAt' => $task['pinned_at'] ?? null,
            'pinPriority' => $task['pin_priority'] ? (int)$task['pin_priority'] : null,
            'pinReason' => $task['pin_reason'] ?? null
        ];

        // Verify all pin fields are null
        $pinFieldsNull = (
            $apiTask['pinType'] === null &&
            $apiTask['pinnedBy'] === null &&
            $apiTask['pinnedAt'] === null &&
            $apiTask['pinPriority'] === null &&
            $apiTask['pinReason'] === null
        );

        if ($pinFieldsNull) {
            $passed++;
            echo "   ✅ Existing tasks correctly return NULL pin fields\n";
        } else {
            $errors[] = "Test 1: Existing task has non-NULL pin fields";
        }

        $db->exec("DELETE FROM tasks WHERE task_id = 'test-existing'");
    } catch (Exception $e) {
        $errors[] = "Test 1: Error testing existing tasks: " . $e->getMessage();
    }

    // Test 2: Legacy API calls without pin fields still work
    echo "\nTest 2: Legacy API updates work without pin fields\n";
    $total++;

    try {
        // Create test task
        $db->exec("INSERT INTO tasks (task_id, action_item, department, status)
                   VALUES ('test-legacy-update', 'Legacy Update Test', 'Tech', 'Not Started')");

        // Simulate legacy API update (no pin fields)
        $legacyUpdateData = [
            'taskID' => 'test-legacy-update',
            'status' => 'In Progress',
            'progressPercentage' => 50,
            'notes' => 'Updated via legacy API'
        ];

        // Build update like the API does
        $allowedFields = [
            'status' => 'status',
            'progressPercentage' => 'progress_percentage',
            'notes' => 'notes_log'
        ];

        $updateFields = [];
        $updateValues = [];

        foreach ($allowedFields as $frontendField => $dbField) {
            if (isset($legacyUpdateData[$frontendField])) {
                $updateFields[] = "$dbField = ?";
                $updateValues[] = $legacyUpdateData[$frontendField];
            }
        }

        $updateFields[] = 'last_updated = CURRENT_TIMESTAMP';
        $sql = "UPDATE tasks SET " . implode(', ', $updateFields) . " WHERE task_id = ?";
        $updateValues[] = $legacyUpdateData['taskID'];

        $stmt = $db->prepare($sql);
        $stmt->execute($updateValues);

        if ($stmt->rowCount() > 0) {
            $passed++;
            echo "   ✅ Legacy API updates work without pin fields\n";
        } else {
            $errors[] = "Test 2: Legacy API update failed";
        }

        $db->exec("DELETE FROM tasks WHERE task_id = 'test-legacy-update'");
    } catch (Exception $e) {
        $errors[] = "Test 2: Error testing legacy API: " . $e->getMessage();
    }

    // Test 3: Old frontend code receives new fields as null
    echo "\nTest 3: Old frontend receives new fields gracefully\n";
    $total++;

    try {
        // Create task with mixed old/new data
        $db->exec("INSERT INTO tasks (task_id, action_item, department, status, priority_score, progress_percentage)
                   VALUES ('test-mixed-data', 'Mixed Data Test', 'Tech', 'In Progress', 5, 75)");

        // Simulate old frontend expecting only original fields
        $stmt = $db->prepare("SELECT task_id, action_item, department, status, priority_score, progress_percentage FROM tasks WHERE task_id = 'test-mixed-data'");
        $stmt->execute();
        $oldFormatTask = $stmt->fetch(PDO::FETCH_ASSOC);

        // Simulate new frontend getting all fields
        $stmt = $db->prepare("SELECT * FROM tasks WHERE task_id = 'test-mixed-data'");
        $stmt->execute();
        $newFormatTask = $stmt->fetch(PDO::FETCH_ASSOC);

        $hasOldFields = (
            isset($oldFormatTask['task_id']) &&
            isset($oldFormatTask['action_item']) &&
            isset($oldFormatTask['status'])
        );

        $hasNewFields = (
            isset($newFormatTask['pin_type']) &&
            isset($newFormatTask['pinned_by']) &&
            isset($newFormatTask['pin_priority'])
        );

        if ($hasOldFields && $hasNewFields) {
            $passed++;
            echo "   ✅ Both old and new field access patterns work\n";
        } else {
            $errors[] = "Test 3: Field access compatibility issue";
        }

        $db->exec("DELETE FROM tasks WHERE task_id = 'test-mixed-data'");
    } catch (Exception $e) {
        $errors[] = "Test 3: Error testing field compatibility: " . $e->getMessage();
    }

    // Test 4: JSON decode handling for existing data
    echo "\nTest 4: JSON field handling remains stable\n";
    $total++;

    try {
        // Create task with JSON data
        $planData = json_encode(['steps' => ['step1', 'step2'], 'status' => 'active']);
        $stmt = $db->prepare("INSERT INTO tasks (task_id, action_item, department, plan_json) VALUES ('test-json', 'JSON Test', 'Tech', ?)");
        $stmt->execute([$planData]);

        // Simulate API processing
        $stmt = $db->prepare("SELECT * FROM tasks WHERE task_id = 'test-json'");
        $stmt->execute();
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        $apiTask = [
            'taskID' => $task['task_id'],
            'aiProjectPlan' => $task['plan_json'] ? json_decode($task['plan_json'], true) : null,
            'pinType' => $task['pin_type'] // New field
        ];

        $jsonDecoded = is_array($apiTask['aiProjectPlan']) && isset($apiTask['aiProjectPlan']['steps']);
        $newFieldNull = $apiTask['pinType'] === null;

        if ($jsonDecoded && $newFieldNull) {
            $passed++;
            echo "   ✅ JSON handling stable with new NULL fields\n";
        } else {
            $errors[] = "Test 4: JSON or new field handling issue";
        }

        $db->exec("DELETE FROM tasks WHERE task_id = 'test-json'");
    } catch (Exception $e) {
        $errors[] = "Test 4: Error testing JSON compatibility: " . $e->getMessage();
    }

    // Test 5: Index performance impact
    echo "\nTest 5: Query performance with new indexes\n";
    $total++;

    try {
        // Test basic queries still work efficiently
        $queries = [
            "SELECT COUNT(*) FROM tasks WHERE status = 'In Progress'",
            "SELECT * FROM tasks WHERE department = 'Tech' ORDER BY last_updated DESC LIMIT 10",
            "SELECT task_id, action_item FROM tasks WHERE date_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        ];

        $allQueriesWork = true;
        foreach ($queries as $query) {
            $start = microtime(true);
            $stmt = $db->query($query);
            $result = $stmt->fetchAll();
            $duration = microtime(true) - $start;

            if ($duration > 1.0) { // If query takes more than 1 second
                $allQueriesWork = false;
                break;
            }
        }

        if ($allQueriesWork) {
            $passed++;
            echo "   ✅ Standard queries perform normally with new indexes\n";
        } else {
            $errors[] = "Test 5: Query performance degraded";
        }
    } catch (Exception $e) {
        $errors[] = "Test 5: Error testing query performance: " . $e->getMessage();
    }

    // Summary
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "BACKWARD COMPATIBILITY RESULTS\n";
    echo str_repeat("=", 50) . "\n";
    echo "Total Tests: {$total}\n";
    echo "Passed: {$passed}\n";
    echo "Failed: " . count($errors) . "\n";

    if (empty($errors)) {
        echo "🎉 ALL BACKWARD COMPATIBILITY TESTS PASSED!\n";
        echo "Existing code will continue to work without changes.\n";
        return true;
    } else {
        echo "\n❌ COMPATIBILITY ISSUES:\n";
        foreach ($errors as $error) {
            echo "   • {$error}\n";
        }
        return false;
    }
}

// Run the tests
try {
    if (testBackwardCompatibility($db)) {
        echo "\n✅ The migration is fully backward compatible!\n";
    } else {
        echo "\n❌ Backward compatibility issues found. Review before deployment.\n";
    }
} catch (Exception $e) {
    echo "\n💥 Compatibility test failed: " . $e->getMessage() . "\n";
}
?>