<?php
$page_title = 'لوحة الاستوديو';
require_once 'config/db.php';
require_once 'includes/notify.php';

if (!isLoggedIn()) redirect('login.php?redirect=my-studio');

$user_id = (int)$_SESSION['user_id'];
$pg_stmt = $conn->prepare("SELECT * FROM photographers WHERE owner_id = ? LIMIT 1");
$pg_stmt->execute([$user_id]);
$studio = $pg_stmt->fetch();

if (!$studio) {
    redirect('register.php?type=photographer');
}

$studio_id = (int)$studio['id'];
$tab = $_GET['tab'] ?? 'overview';
$errors = [];

// ==================== POST ACTIONS ====================

// تحديث بيانات الاستوديو
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $studio_name = sanitize($_POST['studio_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $instagram = sanitize($_POST['instagram'] ?? '');
    $portfolio = sanitize($_POST['portfolio_url'] ?? '');
    $years = max(0, (int)($_POST['years_experience'] ?? 1));

    if (strlen($studio_name) < 3) $errors[] = 'اسم الاستوديو قصير.';

    $image_path = $studio['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['image']['size'] < 5 * 1024 * 1024) {
            $dir = __DIR__ . '/assets/images/photographers/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = 'pg_' . $studio_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dir . $fname)) {
                $image_path = 'assets/images/photographers/' . $fname;
            }
        }
    }

    $cover_path = $studio['cover_image'];
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['cover_image']['size'] < 8 * 1024 * 1024) {
            $dir = __DIR__ . '/assets/images/photographers/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = 'cov_' . $studio_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $dir . $fname)) {
                $cover_path = 'assets/images/photographers/' . $fname;
            }
        }
    }

    if (empty($errors)) {
        $upd = $conn->prepare("
            UPDATE photographers
            SET studio_name=?, description=?, city=?, address=?, phone=?, email=?,
                instagram=?, portfolio_url=?, years_experience=?, image=?, cover_image=?
            WHERE id=? AND owner_id=?
        ");
        $upd->execute([$studio_name, $description, $city, $address, $phone, $email,
            $instagram, $portfolio, $years, $image_path, $cover_path, $studio_id, $user_id]);
        redirect('my-studio.php?tab=profile&updated=1');
    }
}

// تغيير حالة جلسة
if (isset($_GET['session_action'], $_GET['bid'])) {
    $bid = (int)$_GET['bid'];
    $action = $_GET['session_action'];
    $new_status = ['confirm'=>'confirmed','reject'=>'cancelled','complete'=>'completed'][$action] ?? null;
    if ($new_status) {
        // تأكد إن الحجز تبع باقة مملوكة لهذا المصوّر
        $chk = $conn->prepare("
            SELECT pb.id FROM photoshoot_bookings pb
            JOIN photoshoot_packages pkg ON pkg.id = pb.package_id
            WHERE pb.id = ? AND pkg.photographer_id = ?
        ");
        $chk->execute([$bid, $studio_id]);
        $booking_row = $chk->fetch();
        if ($booking_row) {
            // جلب بيانات الحجز والعميل قبل التحديث
            $binfo = $conn->prepare("
                SELECT pb.user_id, pb.session_date, pb.session_time, pkg.title
                FROM photoshoot_bookings pb
                JOIN photoshoot_packages pkg ON pkg.id = pb.package_id
                WHERE pb.id = ?
            ");
            $binfo->execute([$bid]);
            $binfo = $binfo->fetch();

            $conn->prepare("UPDATE photoshoot_bookings SET status = ? WHERE id = ?")->execute([$new_status, $bid]);

            if ($binfo && $binfo['user_id']) {
                $date_fmt = date('d/m/Y', strtotime($binfo['session_date']));
                $time_fmt = substr($binfo['session_time'], 0, 5);
                if ($new_status === 'confirmed') {
                    send_notification($conn, (int)$binfo['user_id'],
                        'تم تأكيد جلستك 📸',
                        'وافق المصور على جلسة "' . $binfo['title'] . '" بتاريخ ' . $date_fmt . ' الساعة ' . $time_fmt,
                        'photoshoot', '✅', 'account.php?tab=photoshoots'
                    );
                } elseif ($new_status === 'cancelled') {
                    send_notification($conn, (int)$binfo['user_id'],
                        'تم رفض جلستك',
                        'اعتذر المصور عن جلسة "' . $binfo['title'] . '" بتاريخ ' . $date_fmt . ' الساعة ' . $time_fmt . '. يمكنك الحجز في وقت آخر.',
                        'photoshoot', '❌', 'photoshoots.php'
                    );
                } elseif ($new_status === 'completed') {
                    send_notification($conn, (int)$binfo['user_id'],
                        'جلستك اكتملت 🏁',
                        'تم إنهاء جلسة "' . $binfo['title'] . '" بتاريخ ' . $date_fmt . '. صورك جاهزة قريباً!',
                        'photoshoot', '🏁', 'account.php?tab=photoshoots'
                    );
                }
            }
        }
        redirect('my-studio.php?tab=sessions&session_updated=1');
    }
}

// حفظ ملاحظات الجلسة ورابط التسليم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_session_notes'])) {
    $bid = (int)($_POST['booking_id'] ?? 0);
    $notes = sanitize($_POST['session_notes'] ?? '');
    $link = trim($_POST['delivery_link'] ?? '');
    $chk = $conn->prepare("
        SELECT pb.id FROM photoshoot_bookings pb
        JOIN photoshoot_packages pkg ON pkg.id = pb.package_id
        WHERE pb.id = ? AND pkg.photographer_id = ?
    ");
    $chk->execute([$bid, $studio_id]);
    if ($chk->fetch()) {
        $delivered_at = $link ? date('Y-m-d H:i:s') : null;
        $conn->prepare("UPDATE photoshoot_bookings SET session_notes=?, delivery_link=?, delivered_at=? WHERE id=?")
            ->execute([$notes, $link ?: null, $delivered_at, $bid]);
    }
    redirect('my-studio.php?tab=sessions&session_saved=1');
}

// رفع صور مسلَّمة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_delivery'])) {
    $bid = (int)($_POST['booking_id'] ?? 0);
    $caption = sanitize($_POST['caption'] ?? '');
    $chk = $conn->prepare("
        SELECT pb.id FROM photoshoot_bookings pb
        JOIN photoshoot_packages pkg ON pkg.id = pb.package_id
        WHERE pb.id = ? AND pkg.photographer_id = ?
    ");
    $chk->execute([$bid, $studio_id]);
    $count = 0;
    if ($chk->fetch() && isset($_FILES['delivery_files'])) {
        $dir = __DIR__ . '/assets/uploads/deliveries/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $allowed = ['jpg','jpeg','png','webp','mp4','mov'];
        $files = $_FILES['delivery_files'];
        $n = count($files['name']);
        for ($i = 0; $i < $n; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed) || $files['size'][$i] > 50 * 1024 * 1024) continue;
            $is_video = in_array($ext, ['mp4','mov']) ? 1 : 0;
            $fname = 'd_' . $bid . '_' . time() . '_' . $i . '.' . $ext;
            if (move_uploaded_file($files['tmp_name'][$i], $dir . $fname)) {
                $path = 'assets/uploads/deliveries/' . $fname;
                $conn->prepare("INSERT INTO photoshoot_deliveries (booking_id, photographer_id, file_path, is_video, caption) VALUES (?,?,?,?,?)")
                    ->execute([$bid, $studio_id, $path, $is_video, $caption]);
                $count++;
            }
        }
        if ($count > 0) {
            $conn->prepare("UPDATE photoshoot_bookings SET delivered_at = NOW() WHERE id = ?")->execute([$bid]);
        }
    }
    redirect('my-studio.php?tab=sessions&uploaded=' . $count);
}

// حذف صورة تسليم
if (isset($_GET['delete_delivery'])) {
    $did = (int)$_GET['delete_delivery'];
    $conn->prepare("DELETE FROM photoshoot_deliveries WHERE id=? AND photographer_id=?")->execute([$did, $studio_id]);
    redirect('my-studio.php?tab=sessions&deleted=1');
}

// إدارة الباقات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_package'])) {
    $pid = (int)($_POST['package_id'] ?? 0);
    $title = sanitize($_POST['title'] ?? '');
    $desc = sanitize($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $dur = (int)($_POST['duration_minutes'] ?? 60);
    $photos = (int)($_POST['photos_count'] ?? 30);
    $outfits = sanitize($_POST['free_outfits'] ?? '');

    $img = null;
    if ($pid) {
        $old = $conn->prepare("SELECT image FROM photoshoot_packages WHERE id=? AND photographer_id=?");
        $old->execute([$pid, $studio_id]);
        $img = $old->fetchColumn() ?: null;
    } else {
        $img = 'assets/images/hero.jpg';
    }
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['image']['size'] < 5 * 1024 * 1024) {
            $dir = __DIR__ . '/assets/images/photoshoots/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = 'pkg_' . $studio_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dir . $fname)) {
                $img = 'assets/images/photoshoots/' . $fname;
            }
        }
    }

    if ($title && $price > 0) {
        if ($pid) {
            $q = $conn->prepare("UPDATE photoshoot_packages SET title=?, description=?, price=?, duration_minutes=?, photos_count=?, free_outfits=?, image=? WHERE id=? AND photographer_id=?");
            $q->execute([$title, $desc, $price, $dur, $photos, $outfits, $img, $pid, $studio_id]);
        } else {
            $q = $conn->prepare("INSERT INTO photoshoot_packages (photographer_id, title, description, price, duration_minutes, photos_count, free_outfits, image, is_active) VALUES (?,?,?,?,?,?,?,?,1)");
            $q->execute([$studio_id, $title, $desc, $price, $dur, $photos, $outfits, $img]);
        }
        redirect('my-studio.php?tab=packages&saved=1');
    }
    $errors[] = 'يجب إدخال عنوان الباقة وسعر أكبر من صفر.';
}
// تحديث صورة الباقة بسرعة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pkg_image'])) {
    $pid = (int)($_POST['package_id'] ?? 0);
    if ($pid && isset($_FILES['quick_image']) && $_FILES['quick_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['quick_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['quick_image']['size'] < 5 * 1024 * 1024) {
            $dir = __DIR__ . '/assets/images/photoshoots/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = 'pkg_' . $studio_id . '_' . $pid . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['quick_image']['tmp_name'], $dir . $fname)) {
                $img = 'assets/images/photoshoots/' . $fname;
                $conn->prepare("UPDATE photoshoot_packages SET image=? WHERE id=? AND photographer_id=?")
                    ->execute([$img, $pid, $studio_id]);
                redirect('my-studio.php?tab=packages&img_updated=1');
            }
        }
    }
}
if (isset($_GET['toggle_package'])) {
    $pid = (int)$_GET['toggle_package'];
    $conn->prepare("UPDATE photoshoot_packages SET is_active = NOT is_active WHERE id=? AND photographer_id=?")
        ->execute([$pid, $studio_id]);
    redirect('my-studio.php?tab=packages');
}
if (isset($_GET['delete_package'])) {
    $conn->prepare("DELETE FROM photoshoot_packages WHERE id=? AND photographer_id=?")
        ->execute([(int)$_GET['delete_package'], $studio_id]);
    redirect('my-studio.php?tab=packages&deleted=1');
}

// معرض الأعمال
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_gallery'])) {
    $count = 0;
    $title = sanitize($_POST['gallery_title'] ?? '');
    $caption = sanitize($_POST['gallery_caption'] ?? '');
    if (isset($_FILES['gallery_files'])) {
        $dir = __DIR__ . '/assets/uploads/gallery/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $allowed = ['jpg','jpeg','png','webp'];
        $files = $_FILES['gallery_files'];
        $n = count($files['name']);
        for ($i = 0; $i < $n; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed) || $files['size'][$i] > 8 * 1024 * 1024) continue;
            $fname = 'g_' . $studio_id . '_' . time() . '_' . $i . '.' . $ext;
            if (move_uploaded_file($files['tmp_name'][$i], $dir . $fname)) {
                $path = 'assets/uploads/gallery/' . $fname;
                $conn->prepare("INSERT INTO photographer_gallery (photographer_id, image, title, caption) VALUES (?,?,?,?)")
                    ->execute([$studio_id, $path, $title ?: null, $caption ?: null]);
                $count++;
            }
        }
    }
    redirect('my-studio.php?tab=portfolio&uploaded=' . $count);
}
if (isset($_GET['delete_gallery'])) {
    $conn->prepare("DELETE FROM photographer_gallery WHERE id=? AND photographer_id=?")
        ->execute([(int)$_GET['delete_gallery'], $studio_id]);
    redirect('my-studio.php?tab=portfolio&deleted=1');
}

// ==================== جلب البيانات ====================

$stats = [
    'sessions_total'   => (int)$conn->query("SELECT COUNT(*) FROM photoshoot_bookings pb JOIN photoshoot_packages pk ON pk.id=pb.package_id WHERE pk.photographer_id=$studio_id")->fetchColumn(),
    'sessions_pending' => (int)$conn->query("SELECT COUNT(*) FROM photoshoot_bookings pb JOIN photoshoot_packages pk ON pk.id=pb.package_id WHERE pk.photographer_id=$studio_id AND pb.status='pending'")->fetchColumn(),
    'sessions_upcoming'=> (int)$conn->query("SELECT COUNT(*) FROM photoshoot_bookings pb JOIN photoshoot_packages pk ON pk.id=pb.package_id WHERE pk.photographer_id=$studio_id AND pb.session_date >= CURDATE() AND pb.status IN ('pending','confirmed')")->fetchColumn(),
    'packages_count'   => (int)$conn->query("SELECT COUNT(*) FROM photoshoot_packages WHERE photographer_id=$studio_id")->fetchColumn(),
    'gallery_count'    => (int)$conn->query("SELECT COUNT(*) FROM photographer_gallery WHERE photographer_id=$studio_id")->fetchColumn(),
    'deliveries_count' => (int)$conn->query("SELECT COUNT(*) FROM photoshoot_deliveries WHERE photographer_id=$studio_id")->fetchColumn(),
    'rating'           => (float)$studio['rating'],
    'reviews_count'    => (int)$studio['reviews_count'],
];

// الجلسات
$sess_status = $_GET['ss_status'] ?? 'all';
$sess_where = "pk.photographer_id = ?";
$sess_params = [$studio_id];
if (in_array($sess_status, ['pending','confirmed','completed','cancelled'])) {
    $sess_where .= " AND pb.status = ?";
    $sess_params[] = $sess_status;
}
$sess_sql = "
    SELECT pb.*, pk.title AS pkg_title, pk.price AS pkg_price, pk.photos_count,
           u.full_name AS client_name, u.phone AS client_phone, h.name AS horse_name,
           (SELECT COUNT(*) FROM photoshoot_deliveries WHERE booking_id = pb.id) AS delivered_count
    FROM photoshoot_bookings pb
    JOIN photoshoot_packages pk ON pk.id = pb.package_id
    JOIN users u ON u.id = pb.user_id
    LEFT JOIN horses h ON h.id = pb.horse_id
    WHERE $sess_where
    ORDER BY pb.session_date DESC, pb.session_time DESC
    LIMIT 200
";
$sess_stmt = $conn->prepare($sess_sql);
$sess_stmt->execute($sess_params);
$sessions = $sess_stmt->fetchAll();

// الجلسات القادمة للـoverview
$upcoming_sessions = $conn->prepare("
    SELECT pb.*, pk.title AS pkg_title, u.full_name AS client_name, h.name AS horse_name
    FROM photoshoot_bookings pb
    JOIN photoshoot_packages pk ON pk.id = pb.package_id
    JOIN users u ON u.id = pb.user_id
    LEFT JOIN horses h ON h.id = pb.horse_id
    WHERE pk.photographer_id = ? AND pb.session_date >= CURDATE() AND pb.status IN ('pending','confirmed')
    ORDER BY pb.session_date ASC, pb.session_time ASC
    LIMIT 5
");
$upcoming_sessions->execute([$studio_id]);
$upcoming_sessions = $upcoming_sessions->fetchAll();

// الباقات
$packages = $conn->prepare("SELECT * FROM photoshoot_packages WHERE photographer_id = ? ORDER BY id DESC");
$packages->execute([$studio_id]);
$packages = $packages->fetchAll();

$edit_package = null;
if (isset($_GET['edit_package'])) {
    $q = $conn->prepare("SELECT * FROM photoshoot_packages WHERE id=? AND photographer_id=?");
    $q->execute([(int)$_GET['edit_package'], $studio_id]);
    $edit_package = $q->fetch() ?: null;
}

// معرض الأعمال
$gallery = $conn->prepare("SELECT * FROM photographer_gallery WHERE photographer_id = ? ORDER BY sort_order ASC, id DESC");
$gallery->execute([$studio_id]);
$gallery = $gallery->fetchAll();

// جلسة محددة (لرفع الصور / تعديل)
$active_session = null;
$session_deliveries = [];
if (isset($_GET['manage_session'])) {
    $q = $conn->prepare("
        SELECT pb.*, pk.title AS pkg_title, u.full_name AS client_name, u.phone AS client_phone, h.name AS horse_name
        FROM photoshoot_bookings pb
        JOIN photoshoot_packages pk ON pk.id = pb.package_id
        JOIN users u ON u.id = pb.user_id
        LEFT JOIN horses h ON h.id = pb.horse_id
        WHERE pb.id = ? AND pk.photographer_id = ?
    ");
    $q->execute([(int)$_GET['manage_session'], $studio_id]);
    $active_session = $q->fetch() ?: null;
    if ($active_session) {
        $d = $conn->prepare("SELECT * FROM photoshoot_deliveries WHERE booking_id = ? ORDER BY id DESC");
        $d->execute([$active_session['id']]);
        $session_deliveries = $d->fetchAll();
    }
}

$status_labels = [
    'pending'   => ['⏳ قيد المراجعة', '#f39c12'],
    'confirmed' => ['✅ مؤكدة',        '#2ecc71'],
    'completed' => ['🏁 مكتملة',       '#3498db'],
    'cancelled' => ['❌ ملغية',        '#e74c3c'],
];
$months = ['','يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];

include 'includes/header.php';
?>

<div class="mc-wrap">
    <aside class="mc-sidebar">
        <div class="mc-brand">
            <img src="<?= sanitize($studio['image']) ?>" alt="" onerror="this.src='assets/images/hero.jpg'">
            <div>
                <strong><?= sanitize($studio['studio_name']) ?></strong>
                <small>📍 <?= sanitize($studio['city']) ?></small>
            </div>
        </div>
        <ul class="mc-nav">
            <li><a href="?tab=overview" class="<?= $tab=='overview'?'active':'' ?>"><span>📊</span> نظرة عامة</a></li>
            <li><a href="?tab=profile" class="<?= $tab=='profile'?'active':'' ?>"><span>✏️</span> بيانات الاستوديو</a></li>
            <li><a href="?tab=sessions" class="<?= ($tab=='sessions')?'active':'' ?>">
                <span>📅</span> الجلسات
                <?php if ($stats['sessions_pending']): ?><b class="mc-badge"><?= $stats['sessions_pending'] ?></b><?php endif; ?>
            </a></li>
            <li><a href="?tab=packages" class="<?= $tab=='packages'?'active':'' ?>"><span>📦</span> الباقات (<?= $stats['packages_count'] ?>)</a></li>
            <li><a href="?tab=portfolio" class="<?= $tab=='portfolio'?'active':'' ?>"><span>🖼️</span> معرض الأعمال (<?= $stats['gallery_count'] ?>)</a></li>
            <li class="mc-divider"></li>
            <li><a href="chat.php"><span>💬</span> المحادثات</a></li>
            <li><a href="photoshoots.php?photographer=<?= $studio_id ?>" target="_blank"><span>👁️</span> معاينة عامة</a></li>
            <li><a href="account.php"><span>👤</span> حسابي</a></li>
        </ul>
    </aside>

    <main class="mc-main">
        <?php if (isset($_GET['updated'])): ?><div class="mc-alert success">✅ تم تحديث البيانات</div><?php endif; ?>
        <?php if (isset($_GET['saved'])): ?><div class="mc-alert success">✅ تم الحفظ</div><?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?><div class="mc-alert success">🗑️ تم الحذف</div><?php endif; ?>
        <?php if (isset($_GET['session_updated'])): ?><div class="mc-alert success">✅ تم تحديث الجلسة</div><?php endif; ?>
        <?php if (isset($_GET['session_saved'])): ?><div class="mc-alert success">✅ تم حفظ ملاحظات الجلسة</div><?php endif; ?>
        <?php if (isset($_GET['uploaded'])): ?><div class="mc-alert success">✅ تم رفع <?= (int)$_GET['uploaded'] ?> ملف</div><?php endif; ?>
        <?php if (!empty($errors)): ?><div class="mc-alert error"><strong>⚠️</strong> <?= implode('، ', $errors) ?></div><?php endif; ?>

        <?php if (isset($_GET['img_updated'])): ?><div class="mc-alert success">✅ تم تحديث صورة الباقة</div><?php endif; ?>

        <?php if ($tab === 'overview'): ?>
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">لوحة الاستوديو / نظرة عامة</div>
                    <h1>📸 لوحة <?= sanitize($studio['studio_name']) ?></h1>
                    <p>إدارة الباقات، الجلسات، معرض أعمالك وتسليم الصور للعملاء.</p>
                </div>
            </div>

            <div class="mc-stats-grid">
                <div class="mc-stat" style="--c:#9b59b6;"><div class="mc-stat-ic">📅</div><div><b><?= $stats['sessions_total'] ?></b><small>إجمالي الجلسات</small></div></div>
                <div class="mc-stat" style="--c:#f39c12;"><div class="mc-stat-ic">⏳</div><div><b><?= $stats['sessions_pending'] ?></b><small>بانتظار الرد</small></div></div>
                <div class="mc-stat" style="--c:#2ecc71;"><div class="mc-stat-ic">🗓️</div><div><b><?= $stats['sessions_upcoming'] ?></b><small>جلسات قادمة</small></div></div>
                <div class="mc-stat" style="--c:#3498db;"><div class="mc-stat-ic">📦</div><div><b><?= $stats['packages_count'] ?></b><small>باقة</small></div></div>
                <div class="mc-stat" style="--c:#c9a227;"><div class="mc-stat-ic">🖼️</div><div><b><?= $stats['gallery_count'] ?></b><small>عمل في المعرض</small></div></div>
                <div class="mc-stat" style="--c:#1abc9c;"><div class="mc-stat-ic">📸</div><div><b><?= $stats['deliveries_count'] ?></b><small>صورة مُسلَّمة</small></div></div>
            </div>

            <?php if (!empty($upcoming_sessions)): ?>
            <div class="mc-panel">
                <div class="mc-panel-head"><h3>📅 جلسات قادمة</h3><a href="?tab=sessions" class="mc-link">الكل ←</a></div>
                <?php foreach ($upcoming_sessions as $s): ?>
                <div class="mc-mini-row">
                    <div><strong><?= sanitize($s['client_name']) ?> — 📷 <?= sanitize($s['pkg_title']) ?></strong><small><?= $s['horse_name'] ? '🐴 '.sanitize($s['horse_name']) : '' ?></small></div>
                    <div><?= date('d/m', strtotime($s['session_date'])) ?> — <?= substr($s['session_time'], 0, 5) ?></div>
                    <div class="mc-mini-actions">
                        <?php if ($s['status'] === 'pending'): ?>
                            <a href="?session_action=confirm&bid=<?= $s['id'] ?>&tab=overview" class="mc-btn sm ok">تأكيد</a>
                            <a href="?session_action=reject&bid=<?= $s['id'] ?>&tab=overview" class="mc-btn sm no" onclick="return confirm('رفض؟')">رفض</a>
                        <?php else: ?>
                            <a href="?tab=sessions&manage_session=<?= $s['id'] ?>" class="mc-btn sm primary">إدارة</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (empty($packages)): ?>
            <div class="mc-panel warn">
                <h3>⚠️ لم تضف باقات بعد</h3>
                <p>العملاء ما يقدروا يحجزوا جلسة تصوير بدون باقات. أضف باقة واحدة على الأقل.</p>
                <a href="?tab=packages" class="mc-btn primary">➕ أضف باقة</a>
            </div>
            <?php endif; ?>

            <?php if (empty($gallery)): ?>
            <div class="mc-panel warn" style="background: linear-gradient(135deg, var(--dark) 0%, rgba(155,89,182,0.1) 100%); border-color: rgba(155,89,182,0.35);">
                <h3 style="color:#b87adf;">🖼️ معرض أعمالك فارغ</h3>
                <p>ارفع أفضل صورك — العملاء يشاهدون معرضك قبل الحجز.</p>
                <a href="?tab=portfolio" class="mc-btn primary">➕ ارفع أول صورة</a>
            </div>
            <?php endif; ?>

        <?php elseif ($tab === 'profile'): ?>
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">لوحة الاستوديو / البيانات</div>
                    <h1>✏️ تعديل بيانات الاستوديو</h1>
                    <p>الزوار يشوفون هذه المعلومات في صفحتك العامة.</p>
                </div>
            </div>

            <div class="mc-panel">
                <form method="POST" enctype="multipart/form-data" class="mc-form">
                    <div class="mc-form-grid">
                        <div class="form-group full">
                            <label>اسم الاستوديو / المصوّر</label>
                            <input type="text" name="studio_name" class="form-control" required value="<?= sanitize($studio['studio_name']) ?>">
                        </div>
                        <div class="form-group full">
                            <label>الوصف</label>
                            <textarea name="description" class="form-control" rows="4"><?= sanitize($studio['description']) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>المدينة</label>
                            <select name="city" class="form-control">
                                <?php foreach ($palestinian_cities as $ct): ?>
                                    <option value="<?= $ct ?>" <?= $studio['city']==$ct?'selected':'' ?>><?= $ct ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>العنوان</label>
                            <input type="text" name="address" class="form-control" value="<?= sanitize($studio['address']) ?>">
                        </div>
                        <div class="form-group">
                            <label>الهاتف</label>
                            <input type="tel" name="phone" class="form-control" value="<?= sanitize($studio['phone']) ?>" dir="ltr">
                        </div>
                        <div class="form-group">
                            <label>البريد الإلكتروني</label>
                            <input type="email" name="email" class="form-control" value="<?= sanitize($studio['email']) ?>">
                        </div>
                        <div class="form-group">
                            <label>📷 Instagram</label>
                            <input type="text" name="instagram" class="form-control" value="<?= sanitize($studio['instagram']) ?>" placeholder="@khyol_photos">
                        </div>
                        <div class="form-group">
                            <label>🌐 موقع/portfolio خارجي</label>
                            <input type="url" name="portfolio_url" class="form-control" value="<?= sanitize($studio['portfolio_url']) ?>" placeholder="https://..." dir="ltr">
                        </div>
                        <div class="form-group">
                            <label>سنوات الخبرة</label>
                            <input type="number" name="years_experience" class="form-control" min="0" value="<?= (int)$studio['years_experience'] ?>">
                        </div>
                        <div class="form-group">
                            <label>الصورة الشخصية</label>
                            <?php if ($studio['image']): ?>
                                <img src="<?= sanitize($studio['image']) ?>" alt="" style="width:100%; max-width:180px; border-radius:10px; margin-bottom:8px;" onerror="this.style.display='none'">
                            <?php endif; ?>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>
                        <div class="form-group full">
                            <label>صورة الغلاف</label>
                            <?php if ($studio['cover_image']): ?>
                                <img src="<?= sanitize($studio['cover_image']) ?>" alt="" style="width:100%; max-height:140px; object-fit:cover; border-radius:10px; margin-bottom:8px;" onerror="this.style.display='none'">
                            <?php endif; ?>
                            <input type="file" name="cover_image" class="form-control" accept="image/*">
                        </div>
                    </div>
                    <button type="submit" name="update_profile" class="mc-btn primary lg">💾 حفظ التغييرات</button>
                </form>
            </div>

        <?php elseif ($tab === 'sessions'): ?>
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">لوحة الاستوديو / الجلسات</div>
                    <h1>📅 إدارة جلسات التصوير</h1>
                    <p><?= count($sessions) ?> جلسة <?= $sess_status!='all'?'(مفلتر)':'' ?></p>
                </div>
            </div>

            <?php if ($active_session): ?>
            <!-- عرض تفاصيل جلسة محددة -->
            <div class="mc-panel" style="background: linear-gradient(135deg, var(--dark) 0%, rgba(155,89,182,0.1) 100%); border-color: rgba(155,89,182,0.4);">
                <div class="mc-panel-head">
                    <h3>🎬 جلسة: <?= sanitize($active_session['pkg_title']) ?></h3>
                    <a href="?tab=sessions" class="mc-link">← العودة للقائمة</a>
                </div>
                <div class="mc-form-grid">
                    <div class="mc-info"><label>العميل</label><div><?= sanitize($active_session['client_name']) ?></div></div>
                    <div class="mc-info"><label>هاتف</label><div dir="ltr"><?= sanitize($active_session['client_phone']) ?></div></div>
                    <div class="mc-info"><label>التاريخ</label><div><?= date('d/m/Y', strtotime($active_session['session_date'])) ?> — <?= substr($active_session['session_time'],0,5) ?></div></div>
                    <div class="mc-info"><label>الفرس</label><div>🐴 <?= sanitize($active_session['horse_name'] ?: '—') ?></div></div>
                    <?php if ($active_session['outfit_choices']): ?>
                    <div class="mc-info"><label>الملابس المختارة</label><div>🎽 <?= sanitize($active_session['outfit_choices']) ?></div></div>
                    <?php endif; ?>
                    <?php if ($active_session['notes']): ?>
                    <div class="mc-info"><label>ملاحظات العميل</label><div><?= sanitize($active_session['notes']) ?></div></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ملاحظات الجلسة + رابط تسليم خارجي -->
            <div class="mc-panel">
                <div class="mc-panel-head"><h3>📝 ملاحظات الجلسة ورابط التسليم</h3></div>
                <form method="POST" class="mc-form">
                    <input type="hidden" name="booking_id" value="<?= $active_session['id'] ?>">
                    <div class="form-group">
                        <label>ملاحظات الجلسة</label>
                        <textarea name="session_notes" class="form-control" rows="3" placeholder="ملاحظات خاصة، تحديات، تفاصيل المعالجة..."><?= sanitize($active_session['session_notes'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>رابط التسليم الخارجي (Google Drive / WeTransfer)</label>
                        <input type="url" name="delivery_link" class="form-control" value="<?= sanitize($active_session['delivery_link'] ?? '') ?>" placeholder="https://drive.google.com/..." dir="ltr">
                    </div>
                    <button type="submit" name="save_session_notes" class="mc-btn primary">💾 حفظ</button>
                </form>
            </div>

            <!-- رفع صور التسليم -->
            <div class="mc-panel">
                <div class="mc-panel-head"><h3>📸 تسليم الصور للعميل (<?= count($session_deliveries) ?>)</h3></div>
                <form method="POST" enctype="multipart/form-data" class="mc-form" style="margin-bottom: 18px;">
                    <input type="hidden" name="booking_id" value="<?= $active_session['id'] ?>">
                    <div class="mc-form-grid">
                        <div class="form-group full">
                            <label>اختر صور/فيديوهات</label>
                            <input type="file" name="delivery_files[]" class="form-control" accept="image/*,video/mp4,video/quicktime" multiple required>
                        </div>
                        <div class="form-group full">
                            <label>تعليق (اختياري، يُطبّق على الكل)</label>
                            <input type="text" name="caption" class="form-control">
                        </div>
                    </div>
                    <button type="submit" name="upload_delivery" class="mc-btn primary">⬆️ رفع</button>
                </form>

                <?php if (!empty($session_deliveries)): ?>
                <div class="mc-gallery-grid">
                    <?php foreach ($session_deliveries as $d): ?>
                    <div class="mc-gallery-item">
                        <?php if ($d['is_video']): ?>
                            <video src="<?= sanitize($d['file_path']) ?>" controls></video>
                        <?php else: ?>
                            <img src="<?= sanitize($d['file_path']) ?>" alt="">
                        <?php endif; ?>
                        <?php if ($d['caption']): ?><small><?= sanitize($d['caption']) ?></small><?php endif; ?>
                        <a href="?delete_delivery=<?= $d['id'] ?>&tab=sessions&manage_session=<?= $active_session['id'] ?>" class="mc-del-btn" onclick="return confirm('حذف؟')">🗑️</a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($active_session['status'] === 'confirmed'): ?>
                <div style="margin-top: 16px;">
                    <a href="?session_action=complete&bid=<?= $active_session['id'] ?>&tab=sessions&manage_session=<?= $active_session['id'] ?>" class="mc-btn primary">✅ إنهاء الجلسة</a>
                </div>
                <?php endif; ?>
            </div>

            <?php else: ?>
            <!-- قائمة كل الجلسات -->
            <div class="mc-filters">
                <a href="?tab=sessions&ss_status=all" class="mc-chip <?= $sess_status=='all'?'active':'' ?>">الكل</a>
                <a href="?tab=sessions&ss_status=pending" class="mc-chip <?= $sess_status=='pending'?'active':'' ?>">⏳ قيد المراجعة</a>
                <a href="?tab=sessions&ss_status=confirmed" class="mc-chip <?= $sess_status=='confirmed'?'active':'' ?>">✅ مؤكدة</a>
                <a href="?tab=sessions&ss_status=completed" class="mc-chip <?= $sess_status=='completed'?'active':'' ?>">🏁 مكتملة</a>
                <a href="?tab=sessions&ss_status=cancelled" class="mc-chip <?= $sess_status=='cancelled'?'active':'' ?>">❌ ملغية</a>
            </div>

            <?php if (empty($sessions)): ?>
                <div class="mc-panel"><div class="mc-empty">📅<p>لا جلسات في هذه الفئة.</p></div></div>
            <?php else: ?>
            <div class="mc-table-wrap">
                <table class="mc-table">
                    <thead>
                        <tr><th>التاريخ</th><th>الوقت</th><th>العميل</th><th>الباقة</th><th>الفرس</th><th>التسليم</th><th>الحالة</th><th>إجراءات</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sessions as $s): $st = $status_labels[$s['status']] ?? ['—','#999']; ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($s['session_date'])) ?></td>
                            <td><?= substr($s['session_time'], 0, 5) ?></td>
                            <td><strong><?= sanitize($s['client_name']) ?></strong><small dir="ltr"><?= sanitize($s['client_phone']) ?></small></td>
                            <td>📷 <?= sanitize($s['pkg_title']) ?><small><?= number_format($s['pkg_price'],0) ?> ₪</small></td>
                            <td>🐴 <?= sanitize($s['horse_name'] ?: '—') ?></td>
                            <td><?= $s['delivered_count'] > 0 ? '<strong style="color:#2ecc71;">'.(int)$s['delivered_count'].' ملف</strong>' : '—' ?></td>
                            <td><span class="mc-pill" style="--c:<?= $st[1] ?>;"><?= $st[0] ?></span></td>
                            <td class="mc-row-actions">
                                <?php if ($s['status'] === 'pending'): ?>
                                    <a href="?session_action=confirm&bid=<?= $s['id'] ?>&tab=sessions" class="mc-btn sm ok">قبول</a>
                                    <a href="?session_action=reject&bid=<?= $s['id'] ?>&tab=sessions" class="mc-btn sm no" onclick="return confirm('رفض؟')">رفض</a>
                                <?php else: ?>
                                    <a href="?tab=sessions&manage_session=<?= $s['id'] ?>" class="mc-btn sm primary">📸 إدارة</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <?php endif; ?>

        <?php elseif ($tab === 'packages'): ?>
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">لوحة الاستوديو / الباقات</div>
                    <h1>📦 إدارة الباقات</h1>
                    <p>الباقات اللي تقدمها لعملائك مع الأسعار والمدد.</p>
                </div>
            </div>

            <div class="mc-panel">
                <div class="mc-panel-head">
                    <h3><?= $edit_package ? '✏️ تعديل باقة' : '➕ باقة جديدة' ?></h3>
                    <?php if ($edit_package): ?><a href="?tab=packages" class="mc-link">إلغاء</a><?php endif; ?>
                </div>
                <?php if (!$edit_package): ?>
                <div style="padding: 12px; background: rgba(201,162,39,0.08); border: 1px solid rgba(201,162,39,0.2); border-radius: 8px; margin-bottom: 16px; color: rgba(245,237,224,0.85); font-size: 13px;">
                    <strong style="color: #e5bf3d;">💡 نصيحة:</strong> كل باقة تحتاج صورة فريدة تعكس طبيعتها. صورة عالية الجودة (1200 × 800 بكسل) ستعطي انطباع احترافي وتجذب العملاء أكثر.
                </div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" class="mc-form">
                    <input type="hidden" name="package_id" value="<?= $edit_package['id'] ?? '' ?>">
                    <div class="mc-form-grid">
                        <div class="form-group full">
                            <label>عنوان الباقة</label>
                            <input type="text" name="title" class="form-control" required value="<?= sanitize($edit_package['title'] ?? '') ?>" placeholder="مثلاً: باقة الفارس الأنيق">
                        </div>
                        <div class="form-group"><label>السعر (₪)</label><input type="number" name="price" class="form-control" required step="10" min="0" value="<?= $edit_package['price'] ?? 350 ?>"></div>
                        <div class="form-group"><label>المدة (دقيقة)</label><input type="number" name="duration_minutes" class="form-control" min="15" value="<?= $edit_package['duration_minutes'] ?? 60 ?>"></div>
                        <div class="form-group"><label>عدد الصور</label><input type="number" name="photos_count" class="form-control" min="1" value="<?= $edit_package['photos_count'] ?? 30 ?>"></div>
                        <div class="form-group"><label>ملابس مجانية (مفصولة بفواصل)</label><input type="text" name="free_outfits" class="form-control" value="<?= sanitize($edit_package['free_outfits'] ?? '') ?>" placeholder="جاكيت، بوت، خوذة"></div>
                        <div class="form-group full"><label>الوصف</label><textarea name="description" class="form-control" rows="3"><?= sanitize($edit_package['description'] ?? '') ?></textarea></div>
                        <div class="form-group full">
                            <label>صورة الباقة (مهمة لجذب العملاء)</label>
                            <div class="pkg-image-preview" style="margin-bottom: 12px;">
                                <?php if (!empty($edit_package['image'])): ?>
                                    <img id="pkgImagePreview" src="<?= sanitize($edit_package['image']) ?>" alt="صورة الباقة" style="width:100%; max-width:300px; height:200px; object-fit:cover; border-radius:10px; border: 2px solid rgba(201,162,39,0.3);" onerror="this.style.display='none'">
                                <?php else: ?>
                                    <div id="pkgImagePlaceholder" style="width:100%; max-width:300px; height:200px; background: rgba(201,162,39,0.1); border: 2px dashed rgba(201,162,39,0.3); border-radius:10px; display:flex; align-items:center; justify-content:center; color:rgba(201,162,39,0.5); font-size:14px;">
                                        📸 لم يتم اختيار صورة بعد
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="pkg-image-info" style="font-size:12px; color:rgba(201,162,39,0.7); margin-bottom:8px;">
                                💡 أنواع معتمدة: JPG, PNG, WebP — الحد الأقصى: 5 MB — التصميم الموصى به: 1200 × 800 بكسل
                            </div>
                            <input type="file" id="pkgImageInput" name="image" class="form-control" accept="image/*" onchange="previewPackageImage(event)">
                        </div>
                    </div>
                    <button type="submit" name="save_package" class="mc-btn primary"><?= $edit_package?'💾 تحديث':'➕ إضافة' ?></button>
                </form>
            </div>

            <div class="mc-panel">
                <div class="mc-panel-head"><h3>الباقات (<?= count($packages) ?>)</h3></div>
                <?php if (empty($packages)): ?>
                    <div class="mc-empty">📦<p>لا باقات بعد.</p></div>
                <?php else: ?>
                <div class="mc-svc-grid">
                    <?php foreach ($packages as $p): ?>
                    <div class="mc-svc-card" style="<?= !$p['is_active']?'opacity:0.55;':'' ?>" data-pkg-id="<?= $p['id'] ?>">
                        <div style="position:relative; width:100%; height:140px; border-radius:10px; margin-bottom:10px; overflow:hidden; background:linear-gradient(135deg, rgba(201,162,39,0.1), rgba(201,162,39,0.05));">
                            <img src="<?= sanitize($p['image']) ?>" alt="<?= sanitize($p['title']) ?>" class="pkg-admin-img" style="width:100%; height:100%; object-fit:cover;" data-pkg-id="<?= $p['id'] ?>">
                            <?php if (strpos($p['image'], 'photoshoots/') === false || strpos($p['image'], 'default') !== false): ?>
                            <div style="position:absolute; top:8px; right:8px; background:#ff9800; color:#fff; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:700;">📸 لا توجد صورة</div>
                            <?php endif; ?>
                        </div>
                        <h4><?= sanitize($p['title']) ?></h4>
                        <?php if ($p['description']): ?><p><?= sanitize(mb_substr($p['description'], 0, 80)) ?></p><?php endif; ?>
                        <div class="mc-svc-meta">
                            <span>⏱️ <?= (int)$p['duration_minutes'] ?>د • 📷 <?= (int)$p['photos_count'] ?></span>
                            <strong><?= number_format($p['price'], 0) ?> ₪</strong>
                        </div>
                        <div class="mc-svc-actions">
                            <a href="?tab=packages&edit_package=<?= $p['id'] ?>" class="mc-btn sm">✏️ تعديل</a>
                            <button type="button" class="mc-btn sm" onclick="openQuickImageUpload(<?= $p['id'] ?>, '<?= sanitize($p['title']) ?>')">🖼️ صورة</button>
                            <a href="?toggle_package=<?= $p['id'] ?>" class="mc-btn sm"><?= $p['is_active']?'⏸️':'▶️' ?></a>
                            <a href="?delete_package=<?= $p['id'] ?>" class="mc-btn sm no" onclick="return confirm('حذف الباقة؟')">🗑️</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'portfolio'): ?>
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">لوحة الاستوديو / معرض الأعمال</div>
                    <h1>🖼️ معرض أعمالك</h1>
                    <p>ارفع أفضل صورك — الزوار يشاهدونها في صفحتك العامة.</p>
                </div>
            </div>

            <div class="mc-panel">
                <div class="mc-panel-head"><h3>➕ رفع صور جديدة</h3></div>
                <form method="POST" enctype="multipart/form-data" class="mc-form">
                    <div class="mc-form-grid">
                        <div class="form-group"><label>عنوان المجموعة (اختياري)</label><input type="text" name="gallery_title" class="form-control" placeholder="جلسة الربيع 2026"></div>
                        <div class="form-group"><label>وصف (اختياري)</label><input type="text" name="gallery_caption" class="form-control"></div>
                        <div class="form-group full"><label>اختر الصور (يمكن اختيار عدة)</label><input type="file" name="gallery_files[]" class="form-control" accept="image/*" multiple required></div>
                    </div>
                    <button type="submit" name="upload_gallery" class="mc-btn primary">⬆️ رفع</button>
                </form>
            </div>

            <div class="mc-panel">
                <div class="mc-panel-head"><h3>الصور (<?= count($gallery) ?>)</h3></div>
                <?php if (empty($gallery)): ?>
                    <div class="mc-empty">🖼️<p>لا صور بعد.</p></div>
                <?php else: ?>
                <div class="mc-gallery-grid">
                    <?php foreach ($gallery as $g): ?>
                    <div class="mc-gallery-item">
                        <img src="<?= sanitize($g['image']) ?>" alt="" onerror="this.src='assets/images/hero.jpg'">
                        <?php if ($g['title'] || $g['caption']): ?>
                        <small><?= sanitize($g['title'] ?: $g['caption']) ?></small>
                        <?php endif; ?>
                        <a href="?delete_gallery=<?= $g['id'] ?>&tab=portfolio" class="mc-del-btn" onclick="return confirm('حذف الصورة؟')">🗑️</a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </main>
</div>

<!-- Quick Image Upload Modal -->
<div id="quickImageModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#0f0b08; border:1px solid rgba(201,162,39,0.3); border-radius:12px; padding:24px; max-width:400px; width:90%; color:#f0e5d0;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="color:#e5bf3d; margin:0;">🖼️ تحديث الصورة</h3>
            <button type="button" onclick="closeQuickImageUpload()" style="background:none; border:none; color:#e5bf3d; font-size:24px; cursor:pointer;">×</button>
        </div>
        <form method="POST" enctype="multipart/form-data" onsubmit="submitQuickImage(event)">
            <input type="hidden" name="update_pkg_image" value="1">
            <input type="hidden" name="package_id" id="quickImgPkgId" value="">
            <p id="quickImgPkgName" style="font-size:14px; color:rgba(201,162,39,0.8); margin:0 0 16px;">—</p>
            <div style="margin-bottom:16px;">
                <div id="quickImgPreview" style="width:100%; height:180px; background:rgba(201,162,39,0.1); border:2px dashed rgba(201,162,39,0.3); border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px; font-size:48px; color:rgba(201,162,39,0.4);">📸</div>
                <input type="file" name="quick_image" id="quickImageInput" accept="image/*" required onchange="previewQuickImage(event)" style="width:100%; padding:8px; background:rgba(201,162,39,0.05); border:1px solid rgba(201,162,39,0.2); border-radius:6px; color:#f0e5d0;">
                <small style="display:block; margin-top:8px; color:rgba(201,162,39,0.7);">JPG, PNG, WebP — الحد الأقصى: 5 MB — الحجم الموصى به: 1200 × 800 بكسل</small>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" style="flex:1; padding:10px; background:linear-gradient(135deg, #e5bf3d 0%, #c9a227 100%); color:#0d0a05; border:none; border-radius:6px; font-weight:700; cursor:pointer;">💾 حفظ</button>
                <button type="button" onclick="closeQuickImageUpload()" style="flex:1; padding:10px; background:rgba(201,162,39,0.1); color:#f0e5d0; border:1px solid rgba(201,162,39,0.2); border-radius:6px; cursor:pointer;">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<script>
function openQuickImageUpload(pkgId, pkgName) {
    document.getElementById('quickImgPkgId').value = pkgId;
    document.getElementById('quickImgPkgName').textContent = '📦 ' + pkgName;
    document.getElementById('quickImageModal').style.display = 'flex';
    document.getElementById('quickImageInput').value = '';
    document.getElementById('quickImgPreview').textContent = '📸';
}

function closeQuickImageUpload() {
    document.getElementById('quickImageModal').style.display = 'none';
}

function previewQuickImage(event) {
    const file = event.target.files[0];
    if (!file) return;

    const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!validTypes.includes(file.type)) {
        alert('⚠️ نوع الملف غير مدعوم. استخدم JPG أو PNG أو WebP');
        event.target.value = '';
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        alert('⚠️ حجم الملف كبير جداً. الحد الأقصى: 5 MB');
        event.target.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById('quickImgPreview');
        preview.style.backgroundImage = 'url(' + e.target.result + ')';
        preview.style.backgroundSize = 'cover';
        preview.style.backgroundPosition = 'center';
        preview.textContent = '';
    };
    reader.readAsDataURL(file);
}

function submitQuickImage(event) {
    const form = event.target;
    const pkgId = document.getElementById('quickImgPkgId').value;
    if (!pkgId || !document.getElementById('quickImageInput').files[0]) {
        alert('⚠️ اختر صورة أولاً');
        return;
    }
    form.submit();
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('quickImageModal');
    if (event.target === modal) {
        closeQuickImageUpload();
    }
});

function previewPackageImage(event) {
    const file = event.target.files[0];
    if (!file) return;

    // Validate file type
    const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!validTypes.includes(file.type)) {
        alert('⚠️ نوع الملف غير مدعوم. استخدم JPG أو PNG أو WebP');
        event.target.value = '';
        return;
    }

    // Validate file size (5 MB max)
    if (file.size > 5 * 1024 * 1024) {
        alert('⚠️ حجم الملف كبير جداً. الحد الأقصى: 5 MB');
        event.target.value = '';
        return;
    }

    // Show preview
    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById('pkgImagePreview');
        const placeholder = document.getElementById('pkgImagePlaceholder');

        if (preview) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        } else if (placeholder) {
            const img = document.createElement('img');
            img.id = 'pkgImagePreview';
            img.src = e.target.result;
            img.style.width = '100%';
            img.style.maxWidth = '300px';
            img.style.height = '200px';
            img.style.objectFit = 'cover';
            img.style.borderRadius = '10px';
            img.style.border = '2px solid rgba(201,162,39,0.3)';
            placeholder.parentNode.replaceChild(img, placeholder);
        }
    };
    reader.readAsDataURL(file);
}

// Handle package form tab switching & admin package image errors
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[name*="package"]');
    if (form && document.getElementById('pkgImageInput')) {
        // Initialize preview on page load for edit mode
        const preview = document.getElementById('pkgImagePreview');
        if (preview && preview.src && !preview.src.includes('data:')) {
            // Preview already loaded from database
        }
    }

    // Handle missing images in admin package list
    const adminPackageImages = document.querySelectorAll('.pkg-admin-img');
    adminPackageImages.forEach((img) => {
        img.addEventListener('error', function() {
            const pkgId = this.getAttribute('data-pkg-id');
            const pkgCard = this.closest('[data-pkg-id]');
            const pkgTitle = pkgCard ? pkgCard.querySelector('h4')?.textContent : 'باقة';

            // Create a gradient placeholder
            const colors = [
                'linear-gradient(135deg, #c9a227 0%, #a88419 100%)',
                'linear-gradient(135deg, #e5bf3d 0%, #c9a227 100%)',
                'linear-gradient(135deg, #f0cc50 0%, #d9b835 100%)',
                'linear-gradient(135deg, #d4ab2c 0%, #b8941f 100%)'
            ];
            const bgColor = colors[parseInt(pkgId) % colors.length];

            this.style.background = bgColor;
            this.style.display = 'flex';
            this.style.alignItems = 'center';
            this.style.justifyContent = 'center';
            this.style.fontSize = '36px';
            this.style.color = '#1a1510';
            this.textContent = '📸';
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
