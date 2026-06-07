<?php
/**
 * Assign default images to packages
 * Run this once to populate all packages with images
 */

require_once 'config/db.php';

header('Content-Type: text/html; charset=utf-8');

// Define images for each package
$images = [
    'باقة الفارس الأنيق' => 'assets/images/photoshoots/elegant.jpg',
    'باقة الفارس الذهبية' => 'assets/images/photoshoots/golden.jpg',
    'باقة العائلة' => 'assets/images/photoshoots/family.jpg'
];

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعيين صور الباقات</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #c9a227; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: right; border: 1px solid #ddd; }
        th { background: #c9a227; color: white; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📸 تعيين صور الباقات</h1>

        <table>
            <thead>
                <tr>
                    <th>اسم الباقة</th>
                    <th>الصورة</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
<?php

try {
    foreach ($images as $packageTitle => $imagePath) {
        // Check if image file exists
        $imagePath2 = __DIR__ . '/' . $imagePath;
        $fileExists = file_exists($imagePath2);

        // Find and update package
        $stmt = $conn->prepare("
            SELECT id FROM photoshoot_packages
            WHERE title = ? LIMIT 1
        ");
        $stmt->execute([$packageTitle]);
        $result = $stmt->fetch();

        if ($result) {
            $id = $result['id'];

            // Update with image
            $updateStmt = $conn->prepare("
                UPDATE photoshoot_packages
                SET image = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$imagePath, $id]);

            echo "                <tr>\n";
            echo "                    <td>{$packageTitle}</td>\n";
            echo "                    <td>{$imagePath}</td>\n";
            echo "                    <td class='success'>✅ تم التحديث</td>\n";
            echo "                </tr>\n";
        } else {
            echo "                <tr>\n";
            echo "                    <td>{$packageTitle}</td>\n";
            echo "                    <td>{$imagePath}</td>\n";
            echo "                    <td class='error'>❌ لم تُعثر عليها</td>\n";
            echo "                </tr>\n";
        }
    }

    // Check result
    $checkStmt = $conn->prepare("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN image != '' AND image IS NOT NULL THEN 1 ELSE 0 END) as with_image
        FROM photoshoot_packages
    ");
    $checkStmt->execute();
    $check = $checkStmt->fetch();

    echo "            </tbody>\n";
    echo "        </table>\n";
    echo "        <h2>📊 النتيجة:</h2>\n";
    echo "        <p><strong>إجمالي الباقات:</strong> " . $check['total'] . "</p>\n";
    echo "        <p><strong>الباقات بصور:</strong> " . $check['with_image'] . "</p>\n";

    if ($check['with_image'] == $check['total']) {
        echo "        <p style='color: green; font-weight: bold; font-size: 18px;'>✅ تم تعيين الصور لجميع الباقات بنجاح!</p>\n";
    }

    echo "        <hr>\n";
    echo "        <p><a href='photoshoots.php' style='padding: 10px 20px; background: #c9a227; color: white; text-decoration: none; border-radius: 5px; display: inline-block;'>👉 شاهد الباقات</a></p>\n";

} catch (Exception $e) {
    echo "        <p style='color: red;'><strong>❌ حدث خطأ:</strong> " . $e->getMessage() . "</p>\n";
}

?>
            </tbody>
        </table>
    </div>
</body>
</html>
