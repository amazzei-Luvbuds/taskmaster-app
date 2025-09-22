<?php
/**
 * TaskMaster Notification System
 * Handles email notifications and calendar integrations
 */

class NotificationService {
    private $smtpConfig;

    public function __construct() {
        // Configure SMTP settings using environment variables for security
        $this->smtpConfig = [
            'host' => $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com',
            'port' => $_ENV['SMTP_PORT'] ?? 587,
            'username' => $_ENV['SMTP_USERNAME'] ?? '',
            'password' => $_ENV['SMTP_PASSWORD'] ?? '',
            'encryption' => $_ENV['SMTP_ENCRYPTION'] ?? 'tls'
        ];
    }

    /**
     * Send task assignment notification
     */
    public function sendTaskAssignmentNotification($task, $assignedUser, $previousOwners = '') {
        try {
            $subject = "New Task Assigned: {$task['action_item']}";
            $message = $this->buildAssignmentEmailHTML($task, $assignedUser);

            // Send email
            $emailSent = $this->sendEmail($assignedUser['email'], $subject, $message);

            // Log the notification
            $this->logNotification([
                'type' => 'task_assignment',
                'task_id' => $task['task_id'],
                'recipient' => $assignedUser['email'],
                'status' => $emailSent ? 'sent' : 'failed'
            ]);

            return $emailSent;
        } catch (Exception $e) {
            error_log("Notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Build HTML email for task assignment
     */
    private function buildAssignmentEmailHTML($task, $user) {
        $taskUrl = "https://luvbudstv.com/task/{$task['task_id']}"; // Update with your actual URL
        $calendarLink = $this->generateGoogleCalendarLink($task);
        $googleTasksLink = $this->generateGoogleTasksLink($task);

        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Task Assignment</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4f46e5; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; }
                .task-details { background: white; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #4f46e5; }
                .action-buttons { margin: 20px 0; }
                .btn { display: inline-block; padding: 12px 24px; margin: 5px; text-decoration: none; border-radius: 5px; font-weight: bold; }
                .btn-primary { background: #4f46e5; color: white; }
                .btn-secondary { background: #6b7280; color: white; }
                .btn-calendar { background: #dc2626; color: white; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎯 New Task Assigned</h1>
                    <p>Hello {$user['name']}, you have been assigned a new task!</p>
                </div>

                <div class='content'>
                    <div class='task-details'>
                        <h2>{$task['action_item']}</h2>
                        <p><strong>Department:</strong> {$task['department']}</p>
                        <p><strong>Status:</strong> {$task['status']}</p>
                        <p><strong>Priority:</strong> {$task['priority_score']}/100</p>
                        " . ($task['due_date'] ? "<p><strong>Due Date:</strong> {$task['due_date']}</p>" : "") . "
                        " . ($task['problem_description'] ? "<p><strong>Description:</strong> {$task['problem_description']}</p>" : "") . "
                    </div>

                    <div class='action-buttons'>
                        <h3>Quick Actions:</h3>
                        <a href='{$taskUrl}' class='btn btn-primary'>📋 View Task Details</a>
                        <a href='{$calendarLink}' class='btn btn-calendar'>📅 Add to Google Calendar</a>
                        <a href='{$googleTasksLink}' class='btn btn-secondary'>✅ Add to Google Tasks</a>
                    </div>

                    <div style='background: #f3f4f6; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #10b981;'>
                        <h4 style='margin: 0 0 10px 0; color: #065f46;'>📝 Google Tasks Setup Instructions:</h4>
                        <p style='margin: 5px 0; font-size: 14px;'>1. Click 'Add to Google Tasks' button above</p>
                        <p style='margin: 5px 0; font-size: 14px;'>2. Click the '+' button to create a new task</p>
                        <p style='margin: 5px 0; font-size: 14px;'>3. Use this information:</p>
                        <div style='background: white; padding: 10px; margin: 10px 0; border-radius: 3px; font-family: monospace; font-size: 12px;'>
                            <strong>Title:</strong> Work on: {$task['action_item']}<br>
                            <strong>Notes:</strong><br>
                            • Department: {$task['department']}<br>
                            • Priority: {$task['priority_score']}/100<br>
                            " . ($task['due_date'] ? "• Due Date: {$task['due_date']}<br>" : "") . "
                            • Task ID: {$task['task_id']}<br>
                            • Details: {$taskUrl}
                        </div>
                    </div>

                    <p><strong>Need help?</strong> Reply to this email or contact your team lead.</p>

                    <hr style='margin: 20px 0; border: none; border-top: 1px solid #ddd;'>
                    <p style='font-size: 12px; color: #666;'>
                        This is an automated notification from TaskMaster.
                        Task ID: {$task['task_id']} | Assigned: " . date('Y-m-d H:i:s') . "
                    </p>
                </div>
            </div>
        </body>
        </html>";

        return $html;
    }

    /**
     * Generate Google Calendar event link
     */
    private function generateGoogleCalendarLink($task) {
        $title = urlencode("Work on: " . $task['action_item']);
        $details = urlencode($task['problem_description'] ?: $task['action_item']);

        // Use due date if available, otherwise default to tomorrow
        if (!empty($task['due_date'])) {
            // Parse due date and set work time from 9 AM to 11 AM on that day
            $dueDate = date('Y-m-d', strtotime($task['due_date']));
            $startTime = date('Ymd\THis\Z', strtotime($dueDate . ' 9:00'));
            $endTime = date('Ymd\THis\Z', strtotime($dueDate . ' 11:00'));
        } else {
            // Set default time as tomorrow 9 AM for 2 hours
            $startTime = date('Ymd\THis\Z', strtotime('tomorrow 9:00'));
            $endTime = date('Ymd\THis\Z', strtotime('tomorrow 11:00'));
        }

        // Add task details and priority to calendar event
        $extendedDetails = urlencode(
            "Task: " . $task['action_item'] . "\n" .
            "Department: " . $task['department'] . "\n" .
            "Priority: " . $task['priority_score'] . "/100\n" .
            ($task['problem_description'] ? "Description: " . $task['problem_description'] . "\n" : "") .
            ($task['proposed_solution'] ? "Solution: " . $task['proposed_solution'] . "\n" : "") .
            "\nView full task: https://luvbudstv.com/task/" . $task['task_id']
        );

        return "https://calendar.google.com/calendar/render?action=TEMPLATE" .
               "&text={$title}" .
               "&dates={$startTime}/{$endTime}" .
               "&details={$extendedDetails}" .
               "&location=TaskMaster";
    }

    /**
     * Generate Google Tasks link with pre-filled information
     */
    private function generateGoogleTasksLink($task) {
        // While Google Tasks doesn't have a direct URL API for task creation,
        // we can provide a deep link that opens Google Tasks
        // and include instructions in the email for manual creation

        $taskTitle = urlencode("Work on: " . $task['action_item']);
        $taskNotes = urlencode(
            "Department: " . $task['department'] . "\n" .
            "Priority: " . $task['priority_score'] . "/100\n" .
            ($task['due_date'] ? "Due: " . $task['due_date'] . "\n" : "") .
            ($task['problem_description'] ? "Description: " . $task['problem_description'] . "\n" : "") .
            "Task ID: " . $task['task_id'] . "\n" .
            "View full task: https://luvbudstv.com/task/" . $task['task_id']
        );

        // Return Google Tasks main page - user will need to manually create the task
        // but we'll include the formatted information in the email
        return "https://tasks.google.com/embed/?origin=https://tasks.google.com&fullWidth=1";
    }

    /**
     * Generate task creation instructions for Google Tasks
     */
    private function getGoogleTasksInstructions($task) {
        $instructions = "To create this task in Google Tasks:\n";
        $instructions .= "1. Click the 'Add to Google Tasks' button above\n";
        $instructions .= "2. Click the '+' button to create a new task\n";
        $instructions .= "3. Copy and paste the following details:\n\n";
        $instructions .= "Title: Work on: " . $task['action_item'] . "\n";
        $instructions .= "Notes:\n";
        $instructions .= "- Department: " . $task['department'] . "\n";
        $instructions .= "- Priority: " . $task['priority_score'] . "/100\n";
        if ($task['due_date']) {
            $instructions .= "- Due Date: " . $task['due_date'] . "\n";
        }
        if ($task['problem_description']) {
            $instructions .= "- Description: " . $task['problem_description'] . "\n";
        }
        $instructions .= "- Task ID: " . $task['task_id'] . "\n";
        $instructions .= "- Full Details: https://luvbudstv.com/task/" . $task['task_id'];

        return $instructions;
    }

    /**
     * Send email using PHP mail() or SMTP
     */
    private function sendEmail($to, $subject, $htmlMessage) {
        // For development, you might want to use a simple approach
        // In production, consider using PHPMailer or similar for SMTP

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: TaskMaster <noreply@luvbudstv.com>',
            'Reply-To: TaskMaster <noreply@luvbudstv.com>',
            'X-Mailer: PHP/' . phpversion()
        ];

        // Try to send email
        $result = mail($to, $subject, $htmlMessage, implode("\r\n", $headers));

        if (!$result) {
            error_log("Failed to send email to {$to}: " . error_get_last()['message']);
        }

        return $result;
    }

    /**
     * Log notification for tracking
     */
    private function logNotification($data) {
        $logEntry = date('Y-m-d H:i:s') . " - " . json_encode($data) . "\n";
        file_put_contents(
            __DIR__ . '/logs/notifications.log',
            $logEntry,
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * Get user details by email or name
     */
    public function getUserDetails($identifier, $db) {
        // First try to find by email
        $stmt = $db->prepare("
            SELECT name, email, department, avatar_url
            FROM avatar_profiles
            WHERE email = ? OR name = ?
            LIMIT 1
        ");
        $stmt->execute([$identifier, $identifier]);
        return $stmt->fetch();
    }

    /**
     * Check if owners changed and send notifications
     */
    public function handleOwnershipChange($taskId, $newOwners, $previousOwners, $task, $db) {
        if (empty($newOwners) || $newOwners === $previousOwners) {
            return; // No change or empty owners
        }

        // Parse new owners
        $newOwnerList = array_map('trim', explode(',', $newOwners));
        $previousOwnerList = $previousOwners ? array_map('trim', explode(',', $previousOwners)) : [];

        // Find newly assigned owners
        $newlyAssigned = array_diff($newOwnerList, $previousOwnerList);

        foreach ($newlyAssigned as $ownerIdentifier) {
            if (empty($ownerIdentifier)) continue;

            $user = $this->getUserDetails($ownerIdentifier, $db);
            if ($user && !empty($user['email'])) {
                $this->sendTaskAssignmentNotification($task, $user, $previousOwners);
            }
        }
    }
}

/**
 * Helper function to send notifications (called from main API)
 */
function sendTaskNotifications($taskId, $newOwners, $previousOwners, $task, $db) {
    $notificationService = new NotificationService();
    $notificationService->handleOwnershipChange($taskId, $newOwners, $previousOwners, $task, $db);
}
?>