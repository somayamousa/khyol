<?php
require_once 'config/db.php';

$accounts = [
    [
        'full_name' => 'محمد المستخدم',
        'email' => 'user@test.com',
        'password' => '123456',
        'phone' => '0591111111',
        'city' => 'رام الله',
        'role' => 'user'
    ],
    [
        'full_name' => 'أحمد صاحب المركز',
        'email' => 'center@test.com',
        'password' => '123456',
        'phone' => '0592222222',
        'city' => 'نابلس',
        'role' => 'center'
    ],
    [
        'full_name' => 'المدير',
        'email' => 'admin@test.com',
        'password' => '123456',
        'phone' => '0593333333',
        'city' => 'القدس',
        'role' => 'admin'
    ],
];

echo "<pre style='background:#000;color:#d4af37;padding:30px;font-size:16px;font-family:monospace;'>";
echo "=============================================\n";
echo "   إنشاء حسابات تجريبية\n";
echo "=============================================\n\n";

foreach ($accounts as $acc) {
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$acc['email']]);

    if ($check->fetch()) {
        $upd = $conn->prepare("UPDATE users SET full_name=?, password=?, phone=?, city=?, role=? WHERE email=?");
        $upd->execute([
            $acc['full_name'],
            password_hash($acc['password'], PASSWORD_BCRYPT),
            $acc['phone'],
            $acc['city'],
            $acc['role'],
            $acc['email']
        ]);
        echo "✓ تم تحديث: " . $acc['email'] . "\n";
    } else {
        $ins = $conn->prepare("INSERT INTO users (full_name, email, password, phone, city, role) VALUES (?, ?, ?, ?, ?, ?)");
        $ins->execute([
            $acc['full_name'],
            $acc['email'],
            password_hash($acc['password'], PASSWORD_BCRYPT),
            $acc['phone'],
            $acc['city'],
            $acc['role']
        ]);
        echo "✓ تم إنشاء: " . $acc['email'] . "\n";
    }
}

echo "\n=============================================\n";
echo "   بيانات تسجيل الدخول\n";
echo "=============================================\n\n";
foreach ($accounts as $acc) {
    echo "📧 الإيميل:    " . $acc['email'] . "\n";
    echo "🔑 كلمة المرور: " . $acc['password'] . "\n";
    echo "👤 الصلاحية:   " . $acc['role'] . "\n";
    echo "---------------------------------------------\n";
}

echo "\n✅ تم! روح لـ login.php وسجّل دخول\n";
echo "</pre>";
echo "<div style='text-align:center;padding:20px;background:#000;'><a href='login.php' style='background:#d4af37;color:#000;padding:15px 30px;border-radius:30px;font-weight:bold;text-decoration:none;font-family:Cairo,sans-serif;'>🔓 اذهب لتسجيل الدخول</a></div>";
?>
