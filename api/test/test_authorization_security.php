<?php
/**
 * Comprehensive Security and Authorization Test Suite
 * Tests for Story 1.3: Role-Based Authorization
 */

require_once '../config.php';
require_once '../auth/middleware.php';

class AuthorizationSecurityTest {
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
        // Create test users with different roles
        $this->testUsers = [
            'leadership' => ['id' => 'test-leader-auth', 'name' => 'Test Leader', 'email' => 'leader@auth.test', 'role' => 'leadership'],
            'manager' => ['id' => 'test-manager-auth', 'name' => 'Test Manager', 'email' => 'manager@auth.test', 'role' => 'manager'],
            'employee' => ['id' => 'test-employee-auth', 'name' => 'Test Employee', 'email' => 'employee@auth.test', 'role' => 'employee'],
            'invalid' => ['id' => 'test-invalid-auth', 'name' => 'Test Invalid', 'email' => 'invalid@auth.test', 'role' => 'hacker']
        ];

        foreach ($this->testUsers as $user) {
            $this->auth->createUser($user['id'], $user['name'], $user['email'], $user['role']);
        }

        // Create test tasks
        $this->testTasks = [
            'task1' => 'test-auth-task-1-' . time(),
            'task2' => 'test-auth-task-2-' . time(),
            'task3' => 'test-auth-task-3-' . time()
        ];

        foreach ($this->testTasks as $taskId) {
            $this->db->exec("
                INSERT INTO tasks (task_id, action_item, department, status, date_created, last_updated)
                VALUES ('$taskId', 'Auth Test Task', 'Tech', 'In Progress', NOW(), NOW())
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
        $this->db->exec("DELETE FROM audit_logs WHERE entity_id LIKE 'test-auth-task-%'");
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
     * Test 1: Authentication Requirements
     */
    public function testAuthenticationRequirements() {
        echo "🔐 Testing Authentication Requirements\n";
        echo "=====================================\n";

        $tests = 0;
        $passed = 0;

        // Test 1.1: No authentication returns 401
        $tests++;
        $response = $this->makeRequest('GET', $this->baseUrl . '/global');
        if ($response['code'] === 401) {
            $passed++;
            echo "   ✅ No authentication returns 401\n";
        } else {
            echo "   ❌ No authentication should return 401, got {$response['code']}\n";
        }

        // Test 1.2: Invalid user returns 401
        $tests++;
        $response = $this->makeRequest('GET', $this->baseUrl . '/global', null, 'non-existent-user');
        if ($response['code'] === 401) {
            $passed++;
            echo "   ✅ Invalid user returns 401\n";
        } else {
            echo "   ❌ Invalid user should return 401, got {$response['code']}\n";
        }

        echo "   Results: $passed/$tests passed\n\n";
        return $passed === $tests;
    }

    /**
     * Test 2: Role-Based Permissions for Global Pins
     */
    public function testGlobalPinPermissions() {
        echo "🛡️ Testing Global Pin Role Permissions\n";
        echo "=======================================\n";

        $tests = 0;
        $passed = 0;

        // Test scenarios for each operation
        $operations = [
            'GET' => ['url' => '/global', 'roles' => ['leadership' => 200, 'manager' => 200, 'employee' => 200]],
            'POST' => ['url' => '/global', 'data' => ['taskId' => $this->testTasks['task1'], 'priority' => 5], 'roles' => ['leadership' => 201, 'manager' => 201, 'employee' => 403]],
        ];

        foreach ($operations as $method => $config) {
            foreach ($config['roles'] as $role => $expectedCode) {
                $tests++;
                $url = $this->baseUrl . $config['url'];
                $response = $this->makeRequest($method, $url, $config['data'] ?? null, $this->testUsers[$role]['id']);

                if ($response['code'] === $expectedCode) {
                    $passed++;
                    echo "   ✅ $method with $role role returns $expectedCode\n";
                } else {
                    echo "   ❌ $method with $role role should return $expectedCode, got {$response['code']}\n";
                    if (isset($response['body']['error'])) {
                        echo "       Error: {$response['body']['error']}\n";
                    }
                }
            }
        }

        echo "   Results: $passed/$tests passed\n\n";
        return $passed === $tests;
    }

    /**
     * Test 3: Manager Delete Restriction
     */
    public function testManagerDeleteRestriction() {
        echo "🚫 Testing Manager Delete Restriction\n";
        echo "======================================\n";

        $tests = 0;
        $passed = 0;

        // First create a pin as manager
        $createData = ['taskId' => $this->testTasks['task2'], 'priority' => 3];
        $createResponse = $this->makeRequest('POST', $this->baseUrl . '/global', $createData, $this->testUsers['manager']['id']);

        if ($createResponse['code'] === 201) {
            // Test 3.1: Manager cannot delete their own pin
            $tests++;
            $deleteResponse = $this->makeRequest('DELETE', $this->baseUrl . "/global/{$this->testTasks['task2']}", null, $this->testUsers['manager']['id']);

            if ($deleteResponse['code'] === 403) {
                $passed++;
                echo "   ✅ Manager cannot delete their own pin (403)\n";
            } else {
                echo "   ❌ Manager should not be able to delete pin, got {$deleteResponse['code']}\n";
            }

            // Test 3.2: Leadership can delete manager's pin
            $tests++;
            $deleteResponse = $this->makeRequest('DELETE', $this->baseUrl . "/global/{$this->testTasks['task2']}", null, $this->testUsers['leadership']['id']);

            if ($deleteResponse['code'] === 200) {
                $passed++;
                echo "   ✅ Leadership can delete manager's pin (200)\n";
            } else {
                echo "   ❌ Leadership should be able to delete pin, got {$deleteResponse['code']}\n";
            }
        } else {
            echo "   ❌ Setup failed: Could not create pin as manager\n";
        }

        echo "   Results: $passed/$tests passed\n\n";
        return $passed === $tests;
    }

    /**
     * Test 4: Permission Escalation Prevention
     */
    public function testPermissionEscalationPrevention() {
        echo "🔒 Testing Permission Escalation Prevention\n";
        echo "============================================\n";

        $tests = 0;
        $passed = 0;

        // Test 4.1: Employee cannot create pins
        $tests++;
        $createData = ['taskId' => $this->testTasks['task3'], 'priority' => 7];
        $response = $this->makeRequest('POST', $this->baseUrl . '/global', $createData, $this->testUsers['employee']['id']);

        if ($response['code'] === 403) {
            $passed++;
            echo "   ✅ Employee cannot create global pins (403)\n";
        } else {
            echo "   ❌ Employee should not create pins, got {$response['code']}\n";
        }

        // Test 4.2: Employee cannot update pins
        $tests++;
        // First create a pin as leadership
        $createResponse = $this->makeRequest('POST', $this->baseUrl . '/global', $createData, $this->testUsers['leadership']['id']);

        if ($createResponse['code'] === 201) {
            $updateData = ['priority' => 9];
            $response = $this->makeRequest('PUT', $this->baseUrl . "/global/{$this->testTasks['task3']}", $updateData, $this->testUsers['employee']['id']);

            if ($response['code'] === 403) {
                $passed++;
                echo "   ✅ Employee cannot update global pins (403)\n";
            } else {
                echo "   ❌ Employee should not update pins, got {$response['code']}\n";
            }
        }

        // Test 4.3: Employee cannot delete pins
        $tests++;
        $response = $this->makeRequest('DELETE', $this->baseUrl . "/global/{$this->testTasks['task3']}", null, $this->testUsers['employee']['id']);

        if ($response['code'] === 403) {
            $passed++;
            echo "   ✅ Employee cannot delete global pins (403)\n";
        } else {
            echo "   ❌ Employee should not delete pins, got {$response['code']}\n";
        }

        echo "   Results: $passed/$tests passed\n\n";
        return $passed === $tests;
    }

    /**
     * Test 5: Ownership Validation
     */
    public function testOwnershipValidation() {
        echo "👤 Testing Ownership Validation\n";
        echo "================================\n";

        $tests = 0;
        $passed = 0;

        // Create pins by different users
        $managerPin = ['taskId' => 'manager-pin-' . time(), 'priority' => 4];
        $leaderPin = ['taskId' => 'leader-pin-' . time(), 'priority' => 6];

        // Create tasks first
        foreach ([$managerPin['taskId'], $leaderPin['taskId']] as $taskId) {
            $this->db->exec("
                INSERT INTO tasks (task_id, action_item, department, status, date_created, last_updated)
                VALUES ('$taskId', 'Ownership Test', 'Tech', 'In Progress', NOW(), NOW())
            ");
        }

        // Create pins
        $this->makeRequest('POST', $this->baseUrl . '/global', $managerPin, $this->testUsers['manager']['id']);
        $this->makeRequest('POST', $this->baseUrl . '/global', $leaderPin, $this->testUsers['leadership']['id']);

        // Test 5.1: Manager cannot update leader's pin
        $tests++;
        $updateData = ['priority' => 8];
        $response = $this->makeRequest('PUT', $this->baseUrl . "/global/{$leaderPin['taskId']}", $updateData, $this->testUsers['manager']['id']);

        if ($response['code'] === 403) {
            $passed++;
            echo "   ✅ Manager cannot update leader's pin (403)\n";
        } else {
            echo "   ❌ Manager should not update leader's pin, got {$response['code']}\n";
        }

        // Test 5.2: Manager can update their own pin
        $tests++;
        $response = $this->makeRequest('PUT', $this->baseUrl . "/global/{$managerPin['taskId']}", $updateData, $this->testUsers['manager']['id']);

        if ($response['code'] === 200) {
            $passed++;
            echo "   ✅ Manager can update their own pin (200)\n";
        } else {
            echo "   ❌ Manager should update own pin, got {$response['code']}\n";
        }

        // Test 5.3: Leadership can update anyone's pin
        $tests++;
        $response = $this->makeRequest('PUT', $this->baseUrl . "/global/{$managerPin['taskId']}", ['priority' => 9], $this->testUsers['leadership']['id']);

        if ($response['code'] === 200) {
            $passed++;
            echo "   ✅ Leadership can update manager's pin (200)\n";
        } else {
            echo "   ❌ Leadership should update any pin, got {$response['code']}\n";
        }

        // Cleanup ownership test data
        foreach ([$managerPin['taskId'], $leaderPin['taskId']] as $taskId) {
            $this->db->exec("DELETE FROM tasks WHERE task_id = '$taskId'");
        }

        echo "   Results: $passed/$tests passed\n\n";
        return $passed === $tests;
    }

    /**
     * Test 6: Security Error Messages
     */
    public function testSecurityErrorMessages() {
        echo "💬 Testing Security Error Messages\n";
        echo "===================================\n";

        $tests = 0;
        $passed = 0;

        // Test 6.1: Permission denied includes required permission
        $tests++;
        $response = $this->makeRequest('POST', $this->baseUrl . '/global', ['taskId' => 'test', 'priority' => 5], $this->testUsers['employee']['id']);

        if ($response['code'] === 403 &&
            isset($response['body']['required_permission']) &&
            $response['body']['required_permission'] === 'create') {
            $passed++;
            echo "   ✅ Error includes required permission info\n";
        } else {
            echo "   ❌ Error should include required permission\n";
        }

        // Test 6.2: Permission denied includes user role
        $tests++;
        if ($response['code'] === 403 &&
            isset($response['body']['user_role']) &&
            $response['body']['user_role'] === 'employee') {
            $passed++;
            echo "   ✅ Error includes user role info\n";
        } else {
            echo "   ❌ Error should include user role\n";
        }

        // Test 6.3: Authentication error is clear
        $tests++;
        $response = $this->makeRequest('GET', $this->baseUrl . '/global');

        if ($response['code'] === 401 &&
            isset($response['body']['error']) &&
            $response['body']['error'] === 'Authentication required') {
            $passed++;
            echo "   ✅ Authentication error is clear\n";
        } else {
            echo "   ❌ Authentication error should be clear\n";
        }

        echo "   Results: $passed/$tests passed\n\n";
        return $passed === $tests;
    }

    /**
     * Run all security tests
     */
    public function runAllSecurityTests() {
        echo "🛡️ AUTHORIZATION SECURITY TEST SUITE\n";
        echo "=====================================\n\n";

        $results = [
            'Authentication Requirements' => $this->testAuthenticationRequirements(),
            'Global Pin Permissions' => $this->testGlobalPinPermissions(),
            'Manager Delete Restriction' => $this->testManagerDeleteRestriction(),
            'Permission Escalation Prevention' => $this->testPermissionEscalationPrevention(),
            'Ownership Validation' => $this->testOwnershipValidation(),
            'Security Error Messages' => $this->testSecurityErrorMessages()
        ];

        $this->cleanup();

        // Summary
        echo "🔒 SECURITY TEST SUMMARY\n";
        echo "========================\n";
        $passed = 0;
        $total = count($results);

        foreach ($results as $test => $result) {
            $status = $result ? '✅ PASS' : '❌ FAIL';
            echo "$status - $test\n";
            if ($result) $passed++;
        }

        echo "\nOverall: $passed/$total security tests passed\n";

        if ($passed === $total) {
            echo "🎉 ALL SECURITY TESTS PASSED! Authorization is secure and ready for production.\n";
        } else {
            echo "❌ Some security tests failed. Please review the authorization implementation.\n";
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

        $tester = new AuthorizationSecurityTest($db);
        $tester->runAllSecurityTests();
    } catch (Exception $e) {
        echo "💥 Security test execution failed: " . $e->getMessage() . "\n";
        echo "This may be due to database connectivity issues.\n";
        echo "Please check your database configuration in config.php\n";
    }
}
?>