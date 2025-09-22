<?php
/**
 * TaskMaster Admin Panel - Team Management
 * Admin-only interface for managing team members and avatars
 */

require_once 'config.php';

// Simple admin authentication
session_start();
$ADMIN_PASSWORD = 'TaskMaster2024!'; // Change this to a secure password

if ($_POST['admin_password'] === $ADMIN_PASSWORD) {
    $_SESSION['admin_logged_in'] = true;
}

if ($_POST['logout']) {
    $_SESSION['admin_logged_in'] = false;
    session_destroy();
}

$isLoggedIn = $_SESSION['admin_logged_in'] ?? false;

// Handle form submissions
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $db = $database->getConnection();

        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_member':
                    $stmt = $db->prepare("
                        INSERT INTO avatar_profiles (name, email, department, avatar_url)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        department = VALUES(department),
                        avatar_url = VALUES(avatar_url)
                    ");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['email'],
                        $_POST['department'],
                        $_POST['avatar_url']
                    ]);
                    $message = "✅ Team member added/updated successfully!";
                    break;

                case 'update_member':
                    $stmt = $db->prepare("
                        UPDATE avatar_profiles
                        SET name = ?, department = ?, avatar_url = ?
                        WHERE email = ?
                    ");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['department'],
                        $_POST['avatar_url'],
                        $_POST['email']
                    ]);
                    $message = "✅ Team member updated successfully!";
                    break;

                case 'delete_member':
                    $stmt = $db->prepare("DELETE FROM avatar_profiles WHERE email = ?");
                    $stmt->execute([$_POST['email']]);
                    $message = "✅ Team member deleted successfully!";
                    break;

                case 'add_department':
                    $stmt = $db->prepare("
                        INSERT INTO departments (name, color, description)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        color = VALUES(color),
                        description = VALUES(description)
                    ");
                    $stmt->execute([
                        $_POST['dept_name'],
                        $_POST['dept_color'],
                        $_POST['dept_description']
                    ]);
                    $message = "✅ Department added/updated successfully!";
                    break;
            }
        }
    } catch (Exception $e) {
        $error = "❌ Error: " . $e->getMessage();
    }
}

// Get current data
if ($isLoggedIn) {
    try {
        $database = new Database();
        $db = $database->getConnection();

        // Get team members
        $stmt = $db->query("SELECT * FROM avatar_profiles ORDER BY name");
        $teamMembers = $stmt->fetchAll();

        // Get departments
        $stmt = $db->query("SELECT * FROM departments ORDER BY name");
        $departments = $stmt->fetchAll();

        // Get statistics
        $stmt = $db->query("SELECT COUNT(*) as total FROM tasks");
        $taskCount = $stmt->fetch()['total'];

        $stmt = $db->query("SELECT COUNT(*) as total FROM avatar_profiles");
        $memberCount = $stmt->fetch()['total'];

    } catch (Exception $e) {
        $error = "❌ Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskMaster Admin Panel</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f7fa;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .login-form {
            max-width: 400px;
            margin: 100px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn {
            background: #007cba;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
        }
        .btn:hover { background: #005a87; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-secondary { background: #6c757d; }
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }
        .member-row {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .member-info { flex: 1; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .stat-card {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
        }
        .stat-number { font-size: 2em; font-weight: bold; }
        .stat-label { opacity: 0.9; }
        .message {
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        .department-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            color: white;
            margin-right: 5px;
        }
        .color-purple { background: #6f42c1; }
        .color-pink { background: #e83e8c; }
        .color-orange { background: #fd7e14; }
        .color-green { background: #28a745; }
        .color-blue { background: #007bff; }
        .color-red { background: #dc3545; }
        .color-teal { background: #20c997; }
        .color-gray { background: #6c757d; }
    </style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
    <!-- Login Form -->
    <div class="login-form">
        <h2>TaskMaster Admin Login</h2>
        <form method="POST">
            <div class="form-group">
                <label>Admin Password:</label>
                <input type="password" name="admin_password" required>
            </div>
            <button type="submit" class="btn">Login</button>
        </form>
    </div>

<?php else: ?>
    <!-- Admin Panel -->
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1>TaskMaster Admin Panel</h1>
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="logout" value="1">
                    <button type="submit" class="btn btn-secondary">Logout</button>
                </form>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($message)): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="card">
            <h3>System Statistics</h3>
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?= $taskCount ?></div>
                    <div class="stat-label">Total Tasks</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $memberCount ?></div>
                    <div class="stat-label">Team Members</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count($departments) ?></div>
                    <div class="stat-label">Departments</div>
                </div>
            </div>
        </div>

        <div class="grid">
            <!-- Team Members -->
            <div class="card">
                <h3>Team Members</h3>

                <!-- Add New Member Form -->
                <form method="POST" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                    <input type="hidden" name="action" value="add_member">
                    <div class="form-group">
                        <label>Name:</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Department:</label>
                        <select name="department" required>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Avatar URL:</label>
                        <input type="url" name="avatar_url" placeholder="http://luvbudstv.com/avatars/...">
                    </div>
                    <button type="submit" class="btn">Add Team Member</button>
                </form>

                <!-- Current Members -->
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($teamMembers as $member): ?>
                        <div class="member-row">
                            <img src="<?= htmlspecialchars($member['avatar_url']) ?>" alt="Avatar" class="avatar" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22><rect width=%2240%22 height=%2240%22 fill=%22%23ddd%22/><text x=%2220%22 y=%2225%22 text-anchor=%22middle%22 fill=%22%23666%22>?</text></svg>'">
                            <div class="member-info">
                                <strong><?= htmlspecialchars($member['name']) ?></strong><br>
                                <small><?= htmlspecialchars($member['email']) ?></small><br>
                                <span class="department-badge color-<?= htmlspecialchars($member['department']) ?>"><?= htmlspecialchars($member['department']) ?></span>
                            </div>
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="action" value="delete_member">
                                <input type="hidden" name="email" value="<?= htmlspecialchars($member['email']) ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this member?')">Delete</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Departments -->
            <div class="card">
                <h3>Departments</h3>

                <!-- Add Department Form -->
                <form method="POST" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                    <input type="hidden" name="action" value="add_department">
                    <div class="form-group">
                        <label>Department Name:</label>
                        <input type="text" name="dept_name" required>
                    </div>
                    <div class="form-group">
                        <label>Color:</label>
                        <select name="dept_color" required>
                            <option value="purple">Purple</option>
                            <option value="pink">Pink</option>
                            <option value="orange">Orange</option>
                            <option value="green">Green</option>
                            <option value="blue">Blue</option>
                            <option value="red">Red</option>
                            <option value="teal">Teal</option>
                            <option value="gray">Gray</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Description:</label>
                        <textarea name="dept_description" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn">Add Department</button>
                </form>

                <!-- Current Departments -->
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($departments as $dept): ?>
                        <div class="member-row">
                            <div class="member-info">
                                <strong><?= htmlspecialchars($dept['name']) ?></strong><br>
                                <span class="department-badge color-<?= htmlspecialchars($dept['color']) ?>"><?= htmlspecialchars($dept['color']) ?></span>
                                <small><?= htmlspecialchars($dept['description']) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="card">
            <h3>Instructions</h3>
            <p><strong>Adding Team Members:</strong></p>
            <ul>
                <li>Upload avatar images to <code>luvbudstv.com/avatars/</code> folder</li>
                <li>Use format: <code>http://luvbudstv.com/avatars/firstname-lastname.jpg</code></li>
                <li>Recommended image size: 150x150 pixels</li>
                <li>Supported formats: JPG, PNG, GIF</li>
            </ul>

            <p><strong>Avatar URL Examples:</strong></p>
            <ul>
                <li><code>http://luvbudstv.com/avatars/john-doe.jpg</code></li>
                <li><code>http://luvbudstv.com/avatars/jane-smith.png</code></li>
                <li><code>http://luvbudstv.com/avatars/default-avatar.jpg</code> (for new employees)</li>
            </ul>
        </div>
    </div>

<?php endif; ?>

</body>
</html>