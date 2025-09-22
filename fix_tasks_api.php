<?php
/**
 * Fix for tasks_simple.php - Upload this to the server and run it
 */

// The fixed code for the pin fields section
$fixedCode = '            // Pin fields for dual-tier pin system
            $task[\'pinType\'] = $task[\'pin_type\'] ?? null;
            $task[\'pinnedBy\'] = $task[\'pinned_by\'] ?? null;
            $task[\'pinnedAt\'] = $task[\'pinned_at\'] ?? null;
            $task[\'pinPriority\'] = isset($task[\'pin_priority\']) ? (int)$task[\'pin_priority\'] : null;
            $task[\'pinReason\'] = $task[\'pin_reason\'] ?? null;';

$oldCode = '            // Pin fields for dual-tier pin system
            $task[\'pinType\'] = $task[\'pin_type\'];
            $task[\'pinnedBy\'] = $task[\'pinned_by\'];
            $task[\'pinnedAt\'] = $task[\'pinned_at\'];
            $task[\'pinPriority\'] = $task[\'pin_priority\'] ? (int)$task[\'pin_priority\'] : null;
            $task[\'pinReason\'] = $task[\'pin_reason\'];';

// Read the current file
$filePath = '/home/www/luvbudstv.com/api/tasks_simple.php';
$content = file_get_contents($filePath);

if ($content === false) {
    echo "❌ Could not read tasks_simple.php\n";
    exit(1);
}

// Replace the problematic code
$newContent = str_replace($oldCode, $fixedCode, $content);

if ($newContent === $content) {
    echo "⚠️ No changes needed - code might already be fixed\n";
} else {
    // Write the fixed content back
    if (file_put_contents($filePath, $newContent) === false) {
        echo "❌ Could not write to tasks_simple.php\n";
        exit(1);
    }
    echo "✅ Successfully fixed tasks_simple.php\n";
}

// Test the API
echo "\n🧪 Testing API endpoint...\n";
$testUrl = 'https://luvbudstv.com/api/tasks_simple.php?endpoint=tasks&limit=1';
$response = file_get_contents($testUrl);

if ($response === false) {
    echo "❌ API test failed\n";
} else {
    $data = json_decode($response, true);
    if ($data && isset($data['success'])) {
        echo "✅ API is working correctly!\n";
        echo "📊 Found " . count($data['data']) . " tasks\n";
    } else {
        echo "⚠️ API response format issue\n";
        echo "Response: " . substr($response, 0, 200) . "...\n";
    }
}

echo "\n🎉 Fix complete!\n";
?>
