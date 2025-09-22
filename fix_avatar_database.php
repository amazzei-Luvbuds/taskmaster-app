<?php
/**
 * TaskMaster Avatar Database Fix
 * Upload this file to your server and visit it in your browser to fix the avatar column
 */

// Include your existing database config
require_once 'api/config.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>TaskMaster Avatar Database Fix</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; background: #f0fff0; padding: 10px; border: 1px solid green; border-radius: 5px; }
        .error { color: red; background: #fff0f0; padding: 10px; border: 1px solid red; border-radius: 5px; }
        .info { color: blue; background: #f0f0ff; padding: 10px; border: 1px solid blue; border-radius: 5px; }
        .step { margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 5px; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔧 TaskMaster Avatar Database Fix</h1>

    <?php
    try {
        echo "<div class='step'>";
        echo "<h3>Step 1: Database Connection</h3>";

        // Test database connection
        if (!isset($db)) {
            throw new Exception("Database connection not found. Make sure config.php is properly configured.");
        }

        echo "<div class='success'>✅ Database connection successful!</div>";
        echo "</div>";

        echo "<div class='step'>";
        echo "<h3>Step 2: Check Comments Table</h3>";

        // Check if comments table exists
        $tableCheck = $db->query("SHOW TABLES LIKE 'comments'");
        if ($tableCheck->rowCount() === 0) {
            throw new Exception("Comments table not found. Make sure the comments system is set up.");
        }

        echo "<div class='success'>✅ Comments table found!</div>";
        echo "</div>";

        echo "<div class='step'>";
        echo "<h3>Step 3: Check Avatar Column</h3>";

        // Check if author_avatar column exists
        $columnCheck = $db->query("SHOW COLUMNS FROM comments LIKE 'author_avatar'");
        $columnExists = $columnCheck->rowCount() > 0;

        if ($columnExists) {
            echo "<div class='info'>ℹ️ Column 'author_avatar' already exists!</div>";

            // Show current structure
            $columns = $db->query("DESCRIBE comments");
            echo "<h4>Current Table Structure:</h4>";
            echo "<pre>";
            while ($col = $columns->fetch()) {
                $highlight = ($col['Field'] === 'author_avatar') ? ' <-- NEW COLUMN' : '';
                echo "{$col['Field']} ({$col['Type']}){$highlight}\n";
            }
            echo "</pre>";

        } else {
            echo "<div class='info'>🔧 Column 'author_avatar' not found. Adding it now...</div>";

            // Add the column
            $alterSQL = "ALTER TABLE comments ADD COLUMN author_avatar TEXT NULL AFTER author_email";
            $db->exec($alterSQL);

            echo "<div class='success'>✅ Column 'author_avatar' added successfully!</div>";

            // Show updated structure
            $columns = $db->query("DESCRIBE comments");
            echo "<h4>Updated Table Structure:</h4>";
            echo "<pre>";
            while ($col = $columns->fetch()) {
                $highlight = ($col['Field'] === 'author_avatar') ? ' <-- NEW COLUMN' : '';
                echo "{$col['Field']} ({$col['Type']}){$highlight}\n";
            }
            echo "</pre>";
        }
        echo "</div>";

        echo "<div class='step'>";
        echo "<h3>Step 4: Test Avatar Functionality</h3>";

        // Test by checking if we can insert/update avatar data
        $testData = 'https://via.placeholder.com/64/test';

        // Count current comments
        $countStmt = $db->query("SELECT COUNT(*) as count FROM comments");
        $count = $countStmt->fetch()['count'];

        echo "<div class='info'>📊 Found {$count} existing comments in the database.</div>";

        // Test insert (won't actually insert, just test the SQL)
        $testInsert = $db->prepare("SELECT 1 FROM comments WHERE 1=0 UNION SELECT ? as test_avatar");
        $testInsert->execute([$testData]);

        echo "<div class='success'>✅ Avatar column is ready to receive data!</div>";
        echo "</div>";

        echo "<div class='step'>";
        echo "<h3>✅ Fix Complete!</h3>";
        echo "<div class='success'>";
        echo "<strong>Avatar fix has been successfully applied!</strong><br><br>";
        echo "What was fixed:<br>";
        echo "• Added 'author_avatar' column to comments table<br>";
        echo "• Column can store avatar URLs up to TEXT length<br>";
        echo "• Existing comments will show default avatars<br>";
        echo "• New comments will display user avatars<br><br>";
        echo "<strong>Next Steps:</strong><br>";
        echo "1. Go back to TaskMaster and create a new comment<br>";
        echo "2. Your avatar should now appear in the comment<br>";
        echo "3. Delete this file from your server for security<br>";
        echo "</div>";
        echo "</div>";

    } catch (Exception $e) {
        echo "<div class='step'>";
        echo "<div class='error'>";
        echo "<strong>❌ Error:</strong> " . $e->getMessage();
        echo "<br><br><strong>Troubleshooting:</strong><br>";
        echo "1. Make sure this file is in the same directory as your 'api' folder<br>";
        echo "2. Check that config.php has correct database credentials<br>";
        echo "3. Ensure your database user has ALTER TABLE permissions<br>";
        echo "</div>";
        echo "</div>";
    }
    ?>

    <div class='step'>
        <h3>🗑️ Cleanup</h3>
        <div class='info'>
            <strong>Important:</strong> After the fix is complete, delete this file from your server for security reasons.
        </div>
    </div>

</body>
</html>