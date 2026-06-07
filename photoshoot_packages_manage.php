<?php
// صفحة قديمة — تحوّل لـ my-studio.php
require_once 'config/db.php';
redirect('my-studio.php?tab=packages');

$page_title = 'باقاتي';

if (!isLoggedIn()) redirect('login.php?redirect=photoshoots');

$user_id = (int)$_SESSION['user_id'];
$pg_stmt = $conn->prepare("SELECT * FROM photographers WHERE owner_id = ?");
$pg_stmt->execute([$user_id]);
$photographer = $pg_stmt->fetch();

if (!$photographer || $photographer['approval_status'] !== 'approved') {
    redirect('photographers.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $duration = (int)($_POST['duration_minutes'] ?? 60);
        $photos_count = (int)($_POST['photos_count'] ?? 30);
        $free_outfits = sanitize($_POST['free_outfits'] ?? '');
        $max_outfit_choices = max(1, (int)($_POST['max_outfit_choices'] ?? 1));
        $image_path = 'assets/images/hero.jpg';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg','jpeg','png','webp'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed) && $_FILES['image']['size'] < 5 * 1024 * 1024) {
                $dir = __DIR__ . '/assets/images/photoshoots/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fname = 'pkg_' . $user_id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $dir . $fname)) {
                    $image_path = 'assets/images/photoshoots/' . $fname;
                }
            }
        }
        if ($title && $price > 0) {
            $conn->prepare("INSERT INTO photoshoot_packages (photographer_id, title, description, price, duration_minutes, photos_count, free_outfits, max_outfit_choices, image, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)")
                ->execute([$photographer['id'], $title, $description, $price, $duration, $photos_count, $free_outfits, $max_outfit_choices, $image_path]);
        }
    } elseif ($action === 'toggle') {
        $pkg_id = (int)$_POST['pkg_id'];
        $conn->prepare("UPDATE photoshoot_packages SET is_active = NOT is_active WHERE id = ? AND photographer_id = ?")
            ->execute([$pkg_id, $photographer['id']]);
    } elseif ($action === 'delete') {
        $pkg_id = (int)$_POST['pkg_id'];
        $conn->prepare("DELETE FROM photoshoot_packages WHERE id = ? AND photographer_id = ?")
            ->execute([$pkg_id, $photographer['id']]);
    }
    redirect('photoshoot_packages_manage.php');
}

$pkgs = $conn->prepare("SELECT * FROM photoshoot_packages WHERE photographer_id = ? ORDER BY id DESC");
$pkgs->execute([$photographer['id']]);
$pkgs = $pkgs->fetchAll();

// إحصائيات الحجوزات
$bookings = $conn->prepare("
    SELECT pb.*, pkg.title AS pkg_title, u.full_name AS client_name, u.phone AS client_phone, h.name AS horse_name
    FROM photoshoot_bookings pb
    JOIN photoshoot_packages pkg ON pkg.id = pb.package_id
    JOIN users u ON u.id = pb.user_id
    LEFT JOIN horses h ON h.id = pb.horse_id
    WHERE pkg.photographer_id = ?
    ORDER BY pb.session_date DESC, pb.session_time DESC
");
$bookings->execute([$photographer['id']]);
$bookings = $bookings->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>📸 لوحة المصوّر — <?= sanitize($photographer['studio_name']) ?></h1>
        <p>أدر باقاتك وحجوزات عملائك</p>
    </div>
</div>

<div class="container" style="padding: 40px 0;">
    <div class="info-card">
        <h3 style="color: var(--gold);">➕ إضافة باقة جديدة</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group">
                    <label>عنوان الباقة *</label>
                    <input type="text" name="title" required class="form-control" placeholder="مثلاً: باقة الفارس الأنيق">
                </div>
                <div class="form-group">
                    <label>السعر (₪) *</label>
                    <input type="number" name="price" required min="50" step="10" class="form-control" value="350">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>مدة الجلسة (دقيقة)</label>
                    <input type="number" name="duration_minutes" min="15" class="form-control" value="60">
                </div>
                <div class="form-group">
                    <label>عدد الصور</label>
                    <input type="number" name="photos_count" min="1" class="form-control" value="30">
                </div>
                <div class="form-group">
                    <label>ملابس مجانية (مفصولة بفاصلة)</label>
                    <input type="text" name="free_outfits" class="form-control" placeholder="جاكيت، بوت، خوذة">
                </div>
                <div class="form-group">
                    <label>عدد القطع المسموح باختيارها</label>
                    <select name="max_outfit_choices" class="form-control">
                        <option value="1">1 قطعة فقط</option>
                        <option value="2">2 قطع</option>
                        <option value="3">3 قطع</option>
                        <option value="4">4 قطع</option>
                        <option value="5">5 قطع</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>الوصف</label>
                <textarea name="description" rows="3" class="form-control" placeholder="ما الذي يميز هذه الباقة..."></textarea>
            </div>
            <div class="form-group">
                <label>صورة الباقة</label>
                <input type="file" name="image" accept="image/*" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary btn-block">➕ إضافة الباقة</button>
        </form>
    </div>

    <h2 style="color: var(--gold); margin: 30px 0 16px;">📦 باقاتي (<?= count($pkgs) ?>)</h2>
    <?php if (empty($pkgs)): ?>
        <div class="empty-small">لم تضف أي باقة بعد</div>
    <?php else: ?>
        <div class="photoshoot-grid">
            <?php foreach ($pkgs as $p): ?>
            <div class="photoshoot-card">
                <div class="ps-image">
                    <img src="<?= sanitize($p['image']) ?>" onerror="this.onerror=null;this.src='assets/images/hero.jpg';">
                    <span class="ps-price"><?= number_format($p['price'],0) ?> ₪</span>
                    <?php if (!$p['is_active']): ?><span class="pg-featured" style="background:#888;">معطّلة</span><?php endif; ?>
                </div>
                <div class="ps-body">
                    <h3><?= sanitize($p['title']) ?></h3>
                    <p class="ps-desc"><?= sanitize(mb_substr($p['description'] ?: '', 0, 100)) ?></p>
                    <div class="ps-features">
                        <div>⏱️ <?= (int)$p['duration_minutes'] ?> د</div>
                        <div>📷 <?= (int)$p['photos_count'] ?></div>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <form method="POST" style="flex:1; margin:0;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="pkg_id" value="<?= $p['id'] ?>">
                            <button class="btn btn-outline btn-block"><?= $p['is_active'] ? '⏸️ تعطيل' : '▶️ تفعيل' ?></button>
                        </form>
                        <form method="POST" onsubmit="return confirm('حذف الباقة؟')" style="margin:0;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="pkg_id" value="<?= $p['id'] ?>">
                            <button class="btn btn-outline" style="border-color: var(--red); color: var(--red);">🗑️</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2 style="color: var(--gold); margin: 30px 0 16px;">📅 الحجوزات الواردة (<?= count($bookings) ?>)</h2>
    <?php if (empty($bookings)): ?>
        <div class="empty-small">لا توجد حجوزات بعد</div>
    <?php else: ?>
        <div class="bookings-cards">
            <?php foreach ($bookings as $b): ?>
            <div class="booking-card-v2">
                <div class="bc-date">
                    <span class="bc-day"><?= date('d', strtotime($b['session_date'])) ?></span>
                    <span class="bc-month"><?= ['','يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'][(int)date('n', strtotime($b['session_date']))] ?></span>
                    <span class="bc-time">🕐 <?= substr($b['session_time'],0,5) ?></span>
                </div>
                <div class="bc-body">
                    <div class="bc-head">
                        <h3>📷 <?= sanitize($b['pkg_title']) ?></h3>
                        <span class="status-badge status-<?= $b['status'] ?>"><?= ['pending'=>'قيد الانتظار','confirmed'=>'مؤكد','completed'=>'منجز','cancelled'=>'ملغى'][$b['status']] ?></span>
                    </div>
                    <div class="bc-meta">
                        <span>👤 <?= sanitize($b['client_name']) ?></span>
                        <span>📱 <?= sanitize($b['client_phone']) ?></span>
                        <?php if ($b['horse_name']): ?><span>🐎 <?= sanitize($b['horse_name']) ?></span><?php endif; ?>
                        <?php if ($b['outfit_choices']): ?><span>🎽 <?= sanitize($b['outfit_choices']) ?></span><?php endif; ?>
                    </div>
                    <?php if ($b['notes']): ?><div style="color: var(--text-dark-muted); font-size: 14px;"><?= sanitize($b['notes']) ?></div><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
