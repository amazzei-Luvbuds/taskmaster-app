<?php
/**
 * Simple file upload script for admin panel
 */

$adminPanelContent = file_get_contents(__DIR__ . '/api/admin_panel.php');

// Upload to your server (you'll need to copy this file manually or use FTP)
echo "Admin panel content length: " . strlen($adminPanelContent) . " bytes\n";
echo "Ready to upload to: https://luvbudstv.com/api/admin_panel.php\n";
echo "\nTo upload:\n";
echo "1. Copy the admin_panel.php file from the /api/ folder\n";
echo "2. Upload it to your server's /api/ directory\n";
echo "3. Access it at: https://luvbudstv.com/api/admin_panel.php\n";
echo "4. Login with password: TaskMaster2024!\n";
?>