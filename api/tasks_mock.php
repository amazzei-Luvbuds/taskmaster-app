<?php
/**
 * TaskMaster API - Tasks Mock Endpoint
 * Mock implementation for local development when database is not available
 */

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-ID-Token, X-User-ID, X-User-Email');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';

// Mock data
$mockDepartments = [
    ['id' => 'tech', 'name' => 'Technology', 'color' => '#3B82F6'],
    ['id' => 'marketing', 'name' => 'Marketing', 'color' => '#10B981'],
    ['id' => 'operations', 'name' => 'Operations', 'color' => '#F59E0B'],
    ['id' => 'sales', 'name' => 'Sales', 'color' => '#EF4444'],
];

$mockTeam = [
    ['id' => 'user1', 'name' => 'Alex Mazzei', 'email' => 'amazzei@luvbuds.co', 'department' => 'tech', 'role' => 'leadership'],
    ['id' => 'user2', 'name' => 'John Doe', 'email' => 'john@luvbuds.co', 'department' => 'tech', 'role' => 'member'],
    ['id' => 'user3', 'name' => 'Jane Smith', 'email' => 'jane@luvbuds.co', 'department' => 'marketing', 'role' => 'member'],
];

$mockAvatars = [
    ['id' => 'user1', 'avatar_url' => 'https://via.placeholder.com/40/3B82F6/FFFFFF?text=AM'],
    ['id' => 'user2', 'avatar_url' => 'https://via.placeholder.com/40/10B981/FFFFFF?text=JD'],
    ['id' => 'user3', 'avatar_url' => 'https://via.placeholder.com/40/F59E0B/FFFFFF?text=JS'],
];

$mockTasks = [
    [
        'id' => 'IT-002a',
        'title' => 'Fix CORS Issues',
        'description' => 'Resolve CORS problems preventing API communication',
        'status' => 'In Progress',
        'priority' => 'High',
        'department' => 'tech',
        'assignee' => 'Alex Mazzei',
        'assignee_email' => 'amazzei@luvbuds.co',
        'progress_percentage' => 75,
        'due_date' => '2025-09-25',
        'created_at' => '2025-09-20T10:00:00Z',
        'updated_at' => '2025-09-22T03:00:00Z',
    ],
    [
        'id' => 'IT-038',
        'title' => 'Update Task System',
        'description' => 'Implement new task management features',
        'status' => 'Completed',
        'priority' => 'Medium',
        'department' => 'tech',
        'assignee' => 'John Doe',
        'assignee_email' => 'john@luvbuds.co',
        'progress_percentage' => 100,
        'due_date' => '2025-09-20',
        'created_at' => '2025-09-15T09:00:00Z',
        'updated_at' => '2025-09-20T17:00:00Z',
    ],
    [
        'id' => 'MK-001',
        'title' => 'Marketing Campaign',
        'description' => 'Launch new product marketing campaign',
        'status' => 'Not Started',
        'priority' => 'High',
        'department' => 'marketing',
        'assignee' => 'Jane Smith',
        'assignee_email' => 'jane@luvbuds.co',
        'progress_percentage' => 0,
        'due_date' => '2025-10-01',
        'created_at' => '2025-09-18T14:00:00Z',
        'updated_at' => '2025-09-18T14:00:00Z',
    ],
];

switch ($endpoint) {
    case 'departments':
        sendResponse(['departments' => $mockDepartments]);
        break;
        
    case 'team':
        sendResponse(['team' => $mockTeam]);
        break;
        
    case 'avatars':
        sendResponse(['avatars' => $mockAvatars]);
        break;
        
    case 'health':
        sendResponse([
            'status' => 'healthy',
            'version' => '1.0.0',
            'timestamp' => date('c'),
            'database' => 'mock'
        ]);
        break;
        
    default:
        // Return tasks for default endpoint
        $limit = intval($_GET['limit'] ?? 1000);
        $offset = intval($_GET['offset'] ?? 0);
        
        $paginatedTasks = array_slice($mockTasks, $offset, $limit);
        
        // The API client expects result.data to be an array of tasks directly
        sendResponse($paginatedTasks);
        break;
}

function sendResponse($data) {
    echo json_encode([
        'success' => true,
        'data' => $data,
        'status' => 200,
        'timestamp' => date('c')
    ]);
    exit();
}
?>
