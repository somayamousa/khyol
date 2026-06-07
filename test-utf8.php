<?php
require_once 'config/db.php';

// اختبار الترميز
echo "<!DOCTYPE html>";
echo "<html lang='ar' dir='rtl'>";
echo "<head>";
echo "<meta charset='utf-8'>";
echo "<title>اختبار UTF-8</title>";
echo "<style>body { font-family: Cairo, sans-serif; background: #000; color: #fff; padding: 20px; }</style>";
echo "</head>";
echo "<body>";

echo "<h1>✓ اختبار الترميز</h1>";

// اختبر قاعدة البيانات
try {
    $result = $conn->query("SELECT DATABASE(), @@character_set_client, @@character_set_connection, @@collation_connection");
    $row = $result->fetch();
    echo "<h2>إعدادات قاعدة البيانات:</h2>";
    echo "<pre>";
    print_r($row);
    echo "</pre>";
    
    // جرب نص عربي بسيط
    $stmt = $conn->prepare("SELECT 'مرحبا بك في منصة الفروسية' as test");
    $stmt->execute();
    $data = $stmt->fetch();
    echo "<h2>اختبار النص العربي:</h2>";
    echo "<p>" . $data['test'] . "</p>";
    
} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage();
}

echo "</body></html>";
?>
