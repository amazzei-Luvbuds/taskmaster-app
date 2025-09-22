<?php
/**
 * Migration Runner for Dual-Pin Support
 * This script safely applies the dual-pin migration to the database
 */

require_once '../api/config.php';

echo "🚀 Dual-Pin Migration Runner\n";
echo "=============================\n\n";

try {
    // Read and execute the migration SQL
    $migrationFile = __DIR__ . '/migrations/001_add_dual_pin_support.sql';

    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }

    echo "📖 Reading migration file...\n";
    $migrationSQL = file_get_contents($migrationFile);

    if (!$migrationSQL) {
        throw new Exception("Could not read migration file");
    }

    echo "🔧 Applying migration to database...\n";

    // Split SQL into individual statements (basic splitting)
    $statements = explode(';', $migrationSQL);

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue; // Skip empty lines and comments
        }

        try {
            $db->exec($statement);
        } catch (PDOException $e) {
            // Some statements might fail if already applied, that's OK
            if (strpos($e->getMessage(), 'already exists') === false &&
                strpos($e->getMessage(), 'Duplicate') === false) {
                echo "   ⚠️  Warning: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "✅ Migration applied successfully!\n\n";

    // Verify migration
    echo "🔍 Verifying migration...\n";

    // Check if new columns exist
    $stmt = $db->query("DESCRIBE tasks");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $newColumns = ['pin_type', 'pinned_by', 'pinned_at', 'pin_priority', 'pin_reason'];
    $found = 0;

    foreach ($newColumns as $col) {
        if (in_array($col, $columns)) {
            echo "   ✅ Column '$col' exists\n";
            $found++;
        } else {
            echo "   ❌ Column '$col' missing\n";
        }
    }

    // Check migration record
    try {
        $stmt = $db->query("SELECT * FROM schema_migrations WHERE version = '001_dual_pin_support'");
        $migration = $stmt->fetch();

        if ($migration) {
            echo "   ✅ Migration recorded in schema_migrations\n";
            echo "   📅 Applied at: " . $migration['applied_at'] . "\n";
        } else {
            echo "   ⚠️  Migration not recorded in schema_migrations\n";
        }
    } catch (Exception $e) {
        echo "   ⚠️  Could not check migration record: " . $e->getMessage() . "\n";
    }

    if ($found === count($newColumns)) {
        echo "\n🎉 Migration completed successfully!\n";
        echo "All pin columns have been added to the tasks table.\n\n";

        echo "📋 Next steps:\n";
        echo "1. Run the test script: php test_dual_pin_migration.php\n";
        echo "2. Update frontend code to use new pin fields\n";
        echo "3. Test the dual-pin functionality\n";
    } else {
        echo "\n❌ Migration incomplete. Some columns are missing.\n";
        echo "Please check the database logs and try again.\n";
    }

} catch (Exception $e) {
    echo "💥 Migration failed: " . $e->getMessage() . "\n";
    echo "\nTo rollback, run: mysql -u username -p database_name < rollback_001_dual_pin_support.sql\n";
}
?>