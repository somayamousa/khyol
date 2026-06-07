<?php
/**
 * Generate placeholder images for photoshoot packages
 * Creates default images for packages that don't have custom uploads yet
 */

$imageDir = __DIR__ . '/assets/images/photoshoots/';
if (!is_dir($imageDir)) {
    mkdir($imageDir, 0755, true);
}

// Define default images for each package type
$defaultImages = [
    'elegant' => [
        'emoji' => '🐎',
        'color1' => '#c9a227',
        'color2' => '#8b7510',
        'title' => 'باقة الفارس الأنيق'
    ],
    'golden' => [
        'emoji' => '👑',
        'color1' => '#f0cc50',
        'color2' => '#c9a227',
        'title' => 'باقة الفارس الذهبية'
    ],
    'family' => [
        'emoji' => '👨‍👩‍👧',
        'color1' => '#e5bf3d',
        'color2' => '#d4ab2c',
        'title' => 'باقة العائلة'
    ]
];

// Create SVG placeholder images
foreach ($defaultImages as $key => $image) {
    $svg = sprintf(
        '<?xml version="1.0" encoding="UTF-8"?>
<svg width="1200" height="800" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <linearGradient id="grad_%s" x1="0%%" y1="0%%" x2="100%%" y2="100%%">
            <stop offset="0%%" style="stop-color:%s;stop-opacity:1" />
            <stop offset="100%%" style="stop-color:%s;stop-opacity:1" />
        </linearGradient>
    </defs>
    <rect width="1200" height="800" fill="url(#grad_%s)"/>
    <text x="600" y="400" font-size="120" text-anchor="middle" dominant-baseline="middle" fill="#fff" opacity="0.3" font-family="Arial">%s</text>
    <text x="600" y="500" font-size="36" text-anchor="middle" dominant-baseline="middle" fill="#fff" opacity="0.6" font-family="Arial, sans-serif" font-weight="bold">%s</text>
</svg>',
        $key,
        $image['color1'],
        $image['color2'],
        $key,
        $image['emoji'],
        $image['title']
    );

    $filename = $imageDir . $key . '.svg';
    file_put_contents($filename, $svg);
    chmod($filename, 0644);
    echo "✅ Created: $key.svg<br>";
}

// Also create PNG versions for better browser compatibility
// Using GD library if available
if (extension_loaded('gd')) {
    foreach ($defaultImages as $key => $image) {
        $width = 1200;
        $height = 800;

        // Create image
        $img = imagecreatetruecolor($width, $height);

        // Parse colors
        list($r1, $g1, $b1) = sscanf($image['color1'], "#%02x%02x%02x");
        list($r2, $g2, $b2) = sscanf($image['color2'], "#%02x%02x%02x");

        // Create gradient
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $r = intval($r1 + ($r2 - $r1) * $ratio);
            $g = intval($g1 + ($g2 - $g1) * $ratio);
            $b = intval($b1 + ($b2 - $b1) * $ratio);
            $color = imagecolorallocate($img, $r, $g, $b);
            imageline($img, 0, $y, $width, $y, $color);
        }

        // Save as PNG
        $filename = $imageDir . $key . '.png';
        imagepng($img, $filename, 9);
        imagedestroy($img);
        chmod($filename, 0644);
        echo "✅ Created: $key.png<br>";
    }
}

echo "<hr>";
echo "✅ All placeholder images created successfully!<br>";
echo "📁 Location: /assets/images/photoshoots/<br>";
echo "💡 Photographers can now upload custom images to replace these defaults.";
?>
