<?php
$page_title = 'حسابي';
require_once 'config/db.php';

if (!isLoggedIn()) redirect('login.php');

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = sanitize($_POST['full_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $city = sanitize($_POST['city'] ?? '');

    if (strlen($full_name) >= 3 && in_array($city, $palestinian_cities)) {
        $upd = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, city = ? WHERE id = ?");
        $upd->execute([$full_name, $phone, $city, $user_id]);
        $_SESSION['user_name'] = $full_name;
        $message = 'تم تحديث بياناتك بنجاح ✅';
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    } else {
        $error = 'الرجاء التأكد من صحة البيانات.';
    }
}

// عدد السلة
$cart_total = $conn->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
$cart_total->execute([$user_id]);
$cart_items_count = (int)$cart_total->fetchColumn();

$tab = $_GET['tab'] ?? 'overview';

// أعمال المستخدم
$center_check = $conn->prepare("SELECT id, name, approval_status FROM centers WHERE owner_id = ?");
$center_check->execute([$user_id]);
$my_center = $center_check->fetch();

$clinic_check = $conn->prepare("SELECT id, name, approval_status FROM clinics WHERE owner_id = ?");
$clinic_check->execute([$user_id]);
$my_clinic = $clinic_check->fetch();

$studio_check = $conn->prepare("SELECT id, studio_name, approval_status FROM photographers WHERE owner_id = ?");
$studio_check->execute([$user_id]);
$my_studio = $studio_check->fetch();

$my_shop_count = (int)$conn->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ?")->execute([$user_id]) ? (int)$conn->query("SELECT COUNT(*) FROM products WHERE seller_id = $user_id")->fetchColumn() : 0;

// جلب الحجوزات
$bookings = [];
// تصفير إشعارات الحجوزات عند فتح تاب حجوزاتي
if ($tab === 'bookings') {
    $conn->prepare("UPDATE notifications SET is_read=1 WHERE user_id=? AND type IN ('booking_confirmed','booking_rejected') AND is_read=0")
         ->execute([$user_id]);
}

if ($tab === 'bookings' || $tab === 'overview') {
    $b_stmt = $conn->prepare("
        SELECT b.*, s.name AS service_name, s.icon, s.price, c.name AS center_name, c.city AS center_city,
               h.name AS my_horse_name
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        JOIN centers c ON b.center_id = c.id
        LEFT JOIN horses h ON h.id = b.horse_id
        WHERE b.user_id = ?
        ORDER BY b.booking_date DESC, b.booking_time DESC
    ");
    $b_stmt->execute([$user_id]);
    $bookings = $b_stmt->fetchAll();
}

$skill_labels = [
    'jumping'=>'القفز','dressage'=>'الترويض','canter'=>'الكانتر','khabab'=>'الخبب',
    'trail'=>'ركوب الدروب','racing'=>'السباق','general'=>'ركوب عام'
];
$level_labels = ['beginner'=>'مبتدئ 🌱','intermediate'=>'متوسط ⚡','advanced'=>'محترف 🏆'];

// جلب الطلبات
$orders = [];
if ($tab === 'orders' || $tab === 'overview') {
    $o_stmt = $conn->prepare("
        SELECT o.*, COUNT(oi.id) AS items_count
        FROM orders o
        LEFT JOIN order_items oi ON oi.order_id = o.id
        WHERE o.user_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $o_stmt->execute([$user_id]);
    $orders = $o_stmt->fetchAll();
}

// جلب تسجيلات الفعاليات
$my_events = [];
if ($tab === 'events') {
    $ev_stmt = $conn->prepare("
        SELECT e.*, r.registered_at, r.status AS reg_status
        FROM event_registrations r
        JOIN events e ON r.event_id = e.id
        WHERE r.user_id = ?
        ORDER BY e.event_date DESC
    ");
    $ev_stmt->execute([$user_id]);
    $my_events = $ev_stmt->fetchAll();
}

// جلب حجوزات التصوير
$my_photoshoots = [];
if ($tab === 'photoshoots') {
    $ps_stmt = $conn->prepare("
        SELECT pb.*, pkg.title AS pkg_title, pkg.price AS pkg_price, pkg.free_outfits,
               c.name AS center_name, c.city AS center_city,
               pg.studio_name AS photographer_name, pg.city AS photographer_city
        FROM photoshoot_bookings pb
        JOIN photoshoot_packages pkg ON pkg.id = pb.package_id
        LEFT JOIN centers c ON c.id = pkg.center_id
        LEFT JOIN photographers pg ON pg.id = pkg.photographer_id
        WHERE pb.user_id = ?
        ORDER BY pb.session_date DESC
    ");
    $ps_stmt->execute([$user_id]);
    $my_photoshoots = $ps_stmt->fetchAll();
}

// جلب اتفاقيات الإيواء
$my_boardings = [];
if ($tab === 'boarding') {
    $bd_stmt = $conn->prepare("
        SELECT b.*, c.name AS center_name, c.city AS center_city, h.name AS horse_name
        FROM boarding_agreements b
        JOIN centers c ON c.id = b.center_id
        JOIN horses h ON h.id = b.horse_id
        WHERE b.owner_id = ?
        ORDER BY b.created_at DESC
    ");
    $bd_stmt->execute([$user_id]);
    $my_boardings = $bd_stmt->fetchAll();
}

// إلغاء حجز
if (isset($_POST['cancel_booking'])) {
    $bid = (int)$_POST['booking_id'];
    $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?")
        ->execute([$bid, $user_id]);
    redirect('account.php?tab=bookings&cancelled=1');
}

$months = ['','يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];

// إحصائيات سريعة
$stats = [
    'bookings_total'    => (int)$conn->query("SELECT COUNT(*) FROM bookings WHERE user_id = $user_id")->fetchColumn(),
    'bookings_upcoming' => (int)$conn->query("SELECT COUNT(*) FROM bookings WHERE user_id = $user_id AND booking_date >= CURDATE() AND status IN ('pending','confirmed')")->fetchColumn(),
    'orders_total'      => (int)$conn->query("SELECT COUNT(*) FROM orders WHERE user_id = $user_id")->fetchColumn(),
    'cart_count'        => $cart_items_count,
    'booking_notifs'    => (int)$conn->query("SELECT COUNT(*) FROM notifications WHERE user_id = $user_id AND type IN ('booking_confirmed','booking_rejected') AND is_read = 0")->fetchColumn(),
];

$order_status_labels = [
    'pending' => ['📝 قيد الانتظار', '#f39c12'],
    'processing' => ['⏳ قيد المعالجة', '#3498db'],
    'shipped' => ['🚚 قيد الشحن', '#9b59b6'],
    'delivered' => ['✅ تم التوصيل', '#2ecc71'],
    'cancelled' => ['❌ ملغي', '#e74c3c'],
];

include 'includes/header.php';
?>

<div class="mc-wrap">
    <!-- السايدبار -->
    <aside class="mc-sidebar">
        <div class="mc-brand">
            <div class="mc-avatar"><?= mb_substr($user['full_name'], 0, 1, 'UTF-8') ?></div>
            <div>
                <strong><?= sanitize($user['full_name']) ?></strong>
                <small><?= sanitize($user['email']) ?></small>
            </div>
        </div>

        <ul class="mc-nav">
            <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
            <li><a href="admin.php" class="mc-admin-link"><span>👑</span> لوحة الإدارة</a></li>
            <?php endif; ?>
            <li><a href="?tab=overview" class="<?= $tab=='overview'?'active':'' ?>"><span>🏠</span> نظرة عامة</a></li>
            <li><a href="?tab=profile" class="<?= $tab=='profile'?'active':'' ?>"><span>👤</span> بياناتي</a></li>
            <li><a href="?tab=bookings" class="<?= $tab=='bookings'?'active':'' ?>">
                <span>📅</span> حجوزاتي
                <?php if ($stats['booking_notifs'] > 0): ?><b class="mc-badge"><?= $stats['booking_notifs'] ?></b><?php endif; ?>
            </a></li>
            <li><a href="?tab=photoshoots" class="<?= $tab=='photoshoots'?'active':'' ?>"><span>📸</span> جلسات التصوير</a></li>
            <li><a href="?tab=boarding" class="<?= $tab=='boarding'?'active':'' ?>"><span>🏇</span> الإيواء والتدريب</a></li>
            <li><a href="?tab=events" class="<?= $tab=='events'?'active':'' ?>"><span>🏆</span> فعالياتي</a></li>
            <li><a href="?tab=orders" class="<?= $tab=='orders'?'active':'' ?>"><span>📦</span> طلباتي</a></li>
            <li><a href="cart.php"><span>🛒</span> سلتي <?= $stats['cart_count']?"($stats[cart_count])":'' ?></a></li>

            <?php if ($my_center || $my_clinic || $my_studio): ?>
            <li class="mc-divider"></li>
            <?php if ($my_center): ?>
            <li><a href="my-center.php" class="mc-biz-link gold"><span>🏇</span> لوحة مركزي</a></li>
            <?php endif; ?>
            <?php if ($my_clinic): ?>
            <li><a href="my-clinic.php" class="mc-biz-link blue"><span>🩺</span> لوحة عيادتي</a></li>
            <?php endif; ?>
            <?php if ($my_studio): ?>
            <li><a href="my-studio.php" class="mc-biz-link purple"><span>📸</span> لوحة استوديوي</a></li>
            <?php endif; ?>
            <?php endif; ?>

            <li><a href="my-shop.php" class="mc-biz-link" style="color:#f39c12;"><span>🛍️</span> متجري <?= $my_shop_count ? "($my_shop_count)" : '' ?></a></li>
            <li class="mc-divider"></li>
            <li><a href="?tab=security" class="<?= $tab=='security'?'active':'' ?>"><span>🔐</span> الأمان</a></li>
            <li><a href="logout.php" class="mc-logout"><span>🚪</span> تسجيل الخروج</a></li>
        </ul>
    </aside>

    <!-- المحتوى -->
    <main class="mc-main">
        <?php if ($message): ?><div class="mc-alert success">✅ <?= $message ?></div><?php endif; ?>
        <?php if ($error): ?><div class="mc-alert error">⚠️ <?= $error ?></div><?php endif; ?>
        <?php if (isset($_GET['cancelled'])): ?><div class="mc-alert success">✅ تم إلغاء الحجز</div><?php endif; ?>

        <?php if ($tab === 'overview'): ?>
            <!-- ==================== نظرة عامة ==================== -->
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">الحساب / نظرة عامة</div>
                    <h1>🌟 مرحباً، <?= sanitize($user['full_name']) ?>!</h1>
                    <p>يسعدنا انضمامك إلى عائلة عشاق الفروسية. استكشف ميزات حسابك من القائمة الجانبية.</p>
                </div>
            </div>

            <?php if ($my_center): ?>
            <a href="my-center.php" class="mc-panel" style="text-decoration: none; background: linear-gradient(135deg, var(--dark) 0%, rgba(46,204,113,0.1) 100%); border-color: rgba(46,204,113,0.45); display: flex; align-items: center; gap: 16px;">
                <div style="font-size: 42px;">🏇</div>
                <div style="flex: 1;">
                    <strong style="color: #2ecc71; display: block; font-size: 17px;">لوحة تحكم المركز</strong>
                    <small style="color: var(--text-muted); font-size: 13px;"><?= sanitize($my_center['name']) ?> — إدارة الحجوزات والخدمات والفعاليات</small>
                </div>
                <div style="color: var(--gold); font-weight: 700;">فتح ←</div>
            </a>
            <?php endif; ?>

            <div class="mc-stats-grid">
                <div class="mc-stat" style="--c:#c9a227;">
                    <div class="mc-stat-ic">📅</div>
                    <div><b><?= $stats['bookings_total'] ?></b><small>إجمالي الحجوزات</small></div>
                </div>
                <div class="mc-stat" style="--c:#2ecc71;">
                    <div class="mc-stat-ic">🗓️</div>
                    <div><b><?= $stats['bookings_upcoming'] ?></b><small>حجوزات قادمة</small></div>
                </div>
                <div class="mc-stat" style="--c:#9b59b6;">
                    <div class="mc-stat-ic">📦</div>
                    <div><b><?= $stats['orders_total'] ?></b><small>طلب</small></div>
                </div>
                <div class="mc-stat" style="--c:#e5bf3d;">
                    <div class="mc-stat-ic">🛒</div>
                    <div><b><?= $stats['cart_count'] ?></b><small>في السلة</small></div>
                </div>
            </div>

            <div class="mc-panel">
                <div class="mc-panel-head"><h3>👤 بياناتي</h3><a href="?tab=profile" class="mc-link">تعديل ←</a></div>
                <div class="mc-info-grid">
                    <div class="mc-info"><label>📧 البريد الإلكتروني</label><div><?= sanitize($user['email']) ?></div></div>
                    <div class="mc-info"><label>📱 رقم الهاتف</label><div dir="ltr"><?= sanitize($user['phone']) ?></div></div>
                    <div class="mc-info"><label>📍 المدينة</label><div><?= sanitize($user['city']) ?></div></div>
                    <div class="mc-info"><label>📅 تاريخ الانضمام</label><div><?= date('Y/m/d', strtotime($user['created_at'])) ?></div></div>
                </div>
            </div>

            <div class="mc-quick-grid">
                <a href="shop.php" class="mc-quick"><div>🛍️</div><strong>تصفح المتجر</strong></a>
                <a href="cart.php" class="mc-quick"><div>🛒</div><strong>سلتي (<?= $stats['cart_count'] ?>)</strong></a>
                <a href="centers.php" class="mc-quick"><div>🏇</div><strong>المراكز</strong></a>
                <a href="events.php" class="mc-quick"><div>🏆</div><strong>الفعاليات</strong></a>
            </div>

        <?php elseif ($tab === 'profile'): ?>
            <!-- ==================== بياناتي ==================== -->
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">الحساب / بياناتي</div>
                    <h1>✏️ تعديل البيانات الشخصية</h1>
                    <p>حدّث معلوماتك الأساسية هنا. لا يمكن تعديل البريد الإلكتروني.</p>
                </div>
            </div>

            <div class="mc-panel">
                <form method="POST" class="mc-form">
                    <div class="mc-form-grid">
                        <div class="form-group full">
                            <label>الاسم الكامل</label>
                            <input type="text" name="full_name" class="form-control" required value="<?= sanitize($user['full_name']) ?>">
                        </div>
                        <div class="form-group full">
                            <label>البريد الإلكتروني (لا يمكن تعديله)</label>
                            <input type="email" class="form-control" value="<?= sanitize($user['email']) ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>رقم الهاتف</label>
                            <input type="tel" name="phone" class="form-control" required value="<?= sanitize($user['phone']) ?>" dir="ltr">
                        </div>
                        <div class="form-group">
                            <label>المدينة</label>
                            <select name="city" class="form-control" required>
                                <?php foreach ($palestinian_cities as $c): ?>
                                <option value="<?= $c ?>" <?= $user['city']==$c?'selected':'' ?>><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="update_profile" class="mc-btn primary lg">💾 حفظ التغييرات</button>
                </form>
            </div>

        <?php elseif ($tab === 'bookings'): ?>
            <!-- ==================== حجوزاتي ==================== -->
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">الحساب / حجوزاتي</div>
                    <h1>📅 حجوزاتي</h1>
                    <p><?= count($bookings) ?> حجز — اضغط "إلغاء" على أي حجز قادم</p>
                </div>
            </div>

            <?php if (empty($bookings)): ?>
                <div class="mc-panel">
                    <div class="mc-empty">📅<p>لا توجد حجوزات حتى الآن</p></div>
                    <div style="text-align: center;"><a href="centers.php" class="mc-btn primary">احجز درسك الأول</a></div>
                </div>
            <?php else: ?>
            <div class="bookings-cards">
                <?php foreach ($bookings as $b):
                    $is_upcoming = $b['booking_date'] >= date('Y-m-d') && in_array($b['status'], ['pending','confirmed']);
                    $skills_arr = $b['skills'] ? array_filter(explode(',', $b['skills'])) : [];
                    $horse_label = $b['my_horse_name'] ?: ($b['preferred_horse_name'] ?: 'يخصص من المركز');
                ?>
                <div class="booking-card-v2 <?= $is_upcoming ? 'upcoming' : '' ?>">
                    <div class="bc-date">
                        <span class="bc-day"><?= date('d', strtotime($b['booking_date'])) ?></span>
                        <span class="bc-month"><?= $months[(int)date('n', strtotime($b['booking_date']))] ?></span>
                        <span class="bc-time">🕐 <?= date('H:i', strtotime($b['booking_time'])) ?></span>
                    </div>
                    <div class="bc-body">
                        <div class="bc-head">
                            <h3><?= $b['icon'] ?> <?= sanitize($b['service_name']) ?></h3>
                            <span class="status-badge status-<?= $b['status'] ?>">
                                <?= ['pending'=>'قيد الانتظار','confirmed'=>'مؤكد','completed'=>'منتهي','cancelled'=>'ملغي'][$b['status']] ?>
                            </span>
                        </div>
                        <div class="bc-meta">
                            <span>🏇 <?= sanitize($b['center_name']) ?> — <?= sanitize($b['center_city']) ?></span>
                            <span>🐎 الفرس: <strong><?= sanitize($horse_label) ?></strong></span>
                            <span>🎓 <?= $level_labels[$b['rider_level']] ?? 'مبتدئ' ?></span>
                            <?php if ($skills_arr): ?>
                            <span>🎯 المهارات:
                                <?php foreach ($skills_arr as $sk): ?>
                                    <span class="skill-tag"><?= $skill_labels[$sk] ?? $sk ?></span>
                                <?php endforeach; ?>
                            </span>
                            <?php endif; ?>
                            <span style="color: var(--gold); font-weight: 800;"><?= number_format($b['price'], 0) ?> ₪</span>
                        </div>
                        <?php if ($is_upcoming && $b['weather_notify']): ?>
                            <div class="bc-weather">
                                <span class="weather-widget weather-loading"
                                      data-city="<?= sanitize($b['center_city']) ?>"
                                      data-date="<?= sanitize($b['booking_date']) ?>">
                                    🌤️ يتم جلب حالة الطقس...
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="bc-actions">
                        <?php if ($b['status']==='pending' || $b['status']==='confirmed'): ?>
                        <form method="POST" style="margin:0;" onsubmit="return confirm('هل أنت متأكد من إلغاء الحجز؟')">
                            <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                            <button name="cancel_booking" class="mc-btn no sm">❌ إلغاء</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        <?php elseif ($tab === 'events'): ?>
            <!-- ==================== فعالياتي ==================== -->
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">الحساب / فعالياتي</div>
                    <h1>🏆 الفعاليات المسجّل فيها</h1>
                    <p>الفعاليات، المسابقات وورش العمل التي سجّلت فيها.</p>
                </div>
            </div>

            <?php if (empty($my_events)): ?>
                <div class="mc-panel">
                    <div class="mc-empty">🏆<p>لم تسجل في أي فعالية بعد</p></div>
                    <div style="text-align:center;"><a href="events.php" class="mc-btn primary">تصفح الفعاليات</a></div>
                </div>
            <?php else: ?>
            <div class="events-grid">
                <?php foreach ($my_events as $e): ?>
                <a href="event.php?id=<?= $e['id'] ?>" class="event-card" style="text-decoration: none;">
                    <div class="event-image">
                        <span class="event-type-badge <?= $e['type'] ?>">
                            <?= $e['type']=='competition'?'🏆 مسابقة':($e['type']=='workshop'?'📚 ورشة':'🎉 فعالية') ?>
                        </span>
                        <img src="<?= sanitize($e['image']) ?>" alt="<?= sanitize($e['title']) ?>" onerror="this.src='assets/images/hero.jpg'">
                    </div>
                    <div class="event-info">
                        <h3><?= sanitize($e['title']) ?></h3>
                        <div class="event-meta">
                            <div>📅 <?= date('d/m/Y', strtotime($e['event_date'])) ?></div>
                            <div>📍 <?= sanitize($e['location']) ?></div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        <?php elseif ($tab === 'orders'): ?>
            <!-- ==================== طلباتي ==================== -->
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">الحساب / طلباتي</div>
                    <h1>📦 طلباتي</h1>
                    <p><?= count($orders) ?> طلب — اضغط على أي طلب لعرض تفاصيله</p>
                </div>
            </div>

            <?php if (empty($orders)): ?>
                <div class="mc-panel">
                    <div class="mc-empty">📦<p>لا توجد طلبات سابقة</p></div>
                    <div style="text-align:center;"><a href="shop.php" class="mc-btn primary">ابدأ التسوق</a></div>
                </div>
            <?php else: ?>
            <div class="mc-orders">
                <?php foreach ($orders as $o): $st = $order_status_labels[$o['status']] ?? ['—','#999']; ?>
                <a href="order.php?id=<?= $o['id'] ?>" class="mc-order-row">
                    <div class="mc-order-head">
                        <div>
                            <div class="mc-order-num"><?= sanitize($o['order_number']) ?></div>
                            <small>📅 <?= date('d/m/Y — H:i', strtotime($o['created_at'])) ?></small>
                        </div>
                        <span class="mc-pill" style="--c:<?= $st[1] ?>;"><?= $st[0] ?></span>
                    </div>
                    <div class="mc-order-body">
                        <div>📦 <?= $o['items_count'] ?> منتج • 📍 <?= sanitize($o['city']) ?></div>
                        <div class="mc-order-total"><?= number_format($o['total'],0) ?> ₪</div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        <?php elseif ($tab === 'photoshoots'): ?>
            <!-- ==================== جلسات التصوير ==================== -->
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">الحساب / جلسات التصوير</div>
                    <h1>📸 جلسات التصوير المحجوزة</h1>
                    <p><?= count($my_photoshoots) ?> جلسة</p>
                </div>
            </div>

            <?php if (empty($my_photoshoots)): ?>
                <div class="mc-panel">
                    <div class="mc-empty">📸<p>لم تحجز أي جلسة تصوير بعد</p></div>
                    <div style="text-align:center;"><a href="photoshoots.php" class="mc-btn primary">تصفح الباقات</a></div>
                </div>
            <?php else: ?>
            <div class="mc-panel">
                <div class="mc-table-wrap">
                    <table class="mc-table">
                        <thead>
                            <tr><th>الباقة</th><th>المركز</th><th>التاريخ</th><th>الوقت</th><th>ملابس</th><th>السعر</th><th>الحالة</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($my_photoshoots as $ps):
                            $ps_status = $ps['status'] ?? 'pending';
                            $st_map = ['pending'=>['⏳ بانتظار التأكيد','#f39c12'],'confirmed'=>['✅ مؤكدة','#2ecc71'],'completed'=>['🏁 منتهية','#3498db'],'cancelled'=>['❌ ملغية','#e74c3c']];
                            $st = $st_map[$ps_status] ?? ['—','#999'];
                        ?>
                        <tr>
                            <td><strong><?= sanitize($ps['pkg_title']) ?></strong></td>
                            <td><?= sanitize($ps['center_name'] ?? $ps['photographer_name'] ?? '—') ?><small><?= sanitize($ps['center_city'] ?? $ps['photographer_city'] ?? '') ?></small></td>
                            <td><?= date('d/m/Y', strtotime($ps['session_date'])) ?></td>
                            <td><?= substr($ps['session_time'] ?? '—', 0, 5) ?></td>
                            <td><?= !empty($ps['free_outfits']) ? '✅ ' . sanitize($ps['free_outfits']) : '—' ?></td>
                            <td><?= number_format($ps['pkg_price'] ?? 0, 0) ?> ₪</td>
                            <td><span class="mc-pill" style="--c:<?= $st[1] ?>;"><?= $st[0] ?></span></td>
                        </tr>
                        <?php if (!empty($ps['outfit_choices'])): ?>
                        <tr class="mc-note-row"><td colspan="7"><em>👗 الملابس المختارة: <?= sanitize($ps['outfit_choices']) ?></em></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($ps['notes'])): ?>
                        <tr class="mc-note-row"><td colspan="7"><em>📝 <?= sanitize($ps['notes']) ?></em></td></tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        <?php elseif ($tab === 'boarding'): ?>
            <!-- ==================== الإيواء والتدريب ==================== -->
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">الحساب / الإيواء والتدريب</div>
                    <h1>🏇 اتفاقيات الإيواء والتدريب</h1>
                    <p><?= count($my_boardings) ?> اتفاقية</p>
                </div>
            </div>

            <?php if (empty($my_boardings)): ?>
                <div class="mc-panel">
                    <div class="mc-empty">🏇<p>لا توجد اتفاقيات إيواء بعد</p></div>
                    <div style="text-align:center;"><a href="boarding.php" class="mc-btn primary">استكشف الإيواء</a></div>
                </div>
            <?php else: ?>
            <?php foreach ($my_boardings as $bd):
                $bd_status = $bd['status'] ?? 'pending';
                $st_map = ['pending'=>['⏳ قيد المراجعة','#f39c12'],'active'=>['✅ نشطة','#2ecc71'],'ended'=>['🏁 منتهية','#3498db'],'rejected'=>['❌ مرفوضة','#e74c3c']];
                $st = $st_map[$bd_status] ?? ['—','#999'];
            ?>
            <div class="mc-panel" style="margin-bottom: 16px;">
                <div class="mc-panel-head">
                    <h3>🐎 <?= sanitize($bd['horse_name']) ?></h3>
                    <span class="mc-pill" style="--c:<?= $st[1] ?>;"><?= $st[0] ?></span>
                </div>
                <div class="mc-info-grid">
                    <div class="mc-info"><label>المركز</label><div><?= sanitize($bd['center_name']) ?> — <?= sanitize($bd['center_city']) ?></div></div>
                    <?php if (!empty($bd['start_date'])): ?>
                    <div class="mc-info"><label>تاريخ البدء</label><div><?= date('d/m/Y', strtotime($bd['start_date'])) ?></div></div>
                    <?php endif; ?>
                    <?php if (!empty($bd['end_date'])): ?>
                    <div class="mc-info"><label>تاريخ الانتهاء</label><div><?= date('d/m/Y', strtotime($bd['end_date'])) ?></div></div>
                    <?php endif; ?>
                    <?php if (!empty($bd['monthly_fee'])): ?>
                    <div class="mc-info"><label>الرسوم الشهرية</label><div><?= number_format($bd['monthly_fee'], 0) ?> ₪</div></div>
                    <?php endif; ?>
                    <?php if (!empty($bd['trainer_share_pct'])): ?>
                    <div class="mc-info"><label>نسبة المركز من التدريب</label><div><?= $bd['trainer_share_pct'] ?>%</div></div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($bd['notes'])): ?>
                    <p style="color: var(--text-muted); margin-top: 8px; font-size: 13px;">📝 <?= sanitize($bd['notes']) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

        <?php elseif ($tab === 'security'): ?>
            <!-- ==================== الأمان ==================== -->
            <?php
            $pw_error = '';
            $pw_success = '';
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
                $current  = $_POST['current_password'] ?? '';
                $new_pw   = $_POST['new_password'] ?? '';
                $confirm  = $_POST['confirm_password'] ?? '';

                if (!password_verify($current, $user['password'])) {
                    $pw_error = 'كلمة المرور الحالية غير صحيحة.';
                } elseif (strlen($new_pw) < 6) {
                    $pw_error = 'كلمة المرور الجديدة يجب أن تكون 6 أحرف على الأقل.';
                } elseif ($new_pw !== $confirm) {
                    $pw_error = 'كلمة المرور الجديدة وتأكيدها غير متطابقتين.';
                } else {
                    $conn->prepare("UPDATE users SET password = ? WHERE id = ?")
                         ->execute([password_hash($new_pw, PASSWORD_BCRYPT), $user_id]);
                    $pw_success = 'تم تغيير كلمة المرور بنجاح ✅';
                }
            }
            ?>

            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">الحساب / الأمان</div>
                    <h1>🔐 إعدادات الأمان</h1>
                    <p>حماية حسابك من خلال كلمة مرور قوية ومحدّثة.</p>
                </div>
            </div>

            <?php if ($pw_success): ?><div class="mc-alert success">✅ <?= $pw_success ?></div><?php endif; ?>
            <?php if ($pw_error): ?><div class="mc-alert error">⚠️ <?= $pw_error ?></div><?php endif; ?>

            <div class="mc-panel">
                <h3 style="color:var(--gold);margin-bottom:20px;">🔑 تغيير كلمة المرور</h3>
                <form method="POST" class="mc-form">
                    <div class="mc-form-grid">
                        <div class="form-group full">
                            <label>كلمة المرور الحالية</label>
                            <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                        </div>
                        <div class="form-group">
                            <label>كلمة المرور الجديدة</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6" autocomplete="new-password">
                        </div>
                        <div class="form-group">
                            <label>تأكيد كلمة المرور الجديدة</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6" autocomplete="new-password">
                        </div>
                    </div>
                    <button type="submit" name="change_password" class="mc-btn primary lg">🔑 تغيير كلمة المرور</button>
                </form>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
