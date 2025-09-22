<?php
/**
 * TaskMaster API - Departments Endpoint
 * Handles department-related operations
 */

require_once 'config.php';

class DepartmentsAPI {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];

        switch ($method) {
            case 'GET':
                $this->getDepartments();
                break;
            default:
                sendError('Method not allowed', 405);
        }
    }

    /**
     * Get all departments
     */
    public function getDepartments() {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    d.name,
                    d.color,
                    d.description,
                    d.workflow_config,
                    COUNT(t.id) as task_count
                FROM departments d
                LEFT JOIN tasks t ON d.name = t.department
                GROUP BY d.id
                ORDER BY d.name
            ");

            $stmt->execute();
            $departments = $stmt->fetchAll();

            // Process workflow_config JSON
            foreach ($departments as &$dept) {
                $dept['workflow_config'] = $dept['workflow_config']
                    ? json_decode($dept['workflow_config'], true)
                    : null;
                $dept['task_count'] = (int)$dept['task_count'];
            }

            sendJSON($departments);
        } catch (Exception $e) {
            sendError('Failed to fetch departments: ' . $e->getMessage(), 500);
        }
    }
}

// Initialize and handle the request
$api = new DepartmentsAPI();
$api->handleRequest();
?>