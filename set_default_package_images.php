<?php
/**
 * Set default images for photoshoot packages
 * This ensures all packages have visual representations
 */

require_once 'config/db.php';

// Define default images by package name
$packageImages = [
    'باقة الفارس الأنيق' => 'assets/images/photoshoots/elegant.jpg',
    'باقة الفارس الذهبية' => 'assets/images/photoshoots/golden.jpg',
    'باقة العائلة' => 'assets/images/photoshoots/family.jpg'
];

echo "<h2>📸 Setting Default Package Images</h2>";
echo "<table border='1' cellpadding='10' style='margin: 20px 0; border-collapse: collapse;'>";
echo "<tr style='background: #c9a227; color: #fff;'><th>Package ID</th><th>Package Title</th><th>Image Set</th><th>Status</th></tr>";

try {
    foreach ($packageImages as $title => $imagePath) {
        // Find package by title and update
        $stmt = $conn->prepare("
            SELECT id FROM photoshoot_packages
            WHERE title = ?
            LIMIT 1
        ");
        $stmt->execute([$title]);
        $result = $stmt->fetch();

        if ($result) {
            $pkgId = $result['id'];

            // Update package with default image
            $updateStmt = $conn->prepare("
                UPDATE photoshoot_packages
                SET image = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$imagePath, $pkgId]);

            echo "<tr>";
            echo "<td style='text-align: center;'><strong>{$pkgId}</strong></td>";
            echo "<td>{$title}</td>";
            echo "<td>{$imagePath}</td>";
            echo "<td style='color: green;'>✅ Updated</td>";
            echo "</tr>";
        } else {
            echo "<tr>";
            echo "<td colspan='4' style='color: orange;'>⚠️ Package '{$title}' not found</td>";
            echo "</tr>";
        }
    }

    echo "</table>";

    // Verify all packages have images
    $verifyStmt = $conn->prepare("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN image IS NULL OR image = '' THEN 1 ELSE 0 END) as without_image
        FROM photoshoot_packages
    ");
    $verifyStmt->execute();
    $verify = $verifyStmt->fetch();

    echo "<h3 style='margin-top: 30px;'>✅ Summary:</h3>";
    echo "<p><strong>Total Packages:</strong> " . $verify['total'] . "</p>";
    echo "<p><strong>Without Images:</strong> " . ($verify['without_image'] ?? 0) . "</p>";

    if ($verify['without_image'] == 0) {
        echo "<p style='color: green; font-weight: bold;'>✅ All packages now have default images!</p>";
    }

    echo "<hr>";
    echo "<p><a href='photoshoots.php' style='display: inline-block; padding: 10px 20px; background: #c9a227; color: #fff; text-decoration: none; border-radius: 5px;'>👉 View Packages on Public Page</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Error:</strong> " . $e->getMessage() . "</p>";
}
?>
