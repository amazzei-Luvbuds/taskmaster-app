<?php
/**
 * Update Config Script
 * This script updates the database configuration on the server
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Read the current config.php file
$configPath = __DIR__ . '/config.php';
$configContent = file_get_contents($configPath);

if ($configContent === false) {
    echo json_encode(['error' => 'Could not read config.php file']);
    exit;
}

// Update the database host from luvbudstv.com to localhost
$updatedContent = str_replace(
    "define('DB_HOST', 'luvbudstv.com');",
    "define('DB_HOST', 'localhost');",
    $configContent
);

// Also update the comment
$updatedContent = str_replace(
    "// Database configuration - Remote MySQL server",
    "// Database configuration - Local MySQL server",
    $updatedContent
);

// Write the updated config back
$result = file_put_contents($configPath, $updatedContent);

if ($result === false) {
    echo json_encode(['error' => 'Could not write to config.php file']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Config updated successfully',
    'changes' => [
        'DB_HOST' => 'luvbudstv.com -> localhost'
    ]
]);
?>
