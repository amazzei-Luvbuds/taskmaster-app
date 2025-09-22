<?php
/**
 * Comprehensive Test Suite for Global Pin Management API
 * Tests all endpoints with different user roles and scenarios
 */

require_once '../config.php';
require_once '../auth/middleware.php';

class GlobalPinAPITest {
    private $db;
    private $auth;
    private $baseUrl;
    private $testUsers = [];
    private $testTasks = [];

    public function __construct($database) {
        $this->db = $database;
        $this->auth = new AuthMiddleware($database);
        $this->baseUrl = 'http://localhost/api/pins.php';
        $this->setupTestData();
    }

    /**
     * Setup test data
     */
    private function setupTestData() {
        // Create test users
        $this->testUsers = [
            'leadership' => ['id' => 'test-leader', 'name' => 'Test Leader', 'email' => 'leader@test.com', 'role' => 'leadership'],
            'manager' => ['id' => 'test-manager', 'name' => 'Test Manager', 'email' => 'manager@test.com', 'role' => 'manager'],
            'employee' => ['id' => 'test-employee', 'name' => 'Test Employee', 'email' => 'employee@test.com', 'role' => 'employee']
        ];

        foreach ($this->testUsers as $user) {
            $this->auth->createUser($user['id'], $user['name'], $user['email'], $user['role']);
        }

        // Create test tasks
        $this->testTasks = [
            'task1' => 'test-task-1-' . time(),
            'task2' => 'test-task-2-' . time(),
            'task3' => 'test-task-3-' . time()
        ];

        foreach ($this->testTasks as $taskId) {
            $this->db->exec("
                INSERT INTO tasks (task_id, action_item, department, status, date_created, last_updated)
                VALUES ('$taskId', 'Test Task', 'Tech', 'In Progress', NOW(), NOW())
            ");
        }
    }

    /**
     * Cleanup test data
     */
    private function cleanup() {
        // Remove test tasks
        foreach ($this->testTasks as $taskId) {
            $this->db->exec("DELETE FROM tasks WHERE task_id = '$taskId'");
        }

        // Remove test users
        foreach ($this->testUsers as $user) {
            $this->db->exec("DELETE FROM users WHERE id = '{$user['id']}'");
        }

        // Clean audit logs
        $this->db->exec("DELETE FROM audit_logs WHERE entity_id LIKE 'test-task-%'");
    }

    /**
     * Make HTTP request to API
     */
    private function makeRequest($method, $url, $data = null, $userId = null) {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                $userId ? "X-User-ID: $userId" : ''
            ]
        ]);

        if ($data && in_array($method, ['POST', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'code' => $httpCode,
            'body' => json_decode($response, true)
        ];
    }

    /**
     * Test authentication
     */
    public function testAuthentication() {
        echo "🔐 Testing Authentication\n";
        echo "==========================\n";

        $tests = 0;
        $passed = 0;

        // Test 1: No authentication
        $tests++;
        $response = $this->makeRequest('GET', $this->baseUrl . '/global');
        if ($response['code'] === 401) {
            $passed++;
            echo "   ✅ No auth returns 401\n";
        } else {
            echo "   ❌ No auth should return 401, got {$response['code']}\n";
        }

        // Test 2: Valid authentication
        $tests++;
        $response = $this->makeRequest('GET', $this->baseUrl . '/global', null, $this->testUsers['leadership']['id']);
        if ($response['code'] === 200) {
            $passed++;
            echo "   ✅ Valid auth returns 200\n";
        } else {
            echo "   ❌ Valid auth should return 200, got {$response['code']}\n";
        }

        echo "   Results: $passed/$tests passed\n\n";
        return $passed === $tests;
    }

    /**
     * Test role-based authorization
     */
    public function testAuthorization() {
        echo "🛡️ Testing Authorization\n";
        echo "=========================\n";

        $tests = 0;
        $passed = 0;

        // Test different operations with different roles
        $operations = [
            ['method' => 'GET', 'url' => '/global', 'roles' => ['leadership' => 200, 'manager' => 200, 'employee' => 200]],
            ['method' => 'POST', 'url' => '/global', 'data' => ['taskId' => $this->testTasks['task1'], 'priority' => 5], 'roles' => ['leadership' => 201, 'manager' => 201, 'employee' => 403]]
        ];

        foreach ($operations as $op) {
            foreach ($op['roles'] as $role => $expectedCode) {
                $tests++;
                $url = $this->baseUrl . $op['url'];
                $response = $this->makeRequest($op['method'], $url, $op['data'] ?? null, $this->testUsers[$role]['id']);

                if ($response['code'] === $expectedCode) {
                    $passed++;
                    echo "   ✅ {$op['method']} with $role role returns $expectedCode\n";
                } else {
                    echo "   ❌ {$op['method']} with $role role should return $expectedCode, got {$response['code']}\n";
                }
            }
        }

        echo "   Results: $passed/$tests passed\n\n";
        return $passed === $tests;
    }

    /**
     * Test global pin creation
     */
    public function testCreateGlobalPin() {
        echo "📌 Testing Global Pin Creation\n";
        echo "===============================\n";

        $tests = 0;
        $passed = 0;

        // Test 1: Valid creation
        $tests++;
        $data = [
            'taskId' => $this->testTasks['task1'],
            'priority' => 5,
            'reason' => 'Critical for testing'
        ];

        $response = $this->makeRequest('POST', $this->baseUrl . '/global', $data, $this->testUsers['leadership']['id']);

        if ($response['code'] === 201 && $response['body']['success']) {
            $passed++;
            echo "   ✅ Valid global pin creation\n";
        } else {
            echo "   ❌ Valid creation failed: " . json_encode($response) . "\n";
        }

        // Test 2: Duplicate creation (should fail)
        $tests++;
        $response = $this->makeRequest('POST', $this->baseUrl . '/global', $data, $this->testUsers['leadership']['id']);

        if ($response['code'] === 409) {
            $passed++;
            echo "   ✅ Duplicate creation returns 409\n";
        } else {
            echo "   ❌ Duplicate creation should return 409, got {$response['code']}\n";
        }

        // Test 3: Invalid priority
        $tests++;
        $invalidData = [
            'taskId' => $this->testTasks['task2'],
            'priority' => 15 // Invalid
        ];

        $response = $this->makeRequest('POST', $this->baseUrl . '/global', $invalidData, $this->testUsers['leadership']['id']);

        if ($response['code'] === 400) {
            $passed++;
            echo "   ✅ Invalid priority returns 400\n";
        } else {
            echo "   ❌ Invalid priority should return 400, got {$response['code']}\n";
        }

        // Test 4: Missing required fields
        $tests++;
        $incompleteData = ['priority' => 5]; // Missing taskId

        $response = $this->makeRequest('POST', $this->baseUrl . '/global', $incompleteData, $this->testUsers['leadership']['id']);

        if ($response['code'] === 400) {
            $passed++;
            echo "   ✅ Missing fields returns 400\n";
        } else {
            echo "   ❌ Missing fields should return 400, got {$response['code']}\n";
        }

        echo "   Results: $passed/$tests passed\n\n";
        return $passed === $tests;
    }

    /**
     * Test global pin listing
     */
    public function testListGlobalPins() {
        echo "📋 Testing Global Pin Listing\n";
        echo "==============================\n";

        $tests = 0;
        $passed = 0;

        // Test 1: Basic listing
        $tests++;
        $response = $this->makeRequest('GET', $this->baseUrl . '/global', null, $this->testUsers['leadership']['id']);

        if ($response['code'] === 200 && isset($response['body']['data']['pins'])) {
            $passed++;
            echo "   ✅ Basic listing works\n";
        } else {
            echo "   ❌ Basic listing failed\n";
        }

        // Test 2: Filtered listing
        $tests++;
        $url = $this->baseUrl . '/global?priority=5&limit=10';
        $response = $this->makeRequest('GET', $url, null, $this->testUsers['leadership']['id']);

        if ($response['code'] === 200 && isset($response['body']['data']['pagination'])) {
            $passed++;
            echo "   ✅ Filtered listing with pagination works\n";
        } else {
            echo "   ❌ Filtered listing failed\n";
        }

        echo "   Results: $passed/$tests passed\n\n";
        return $passed === $tests;
    }

    /**
     * Test global pin updates
     */
    public function testUpdateGlobalPin() {
        echo "✏️ Testing Global Pin Updates\n";
        echo "==============================\n";

        $tests = 0;
        $passed = 0;

        $taskId = $this->testTasks['task1'];

        // Test 1: Valid update by owner
        $tests++;
        $updateData = [
            'priority' => 8,
            'reason' => 'Updated priority for testing'
        ];

        $response = $this->makeRequest('PUT', $this->baseUrl . "/global/$taskId", $updateData, $this->testUsers['leadership']['id']);

        if ($response['code'] === 200 && $response['body']['success']) {
            $passed++;
            echo "   ✅ Valid update by owner\n";
        } else {
            echo "   ❌ Valid update failed: " . json_encode($response) . "\n";
        }

        // Test 2: Update non-existent pin
        $tests++;
        $response = $this->makeRequest('PUT', $this->baseUrl . "/global/non-existent-task", $updateData, $this->testUsers['leadership']['id']);

        if ($response['code'] === 404) {
            $passed++;
            echo "   ✅ Update non-existent returns 404\n";
        } else {
            echo "   ❌ Update non-existent should return 404, got {$response['code']}\n";
        }

        // Test 3: Invalid priority in update
        $tests++;
        $invalidUpdate = ['priority' => 0]; // Invalid priority

        $response = $this->makeRequest('PUT', $this->baseUrl . "/global/$taskId", $invalidUpdate, $this->testUsers['leadership']['id']);

        if ($response['code'] === 400) {
            $passed++;
            echo "   ✅ Invalid priority update returns 400\n";
        } else {
            echo "   ❌ Invalid priority should return 400, got {$response['code']}\n";
        }

        echo "   Results: $passed/$tests passed\n\n";
        return $passed === $tests;
    }

    /**
     * Test global pin deletion
     */
    public function testDeleteGlobalPin() {
        echo "🗑️ Testing Global Pin Deletion\n";
        echo "===============================\n";

        $tests = 0;
        $passed = 0;

        $taskId = $this->testTasks['task1'];

        // Test 1: Valid deletion by leadership
        $tests++;
        $response = $this->makeRequest('DELETE', $this->baseUrl . "/global/$taskId", null, $this->testUsers['leadership']['id']);

        if ($response['code'] === 200 && $response['body']['success']) {
            $passed++;
            echo "   ✅ Valid deletion by leadership\n";
        } else {
            echo "   ❌ Valid deletion failed: " . json_encode($response) . "\n";
        }

        // Test 2: Delete non-existent pin
        $tests++;
        $response = $this->makeRequest('DELETE', $this->baseUrl . "/global/non-existent-task", null, $this->testUsers['leadership']['id']);

        if ($response['code'] === 404) {
            $passed++;
            echo "   ✅ Delete non-existent returns 404\n";
        } else {
            echo "   ❌ Delete non-existent should return 404, got {$response['code']}\n";
        }

        // Test 3: Employee trying to delete (should fail)
        $tests++;
        // First create a pin to delete
        $createData = ['taskId' => $this->testTasks['task2'], 'priority' => 3];
        $this->makeRequest('POST', $this->baseUrl . '/global', $createData, $this->testUsers['leadership']['id']);

        $response = $this->makeRequest('DELETE', $this->baseUrl . "/global/{$this->testTasks['task2']}", null, $this->testUsers['employee']['id']);

        if ($response['code'] === 403) {
            $passed++;
            echo "   ✅ Employee deletion returns 403\n";
        } else {
            echo "   ❌ Employee deletion should return 403, got {$response['code']}\n";
        }

        echo "   Results: $passed/$tests passed\n\n";
        return $passed === $tests;
    }

    /**
     * Test audit logging
     */
    public function testAuditLogging() {
        echo "📝 Testing Audit Logging\n";
        echo "=========================\n";

        $tests = 0;
        $passed = 0;

        // Test 1: Audit log creation
        $tests++;
        $taskId = $this->testTasks['task3'];
        $createData = ['taskId' => $taskId, 'priority' => 7, 'reason' => 'Audit test'];

        $this->makeRequest('POST', $this->baseUrl . '/global', $createData, $this->testUsers['manager']['id']);

        // Check if audit log was created
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM audit_logs
            WHERE entity_id = ? AND action = 'global_pin_created'
        ");
        $stmt->execute([$taskId]);
        $count = $stmt->fetch()['count'];

        if ($count > 0) {
            $passed++;
            echo "   ✅ Audit log created for pin creation\n";
        } else {
            echo "   ❌ Audit log not created for pin creation\n";
        }

        // Test 2: Check audit log details
        $tests++;
        $stmt = $this->db->prepare("
            SELECT user_id, action, details FROM audit_logs
            WHERE entity_id = ? AND action = 'global_pin_created'
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$taskId]);
        $log = $stmt->fetch();

        if ($log && $log['user_id'] === $this->testUsers['manager']['id']) {
            $passed++;
            echo "   ✅ Audit log contains correct user information\n";
        } else {
            echo "   ❌ Audit log missing or incorrect user information\n";
        }

        echo "   Results: $passed/$tests passed\n\n";
        return $passed === $tests;
    }

    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "🧪 GLOBAL PIN API TEST SUITE\n";
        echo "=============================\n\n";

        $results = [
            'Authentication' => $this->testAuthentication(),
            'Authorization' => $this->testAuthorization(),
            'Create Global Pin' => $this->testCreateGlobalPin(),
            'List Global Pins' => $this->testListGlobalPins(),
            'Update Global Pin' => $this->testUpdateGlobalPin(),
            'Delete Global Pin' => $this->testDeleteGlobalPin(),
            'Audit Logging' => $this->testAuditLogging()
        ];

        $this->cleanup();

        // Summary
        echo "📊 TEST SUMMARY\n";
        echo "================\n";
        $passed = 0;
        $total = count($results);

        foreach ($results as $test => $result) {
            $status = $result ? '✅ PASS' : '❌ FAIL';
            echo "$status - $test\n";
            if ($result) $passed++;
        }

        echo "\nOverall: $passed/$total tests passed\n";

        if ($passed === $total) {
            echo "🎉 ALL TESTS PASSED! Global Pin API is ready for production.\n";
        } else {
            echo "❌ Some tests failed. Please review the implementation.\n";
        }

        return $passed === $total;
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        // Create database connection
        $database = new Database();
        $db = $database->getConnection();

        $tester = new GlobalPinAPITest($db);
        $tester->runAllTests();
    } catch (Exception $e) {
        echo "💥 Test execution failed: " . $e->getMessage() . "\n";
        echo "This may be due to database connectivity issues.\n";
        echo "Please check your database configuration in config.php\n";
    }
}
?>