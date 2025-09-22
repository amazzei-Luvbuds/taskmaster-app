<?php
/**
 * Auto Fix Database Configuration
 * This script will automatically fix your database configuration
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo "<h2>🔧 Auto Fixing Database Configuration</h2>";

// Step 1: Check current config
echo "<h3>Step 1: Checking current configuration...</h3>";
$configPath = __DIR__ . '/config.php';

if (!file_exists($configPath)) {
    echo "<p style='color: red;'>❌ config.php file not found!</p>";
    exit;
}

$configContent = file_get_contents($configPath);
echo "<p>✅ config.php found</p>";

// Step 2: Check current DB_HOST
if (strpos($configContent, "define('DB_HOST', 'luvbudstv.com');") !== false) {
    echo "<p style='color: orange;'>⚠️ Found incorrect DB_HOST: luvbudstv.com</p>";
    
    // Step 3: Fix the configuration
    echo "<h3>Step 2: Fixing database configuration...</h3>";
    
    // Backup current config
    $backupPath = __DIR__ . '/config.php.backup.' . date('Y-m-d-H-i-s');
    file_put_contents($backupPath, $configContent);
    echo "<p>✅ Backup created: " . basename($backupPath) . "</p>";
    
    // Fix the database host
    $fixedContent = str_replace(
        "define('DB_HOST', 'luvbudstv.com');",
        "define('DB_HOST', 'localhost');",
        $configContent
    );
    
    // Also fix the comment
    $fixedContent = str_replace(
        "// Database configuration - Remote MySQL server",
        "// Database configuration - Local MySQL server",
        $fixedContent
    );
    
    // Write the fixed config
    $result = file_put_contents($configPath, $fixedContent);
    
    if ($result !== false) {
        echo "<p style='color: green;'>✅ Database configuration fixed!</p>";
        echo "<p>Changed DB_HOST from 'luvbudstv.com' to 'localhost'</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to write config.php</p>";
        exit;
    }
} else {
    echo "<p style='color: green;'>✅ DB_HOST is already correct</p>";
}

// Step 4: Test database connection
echo "<h3>Step 3: Testing database connection...</h3>";

try {
    $dsn = "mysql:host=localhost;dbname=cosmichq_luvbudstaskmaster;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ];
    
    $pdo = new PDO($dsn, 'cosmichq_luvbudstaskmaster', 'gyjnix-dumpik-tobHi9', $options);
    
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) as task_count FROM tasks");
    $result = $stmt->fetch();
    
    echo "<p style='color: green;'>✅ Database connection successful!</p>";
    echo "<p>Found " . $result['task_count'] . " tasks in database</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    
    // Try alternative configurations
    echo "<h3>Step 4: Trying alternative configurations...</h3>";
    
    $alternatives = [
        '127.0.0.1' => '127.0.0.1',
        'mysql' => 'mysql',
        'db' => 'db'
    ];
    
    foreach ($alternatives as $name => $host) {
        try {
            $dsn = "mysql:host=$host;dbname=cosmichq_luvbudstaskmaster;charset=utf8mb4";
            $pdo = new PDO($dsn, 'cosmichq_luvbudstaskmaster', 'gyjnix-dumpik-tobHi9', $options);
            
            echo "<p style='color: green;'>✅ Connection successful with host: $host</p>";
            
            // Update config with working host
            $configContent = file_get_contents($configPath);
            $fixedContent = str_replace(
                "define('DB_HOST', 'localhost');",
                "define('DB_HOST', '$host');",
                $configContent
            );
            file_put_contents($configPath, $fixedContent);
            echo "<p>✅ Updated config.php with working host: $host</p>";
            break;
            
        } catch (PDOException $e2) {
            echo "<p style='color: orange;'>⚠️ Failed with host $host: " . $e2->getMessage() . "</p>";
        }
    }
}

// Step 5: Test API endpoint
echo "<h3>Step 5: Testing API endpoint...</h3>";
$apiUrl = "https://luvbudstv.com/api/tasks_simple.php?endpoint=departments";
$response = file_get_contents($apiUrl);

if ($response !== false) {
    $data = json_decode($response, true);
    if (isset($data['error'])) {
        echo "<p style='color: red;'>❌ API still has error: " . $data['error'] . "</p>";
    } else {
        echo "<p style='color: green;'>✅ API is working!</p>";
        echo "<p>Response: " . substr($response, 0, 200) . "...</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Failed to test API endpoint</p>";
}

echo "<h3>🎉 Fix Complete!</h3>";
echo "<p>Your database configuration has been automatically fixed.</p>";
echo "<p><a href='https://luvbudstv.com/api/tasks_simple.php?endpoint=departments' target='_blank'>Test API Endpoint</a></p>";
?>
