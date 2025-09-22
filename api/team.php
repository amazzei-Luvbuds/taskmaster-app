<?php
/**
 * Team endpoint - Returns team member data with avatars
 */

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-ID-Token, X-User-ID, X-User-Email');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    sendError('Method not allowed', 405);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get all team members from avatar_profiles
    $stmt = $db->prepare("
        SELECT
            ap.name,
            ap.email,
            ap.department,
            ap.avatar_url,
            d.color as department_color
        FROM avatar_profiles ap
        LEFT JOIN departments d ON ap.department = d.name
        ORDER BY ap.name
    ");

    $stmt->execute();
    $teamMembers = $stmt->fetchAll();

    // Format for frontend
    $team = [];
    foreach ($teamMembers as $member) {
        $team[] = [
            'fullName' => $member['name'],
            'email' => $member['email'],
            'department' => $member['department'],
            'avatarUrl' => $member['avatar_url'],
            'department_color' => $member['department_color']
        ];
    }

    sendJSON(['team' => $team]);

} catch (Exception $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
?>