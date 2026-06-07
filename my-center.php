<?php
$page_title = 'لوحة المركز';
require_once 'config/db.php';

if (!isLoggedIn()) redirect('login.php?redirect=my-center');

$user_id = (int)$_SESSION['user_id'];

// جلب المركز الخاص بالمستخدم
$c_stmt = $conn->prepare("SELECT * FROM centers WHERE owner_id = ? LIMIT 1");
$c_stmt->execute([$user_id]);
$center = $c_stmt->fetch();

if (!$center) {
    $_SESSION['flash_error'] = 'ليس لديك مركز مسجّل. سجّل مركزك أولاً.';
    redirect('register.php?type=center');
}

$center_id = (int)$center['id'];
$tab = $_GET['tab'] ?? 'overview';
$errors = [];
$success = '';

// ========== POST ACTIONS ==========

// تحديث بيانات المركز
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $opening_hours = sanitize($_POST['opening_hours'] ?? '');

    if (strlen($name) < 4) $errors[] = 'اسم المركز قصير جداً.';
    if (!$phone) $errors[] = 'رقم الهاتف مطلوب.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'البريد غير صحيح.';

    $image_path = $center['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['image']['size'] < 5 * 1024 * 1024) {
            $upload_dir = __DIR__ . '/assets/images/centers/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $fname = 'c_' . $center_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $fname)) {
                $image_path = 'assets/images/centers/' . $fname;
            }
        } else {
            $errors[] = 'صورة غير صحيحة (jpg/png/webp بحجم < 5MB).';
        }
    }

    $cover_path = $center['cover_image'];
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['cover_image']['size'] < 8 * 1024 * 1024) {
            $upload_dir = __DIR__ . '/assets/images/centers/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $fname = 'cov_' . $center_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_dir . $fname)) {
                $cover_path = 'assets/images/centers/' . $fname;
            }
        }
    }

    if (empty($errors)) {
        $upd = $conn->prepare("
            UPDATE centers
            SET name = ?, description = ?, city = ?, address = ?, phone = ?, email = ?, opening_hours = ?, image = ?, cover_image = ?
            WHERE id = ? AND owner_id = ?
        ");
        $upd->execute([$name, $description, $city, $address, $phone, $email, $opening_hours, $image_path, $cover_path, $center_id, $user_id]);
        redirect('my-center.php?tab=profile&updated=1');
    }
}

// تغيير حالة حجز
if (isset($_GET['booking_action'], $_GET['bid'])) {
    $bid = (int)$_GET['bid'];
    $action = $_GET['booking_action'];
    $new_status = ['confirm'=>'confirmed','reject'=>'cancelled','complete'=>'completed'][$action] ?? null;
    if ($new_status) {
        $upd = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ? AND center_id = ?");
        $upd->execute([$new_status, $bid, $center_id]);

        // إشعار للمستخدم عند القبول أو الرفض فقط
        if (in_array($new_status, ['confirmed', 'cancelled'])) {
            $bk_info = $conn->prepare("SELECT user_id, booking_date FROM bookings WHERE id = ?");
            $bk_info->execute([$bid]);
            $bk = $bk_info->fetch();
            if ($bk) {
                $notif_data = [
                    'confirmed' => [
                        'title' => '✅ تم قبول حجزك',
                        'body'  => 'تم تأكيد حجزك في ' . date('d/m/Y', strtotime($bk['booking_date'])) . ' — نراك قريباً!',
                        'icon'  => '✅',
                        'type'  => 'booking_confirmed',
                    ],
                    'cancelled' => [
                        'title' => '❌ تم رفض حجزك',
                        'body'  => 'للأسف، تم رفض حجزك بتاريخ ' . date('d/m/Y', strtotime($bk['booking_date'])) . '. يمكنك الحجز في موعد آخر.',
                        'icon'  => '❌',
                        'type'  => 'booking_rejected',
                    ],
                ];
                $n = $notif_data[$new_status];
                $ins = $conn->prepare("INSERT INTO notifications (user_id, title, body, icon, link, type) VALUES (?, ?, ?, ?, ?, ?)");
                $ins->execute([$bk['user_id'], $n['title'], $n['body'], $n['icon'], 'account.php?tab=bookings', $n['type']]);
            }
        }

        redirect('my-center.php?tab=bookings&booking_updated=1');
    }
}

// إضافة / تعديل / حذف خدمة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_service'])) {
    $sid = (int)($_POST['service_id'] ?? 0);
    $sname = sanitize($_POST['service_name'] ?? '');
    $sdesc = sanitize($_POST['service_description'] ?? '');
    $sprice = (float)($_POST['service_price'] ?? 0);
    $sduration = sanitize($_POST['service_duration'] ?? '60 دقيقة');
    $sicon = sanitize($_POST['service_icon'] ?? '🏇');

    if (strlen($sname) >= 3 && $sprice >= 0) {
        if ($sid) {
            $q = $conn->prepare("UPDATE services SET name=?, description=?, price=?, duration=?, icon=? WHERE id=? AND center_id=?");
            $q->execute([$sname, $sdesc, $sprice, $sduration, $sicon, $sid, $center_id]);
        } else {
            $q = $conn->prepare("INSERT INTO services (center_id, name, description, price, duration, icon) VALUES (?,?,?,?,?,?)");
            $q->execute([$center_id, $sname, $sdesc, $sprice, $sduration, $sicon]);
        }
        redirect('my-center.php?tab=services&saved=1');
    }
    $errors[] = 'بيانات الخدمة غير مكتملة.';
}

if (isset($_GET['delete_service'])) {
    $sid = (int)$_GET['delete_service'];
    $q = $conn->prepare("DELETE FROM services WHERE id=? AND center_id=?");
    $q->execute([$sid, $center_id]);
    redirect('my-center.php?tab=services&deleted=1');
}

// إضافة / تعديل / حذف فعالية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_event'])) {
    $eid = (int)($_POST['event_id'] ?? 0);
    $etitle = sanitize($_POST['event_title'] ?? '');
    $edesc = sanitize($_POST['event_description'] ?? '');
    $edate = $_POST['event_date'] ?? '';
    $etime = $_POST['event_time'] ?? null;
    $eloc = sanitize($_POST['event_location'] ?? $center['name']);
    $eprice = (float)($_POST['event_price'] ?? 0);
    $emax = (int)($_POST['event_max'] ?? 50);
    $etype = in_array($_POST['event_type'] ?? '', ['competition','event','workshop']) ? $_POST['event_type'] : 'event';

    $event_image = null;
    if ($eid) {
        $old = $conn->prepare("SELECT image FROM events WHERE id=? AND center_id=?");
        $old->execute([$eid, $center_id]);
        $event_image = $old->fetchColumn() ?: null;
    }
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['event_image']['size'] < 5 * 1024 * 1024) {
            $upload_dir = __DIR__ . '/assets/images/events/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $fname = 'e_' . $center_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['event_image']['tmp_name'], $upload_dir . $fname)) {
                $event_image = 'assets/images/events/' . $fname;
            }
        }
    }

    if (strlen($etitle) >= 4 && $edate) {
        if ($eid) {
            $q = $conn->prepare("UPDATE events SET title=?, description=?, image=?, event_date=?, event_time=?, location=?, price=?, max_participants=?, type=? WHERE id=? AND center_id=?");
            $q->execute([$etitle, $edesc, $event_image, $edate, $etime ?: null, $eloc, $eprice, $emax, $etype, $eid, $center_id]);
        } else {
            $q = $conn->prepare("INSERT INTO events (center_id, title, description, image, event_date, event_time, location, price, max_participants, type) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $q->execute([$center_id, $etitle, $edesc, $event_image, $edate, $etime ?: null, $eloc, $eprice, $emax, $etype]);
        }
        redirect('my-center.php?tab=events&saved=1');
    }
    $errors[] = 'بيانات الفعالية غير مكتملة (العنوان والتاريخ مطلوبان).';
}

if (isset($_GET['delete_event'])) {
    $eid = (int)$_GET['delete_event'];
    $q = $conn->prepare("DELETE FROM events WHERE id=? AND center_id=?");
    $q->execute([$eid, $center_id]);
    redirect('my-center.php?tab=events&deleted=1');
}

// تغيير حالة طلب إيواء
if (isset($_GET['boarding_action'], $_GET['ba_id'])) {
    $ba_id = (int)$_GET['ba_id'];
    $action = $_GET['boarding_action'];
    $new_status = ['approve' => 'active', 'reject' => 'rejected', 'end' => 'ended'][$action] ?? null;
    if ($new_status) {
        $upd = $conn->prepare("UPDATE boarding_agreements SET status = ? WHERE id = ? AND center_id = ?");
        $upd->execute([$new_status, $ba_id, $center_id]);

        // إشعار لصاحب الفرس
        $ba_info = $conn->prepare("SELECT ba.owner_id, h.name AS horse_name FROM boarding_agreements ba JOIN horses h ON h.id = ba.horse_id WHERE ba.id = ?");
        $ba_info->execute([$ba_id]);
        $ba = $ba_info->fetch();
        if ($ba) {
            $notif_map = [
                'active'   => ['✅ تم قبول طلب الإيواء', 'تم قبول إيواء فرسك "' . $ba['horse_name'] . '" في ' . $center['name'], '✅'],
                'rejected' => ['❌ تم رفض طلب الإيواء', 'للأسف تم رفض طلب إيواء فرسك "' . $ba['horse_name'] . '" في ' . $center['name'], '❌'],
                'ended'    => ['🏁 انتهت اتفاقية الإيواء', 'انتهت اتفاقية إيواء فرسك "' . $ba['horse_name'] . '" في ' . $center['name'], '🏁'],
            ];
            if (isset($notif_map[$new_status])) {
                [$title, $body, $icon] = $notif_map[$new_status];
                $ins = $conn->prepare("INSERT INTO notifications (user_id, title, body, icon, link, type) VALUES (?, ?, ?, ?, 'account.php?tab=boarding', 'boarding')");
                $ins->execute([$ba['owner_id'], $title, $body, $icon]);
            }
        }
        redirect('my-center.php?tab=boarding&boarding_updated=1');
    }
}

// ========== جلب البيانات للعرض ==========

// إحصائيات
$stats = [
    'bookings_total'    => (int)$conn->query("SELECT COUNT(*) FROM bookings WHERE center_id = $center_id")->fetchColumn(),
    'bookings_pending'  => (int)$conn->query("SELECT COUNT(*) FROM bookings WHERE center_id = $center_id AND status='pending'")->fetchColumn(),
    'bookings_today'    => (int)$conn->query("SELECT COUNT(*) FROM bookings WHERE center_id = $center_id AND booking_date = CURDATE() AND status IN ('pending','confirmed')")->fetchColumn(),
    'services_count'    => (int)$conn->query("SELECT COUNT(*) FROM services WHERE center_id = $center_id")->fetchColumn(),
    'events_count'      => (int)$conn->query("SELECT COUNT(*) FROM events WHERE center_id = $center_id AND event_date >= CURDATE()")->fetchColumn(),
    'rating'            => (float)$center['rating'],
    'reviews_count'     => (int)$center['reviews_count'],
    'boarding_pending'  => (int)$conn->query("SELECT COUNT(*) FROM boarding_agreements WHERE center_id = $center_id AND status='pending'")->fetchColumn(),
];

// حجوزات (كلها للمركز)
$bk_status = $_GET['bk_status'] ?? 'all';
$bk_where = "b.center_id = ?";
$bk_params = [$center_id];
if (in_array($bk_status, ['pending','confirmed','completed','cancelled'])) {
    $bk_where .= " AND b.status = ?";
    $bk_params[] = $bk_status;
}
$bk_sql = "
    SELECT b.*, u.full_name AS user_name, u.phone AS user_phone, s.name AS service_name, s.price AS service_price
    FROM bookings b
    JOIN users u ON u.id = b.user_id
    LEFT JOIN services s ON s.id = b.service_id
    WHERE $bk_where
    ORDER BY b.booking_date DESC, b.booking_time DESC
    LIMIT 200
";
$bk_stmt = $conn->prepare($bk_sql);
$bk_stmt->execute($bk_params);
$bookings = $bk_stmt->fetchAll();

// خدمات
$services = $conn->prepare("SELECT * FROM services WHERE center_id = ? ORDER BY id DESC");
$services->execute([$center_id]);
$services = $services->fetchAll();

// فعاليات
$events = $conn->prepare("SELECT * FROM events WHERE center_id = ? ORDER BY event_date DESC LIMIT 100");
$events->execute([$center_id]);
$events = $events->fetchAll();

// طلبات الإيواء
$ba_status = $_GET['ba_status'] ?? 'all';
$ba_where = "ba.center_id = ?";
$ba_params = [$center_id];
if (in_array($ba_status, ['pending','active','ended','rejected'])) {
    $ba_where .= " AND ba.status = ?";
    $ba_params[] = $ba_status;
}
$ba_stmt = $conn->prepare("
    SELECT ba.*, u.full_name AS owner_name, u.phone AS owner_phone, h.name AS horse_name
    FROM boarding_agreements ba
    JOIN users u ON u.id = ba.owner_id
    JOIN horses h ON h.id = ba.horse_id
    WHERE $ba_where
    ORDER BY ba.created_at DESC
    LIMIT 200
");
$ba_stmt->execute($ba_params);
$boardings = $ba_stmt->fetchAll();

// خدمة أو فعالية لتعديلها
$edit_service = null;
if (isset($_GET['edit_service'])) {
    $s = $conn->prepare("SELECT * FROM services WHERE id=? AND center_id=?");
    $s->execute([(int)$_GET['edit_service'], $center_id]);
    $edit_service = $s->fetch() ?: null;
}
$edit_event = null;
if (isset($_GET['edit_event'])) {
    $e = $conn->prepare("SELECT * FROM events WHERE id=? AND center_id=?");
    $e->execute([(int)$_GET['edit_event'], $center_id]);
    $edit_event = $e->fetch() ?: null;
}

$status_labels = [
    'pending'   => ['⏳ قيد المراجعة', '#f39c12'],
    'confirmed' => ['✅ مؤكد',         '#2ecc71'],
    'completed' => ['🏁 مكتمل',        '#3498db'],
    'cancelled' => ['❌ ملغي',         '#e74c3c'],
];

include 'includes/header.php';
?>

<div class="mc-wrap">
    <!-- السايدبار -->
    <aside class="mc-sidebar">
        <div class="mc-brand">
            <img src="<?= sanitize($center['image']) ?>" alt="" onerror="this.src='assets/images/hero.jpg'">
            <div>
                <strong><?= sanitize($center['name']) ?></strong>
                <small>📍 <?= sanitize($center['city']) ?></small>
            </div>
        </div>
        <ul class="mc-nav">
            <li><a href="?tab=overview" class="<?= $tab=='overview'?'active':'' ?>"><span>📊</span> نظرة عامة</a></li>
            <li><a href="?tab=profile" class="<?= $tab=='profile'?'active':'' ?>"><span>✏️</span> بيانات المركز</a></li>
            <li><a href="?tab=bookings" class="<?= $tab=='bookings'?'active':'' ?>">
                <span>📅</span> الحجوزات
                <?php if ($stats['bookings_pending']): ?><b class="mc-badge"><?= $stats['bookings_pending'] ?></b><?php endif; ?>
            </a></li>
            <li><a href="?tab=services" class="<?= $tab=='services'?'active':'' ?>"><span>🎯</span> الخدمات (<?= $stats['services_count'] ?>)</a></li>
            <li><a href="?tab=events" class="<?= $tab=='events'?'active':'' ?>"><span>🏆</span> الفعاليات (<?= $stats['events_count'] ?>)</a></li>
            <li><a href="?tab=boarding" class="<?= $tab=='boarding'?'active':'' ?>">
                <span>🏇</span> طلبات الإيواء
                <?php if ($stats['boarding_pending']): ?><b class="mc-badge"><?= $stats['boarding_pending'] ?></b><?php endif; ?>
            </a></li>
            <li class="mc-divider"></li>
            <li><a href="chat.php"><span>💬</span> المحادثات</a></li>
            <li><a href="center.php?id=<?= $center_id ?>" target="_blank"><span>👁️</span> معاينة عامة</a></li>
            <li><a href="account.php"><span>👤</span> حسابي</a></li>
        </ul>
    </aside>

    <!-- المحتوى -->
    <main class="mc-main">
        <?php if (isset($_GET['updated'])): ?><div class="mc-alert success">✅ تم تحديث البيانات</div><?php endif; ?>
        <?php if (isset($_GET['saved'])): ?><div class="mc-alert success">✅ تم الحفظ</div><?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?><div class="mc-alert success">🗑️ تم الحذف</div><?php endif; ?>
        <?php if (isset($_GET['booking_updated'])): ?><div class="mc-alert success">✅ تم تحديث حالة الحجز</div><?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="mc-alert error"><strong>⚠️ أخطاء:</strong><ul><?php foreach($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if ($tab === 'overview'): ?>
            <!-- ==================== نظرة عامة ==================== -->
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">لوحة المركز / نظرة عامة</div>
                    <h1>📊 مرحباً في لوحة <?= sanitize($center['name']) ?></h1>
                    <p>إدارة مركزك، الحجوزات، الخدمات والفعاليات من مكان واحد.</p>
                </div>
            </div>

            <div class="mc-stats-grid">
                <div class="mc-stat" style="--c:#c9a227;">
                    <div class="mc-stat-ic">📅</div>
                    <div><b><?= $stats['bookings_total'] ?></b><small>إجمالي الحجوزات</small></div>
                </div>
                <div class="mc-stat" style="--c:#f39c12;">
                    <div class="mc-stat-ic">⏳</div>
                    <div><b><?= $stats['bookings_pending'] ?></b><small>بانتظار الرد</small></div>
                </div>
                <div class="mc-stat" style="--c:#2ecc71;">
                    <div class="mc-stat-ic">🗓️</div>
                    <div><b><?= $stats['bookings_today'] ?></b><small>حجوزات اليوم</small></div>
                </div>
                <div class="mc-stat" style="--c:#9b59b6;">
                    <div class="mc-stat-ic">🎯</div>
                    <div><b><?= $stats['services_count'] ?></b><small>خدمة</small></div>
                </div>
                <div class="mc-stat" style="--c:#e74c3c;">
                    <div class="mc-stat-ic">🏆</div>
                    <div><b><?= $stats['events_count'] ?></b><small>فعالية قادمة</small></div>
                </div>
                <div class="mc-stat" style="--c:#e5bf3d;">
                    <div class="mc-stat-ic">⭐</div>
                    <div><b><?= number_format($stats['rating'], 1) ?></b><small><?= $stats['reviews_count'] ?> تقييم</small></div>
                </div>
            </div>

            <?php if ($stats['bookings_pending'] > 0): ?>
            <div class="mc-panel">
                <div class="mc-panel-head"><h3>⏳ حجوزات بانتظار ردّك</h3><a href="?tab=bookings&bk_status=pending" class="mc-link">الكل ←</a></div>
                <?php
                $recent = $conn->prepare("
                    SELECT b.*, u.full_name AS user_name, s.name AS service_name
                    FROM bookings b JOIN users u ON u.id = b.user_id
                    LEFT JOIN services s ON s.id = b.service_id
                    WHERE b.center_id = ? AND b.status = 'pending'
                    ORDER BY b.booking_date ASC, b.booking_time ASC LIMIT 5
                ");
                $recent->execute([$center_id]);
                foreach ($recent as $b): ?>
                <div class="mc-mini-row">
                    <div><strong><?= sanitize($b['user_name']) ?></strong><small><?= sanitize($b['service_name'] ?: '—') ?></small></div>
                    <div><?= date('d/m', strtotime($b['booking_date'])) ?> — <?= substr($b['booking_time'], 0, 5) ?></div>
                    <div class="mc-mini-actions">
                        <a href="?booking_action=confirm&bid=<?= $b['id'] ?>&tab=overview" class="mc-btn sm ok">قبول</a>
                        <a href="?booking_action=reject&bid=<?= $b['id'] ?>&tab=overview" class="mc-btn sm no" onclick="return confirm('رفض الحجز؟')">رفض</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (empty($services)): ?>
            <div class="mc-panel warn">
                <h3>⚠️ لم تضف خدمات بعد</h3>
                <p>الزبائن ما يقدروا يحجزوا بدون خدمات. أضف خدمة واحدة على الأقل (تدريب، إيواء، درس...).</p>
                <a href="?tab=services" class="mc-btn primary">➕ أضف خدمة الآن</a>
            </div>
            <?php endif; ?>

        <?php elseif ($tab === 'profile'): ?>
            <!-- ==================== تعديل البيانات ==================== -->
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">لوحة المركز / بيانات المركز</div>
                    <h1>✏️ تعديل بيانات المركز</h1>
                    <p>حدّث معلومات مركزك وصوره. الزوار سيشاهدون هذه المعلومات في صفحة المركز.</p>
                </div>
            </div>

            <div class="mc-panel">
                <form method="POST" enctype="multipart/form-data" class="mc-form">
                    <div class="mc-form-grid">
                        <div class="form-group full">
                            <label>اسم المركز</label>
                            <input type="text" name="name" class="form-control" required value="<?= sanitize($center['name']) ?>">
                        </div>
                        <div class="form-group full">
                            <label>الوصف</label>
                            <textarea name="description" class="form-control" rows="4"><?= sanitize($center['description']) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>المدينة</label>
                            <select name="city" class="form-control">
                                <?php foreach ($palestinian_cities as $ct): ?>
                                    <option value="<?= $ct ?>" <?= $center['city']==$ct?'selected':'' ?>><?= $ct ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>العنوان التفصيلي</label>
                            <input type="text" name="address" class="form-control" value="<?= sanitize($center['address']) ?>">
                        </div>
                        <div class="form-group">
                            <label>رقم الهاتف</label>
                            <input type="tel" name="phone" class="form-control" required value="<?= sanitize($center['phone']) ?>" dir="ltr">
                        </div>
                        <div class="form-group">
                            <label>البريد الإلكتروني</label>
                            <input type="email" name="email" class="form-control" value="<?= sanitize($center['email']) ?>">
                        </div>
                        <div class="form-group full">
                            <label>ساعات الدوام</label>
                            <input type="text" name="opening_hours" class="form-control" value="<?= sanitize($center['opening_hours']) ?>" placeholder="مثلاً: 8:00 ص - 8:00 م">
                        </div>
                        <div class="form-group">
                            <label>الصورة الرئيسية</label>
                            <?php if ($center['image']): ?>
                                <img src="<?= sanitize($center['image']) ?>" alt="" style="width:100%; max-width:200px; border-radius:10px; margin-bottom:8px;" onerror="this.style.display='none'">
                            <?php endif; ?>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label>صورة الغلاف</label>
                            <?php if ($center['cover_image']): ?>
                                <img src="<?= sanitize($center['cover_image']) ?>" alt="" style="width:100%; max-width:200px; border-radius:10px; margin-bottom:8px;" onerror="this.style.display='none'">
                            <?php endif; ?>
                            <input type="file" name="cover_image" class="form-control" accept="image/*">
                        </div>
                    </div>
                    <button type="submit" name="update_profile" class="mc-btn primary lg">💾 حفظ التغييرات</button>
                </form>
            </div>

        <?php elseif ($tab === 'bookings'): ?>
            <!-- ==================== الحجوزات ==================== -->
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">لوحة المركز / الحجوزات</div>
                    <h1>📅 إدارة الحجوزات</h1>
                    <p><?= count($bookings) ?> حجز <?= $bk_status!='all'?'(مفلتر)':'' ?></p>
                </div>
            </div>

            <div class="mc-filters">
                <a href="?tab=bookings&bk_status=all" class="mc-chip <?= $bk_status=='all'?'active':'' ?>">الكل</a>
                <a href="?tab=bookings&bk_status=pending" class="mc-chip <?= $bk_status=='pending'?'active':'' ?>">⏳ قيد المراجعة</a>
                <a href="?tab=bookings&bk_status=confirmed" class="mc-chip <?= $bk_status=='confirmed'?'active':'' ?>">✅ مؤكدة</a>
                <a href="?tab=bookings&bk_status=completed" class="mc-chip <?= $bk_status=='completed'?'active':'' ?>">🏁 مكتملة</a>
                <a href="?tab=bookings&bk_status=cancelled" class="mc-chip <?= $bk_status=='cancelled'?'active':'' ?>">❌ ملغية</a>
            </div>

            <?php if (empty($bookings)): ?>
                <div class="mc-empty">📅<p>لا توجد حجوزات في هذه الفئة.</p></div>
            <?php else: ?>
            <div class="mc-table-wrap">
                <table class="mc-table">
                    <thead>
                        <tr>
                            <th>العميل</th><th>الخدمة</th><th>التاريخ</th><th>الوقت</th>
                            <th>المستوى</th><th>السعر</th><th>الحالة</th><th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($bookings as $b): $st = $status_labels[$b['status']] ?? ['—','#999']; ?>
                        <tr>
                            <td>
                                <strong><?= sanitize($b['user_name']) ?></strong>
                                <?php if ($b['user_phone']): ?><small dir="ltr"><?= sanitize($b['user_phone']) ?></small><?php endif; ?>
                            </td>
                            <td><?= sanitize($b['service_name'] ?: '—') ?></td>
                            <td><?= date('d/m/Y', strtotime($b['booking_date'])) ?></td>
                            <td><?= substr($b['booking_time'], 0, 5) ?></td>
                            <td><?= ['beginner'=>'مبتدئ','intermediate'=>'متوسط','advanced'=>'متقدم'][$b['rider_level'] ?? 'beginner'] ?? '—' ?></td>
                            <td><?= $b['service_price'] ? number_format($b['service_price'],0).' ₪' : '—' ?></td>
                            <td><span class="mc-pill" style="--c:<?= $st[1] ?>;"><?= $st[0] ?></span></td>
                            <td class="mc-row-actions">
                                <?php if ($b['status'] === 'pending'): ?>
                                    <a href="?booking_action=confirm&bid=<?= $b['id'] ?>&tab=bookings" class="mc-btn sm ok">قبول</a>
                                    <a href="?booking_action=reject&bid=<?= $b['id'] ?>&tab=bookings" class="mc-btn sm no" onclick="return confirm('رفض؟')">رفض</a>
                                <?php elseif ($b['status'] === 'confirmed'): ?>
                                    <a href="?booking_action=complete&bid=<?= $b['id'] ?>&tab=bookings" class="mc-btn sm primary">إنهاء</a>
                                    <a href="?booking_action=reject&bid=<?= $b['id'] ?>&tab=bookings" class="mc-btn sm no" onclick="return confirm('إلغاء؟')">إلغاء</a>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!empty($b['notes'])): ?>
                        <tr class="mc-note-row"><td colspan="8"><em>📝 <?= sanitize($b['notes']) ?></em></td></tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        <?php elseif ($tab === 'services'): ?>
            <!-- ==================== الخدمات ==================== -->
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">لوحة المركز / الخدمات</div>
                    <h1>🎯 إدارة الخدمات</h1>
                    <p>أضف الخدمات التي يقدمها مركزك (دروس، تدريب، إيواء...)</p>
                </div>
            </div>

            <div class="mc-panel">
                <div class="mc-panel-head">
                    <h3><?= $edit_service ? '✏️ تعديل خدمة' : '➕ خدمة جديدة' ?></h3>
                    <?php if ($edit_service): ?><a href="?tab=services" class="mc-link">إلغاء</a><?php endif; ?>
                </div>
                <form method="POST" class="mc-form">
                    <input type="hidden" name="service_id" value="<?= $edit_service['id'] ?? '' ?>">
                    <div class="mc-form-grid">
                        <div class="form-group"><label>اسم الخدمة</label><input type="text" name="service_name" class="form-control" required value="<?= sanitize($edit_service['name'] ?? '') ?>"></div>
                        <div class="form-group"><label>السعر (₪)</label><input type="number" name="service_price" class="form-control" required step="0.01" min="0" value="<?= $edit_service['price'] ?? '' ?>"></div>
                        <div class="form-group"><label>المدة</label><input type="text" name="service_duration" class="form-control" value="<?= sanitize($edit_service['duration'] ?? '60 دقيقة') ?>"></div>
                        <div class="form-group"><label>الأيقونة (إيموجي)</label><input type="text" name="service_icon" class="form-control" maxlength="4" value="<?= sanitize($edit_service['icon'] ?? '🏇') ?>"></div>
                        <div class="form-group full"><label>الوصف</label><textarea name="service_description" class="form-control" rows="3"><?= sanitize($edit_service['description'] ?? '') ?></textarea></div>
                    </div>
                    <button type="submit" name="save_service" class="mc-btn primary"><?= $edit_service?'💾 تحديث':'➕ إضافة' ?></button>
                </form>
            </div>

            <div class="mc-panel">
                <div class="mc-panel-head"><h3>الخدمات الحالية (<?= count($services) ?>)</h3></div>
                <?php if (empty($services)): ?>
                    <div class="mc-empty">🎯<p>لم تضف خدمات بعد.</p></div>
                <?php else: ?>
                <div class="mc-svc-grid">
                    <?php foreach ($services as $s): ?>
                    <div class="mc-svc-card">
                        <div class="mc-svc-ic"><?= sanitize($s['icon']) ?></div>
                        <h4><?= sanitize($s['name']) ?></h4>
                        <?php if ($s['description']): ?><p><?= sanitize($s['description']) ?></p><?php endif; ?>
                        <div class="mc-svc-meta">
                            <span>⏱️ <?= sanitize($s['duration']) ?></span>
                            <strong><?= number_format($s['price'], 0) ?> ₪</strong>
                        </div>
                        <div class="mc-svc-actions">
                            <a href="?tab=services&edit_service=<?= $s['id'] ?>" class="mc-btn sm">✏️ تعديل</a>
                            <a href="?delete_service=<?= $s['id'] ?>" class="mc-btn sm no" onclick="return confirm('حذف الخدمة؟')">🗑️</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'events'): ?>
            <!-- ==================== الفعاليات ==================== -->
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">لوحة المركز / الفعاليات</div>
                    <h1>🏆 إدارة الفعاليات</h1>
                    <p>أنشئ مسابقات وفعاليات وورشات عمل.</p>
                </div>
            </div>

            <div class="mc-panel">
                <div class="mc-panel-head">
                    <h3><?= $edit_event ? '✏️ تعديل فعالية' : '➕ فعالية جديدة' ?></h3>
                    <?php if ($edit_event): ?><a href="?tab=events" class="mc-link">إلغاء</a><?php endif; ?>
                </div>
                <form method="POST" enctype="multipart/form-data" class="mc-form">
                    <input type="hidden" name="event_id" value="<?= $edit_event['id'] ?? '' ?>">
                    <div class="mc-form-grid">
                        <div class="form-group full"><label>العنوان</label><input type="text" name="event_title" class="form-control" required value="<?= sanitize($edit_event['title'] ?? '') ?>"></div>
                        <div class="form-group"><label>النوع</label>
                            <select name="event_type" class="form-control">
                                <option value="event" <?= ($edit_event['type'] ?? '')=='event'?'selected':'' ?>>🎉 فعالية</option>
                                <option value="competition" <?= ($edit_event['type'] ?? '')=='competition'?'selected':'' ?>>🏆 مسابقة</option>
                                <option value="workshop" <?= ($edit_event['type'] ?? '')=='workshop'?'selected':'' ?>>📚 ورشة</option>
                            </select>
                        </div>
                        <div class="form-group"><label>التاريخ</label><input type="date" name="event_date" class="form-control" required value="<?= $edit_event['event_date'] ?? '' ?>"></div>
                        <div class="form-group"><label>الوقت</label><input type="time" name="event_time" class="form-control" value="<?= $edit_event['event_time'] ?? '' ?>"></div>
                        <div class="form-group"><label>الموقع</label><input type="text" name="event_location" class="form-control" value="<?= sanitize($edit_event['location'] ?? $center['name']) ?>"></div>
                        <div class="form-group"><label>سعر التذكرة (₪)</label><input type="number" name="event_price" class="form-control" step="0.01" min="0" value="<?= $edit_event['price'] ?? '0' ?>"></div>
                        <div class="form-group"><label>الحد الأقصى للمشاركين</label><input type="number" name="event_max" class="form-control" min="1" value="<?= $edit_event['max_participants'] ?? '50' ?>"></div>
                        <div class="form-group"><label>الصورة</label>
                            <?php if (!empty($edit_event['image'])): ?><img src="<?= sanitize($edit_event['image']) ?>" alt="" style="width:100%; max-width:160px; border-radius:8px; margin-bottom:6px;" onerror="this.style.display='none'"><?php endif; ?>
                            <input type="file" name="event_image" class="form-control" accept="image/*">
                        </div>
                        <div class="form-group full"><label>الوصف</label><textarea name="event_description" class="form-control" rows="3"><?= sanitize($edit_event['description'] ?? '') ?></textarea></div>
                    </div>
                    <button type="submit" name="save_event" class="mc-btn primary"><?= $edit_event?'💾 تحديث':'➕ نشر' ?></button>
                </form>
            </div>

            <div class="mc-panel">
                <div class="mc-panel-head"><h3>الفعاليات (<?= count($events) ?>)</h3></div>
                <?php if (empty($events)): ?>
                    <div class="mc-empty">🏆<p>لم تنشر فعاليات بعد.</p></div>
                <?php else: ?>
                <div class="mc-ev-list">
                    <?php foreach ($events as $e):
                        $is_past = strtotime($e['event_date']) < strtotime('today');
                        $type_label = ['competition'=>'🏆 مسابقة','workshop'=>'📚 ورشة','event'=>'🎉 فعالية'][$e['type']] ?? '';
                    ?>
                    <?php
                    // جلب المسجلين في هذه الفعالية
                    $reg_stmt = $conn->prepare("
                        SELECT u.full_name, u.phone, u.email, r.registered_at, r.status
                        FROM event_registrations r
                        JOIN users u ON u.id = r.user_id
                        WHERE r.event_id = ? AND r.status != 'cancelled'
                        ORDER BY r.registered_at ASC
                    ");
                    $reg_stmt->execute([$e['id']]);
                    $registrants = $reg_stmt->fetchAll();
                    $reg_count   = count($registrants);
                    $reg_pct     = $e['max_participants'] > 0 ? round(($reg_count / $e['max_participants']) * 100) : 0;
                    ?>
                    <div class="mc-ev-row <?= $is_past?'past':'' ?>">
                        <img src="<?= sanitize($e['image'] ?: 'assets/images/hero.jpg') ?>" alt="" onerror="this.src='assets/images/hero.jpg'">
                        <div class="mc-ev-info">
                            <div class="mc-ev-meta"><?= $type_label ?> • <?= date('d/m/Y', strtotime($e['event_date'])) ?> <?= $e['event_time']?'— '.substr($e['event_time'],0,5):'' ?></div>
                            <h4><?= sanitize($e['title']) ?></h4>
                            <small><?= sanitize($e['location']) ?> • <?= $e['price']>0 ? number_format($e['price'],0).' ₪' : 'مجاناً' ?></small>
                            <!-- شريط الامتلاء -->
                            <div style="margin-top:8px;">
                                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
                                    <span style="color:var(--gold);font-weight:700;">👥 <?= $reg_count ?> / <?= (int)$e['max_participants'] ?> مسجّل</span>
                                    <span style="color:<?= $reg_pct>=80?'#e74c3c':'#888' ?>"><?= $reg_pct ?>%</span>
                                </div>
                                <div style="height:5px;background:rgba(255,255,255,0.1);border-radius:10px;overflow:hidden;">
                                    <div style="height:100%;width:<?= $reg_pct ?>%;background:<?= $reg_pct>=80?'linear-gradient(90deg,#e74c3c,#f07040)':'linear-gradient(90deg,#c9a227,#e5bf3d)' ?>;border-radius:10px;transition:width 0.5s;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="mc-ev-actions">
                            <?php if ($reg_count > 0): ?>
                            <button onclick="toggleRegs(<?= $e['id'] ?>)" class="mc-btn sm primary">👥 المسجلون</button>
                            <?php endif; ?>
                            <a href="?tab=events&edit_event=<?= $e['id'] ?>" class="mc-btn sm">✏️</a>
                            <a href="?delete_event=<?= $e['id'] ?>" class="mc-btn sm no" onclick="return confirm('حذف؟')">🗑️</a>
                        </div>
                    </div>
                    <?php if ($reg_count > 0): ?>
                    <div id="regs-<?= $e['id'] ?>" style="display:none;padding:16px;background:rgba(201,162,39,0.05);border-top:1px solid rgba(201,162,39,0.15);border-radius:0 0 12px 12px;">
                        <table style="width:100%;border-collapse:collapse;font-size:13px;">
                            <thead>
                                <tr style="color:var(--gold);border-bottom:1px solid rgba(201,162,39,0.2);">
                                    <th style="padding:8px;text-align:right;">#</th>
                                    <th style="padding:8px;text-align:right;">الاسم</th>
                                    <th style="padding:8px;text-align:right;">الهاتف</th>
                                    <th style="padding:8px;text-align:right;">تاريخ التسجيل</th>
                                    <th style="padding:8px;text-align:right;">الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($registrants as $i => $r): ?>
                                <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">
                                    <td style="padding:8px;color:#888;"><?= $i+1 ?></td>
                                    <td style="padding:8px;font-weight:700;"><?= sanitize($r['full_name']) ?></td>
                                    <td style="padding:8px;direction:ltr;"><?= sanitize($r['phone'] ?: '—') ?></td>
                                    <td style="padding:8px;color:#888;"><?= date('d/m/Y H:i', strtotime($r['registered_at'])) ?></td>
                                    <td style="padding:8px;"><span style="background:rgba(46,204,113,0.15);color:#2ecc71;padding:2px 10px;border-radius:20px;font-size:12px;">✅ مسجّل</span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'boarding'): ?>
            <!-- ==================== طلبات الإيواء ==================== -->
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">لوحة المركز / طلبات الإيواء</div>
                    <h1>🏇 إدارة طلبات الإيواء</h1>
                    <p><?= count($boardings) ?> طلب <?= $ba_status!='all'?'(مفلتر)':'' ?></p>
                </div>
            </div>

            <?php if (isset($_GET['boarding_updated'])): ?><div class="mc-alert success">✅ تم تحديث حالة الطلب</div><?php endif; ?>

            <div class="mc-filters">
                <a href="?tab=boarding&ba_status=all"      class="mc-chip <?= $ba_status=='all'?'active':'' ?>">الكل</a>
                <a href="?tab=boarding&ba_status=pending"   class="mc-chip <?= $ba_status=='pending'?'active':'' ?>">⏳ جديد</a>
                <a href="?tab=boarding&ba_status=active"    class="mc-chip <?= $ba_status=='active'?'active':'' ?>">✅ نشط</a>
                <a href="?tab=boarding&ba_status=ended"     class="mc-chip <?= $ba_status=='ended'?'active':'' ?>">🏁 منتهٍ</a>
                <a href="?tab=boarding&ba_status=rejected"  class="mc-chip <?= $ba_status=='rejected'?'active':'' ?>">❌ مرفوض</a>
            </div>

            <?php if (empty($boardings)): ?>
                <div class="mc-empty">🏇<p>لا توجد طلبات إيواء في هذه الفئة.</p></div>
            <?php else: ?>
            <?php
            $ba_status_labels = [
                'pending'  => ['⏳ جديد',   '#f39c12'],
                'active'   => ['✅ نشط',    '#2ecc71'],
                'ended'    => ['🏁 منتهٍ',  '#3498db'],
                'rejected' => ['❌ مرفوض', '#e74c3c'],
            ];
            ?>
            <div class="mc-table-wrap">
                <table class="mc-table">
                    <thead>
                        <tr>
                            <th>صاحب الفرس</th>
                            <th>الفرس</th>
                            <th>تاريخ البدء</th>
                            <th>الرسم الشهري</th>
                            <th>نسبة المركز</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($boardings as $ba):
                        $bst = $ba_status_labels[$ba['status']] ?? ['—','#999'];
                    ?>
                        <tr>
                            <td>
                                <strong><?= sanitize($ba['owner_name']) ?></strong>
                                <?php if ($ba['owner_phone']): ?><small dir="ltr"><?= sanitize($ba['owner_phone']) ?></small><?php endif; ?>
                            </td>
                            <td>🐎 <?= sanitize($ba['horse_name']) ?></td>
                            <td><?= date('d/m/Y', strtotime($ba['start_date'])) ?></td>
                            <td><?= number_format($ba['monthly_fee'], 0) ?> ₪ / شهر</td>
                            <td><?= (float)$ba['trainer_share_pct'] ?>%</td>
                            <td><span class="mc-pill" style="--c:<?= $bst[1] ?>;"><?= $bst[0] ?></span></td>
                            <td class="mc-row-actions">
                                <?php if ($ba['status'] === 'pending'): ?>
                                    <a href="?boarding_action=approve&ba_id=<?= $ba['id'] ?>&tab=boarding" class="mc-btn sm ok">قبول</a>
                                    <a href="?boarding_action=reject&ba_id=<?= $ba['id'] ?>&tab=boarding" class="mc-btn sm no" onclick="return confirm('رفض الطلب؟')">رفض</a>
                                <?php elseif ($ba['status'] === 'active'): ?>
                                    <a href="?boarding_action=end&ba_id=<?= $ba['id'] ?>&tab=boarding" class="mc-btn sm primary" onclick="return confirm('إنهاء اتفاقية الإيواء؟')">إنهاء</a>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!empty($ba['notes'])): ?>
                        <tr class="mc-note-row"><td colspan="7"><em>📝 <?= sanitize($ba['notes']) ?></em></td></tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </main>
</div>

<script>
function toggleRegs(id) {
    var el = document.getElementById('regs-' + id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
<?php include 'includes/footer.php'; ?>
