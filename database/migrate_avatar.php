<?php
/**
 * Database Migration: Add author_avatar column to comments table
 */

require_once '../api/config.php';

try {
    echo "🔧 Starting avatar column migration...\n";

    // Check if column exists
    $check = $db->query("SHOW COLUMNS FROM comments LIKE 'author_avatar'");
    $columnExists = $check->rowCount() > 0;

    if ($columnExists) {
        echo "✅ Column 'author_avatar' already exists\n";
    } else {
        echo "📝 Adding 'author_avatar' column...\n";

        $db->exec("ALTER TABLE comments ADD COLUMN author_avatar TEXT NULL AFTER author_email");

        echo "✅ Column 'author_avatar' added successfully\n";
    }

    // Show table structure
    echo "\n📋 Current table structure:\n";
    $columns = $db->query("DESCRIBE comments");
    while ($col = $columns->fetch()) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }

    echo "\n✅ Migration completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
}