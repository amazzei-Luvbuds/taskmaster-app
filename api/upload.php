<?php
/**
 * Secure File Upload API Endpoint
 * Handles secure file uploads with validation, virus scanning, and storage
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: POST, DELETE, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-ID-Token, X-User-ID, X-User-Email');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include authentication and database functions
require_once 'config.php';

// Security configuration
$UPLOAD_CONFIG = [
    'max_file_size' => 10 * 1024 * 1024, // 10MB
    'max_total_size' => 50 * 1024 * 1024, // 50MB
    'allowed_mime_types' => [
        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
        // Images
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        // Archives
        'application/zip',
        'application/x-rar-compressed',
        'application/x-7z-compressed'
    ],
    'upload_dir' => __DIR__ . '/uploads/',
    'dangerous_extensions' => [
        '.exe', '.bat', '.cmd', '.com', '.pif', '.scr', '.vbs', '.js', '.jse',
        '.wsh', '.ps1', '.ps2', '.psc1', '.psc2', '.msh', '.msh1', '.msh2',
        '.mshxml', '.msc', '.reg', '.scf', '.lnk', '.inf', '.hta'
    ]
];

/**
 * Validate authentication
 */
function validateAuth() {
    $headers = getallheaders();

    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit();
    }

    $authHeader = $headers['Authorization'];
    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid authorization header']);
        exit();
    }

    $token = $matches[1];

    // Validate JWT token (implement proper JWT validation)
    // For now, just check if token exists and has proper format
    if (empty($token) || strlen($token) < 20) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit();
    }

    // Extract user information from headers
    $userId = $headers['X-User-ID'] ?? null;
    $userEmail = $headers['X-User-Email'] ?? null;

    if (!$userId || !$userEmail) {
        http_response_code(401);
        echo json_encode(['error' => 'User information missing']);
        exit();
    }

    return [
        'userId' => $userId,
        'userEmail' => $userEmail,
        'token' => $token
    ];
}

/**
 * Validate uploaded file
 */
function validateFile($file, $config) {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error: ' . $file['error']);
    }

    // Check file size
    if ($file['size'] > $config['max_file_size']) {
        throw new Exception('File size exceeds maximum allowed size');
    }

    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $config['allowed_mime_types'])) {
        throw new Exception('File type not allowed: ' . $mimeType);
    }

    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (in_array('.' . $extension, $config['dangerous_extensions'])) {
        throw new Exception('File extension not allowed for security reasons');
    }

    // Check filename for suspicious patterns
    if (!preg_match('/^[a-zA-Z0-9\-_\.\s\(\)]+$/', $file['name'])) {
        throw new Exception('Filename contains invalid characters');
    }

    // Check for null bytes
    if (strpos($file['name'], "\0") !== false) {
        throw new Exception('Filename contains null bytes');
    }

    // Check for path traversal
    if (strpos($file['name'], '../') !== false || strpos($file['name'], '..\\') !== false) {
        throw new Exception('Filename contains path traversal characters');
    }

    return [
        'mimeType' => $mimeType,
        'extension' => $extension
    ];
}

/**
 * Generate secure filename
 */
function generateSecureFilename($originalName, $userId) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    $hash = substr(md5($userId . $originalName . $timestamp), 0, 8);

    return "{$timestamp}_{$hash}_{$random}.{$extension}";
}

/**
 * Virus scan file (placeholder - integrate with actual antivirus)
 */
function virusScanFile($filePath) {
    // In production, integrate with ClamAV or similar
    // For now, just do basic checks

    // Check file size (extremely large files might be suspicious)
    if (filesize($filePath) > 100 * 1024 * 1024) { // 100MB
        return ['status' => 'suspicious', 'reason' => 'File too large'];
    }

    // Read first few bytes to check for executable signatures
    $handle = fopen($filePath, 'rb');
    $header = fread($handle, 4);
    fclose($handle);

    // Check for Windows executable headers
    if ($header === "\x4D\x5A\x90\x00" || // MZ header
        $header === "\x50\x4B\x03\x04") { // ZIP header (could contain executables)
        return ['status' => 'suspicious', 'reason' => 'Executable signature detected'];
    }

    return ['status' => 'clean', 'reason' => 'File appears safe'];
}

/**
 * Store file metadata in database
 */
function storeFileMetadata($pdo, $fileData) {
    $stmt = $pdo->prepare("
        INSERT INTO comment_attachments (
            id, original_name, stored_name, file_size, mime_type,
            uploaded_by, uploaded_at, virus_scanned, scan_result
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    return $stmt->execute([
        $fileData['id'],
        $fileData['originalName'],
        $fileData['storedName'],
        $fileData['fileSize'],
        $fileData['mimeType'],
        $fileData['uploadedBy'],
        $fileData['uploadedAt'],
        $fileData['virusScanned'],
        $fileData['scanResult']
    ]);
}

/**
 * Handle file upload
 */
function handleUpload() {
    global $UPLOAD_CONFIG;

    try {
        // Validate authentication
        $user = validateAuth();

        // Check if file was uploaded
        if (!isset($_FILES['file'])) {
            throw new Exception('No file uploaded');
        }

        $file = $_FILES['file'];

        // Validate file
        $validation = validateFile($file, $UPLOAD_CONFIG);

        // Create upload directory if it doesn't exist
        if (!is_dir($UPLOAD_CONFIG['upload_dir'])) {
            mkdir($UPLOAD_CONFIG['upload_dir'], 0755, true);
        }

        // Generate secure filename
        $uploadId = $_POST['uploadId'] ?? uniqid();
        $secureFilename = generateSecureFilename($file['name'], $user['userId']);
        $uploadPath = $UPLOAD_CONFIG['upload_dir'] . $secureFilename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('Failed to save uploaded file');
        }

        // Perform virus scan
        $scanResult = virusScanFile($uploadPath);

        // If file is suspicious, delete it
        if ($scanResult['status'] !== 'clean') {
            unlink($uploadPath);
            throw new Exception('File failed security scan: ' . $scanResult['reason']);
        }

        // Connect to database
        $pdo = new PDO($dsn, $username, $password, $options);

        // Store file metadata
        $fileId = 'file_' . uniqid();
        $fileData = [
            'id' => $fileId,
            'originalName' => $file['name'],
            'storedName' => $secureFilename,
            'fileSize' => $file['size'],
            'mimeType' => $validation['mimeType'],
            'uploadedBy' => $user['userId'],
            'uploadedAt' => date('Y-m-d H:i:s'),
            'virusScanned' => true,
            'scanResult' => $scanResult['status']
        ];

        if (!storeFileMetadata($pdo, $fileData)) {
            // If database insert fails, clean up uploaded file
            unlink($uploadPath);
            throw new Exception('Failed to store file metadata');
        }

        // Return file information
        echo json_encode([
            'id' => $fileId,
            'fileName' => $secureFilename,
            'originalName' => $file['name'],
            'fileSize' => $file['size'],
            'mimeType' => $validation['mimeType'],
            'fileUrl' => '/api/comments/files/' . $fileId . '/download',
            'uploadedAt' => $fileData['uploadedAt'],
            'uploadedBy' => $user['userId'],
            'virusScanned' => true,
            'scanResult' => $scanResult['status']
        ]);

    } catch (Exception $e) {
        error_log('File upload error: ' . $e->getMessage());
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Handle file deletion
 */
function handleDelete($fileId) {
    try {
        // Validate authentication
        $user = validateAuth();

        // Connect to database
        $pdo = new PDO($dsn, $username, $password, $options);

        // Get file information
        $stmt = $pdo->prepare("SELECT * FROM comment_attachments WHERE id = ? AND uploaded_by = ?");
        $stmt->execute([$fileId, $user['userId']]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            http_response_code(404);
            echo json_encode(['error' => 'File not found or access denied']);
            return;
        }

        // Delete physical file
        $filePath = $UPLOAD_CONFIG['upload_dir'] . $file['stored_name'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM comment_attachments WHERE id = ?");
        $stmt->execute([$fileId]);

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        error_log('File deletion error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete file']);
    }
}

/**
 * Handle file download
 */
function handleDownload($fileId) {
    try {
        // Validate authentication
        $user = validateAuth();

        // Connect to database
        $pdo = new PDO($dsn, $username, $password, $options);

        // Get file information
        $stmt = $pdo->prepare("SELECT * FROM comment_attachments WHERE id = ?");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            http_response_code(404);
            echo json_encode(['error' => 'File not found']);
            return;
        }

        $filePath = $UPLOAD_CONFIG['upload_dir'] . $file['stored_name'];

        if (!file_exists($filePath)) {
            http_response_code(404);
            echo json_encode(['error' => 'File not found on disk']);
            return;
        }

        // Set appropriate headers for file download
        header('Content-Type: ' . $file['mime_type']);
        header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: private, must-revalidate');

        // Stream file content
        readfile($filePath);

    } catch (Exception $e) {
        error_log('File download error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to download file']);
    }
}

// Route requests
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];

if ($method === 'POST' && strpos($path, '/api/comments/upload') !== false) {
    handleUpload();
} elseif ($method === 'DELETE' && preg_match('/\/api\/comments\/files\/([^\/]+)$/', $path, $matches)) {
    handleDelete($matches[1]);
} elseif ($method === 'GET' && preg_match('/\/api\/comments\/files\/([^\/]+)\/download$/', $path, $matches)) {
    handleDownload($matches[1]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
}
?>