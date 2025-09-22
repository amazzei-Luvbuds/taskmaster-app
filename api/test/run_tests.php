<?php
/**
 * Test Runner for Global Pin Management API
 * Runs all API tests and provides detailed output
 */

// Include the test file
require_once 'test_global_pin_api.php';

echo "🚀 GLOBAL PIN API TEST RUNNER\n";
echo "==============================\n\n";

try {
    // Create database connection
    echo "Initializing test environment...\n";
    $database = new Database();
    $db = $database->getConnection();

    // Test database connection
    $db->query("SELECT 1");
    echo "Database connection successful!\n";

    // Create test instance
    $tester = new GlobalPinAPITest($db);

    echo "Starting test execution...\n\n";

    // Run all tests
    $success = $tester->runAllTests();

    echo "\n" . str_repeat("=", 50) . "\n";

    if ($success) {
        echo "🎉 ALL TESTS PASSED!\n";
        echo "The Global Pin Management API is ready for production deployment.\n\n";

        echo "Next Steps:\n";
        echo "1. Deploy the API to your server\n";
        echo "2. Update frontend to use the new endpoints\n";
        echo "3. Configure proper authentication in production\n";
        echo "4. Set up monitoring and logging\n";
    } else {
        echo "❌ SOME TESTS FAILED!\n";
        echo "Please review the implementation and fix the failing tests.\n\n";

        echo "Debugging Tips:\n";
        echo "1. Check database connection and permissions\n";
        echo "2. Verify API endpoint URLs are correct\n";
        echo "3. Ensure all required tables exist\n";
        echo "4. Check error logs for detailed error messages\n";
    }

} catch (Exception $e) {
    echo "💥 Test execution failed with exception:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n\n";

    echo "Please check:\n";
    echo "1. Database connection settings in config.php\n";
    echo "2. Required PHP extensions (PDO, curl)\n";
    echo "3. File permissions\n";
    echo "4. Web server configuration\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Test run completed at " . date('Y-m-d H:i:s') . "\n";
?>