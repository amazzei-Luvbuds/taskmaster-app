<?php
/**
 * Report download handler
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$type = $_GET['type'] ?? '';
$data = $_GET['data'] ?? '';
$report = $_GET['report'] ?? '';

try {
    switch ($type) {
        case 'csv':
            if (empty($data)) {
                throw new Exception('No data provided for CSV download');
            }

            $csvContent = base64_decode(urldecode($data));
            $filename = 'taskmaster_report_' . date('Y-m-d_H-i-s') . '.csv';

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($csvContent));

            echo $csvContent;
            break;

        case 'excel':
            if (empty($data)) {
                throw new Exception('No data provided for Excel download');
            }

            $csvContent = base64_decode(urldecode($data));
            $filename = 'taskmaster_report_' . date('Y-m-d_H-i-s') . '.xlsx';

            // For basic Excel export, we'll use CSV format with Excel headers
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($csvContent));

            echo $csvContent;
            break;

        case 'pdf':
            if (empty($report)) {
                throw new Exception('No report data provided for PDF download');
            }

            $reportData = json_decode(base64_decode(urldecode($report)), true);
            if (!$reportData) {
                throw new Exception('Invalid report data');
            }

            $filename = 'taskmaster_report_' . date('Y-m-d_H-i-s') . '.pdf';

            // Generate basic HTML for PDF (in real implementation, would use a PDF library)
            $html = generatePDFContent($reportData);

            header('Content-Type: text/html');
            header('Content-Disposition: inline; filename="' . $filename . '"');

            echo $html;
            break;

        default:
            throw new Exception('Invalid download type');
    }

} catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}

function generatePDFContent($reportData) {
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <title>' . htmlspecialchars($reportData['reportName']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { border-bottom: 2px solid #3B82F6; padding-bottom: 10px; margin-bottom: 20px; }
            .summary { background: #F3F4F6; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
            .metric { display: inline-block; margin: 10px 20px 10px 0; }
            .metric-value { font-size: 24px; font-weight: bold; color: #3B82F6; }
            .metric-label { font-size: 12px; color: #6B7280; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #D1D5DB; padding: 8px; text-align: left; }
            th { background: #F9FAFB; font-weight: bold; }
            .status-completed { color: #059669; }
            .status-progress { color: #D97706; }
            .status-pending { color: #6B7280; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>' . htmlspecialchars($reportData['reportName']) . '</h1>
            <p>Generated: ' . htmlspecialchars($reportData['generatedAt']) . '</p>
        </div>

        <div class="summary">
            <h2>Summary Metrics</h2>
            <div class="metric">
                <div class="metric-value">' . $reportData['summary']['totalTasks'] . '</div>
                <div class="metric-label">Total Tasks</div>
            </div>
            <div class="metric">
                <div class="metric-value">' . $reportData['summary']['completionRate'] . '%</div>
                <div class="metric-label">Completion Rate</div>
            </div>
            <div class="metric">
                <div class="metric-value">' . $reportData['summary']['averageProgress'] . '%</div>
                <div class="metric-label">Average Progress</div>
            </div>
            <div class="metric">
                <div class="metric-value">' . $reportData['summary']['completedTasks'] . '</div>
                <div class="metric-label">Completed Tasks</div>
            </div>
            <div class="metric">
                <div class="metric-value">' . $reportData['summary']['inProgressTasks'] . '</div>
                <div class="metric-label">In Progress Tasks</div>
            </div>
            <div class="metric">
                <div class="metric-value">' . $reportData['summary']['overdueTasks'] . '</div>
                <div class="metric-label">Overdue Tasks</div>
            </div>
        </div>

        <h2>Task Details</h2>
        <table>
            <thead>
                <tr>
                    <th>Task ID</th>
                    <th>Title</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Due Date</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($reportData['tasks'] as $task) {
        $statusClass = '';
        switch ($task['status']) {
            case 'Completed':
                $statusClass = 'status-completed';
                break;
            case 'In Progress':
            case 'Started':
                $statusClass = 'status-progress';
                break;
            default:
                $statusClass = 'status-pending';
        }

        $html .= '<tr>
            <td>' . htmlspecialchars($task['task_id'] ?? '') . '</td>
            <td>' . htmlspecialchars($task['title'] ?? '') . '</td>
            <td>' . htmlspecialchars($task['department'] ?? '') . '</td>
            <td class="' . $statusClass . '">' . htmlspecialchars($task['status'] ?? '') . '</td>
            <td>' . ($task['progress_percentage'] ?? 0) . '%</td>
            <td>' . htmlspecialchars($task['due_date'] ?? '') . '</td>
        </tr>';
    }

    $html .= '      </tbody>
        </table>

        <div style="margin-top: 30px; font-size: 12px; color: #6B7280;">
            <p>Generated by TaskMaster Analytics on ' . date('Y-m-d H:i:s') . '</p>
        </div>
    </body>
    </html>';

    return $html;
}
?>