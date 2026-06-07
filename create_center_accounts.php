<?php
require_once 'config/db.php';

$centers_data = [
    [
        'center_id'  => 7,
        'full_name'  => 'نادي الملكي للفروسية',
        'email'      => 'center.malki@khyol.ps',
        'password'   => '123456',
        'phone'      => '0592100007',
        'city'       => 'رام الله',
        'role'       => 'center',
    ],
    [
        'center_id'  => 8,
        'full_name'  => 'نادي النخيل للفروسية',
        'email'      => 'center.nakheel@khyol.ps',
        'password'   => '123456',
        'phone'      => '0592100008',
        'city'       => 'نابلس',
        'role'       => 'center',
    ],
    [
        'center_id'  => 9,
        'full_name'  => 'مركز القدس للفروسية',
        'email'      => 'center.quds@khyol.ps',
        'password'   => '123456',
        'phone'      => '0592100009',
        'city'       => 'القدس',
        'role'       => 'center',
    ],
    [
        'center_id'  => 10,
        'full_name'  => 'اسطبلات الجليل',
        'email'      => 'center.galilee@khyol.ps',
        'password'   => '123456',
        'phone'      => '0592100010',
        'city'       => 'الناصرة',
        'role'       => 'center',
    ],
    [
        'center_id'  => 11,
        'full_name'  => 'فرسان الخليل',
        'email'      => 'center.khalil@khyol.ps',
        'password'   => '123456',
        'phone'      => '0592100011',
        'city'       => 'الخليل',
        'role'       => 'center',
    ],
    [
        'center_id'  => 12,
        'full_name'  => 'نادي طولكرم الرياضي للفروسية',
        'email'      => 'center.tulkarm@khyol.ps',
        'password'   => '123456',
        'phone'      => '0592100012',
        'city'       => 'طولكرم',
        'role'       => 'center',
    ],
    [
        'center_id'  => 13,
        'full_name'  => 'مركز غزة للفروسية',
        'email'      => 'center.gaza@khyol.ps',
        'password'   => '123456',
        'phone'      => '0592100013',
        'city'       => 'غزة',
        'role'       => 'center',
    ],
];

echo "<pre style='background:#000;color:#d4af37;padding:30px;font-size:15px;font-family:monospace;direction:rtl;'>";
echo "=============================================\n";
echo "   إنشاء حسابات المراكز الفروسية\n";
echo "=============================================\n\n";

foreach ($centers_data as $acc) {
    $center_id = $acc['center_id'];
    unset($acc['center_id']);

    // إنشاء/تحديث حساب المستخدم
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$acc['email']]);
    $existing = $check->fetch();

    if ($existing) {
        $user_id = $existing['id'];
        $upd = $conn->prepare("UPDATE users SET full_name=?, password=?, phone=?, city=?, role='center', status='active' WHERE email=?");
        $upd->execute([
            $acc['full_name'],
            password_hash($acc['password'], PASSWORD_BCRYPT),
            $acc['phone'],
            $acc['city'],
            $acc['email'],
        ]);
        echo "↺ تم تحديث الحساب: " . $acc['email'] . " (user_id=$user_id)\n";
    } else {
        $ins = $conn->prepare("INSERT INTO users (full_name, email, password, phone, city, role, status, verified) VALUES (?, ?, ?, ?, ?, 'center', 'active', 1)");
        $ins->execute([
            $acc['full_name'],
            $acc['email'],
            password_hash($acc['password'], PASSWORD_BCRYPT),
            $acc['phone'],
            $acc['city'],
        ]);
        $user_id = (int)$conn->lastInsertId();
        echo "✓ تم إنشاء الحساب:  " . $acc['email'] . " (user_id=$user_id)\n";
    }

    // ربط المركز بالحساب
    $link = $conn->prepare("UPDATE centers SET owner_id = ? WHERE id = ?");
    $link->execute([$user_id, $center_id]);
    echo "  ↳ تم ربط center_id=$center_id بـ owner_id=$user_id\n\n";
}

echo "=============================================\n";
echo "   بيانات تسجيل الدخول للمراكز\n";
echo "=============================================\n\n";

$rows = $conn->query("SELECT u.email, u.city, c.name
    FROM users u JOIN centers c ON c.owner_id = u.id
    WHERE u.role = 'center' AND u.email LIKE '%@khyol.ps'
    ORDER BY c.id")->fetchAll();

foreach ($rows as $r) {
    echo "🏇 المركز:       " . $r['name'] . "\n";
    echo "📧 الإيميل:      " . $r['email'] . "\n";
    echo "🔑 كلمة المرور:  123456\n";
    echo "📍 المدينة:      " . $r['city'] . "\n";
    echo "---------------------------------------------\n";
}

echo "\n✅ تم! يمكنك تسجيل الدخول بأي حساب أعلاه.\n";
echo "</pre>";
echo "<div style='text-align:center;padding:20px;background:#000;'>";
echo "<a href='login.php' style='background:#d4af37;color:#000;padding:15px 30px;border-radius:30px;font-weight:bold;text-decoration:none;font-family:Cairo,sans-serif;'>🔓 تسجيل الدخول</a>";
echo "</div>";
?>
