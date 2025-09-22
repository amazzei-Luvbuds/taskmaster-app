<?php
/**
 * Import Department Colors and Avatar Profiles
 */

require_once 'config.php';

echo "<h2>Import Department Colors & Avatar Profiles</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();

    // Step 1: Update department colors
    echo "<h3>Updating Department Colors</h3>";

    $departmentColors = [
        'Purchasing' => 'purple',
        'Trade Shows' => 'pink',
        'Ideas' => 'orange',
        'Swag' => 'green',
        'Sales' => 'blue',
        'Accounting' => 'green',
        'HR' => 'red',
        'Customer Retention' => 'teal',
        'Tech' => 'purple',
        'Marketing' => 'orange'
    ];

    foreach ($departmentColors as $dept => $color) {
        $stmt = $db->prepare("UPDATE departments SET color = ? WHERE name LIKE ?");
        $stmt->execute([$color, "%{$dept}%"]);
        echo "<p>✅ Updated {$dept} → {$color}</p>";
    }

    // Step 2: Create avatar_profiles table
    echo "<h3>Creating Avatar Profiles Table</h3>";

    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS avatar_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            department VARCHAR(100),
            avatar_url VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_email (email),
            INDEX idx_name (name)
        ) ENGINE=InnoDB
    ");
    $stmt->execute();
    echo "<p>✅ Avatar profiles table created</p>";

    // Step 3: Insert avatar profiles
    echo "<h3>Importing Avatar Profiles</h3>";

    $avatarProfiles = [
        ['Adam Rubin', 'adam@luvbuds.co', 'procurement', 'http://luvbudstv.com/avatars/adam%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Alex Mazzei', 'amazzei@luvbuds.co', 'ai', 'http://luvbudstv.com/avatars/alex%20ai%203%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Ben PEACH', 'ben@luvbuds.co', 'procurement', 'http://luvbudstv.com/avatars/ben%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Brett Harris', 'brett@luvbuds.co', 'CEO', 'http://luvbudstv.com/avatars/brett%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Chris PRICE', 'cprice@luvbuds.co', 'sales', 'http://luvbudstv.com/avatars/chris%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Danny BAGAPORO', 'dbagaporo@luvbuds.co', 'it', 'http://luvbudstv.com/avatars/danny%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['David CALLEJAS', 'dcallejas@luvbuds.co', 'accounting', 'http://luvbudstv.com/avatars/david%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Unassigned', 'amazzei+ai@luvbuds.co', 'Unassigned', 'http://luvbudstv.com/avatars/default%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Diana CARBALLO', 'diana@luvbuds.co', 'sales', 'http://luvbudstv.com/avatars/diana%20c%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Diana Maldanado', 'dmaldonado@luvbuds.co', 'accounting', 'http://luvbudstv.com/avatars/diana%20Maldanado%20%20luvbuds%20avatar%20with%20logos.jpg'],
        ['Ish HARTLEY', 'ihartley@luvbuds.co', 'sales', 'http://luvbudstv.com/avatars/ish%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Jack MARTIN', 'jmartin@luvbuds.co', 'customer service', 'http://luvbudstv.com/avatars/jack%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Jeff KRIEG', 'jkrieg@luvbuds.co', 'hr', 'http://luvbudstv.com/avatars/jeff%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Joe CABRERA', 'jcabrera@luvbuds.co', 'warehouse', 'http://luvbudstv.com/avatars/joe%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Justin PRYOR', 'jpryor@luvbuds.co', 'procurement', 'http://luvbudstv.com/avatars/justin%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Keenan PATEL', 'kpatel@luvbuds.co', 'erp', 'http://luvbudstv.com/avatars/Keenan%20Patel%20erp.jpg'],
        ['Keith ORTEGA', 'kortega@luvbuds.co', 'sales', 'http://luvbudstv.com/avatars/keith.jpg'],
        ['Kevin GILES', 'kgiles@luvbuds.co', 'swagsupply', 'http://luvbudstv.com/avatars/kevin%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Mike Martin', 'mmartin@luvbuds.co', 'VP of Sales', 'http://luvbudstv.com/avatars/mike%20martin%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Mike Metoyer', 'mikem@luvbuds.co', 'sales', 'http://luvbudstv.com/avatars/mikemetoya%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Mike Metz', 'mmetz@luvbuds.co', 'accounting', 'http://luvbudstv.com/avatars/mike%20metz%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Nett Jackson', 'njackson@luvbuds.co', 'accounting', 'http://luvbudstv.com/avatars/nett%20jackson%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Phil MARTIN', 'pmartin@luvbuds.co', 'President', 'http://luvbudstv.com/avatars/phil%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Robert GRINE', 'grine@luvbuds.co', 'warehouse', 'http://luvbudstv.com/avatars/robert%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Ruben RODRIGUEZ', 'ruben@luvbuds.co', 'sales', 'http://luvbudstv.com/avatars/rubin%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Steven OWEN', 'sowen@luvbuds.co', 'warehouse', 'http://luvbudstv.com/avatars/steven%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Will WRONA', 'will@luvbuds.co', 'marketing', 'http://luvbudstv.com/avatars/will%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Zach UNGERMAN', 'zungerman@luvbuds.co', 'it', 'http://luvbudstv.com/avatars/zach%20luvbuds%20avatar%20with%20logos%20copy.jpg'],
        ['Kevin Farmer', 'kfarmer@luvbuds.co', 'accounting', 'http://luvbudstv.com/avatars/kevin%20farmer.jpg']
    ];

    $stmt = $db->prepare("
        INSERT IGNORE INTO avatar_profiles (name, email, department, avatar_url)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        department = VALUES(department),
        avatar_url = VALUES(avatar_url)
    ");

    $imported = 0;
    foreach ($avatarProfiles as $profile) {
        $stmt->execute([
            $profile[0], // name
            $profile[1], // email
            $profile[2], // department
            $profile[3]  // direct image url
        ]);

        if ($stmt->rowCount() > 0) {
            $imported++;
            echo "<p>✅ Imported: {$profile[0]} ({$profile[1]})</p>";
        } else {
            echo "<p>🔄 Updated: {$profile[0]} ({$profile[1]})</p>";
        }
    }

    echo "<h3>Import Summary</h3>";
    echo "<p><strong>Department colors:</strong> Updated " . count($departmentColors) . " departments</p>";
    echo "<p><strong>Avatar profiles:</strong> Imported {$imported} profiles</p>";
    echo "<p style='color: green; font-weight: bold;'>🎉 Import completed! Your tasks should now show colors and avatars.</p>";

} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Import failed:</strong> " . $e->getMessage() . "</p>";
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
p { margin: 5px 0; }
</style>