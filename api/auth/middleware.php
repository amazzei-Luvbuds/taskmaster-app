<?php
/**
 * Authorization Middleware for Global Pin Management
 * Handles user authentication and role-based permissions
 */

class AuthMiddleware {
    private $db;
    private $currentUser = null;

    public function __construct($database) {
        $this->db = $database;
        $this->initializeUserTable();
    }

    /**
     * Initialize users table if it doesn't exist
     */
    private function initializeUserTable() {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id VARCHAR(255) PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(320) UNIQUE NOT NULL,
                    role ENUM('leadership', 'manager', 'employee') DEFAULT 'employee',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    is_active BOOLEAN DEFAULT TRUE,

                    INDEX idx_email (email),
                    INDEX idx_role (role),
                    INDEX idx_active (is_active)
                ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            ");

            // Insert default admin user if table is empty
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM users");
            $count = $stmt->fetch()['count'];

            if ($count == 0) {
                $this->db->exec("
                    INSERT INTO users (id, name, email, role)
                    VALUES
                    ('admin123', 'Admin User', 'admin@taskmaster.com', 'leadership'),
                    ('manager456', 'Manager User', 'manager@taskmaster.com', 'manager'),
                    ('employee789', 'Employee User', 'employee@taskmaster.com', 'employee')
                ");
            }
        } catch (Exception $e) {
            error_log("Failed to initialize users table: " . $e->getMessage());
        }
    }

    /**
     * Authenticate user from request headers
     */
    public function authenticate() {
        // Check for Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        // For development/demo purposes, also check for X-User-ID header
        $userIdHeader = $_SERVER['HTTP_X_USER_ID'] ?? '';

        // Try to get user from various authentication methods
        $userId = null;

        if (!empty($authHeader)) {
            // Parse Authorization header (Bearer token, Basic auth, etc.)
            $userId = $this->parseAuthHeader($authHeader);
        } elseif (!empty($userIdHeader)) {
            // Development mode: direct user ID
            $userId = $userIdHeader;
        } else {
            // Check for session-based authentication
            session_start();
            $userId = $_SESSION['user_id'] ?? null;
        }

        if (!$userId) {
            return null;
        }

        // Load user from database
        return $this->loadUser($userId);
    }

    /**
     * Parse authorization header
     */
    private function parseAuthHeader($authHeader) {
        if (strpos($authHeader, 'Bearer ') === 0) {
            // JWT token authentication
            $token = substr($authHeader, 7);
            return $this->validateJWTToken($token);
        } elseif (strpos($authHeader, 'Basic ') === 0) {
            // Basic authentication
            $credentials = base64_decode(substr($authHeader, 6));
            list($username, $password) = explode(':', $credentials, 2);
            return $this->validateCredentials($username, $password);
        }

        return null;
    }

    /**
     * Validate JWT token (mock implementation)
     */
    private function validateJWTToken($token) {
        // In production, use a proper JWT library like firebase/jwt
        // For now, return mock user ID for demo purposes
        if ($token === 'demo-admin-token') {
            return 'admin123';
        } elseif ($token === 'demo-manager-token') {
            return 'manager456';
        } elseif ($token === 'demo-employee-token') {
            return 'employee789';
        }

        return null;
    }

    /**
     * Validate username/password credentials (mock implementation)
     */
    private function validateCredentials($username, $password) {
        // In production, hash passwords and validate properly
        $credentials = [
            'admin@taskmaster.com' => ['password' => 'admin123', 'userId' => 'admin123'],
            'manager@taskmaster.com' => ['password' => 'manager456', 'userId' => 'manager456'],
            'employee@taskmaster.com' => ['password' => 'employee789', 'userId' => 'employee789']
        ];

        if (isset($credentials[$username]) && $credentials[$username]['password'] === $password) {
            return $credentials[$username]['userId'];
        }

        return null;
    }

    /**
     * Load user data from database
     */
    private function loadUser($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, name, email, role, is_active
                FROM users
                WHERE id = ? AND is_active = TRUE
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $this->currentUser = [
                    'userId' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'isActive' => (bool)$user['is_active']
                ];
                return $this->currentUser;
            }
        } catch (Exception $e) {
            error_log("Failed to load user: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Get current authenticated user
     */
    public function getCurrentUser() {
        return $this->currentUser;
    }

    /**
     * Check if user has specific permission
     */
    public function hasPermission($operation) {
        if (!$this->currentUser) {
            return false;
        }

        $rolePermissions = [
            'leadership' => ['create', 'update', 'delete', 'view', 'manage_all'],
            'manager' => ['create', 'update', 'view', 'manage_own'],
            'employee' => ['view']
        ];

        $userRole = $this->currentUser['role'] ?? 'employee';
        $permissions = $rolePermissions[$userRole] ?? [];

        return in_array($operation, $permissions);
    }

    /**
     * Check if user can modify specific pin
     */
    public function canModifyPin($pinOwnerId) {
        if (!$this->currentUser) {
            return false;
        }

        // Leadership can modify any pin
        if ($this->currentUser['role'] === 'leadership') {
            return true;
        }

        // Others can only modify their own pins
        return $this->currentUser['userId'] === $pinOwnerId;
    }

    /**
     * Require authentication
     */
    public function requireAuth() {
        if (!$this->currentUser) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Authentication required',
                'code' => 401
            ]);
            exit();
        }
    }

    /**
     * Require specific permission
     */
    public function requirePermission($operation) {
        $this->requireAuth();

        if (!$this->hasPermission($operation)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Insufficient permissions',
                'code' => 403,
                'required_permission' => $operation,
                'user_role' => $this->currentUser['role']
            ]);
            exit();
        }
    }

    /**
     * Get user by ID (for audit logging)
     */
    public function getUserById($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, name, email, role
                FROM users
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get user by ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create or update user (for testing purposes)
     */
    public function createUser($userId, $name, $email, $role = 'employee') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO users (id, name, email, role)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                email = VALUES(email),
                role = VALUES(role),
                updated_at = NOW()
            ");
            return $stmt->execute([$userId, $name, $email, $role]);
        } catch (Exception $e) {
            error_log("Failed to create/update user: " . $e->getMessage());
            return false;
        }
    }
}
?>