<?php
// Create default placeholder images for packages

$imageDir = __DIR__ . '/assets/images/photoshoots/';
if (!is_dir($imageDir)) {
    mkdir($imageDir, 0755, true);
}

// Package data
$packages = [
    ['name' => 'elegant', 'color1' => 0xc9a227, 'color2' => 0x8b7510, 'emoji' => '🐎'],
    ['name' => 'golden', 'color1' => 0xf0cc50, 'color2' => 0xc9a227, 'emoji' => '👑'],
    ['name' => 'family', 'color1' => 0xe5bf3d, 'color2' => 0xd4ab2c, 'emoji' => '👨‍👩‍👧']
];

// Create images using GD if available
if (extension_loaded('gd')) {
    foreach ($packages as $pkg) {
        $width = 1200;
        $height = 800;
        
        // Extract RGB from hex
        $r1 = ($pkg['color1'] >> 16) & 0xFF;
        $g1 = ($pkg['color1'] >> 8) & 0xFF;
        $b1 = $pkg['color1'] & 0xFF;
        
        $r2 = ($pkg['color2'] >> 16) & 0xFF;
        $g2 = ($pkg['color2'] >> 8) & 0xFF;
        $b2 = $pkg['color2'] & 0xFF;
        
        // Create image
        $img = imagecreatetruecolor($width, $height);
        
        // Draw gradient
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $r = intval($r1 + ($r2 - $r1) * $ratio);
            $g = intval($g1 + ($g2 - $g1) * $ratio);
            $b = intval($b1 + ($b2 - $b1) * $ratio);
            $color = imagecolorallocate($img, $r, $g, $b);
            imageline($img, 0, $y, $width, $y, $color);
        }
        
        // Save as PNG
        $file = $imageDir . $pkg['name'] . '.png';
        imagepng($img, $file, 9);
        imagedestroy($img);
        chmod($file, 0644);
        echo "✅ Created: " . $pkg['name'] . ".png\n";
    }
    echo "\n✅ All images created!\n";
} else {
    echo "❌ GD extension not available\n";
}
