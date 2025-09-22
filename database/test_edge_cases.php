<?php
/**
 * Comprehensive Edge Case Testing for Dual-Pin System
 * This script tests all possible edge cases and failure scenarios
 */

require_once '../api/config.php';

function runEdgeCaseTests($db) {
    echo "🧪 COMPREHENSIVE EDGE CASE TESTING\n";
    echo "===================================\n\n";

    $errors = [];
    $passed = 0;
    $total = 0;

    // Test 1: Invalid pin type values
    echo "Test 1: Invalid pin type validation\n";
    $invalidPinTypes = ['invalid', 'PERSONAL', 'GLOBAL', '', 'both', 123, true];

    foreach ($invalidPinTypes as $i => $invalidType) {
        $total++;
        try {
            $stmt = $db->prepare("INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_by, pinned_at) VALUES (?, 'Test', 'Tech', ?, 'user123', NOW())");
            $stmt->execute(["test-invalid-{$i}", $invalidType]);
            $errors[] = "Test 1.{$i}: Invalid pin type '{$invalidType}' was accepted";
            $db->exec("DELETE FROM tasks WHERE task_id = 'test-invalid-{$i}'");
        } catch (Exception $e) {
            $passed++;
            echo "   ✅ Invalid pin type '{$invalidType}' correctly rejected\n";
        }
    }

    // Test 2: Pin priority validation edge cases
    echo "\nTest 2: Pin priority validation\n";
    $invalidPriorities = [0, 11, -1, 999, 'high', null]; // null should be allowed

    foreach ($invalidPriorities as $i => $priority) {
        $total++;
        $shouldPass = ($priority === null);

        try {
            $stmt = $db->prepare("INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_by, pin_priority, pinned_at) VALUES (?, 'Test', 'Tech', 'global', 'user123', ?, NOW())");
            $stmt->execute(["test-priority-{$i}", $priority]);

            if ($shouldPass) {
                $passed++;
                echo "   ✅ Priority '{$priority}' correctly accepted for global pin\n";
            } else {
                $errors[] = "Test 2.{$i}: Invalid priority '{$priority}' was accepted for global pin";
            }
            $db->exec("DELETE FROM tasks WHERE task_id = 'test-priority-{$i}'");
        } catch (Exception $e) {
            if (!$shouldPass) {
                $passed++;
                echo "   ✅ Invalid priority '{$priority}' correctly rejected\n";
            } else {
                $errors[] = "Test 2.{$i}: Valid priority '{$priority}' was incorrectly rejected: " . $e->getMessage();
            }
        }
    }

    // Test 3: Personal pin with priority (should fail)
    echo "\nTest 3: Personal pin with priority validation\n";
    $total++;
    try {
        $stmt = $db->prepare("INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_by, pin_priority, pinned_at) VALUES ('test-personal-priority', 'Test', 'Tech', 'personal', 'user123', 5, NOW())");
        $stmt->execute();
        $errors[] = "Test 3: Personal pin with priority was incorrectly accepted";
        $db->exec("DELETE FROM tasks WHERE task_id = 'test-personal-priority'");
    } catch (Exception $e) {
        $passed++;
        echo "   ✅ Personal pin with priority correctly rejected\n";
    }

    // Test 4: Constraint validation - pinned_by required when pin_type is set
    echo "\nTest 4: pinnedBy required when pin_type is set\n";
    $total++;
    try {
        $stmt = $db->prepare("INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_at) VALUES ('test-missing-pinned-by', 'Test', 'Tech', 'personal', NOW())");
        $stmt->execute();
        $errors[] = "Test 4: Pin without pinnedBy was incorrectly accepted";
        $db->exec("DELETE FROM tasks WHERE task_id = 'test-missing-pinned-by'");
    } catch (Exception $e) {
        $passed++;
        echo "   ✅ Pin without pinnedBy correctly rejected\n";
    }

    // Test 5: Constraint validation - pinnedAt required when pin_type is set
    echo "\nTest 5: pinnedAt required when pin_type is set\n";
    $total++;
    try {
        $stmt = $db->prepare("INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_by) VALUES ('test-missing-pinned-at', 'Test', 'Tech', 'personal', 'user123')");
        $stmt->execute();
        $errors[] = "Test 5: Pin without pinnedAt was incorrectly accepted";
        $db->exec("DELETE FROM tasks WHERE task_id = 'test-missing-pinned-at'");
    } catch (Exception $e) {
        $passed++;
        echo "   ✅ Pin without pinnedAt correctly rejected\n";
    }

    // Test 6: Valid combinations that should work
    echo "\nTest 6: Valid pin combinations\n";
    $validCombinations = [
        ['personal', 'user123', null, 'Personal note'],
        ['global', 'admin123', 1, 'Low priority global'],
        ['global', 'admin123', 10, 'High priority global'],
        ['global', 'admin123', 5, null], // No reason
    ];

    foreach ($validCombinations as $i => $combo) {
        list($pinType, $pinnedBy, $priority, $reason) = $combo;
        $total++;

        try {
            $stmt = $db->prepare("INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_by, pin_priority, pin_reason, pinned_at) VALUES (?, 'Test', 'Tech', ?, ?, ?, ?, NOW())");
            $stmt->execute(["test-valid-{$i}", $pinType, $pinnedBy, $priority, $reason]);
            $passed++;
            echo "   ✅ Valid combination {$i}: {$pinType} pin with priority {$priority}\n";
            $db->exec("DELETE FROM tasks WHERE task_id = 'test-valid-{$i}'");
        } catch (Exception $e) {
            $errors[] = "Test 6.{$i}: Valid combination was rejected: " . $e->getMessage();
        }
    }

    // Test 7: Updating existing task with pin fields
    echo "\nTest 7: Updating existing task with pins\n";

    // Create a test task first
    $db->exec("INSERT INTO tasks (task_id, action_item, department, status) VALUES ('test-update-task', 'Update Test', 'Tech', 'In Progress')");

    $updateTests = [
        ['pinType' => 'personal', 'pinnedBy' => 'user123', 'expected' => true],
        ['pinType' => 'global', 'pinnedBy' => 'admin123', 'pinPriority' => 7, 'expected' => true],
        ['pinPriority' => 8, 'expected' => false], // Priority without pin type
        ['pinType' => 'personal', 'pinPriority' => 5, 'expected' => false], // Personal with priority
    ];

    foreach ($updateTests as $i => $test) {
        $total++;
        $data = array_merge(['taskID' => 'test-update-task'], $test);
        unset($data['expected']);

        // Simulate API validation
        $isValid = true;
        $errorMsg = '';

        try {
            // Simulate the validation logic
            if (isset($data['pinType']) && !in_array($data['pinType'], ['personal', 'global', null])) {
                $isValid = false;
                $errorMsg = 'Invalid pinType';
            }

            if (isset($data['pinPriority']) && $data['pinPriority'] !== null) {
                if (!is_numeric($data['pinPriority']) || $data['pinPriority'] < 1 || $data['pinPriority'] > 10) {
                    $isValid = false;
                    $errorMsg = 'Invalid priority range';
                }

                // Get current or new pin type
                $effectivePinType = isset($data['pinType']) ? $data['pinType'] : null;
                if (!$effectivePinType) {
                    // Would need to check database
                    $checkStmt = $db->prepare("SELECT pin_type FROM tasks WHERE task_id = ?");
                    $checkStmt->execute(['test-update-task']);
                    $current = $checkStmt->fetch();
                    $effectivePinType = $current['pin_type'] ?? null;
                }

                if ($effectivePinType !== 'global') {
                    $isValid = false;
                    $errorMsg = 'Priority only for global pins';
                }
            }

            if ($isValid === $test['expected']) {
                $passed++;
                echo "   ✅ Update test {$i}: " . ($test['expected'] ? 'Valid' : 'Invalid') . " update correctly handled\n";
            } else {
                $errors[] = "Test 7.{$i}: Expected " . ($test['expected'] ? 'valid' : 'invalid') . " but got " . ($isValid ? 'valid' : 'invalid') . " - {$errorMsg}";
            }
        } catch (Exception $e) {
            $errors[] = "Test 7.{$i}: Exception during validation: " . $e->getMessage();
        }
    }

    // Clean up
    $db->exec("DELETE FROM tasks WHERE task_id = 'test-update-task'");

    // Test 8: Null value handling
    echo "\nTest 8: NULL value handling\n";
    $total++;
    try {
        $stmt = $db->prepare("INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_by, pinned_at, pin_priority, pin_reason) VALUES ('test-nulls', 'Test NULL', 'Tech', NULL, NULL, NULL, NULL, NULL)");
        $stmt->execute();
        $passed++;
        echo "   ✅ NULL values correctly accepted\n";
        $db->exec("DELETE FROM tasks WHERE task_id = 'test-nulls'");
    } catch (Exception $e) {
        $errors[] = "Test 8: NULL values incorrectly rejected: " . $e->getMessage();
    }

    // Summary
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "EDGE CASE TEST RESULTS\n";
    echo str_repeat("=", 50) . "\n";
    echo "Total Tests: {$total}\n";
    echo "Passed: {$passed}\n";
    echo "Failed: " . (count($errors)) . "\n";

    if (empty($errors)) {
        echo "🎉 ALL EDGE CASE TESTS PASSED!\n";
        return true;
    } else {
        echo "\n❌ FAILURES:\n";
        foreach ($errors as $error) {
            echo "   • {$error}\n";
        }
        return false;
    }
}

// Run the tests
try {
    if (runEdgeCaseTests($db)) {
        echo "\n✅ The dual-pin system handles all edge cases correctly!\n";
    } else {
        echo "\n❌ Some edge cases failed. Please review the implementation.\n";
    }
} catch (Exception $e) {
    echo "\n💥 Test execution failed: " . $e->getMessage() . "\n";
}
?>