<?php
/**
 * Update photoshoot packages with default images
 * Run this once to set default images for all packages
 */

require_once 'config/db.php';

// Map package types to default images
$imageMap = [
    'باقة الفارس الأنيق' => 'assets/images/photoshoots/elegant.png',
    'باقة الفارس الذهبية' => 'assets/images/photoshoots/golden.png',
    'باقة العائلة' => 'assets/images/photoshoots/family.png'
];

// Check which packages exist and update them
$stmt = $conn->prepare("SELECT id, title FROM photoshoot_packages");
$stmt->execute();
$packages = $stmt->fetchAll();

$updated = 0;
echo "<h2>Updating Package Images...</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Title</th><th>Current Image</th><th>New Image</th><th>Status</th></tr>";

foreach ($packages as $pkg) {
    $title = $pkg['title'];
    $newImage = $imageMap[$title] ?? 'assets/images/photoshoots/elegant.png';

    // Check if image already exists
    $currentStmt = $conn->prepare("SELECT image FROM photoshoot_packages WHERE id = ?");
    $currentStmt->execute([$pkg['id']]);
    $current = $currentStmt->fetchColumn();

    // Only update if it's using old default or hero.jpg
    if (strpos($current, 'hero.jpg') !== false || strpos($current, 'default.jpg') !== false || empty($current)) {
        $updateStmt = $conn->prepare("UPDATE photoshoot_packages SET image = ? WHERE id = ?");
        $updateStmt->execute([$newImage, $pkg['id']]);
        $updated++;
        $status = "✅ Updated";
    } else {
        $status = "⏭️ Kept (custom image)";
    }

    echo "<tr>";
    echo "<td>{$pkg['id']}</td>";
    echo "<td>{$title}</td>";
    echo "<td>{$current}</td>";
    echo "<td>{$newImage}</td>";
    echo "<td>{$status}</td>";
    echo "</tr>";
}

echo "</table>";
echo "<h3>✅ Updated {$updated} packages with new default images!</h3>";
echo "<p><a href='photoshoots.php'>← View packages on public page</a></p>";
?>
