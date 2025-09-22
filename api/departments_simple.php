<?php
/**
 * Simple departments endpoint - works without routing
 */

require_once 'config.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("
        SELECT
            d.name,
            d.color,
            d.description,
            COUNT(t.id) as task_count
        FROM departments d
        LEFT JOIN tasks t ON d.name = t.department
        GROUP BY d.id
        ORDER BY d.name
    ");

    $stmt->execute();
    $departments = $stmt->fetchAll();

    // Process the results
    foreach ($departments as &$dept) {
        $dept['task_count'] = (int)$dept['task_count'];
    }

    sendJSON($departments);

} catch (Exception $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
?>