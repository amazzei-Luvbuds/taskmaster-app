<?php
/**
 * Temporary file upload script for comments API
 */

$targetServer = 'luvbudstv.com';
$targetPath = '/api/comments_optimized.php';
$sourceFile = __DIR__ . '/api/comments_optimized.php';

echo "🚀 DEPLOYING COMMENTS API\n\n";

// Read the source file
$content = file_get_contents($sourceFile);
$content = str_replace('http://localhost:5173', 'https://taskmaster-react.vercel.app', $content);

// Create deployment payload
$deploymentData = [
    'filename' => 'comments_optimized.php',
    'content' => $content,
    'size' => strlen($content)
];

echo "📁 File: comments_optimized.php\n";
echo "📊 Size: " . number_format(strlen($content)) . " bytes\n";
echo "🎯 Target: https://{$targetServer}{$targetPath}\n\n";

// Try uploading via existing API endpoints
$apiUrls = [
    'https://luvbudstv.com/api/health.php',
    'https://luvbudstv.com/api/tasks_simple.php'
];

foreach ($apiUrls as $url) {
    echo "🔍 Testing endpoint: $url\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "   Status: $httpCode\n";

    if ($httpCode == 200) {
        echo "   ✅ Server is accessible\n";
        break;
    }
}

echo "\n📋 MANUAL DEPLOYMENT REQUIRED\n";
echo "================================\n\n";

echo "**Option 1: FTP Upload**\n";
echo "1. Connect to your hosting FTP\n";
echo "2. Navigate to /api/ directory\n";
echo "3. Upload: {$sourceFile}\n";
echo "4. Rename to: comments_optimized.php\n\n";

echo "**Option 2: cPanel File Manager**\n";
echo "1. Login to your hosting cPanel\n";
echo "2. Open File Manager\n";
echo "3. Go to public_html/api/\n";
echo "4. Upload the file\n\n";

echo "**Option 3: Create via Admin Panel**\n";
echo "1. Go to: https://luvbudstv.com/api/admin_panel.php\n";
echo "2. Login with: TaskMaster2024!\n";
echo "3. Create new file with content below\n\n";

echo "🔍 **VERIFICATION:**\n";
echo "After upload, test: https://luvbudstv.com/api/comments_optimized.php?task_id=test\n";
echo "Expected: JSON response with empty comments array\n\n";

// Save content to a simple text file for easy copying
file_put_contents(__DIR__ . '/comments_api_content.txt', $content);
echo "📄 File content saved to: comments_api_content.txt\n";
echo "   You can copy this content directly if creating the file manually\n\n";

echo "✅ READY FOR DEPLOYMENT!\n";
?>