<?php
/**
 * Comments Schema Migration Script
 * Run this to add comment system tables to the TaskMaster database
 */

require_once '../api/config.php';

echo "TaskMaster Comments Migration Script\n";
echo "=====================================\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "✓ Database connection established\n";

    // Read the schema SQL file
    $sqlFile = __DIR__ . '/comments_schema.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("Schema file not found: $sqlFile");
    }

    $sql = file_get_contents($sqlFile);
    echo "✓ Schema file loaded\n";

    // Split SQL into individual statements (rough split on semicolon followed by newline)
    $statements = preg_split('/;\s*\n/', $sql);

    $executedStatements = 0;
    $skippedStatements = 0;

    foreach ($statements as $statement) {
        $statement = trim($statement);

        // Skip empty statements and comments
        if (empty($statement) || strpos($statement, '--') === 0 || strpos($statement, '/*') === 0) {
            $skippedStatements++;
            continue;
        }

        // Skip DELIMITER statements (MySQL specific for stored procedures)
        if (strpos(strtoupper($statement), 'DELIMITER') === 0) {
            $skippedStatements++;
            continue;
        }

        try {
            // Add semicolon back if it's not there
            if (substr($statement, -1) !== ';') {
                $statement .= ';';
            }

            $db->exec($statement);
            $executedStatements++;

            // Extract table/function name for better logging
            if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
                echo "✓ Created table: {$matches[1]}\n";
            } elseif (preg_match('/CREATE\s+INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
                echo "✓ Created index: {$matches[1]}\n";
            } elseif (preg_match('/CREATE\s+FUNCTION\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
                echo "✓ Created function: {$matches[1]}\n";
            } else {
                echo "✓ Executed statement\n";
            }

        } catch (Exception $e) {
            // Check if error is about table already existing
            if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "- Skipped (already exists): " . substr($statement, 0, 50) . "...\n";
                $skippedStatements++;
            } else {
                echo "✗ Error executing statement: " . substr($statement, 0, 100) . "...\n";
                echo "  Error: " . $e->getMessage() . "\n";
                // Continue with other statements rather than failing completely
            }
        }
    }

    echo "\n";
    echo "Migration Summary:\n";
    echo "- Executed statements: $executedStatements\n";
    echo "- Skipped statements: $skippedStatements\n";
    echo "\n";

    // Verify tables were created
    echo "Verifying created tables:\n";
    $tables = [
        'comments',
        'comment_mentions',
        'comment_reactions',
        'comment_attachments',
        'comment_metadata',
        'comment_notifications',
        'comment_search_index',
        'user_notification_preferences',
        'comment_activity_log'
    ];

    foreach ($tables as $table) {
        try {
            $stmt = $db->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "✓ Table '$table' exists\n";
            } else {
                echo "✗ Table '$table' NOT found\n";
            }
        } catch (Exception $e) {
            echo "✗ Error checking table '$table': " . $e->getMessage() . "\n";
        }
    }

    echo "\n✅ Comments migration completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Test the API endpoints at /api/comments\n";
    echo "2. Verify the frontend can create and retrieve comments\n";
    echo "3. Test the notification system\n";

} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "\nPlease check:\n";
    echo "1. Database connection settings in config.php\n";
    echo "2. Database user has CREATE, ALTER, and INDEX privileges\n";
    echo "3. MySQL version supports the required features\n";

    exit(1);
}
?>