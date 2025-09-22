<?php
/**
 * Mock Upload Endpoint for Local Development
 * Simulates file uploads for testing purposes
 */

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-ID-Token, X-User-ID, X-User-Email');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'No file uploaded or upload error']);
        exit;
    }

    $file = $_FILES['file'];
    $uploadId = $_POST['uploadId'] ?? 'mock_' . uniqid();
    $originalName = $_POST['originalName'] ?? $file['name'];

    // Generate a mock file URL (using a placeholder service)
    $fileId = 'mock_' . time() . '_' . substr(md5($file['name']), 0, 8);
    $fileUrl = 'https://via.placeholder.com/400x300/cccccc/666666?text=' . urlencode($originalName);

    // Create mock response
    $response = [
        'id' => $fileId,
        'fileName' => $originalName,
        'originalName' => $originalName,
        'fileSize' => $file['size'],
        'mimeType' => $file['type'],
        'fileUrl' => $fileUrl,
        'uploadedAt' => date('c'),
        'uploadedBy' => 'mock-user',
        'virusScanned' => true,
        'scanResult' => 'clean',
        'uploadId' => $uploadId,
        'mock' => true
    ];

    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Upload failed: ' . $e->getMessage()
    ]);
}
?>
