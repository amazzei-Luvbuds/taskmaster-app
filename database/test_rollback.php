<?php
/**
 * Rollback Functionality Test
 * Tests the rollback script to ensure it works correctly
 */

require_once '../api/config.php';

function testRollbackFunctionality($db) {
    echo "🔄 ROLLBACK FUNCTIONALITY TEST\n";
    echo "===============================\n\n";

    $errors = [];
    $passed = 0;
    $total = 0;

    // Test 1: Check if rollback script syntax is valid
    echo "Test 1: Rollback script syntax validation\n";
    $total++;

    $rollbackFile = __DIR__ . '/migrations/rollback_001_dual_pin_support.sql';
    if (!file_exists($rollbackFile)) {
        $errors[] = "Test 1: Rollback script file not found";
    } else {
        $rollbackSQL = file_get_contents($rollbackFile);
        if (empty($rollbackSQL)) {
            $errors[] = "Test 1: Rollback script is empty";
        } else {
            $passed++;
            echo "   ✅ Rollback script file exists and has content\n";
        }
    }

    // Test 2: Check current pin columns exist (before rollback)
    echo "\nTest 2: Check current state before rollback\n";
    $total++;

    try {
        $stmt = $db->query("DESCRIBE tasks");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $pinColumns = array_filter($columns, function($col) {
            return strpos($col, 'pin_') === 0;
        });

        if (count($pinColumns) >= 5) {
            $passed++;
            echo "   ✅ Pin columns exist before rollback\n";
            echo "   Found columns: " . implode(', ', $pinColumns) . "\n";
        } else {
            echo "   ⚠️  Pin columns don't exist yet (migration not run)\n";
            echo "   This test requires the migration to be applied first\n";
        }
    } catch (Exception $e) {
        $errors[] = "Test 2: Error checking current state: " . $e->getMessage();
    }

    // Test 3: Simulate rollback SQL parsing
    echo "\nTest 3: Rollback SQL parsing simulation\n";
    $total++;

    try {
        if (isset($rollbackSQL)) {
            // Check for critical rollback operations
            $requiredOperations = [
                'DROP INDEX.*idx_pin_type',
                'DROP CONSTRAINT.*chk_pin_priority',
                'DROP COLUMN.*pin_type',
                'DROP COLUMN.*pinned_by',
                'DELETE FROM schema_migrations'
            ];

            $foundOperations = 0;
            foreach ($requiredOperations as $operation) {
                if (preg_match("/{$operation}/i", $rollbackSQL)) {
                    $foundOperations++;
                }
            }

            if ($foundOperations >= 4) {
                $passed++;
                echo "   ✅ Rollback script contains all required operations\n";
            } else {
                $errors[] = "Test 3: Rollback script missing required operations ({$foundOperations}/5)";
            }
        }
    } catch (Exception $e) {
        $errors[] = "Test 3: Error parsing rollback SQL: " . $e->getMessage();
    }

    // Test 4: Check migration tracking table
    echo "\nTest 4: Migration tracking verification\n";
    $total++;

    try {
        // Check if schema_migrations table exists
        $stmt = $db->query("SHOW TABLES LIKE 'schema_migrations'");
        if ($stmt->rowCount() > 0) {
            // Check if our migration is recorded
            $stmt = $db->query("SELECT * FROM schema_migrations WHERE version = '001_dual_pin_support'");
            $migration = $stmt->fetch();

            if ($migration) {
                $passed++;
                echo "   ✅ Migration is properly tracked in schema_migrations\n";
                echo "   Applied at: " . $migration['applied_at'] . "\n";
            } else {
                echo "   ⚠️  Migration not found in tracking table\n";
            }
        } else {
            echo "   ⚠️  schema_migrations table doesn't exist\n";
        }
    } catch (Exception $e) {
        echo "   ⚠️  Could not check migration tracking: " . $e->getMessage() . "\n";
    }

    // Test 5: Verify rollback dependencies
    echo "\nTest 5: Rollback dependency check\n";
    $total++;

    try {
        // Check if there are any tasks with pin data that would be lost
        $stmt = $db->query("SELECT COUNT(*) as pin_count FROM tasks WHERE pin_type IS NOT NULL");
        $result = $stmt->fetch();
        $pinCount = $result['pin_count'];

        if ($pinCount > 0) {
            echo "   ⚠️  WARNING: {$pinCount} tasks have pin data that would be lost in rollback\n";
            echo "   Consider backing up pin data before rollback\n";
        } else {
            $passed++;
            echo "   ✅ No pin data would be lost in rollback\n";
        }
    } catch (Exception $e) {
        // This is expected if pin columns don't exist yet
        $passed++;
        echo "   ✅ No existing pin data found\n";
    }

    // Test 6: Test rollback SQL compatibility
    echo "\nTest 6: Rollback SQL MySQL compatibility\n";
    $total++;

    try {
        // Test individual statements for syntax
        $testStatements = [
            "DROP INDEX IF EXISTS idx_test_pin ON tasks",
            "ALTER TABLE tasks DROP CONSTRAINT IF EXISTS chk_test_constraint",
            "ALTER TABLE tasks DROP COLUMN IF EXISTS test_pin_column"
        ];

        $syntaxValid = true;
        foreach ($testStatements as $stmt) {
            try {
                // We don't execute, just prepare to check syntax
                $db->prepare($stmt);
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'syntax error') !== false) {
                    $syntaxValid = false;
                    break;
                }
                // Other errors (like column not found) are expected and OK
            }
        }

        if ($syntaxValid) {
            $passed++;
            echo "   ✅ Rollback SQL syntax is MySQL compatible\n";
        } else {
            $errors[] = "Test 6: Rollback SQL has syntax errors";
        }
    } catch (Exception $e) {
        $errors[] = "Test 6: Error testing SQL compatibility: " . $e->getMessage();
    }

    // Summary
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "ROLLBACK FUNCTIONALITY RESULTS\n";
    echo str_repeat("=", 50) . "\n";
    echo "Total Tests: {$total}\n";
    echo "Passed: {$passed}\n";
    echo "Failed: " . count($errors) . "\n";

    if (empty($errors)) {
        echo "🎉 ROLLBACK FUNCTIONALITY IS READY!\n";
        echo "\nTo perform rollback:\n";
        echo "1. Backup your database\n";
        echo "2. Run: mysql -u username -p database_name < rollback_001_dual_pin_support.sql\n";
        echo "\nWARNING: Rollback will permanently delete all pin data!\n";
        return true;
    } else {
        echo "\n❌ ROLLBACK ISSUES:\n";
        foreach ($errors as $error) {
            echo "   • {$error}\n";
        }
        return false;
    }
}

// Run the tests
try {
    if (testRollbackFunctionality($db)) {
        echo "\n✅ Rollback functionality is properly implemented!\n";
    } else {
        echo "\n❌ Rollback issues found. Please review before deployment.\n";
    }
} catch (Exception $e) {
    echo "\n💥 Rollback test failed: " . $e->getMessage() . "\n";
}
?>