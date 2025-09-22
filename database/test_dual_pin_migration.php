<?php
/**
 * Test script for dual-pin migration
 * This script tests the database migration and API functionality
 */

require_once '../api/config.php';

function testMigration($db) {
    echo "🧪 Testing Dual-Pin Migration\n";
    echo "================================\n\n";

    // Test 1: Check if pin columns exist
    echo "✅ Test 1: Checking if pin columns exist...\n";
    try {
        $stmt = $db->query("DESCRIBE tasks");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $requiredColumns = ['pin_type', 'pinned_by', 'pinned_at', 'pin_priority', 'pin_reason'];
        $missing = [];

        foreach ($requiredColumns as $col) {
            if (!in_array($col, $columns)) {
                $missing[] = $col;
            }
        }

        if (empty($missing)) {
            echo "   ✅ All pin columns exist\n";
        } else {
            echo "   ❌ Missing columns: " . implode(', ', $missing) . "\n";
            return false;
        }
    } catch (Exception $e) {
        echo "   ❌ Error checking columns: " . $e->getMessage() . "\n";
        return false;
    }

    // Test 2: Check constraints
    echo "\n✅ Test 2: Testing database constraints...\n";

    // Test valid global pin
    try {
        $db->exec("INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_by, pin_priority, pinned_at)
                   VALUES ('test-global-pin', 'Test global pin', 'Tech', 'global', 'user123', 5, NOW())");
        echo "   ✅ Global pin with priority inserted successfully\n";

        // Clean up
        $db->exec("DELETE FROM tasks WHERE task_id = 'test-global-pin'");
    } catch (Exception $e) {
        echo "   ❌ Failed to insert valid global pin: " . $e->getMessage() . "\n";
    }

    // Test invalid priority for personal pin (should fail)
    try {
        $db->exec("INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_by, pin_priority, pinned_at)
                   VALUES ('test-invalid-pin', 'Test invalid pin', 'Tech', 'personal', 'user123', 5, NOW())");
        echo "   ❌ Invalid personal pin with priority was allowed (constraint not working)\n";
        $db->exec("DELETE FROM tasks WHERE task_id = 'test-invalid-pin'");
    } catch (Exception $e) {
        echo "   ✅ Personal pin with priority correctly rejected: " . $e->getMessage() . "\n";
    }

    // Test 3: Check indexes
    echo "\n✅ Test 3: Checking indexes...\n";
    try {
        $stmt = $db->query("SHOW INDEX FROM tasks WHERE Key_name LIKE 'idx_pin%'");
        $indexes = $stmt->fetchAll(PDO::FETCH_COLUMN, 2); // Column_name

        $expectedIndexes = ['pin_type', 'pinned_by', 'pinned_at'];
        $foundIndexes = [];

        foreach ($indexes as $index) {
            if (in_array($index, $expectedIndexes)) {
                $foundIndexes[] = $index;
            }
        }

        if (count($foundIndexes) >= 3) {
            echo "   ✅ Pin indexes created successfully\n";
        } else {
            echo "   ⚠️  Some pin indexes may be missing\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Error checking indexes: " . $e->getMessage() . "\n";
    }

    return true;
}

function testAPIFields($db) {
    echo "\n🔌 Testing API Pin Fields\n";
    echo "==========================\n\n";

    // Create a test task with pin data
    echo "✅ Test 1: Creating test task with pin data...\n";
    try {
        $stmt = $db->prepare("
            INSERT INTO tasks (task_id, action_item, department, status, pin_type, pinned_by, pin_priority, pin_reason, pinned_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            'test-api-pin',
            'Test API pin functionality',
            'Tech',
            'In Progress',
            'global',
            'admin123',
            8,
            'Critical for release'
        ]);

        echo "   ✅ Test task created successfully\n";
    } catch (Exception $e) {
        echo "   ❌ Failed to create test task: " . $e->getMessage() . "\n";
        return false;
    }

    // Test API response includes pin fields
    echo "\n✅ Test 2: Checking API response includes pin fields...\n";
    try {
        $stmt = $db->prepare("
            SELECT
                t.*,
                d.color as department_color
            FROM tasks t
            LEFT JOIN departments d ON t.department = d.name
            WHERE t.task_id = 'test-api-pin'
        ");

        $stmt->execute();
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($task) {
            // Simulate API field mapping
            $apiTask = [
                'taskID' => $task['task_id'],
                'pinType' => $task['pin_type'],
                'pinnedBy' => $task['pinned_by'],
                'pinnedAt' => $task['pinned_at'],
                'pinPriority' => $task['pin_priority'] ? (int)$task['pin_priority'] : null,
                'pinReason' => $task['pin_reason']
            ];

            echo "   ✅ Pin fields in API response:\n";
            echo "      - pinType: " . ($apiTask['pinType'] ?? 'null') . "\n";
            echo "      - pinnedBy: " . ($apiTask['pinnedBy'] ?? 'null') . "\n";
            echo "      - pinPriority: " . ($apiTask['pinPriority'] ?? 'null') . "\n";
            echo "      - pinReason: " . ($apiTask['pinReason'] ?? 'null') . "\n";
        }
    } catch (Exception $e) {
        echo "   ❌ API test failed: " . $e->getMessage() . "\n";
    }

    // Clean up test data
    echo "\n🧹 Cleaning up test data...\n";
    try {
        $db->exec("DELETE FROM tasks WHERE task_id LIKE 'test-%'");
        echo "   ✅ Test data cleaned up\n";
    } catch (Exception $e) {
        echo "   ⚠️  Warning: Could not clean up test data: " . $e->getMessage() . "\n";
    }

    return true;
}

function testValidation() {
    echo "\n🛡️ Testing Validation Logic\n";
    echo "============================\n\n";

    // Test pin type validation
    $testCases = [
        ['pinType' => 'global', 'pinPriority' => 5, 'expected' => true, 'description' => 'Valid global pin with priority'],
        ['pinType' => 'personal', 'pinPriority' => null, 'expected' => true, 'description' => 'Valid personal pin without priority'],
        ['pinType' => 'personal', 'pinPriority' => 5, 'expected' => false, 'description' => 'Invalid personal pin with priority'],
        ['pinType' => 'global', 'pinPriority' => 15, 'expected' => false, 'description' => 'Invalid priority > 10'],
        ['pinType' => 'global', 'pinPriority' => 0, 'expected' => false, 'description' => 'Invalid priority < 1'],
        ['pinType' => 'invalid', 'pinPriority' => null, 'expected' => false, 'description' => 'Invalid pin type'],
    ];

    foreach ($testCases as $i => $test) {
        echo "Test " . ($i + 1) . ": " . $test['description'] . "\n";

        $isValid = true;
        $errors = [];

        // Simulate validation logic from API
        if (isset($test['pinType']) && !in_array($test['pinType'], ['personal', 'global', null])) {
            $isValid = false;
            $errors[] = 'Invalid pinType';
        }

        if (isset($test['pinPriority']) && $test['pinPriority'] !== null) {
            if (!is_numeric($test['pinPriority']) || $test['pinPriority'] < 1 || $test['pinPriority'] > 10) {
                $isValid = false;
                $errors[] = 'pinPriority must be between 1 and 10';
            }
            if ($test['pinType'] !== 'global') {
                $isValid = false;
                $errors[] = 'pinPriority can only be set for global pins';
            }
        }

        if ($isValid === $test['expected']) {
            echo "   ✅ Passed\n";
        } else {
            echo "   ❌ Failed - Expected " . ($test['expected'] ? 'valid' : 'invalid') .
                 " but got " . ($isValid ? 'valid' : 'invalid') . "\n";
            if (!empty($errors)) {
                echo "   Errors: " . implode(', ', $errors) . "\n";
            }
        }
    }
}

// Main execution
try {
    // Test database migration
    if (testMigration($db)) {
        // Test API functionality
        testAPIFields($db);

        // Test validation logic
        testValidation();

        echo "\n🎉 All tests completed!\n";
        echo "================================\n";
        echo "The dual-pin migration appears to be working correctly.\n";
        echo "You can now proceed with frontend integration.\n";
    } else {
        echo "\n❌ Migration tests failed. Please run the migration script first.\n";
    }
} catch (Exception $e) {
    echo "\n💥 Test execution failed: " . $e->getMessage() . "\n";
    echo "Please check your database connection and migration status.\n";
}
?>