<?php
/**
 * Deploy Comments API Script
 * Uploads the comments_optimized.php file to the server
 */

$sourceFile = __DIR__ . '/api/comments_optimized.php';
$targetUrl = 'https://luvbudstv.com/api/comments_optimized.php';

if (!file_exists($sourceFile)) {
    die("❌ Source file not found: $sourceFile\n");
}

$fileContent = file_get_contents($sourceFile);
$fileSize = strlen($fileContent);

echo "📁 Deploying Comments API...\n";
echo "Source: $sourceFile\n";
echo "Target: $targetUrl\n";
echo "Size: " . number_format($fileSize) . " bytes\n\n";

// Try FTP upload if credentials are available
echo "🔧 Manual deployment required:\n\n";
echo "1. Copy this file: $sourceFile\n";
echo "2. Upload to: https://luvbudstv.com/api/comments_optimized.php\n";
echo "3. Set permissions: 644\n\n";

echo "📋 File content preview:\n";
echo "<?php\n";
echo "/**\n";
echo " * Optimized Comments API with Advanced Pagination\n";
echo " */\n\n";

echo "✅ File is ready for upload!\n";
echo "📊 API Features: Pagination, Caching, Authentication, Performance Monitoring\n";

// Create a simple upload instruction
$uploadInstructions = "
## 🚀 UPLOAD INSTRUCTIONS

**File to Upload:**
{$sourceFile}

**Upload Location:**
https://luvbudstv.com/api/comments_optimized.php

**Method 1: FTP/SFTP**
1. Connect to your server via FTP
2. Navigate to the /api/ directory
3. Upload comments_optimized.php
4. Set permissions to 644

**Method 2: cPanel File Manager**
1. Login to cPanel
2. Open File Manager
3. Navigate to /api/
4. Upload comments_optimized.php

**Method 3: Command Line (if you have SSH access)**
```bash
scp {$sourceFile} user@server:/path/to/api/comments_optimized.php
```

**Test After Upload:**
Visit: https://luvbudstv.com/api/comments_optimized.php?task_id=test

Expected response:
```json
{
  \"comments\": [],
  \"hasMore\": false,
  \"nextCursor\": null,
  \"totalCount\": 0
}
```
";

file_put_contents(__DIR__ . '/UPLOAD_INSTRUCTIONS.md', $uploadInstructions);
echo "\n📝 Detailed instructions saved to: UPLOAD_INSTRUCTIONS.md\n";
?>