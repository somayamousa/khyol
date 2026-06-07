<?php
$page_title = 'إنشاء مزاد';
require_once 'config/db.php';

if (!isLoggedIn()) redirect('login.php?redirect=auctions');

$user_id = (int)$_SESSION['user_id'];
$horses_stmt = $conn->prepare("SELECT id, name, main_image FROM horses WHERE owner_id = ?");
$horses_stmt->execute([$user_id]);
$user_horses = $horses_stmt->fetchAll();

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $horse_id = (int)($_POST['horse_id'] ?? 0) ?: null;
    $starting_price = (float)($_POST['starting_price'] ?? 0);
    $reserve_price = (float)($_POST['reserve_price'] ?? 0) ?: null;
    $buyout_price = (float)($_POST['buyout_price'] ?? 0) ?: null;
    $min_increment = (float)($_POST['min_increment'] ?? 100);
    $deposit_pct = 5;
    $city = sanitize($_POST['city'] ?? '');
    $starts_at = $_POST['starts_at'] ?? '';
    $ends_at = $_POST['ends_at'] ?? '';

    // اختيار الصورة من الفرس إن وُجد
    $main_image = 'assets/images/horses/default.jpg';
    if ($horse_id) {
        foreach ($user_horses as $h) if ((int)$h['id'] === $horse_id) { $main_image = $h['main_image']; break; }
    }
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['image']['size'] < 5 * 1024 * 1024) {
            $dir = __DIR__ . '/assets/images/horses/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = 'a_' . $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dir . $fname)) {
                $main_image = 'assets/images/horses/' . $fname;
            }
        }
    }

    if (strlen($title) < 4) $error = 'عنوان المزاد قصير';
    elseif (strlen($city) < 2) $error = 'حدد مدينة المزاد';
    elseif ($starting_price <= 0) $error = 'حدد سعر البداية';
    elseif (!$starts_at || !$ends_at) $error = 'حدد تاريخ البداية والنهاية';
    elseif (strtotime($ends_at) <= strtotime($starts_at)) $error = 'تاريخ النهاية يجب أن يكون بعد البداية';

    if (!$error) {
        $status = strtotime($starts_at) <= time() ? 'live' : 'scheduled';
        $ins = $conn->prepare("INSERT INTO auctions (seller_id, horse_id, title, description, main_image, city, starting_price, reserve_price, buyout_price, min_increment, deposit_pct, starts_at, ends_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($ins->execute([$user_id, $horse_id, $title, $description, $main_image, $city, $starting_price, $reserve_price, $buyout_price, $min_increment, $deposit_pct, $starts_at, $ends_at, $status])) {
            redirect('auction.php?id=' . $conn->lastInsertId());
        } else {
            $error = 'فشل إنشاء المزاد';
        }
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>➕ إنشاء مزاد جديد</h1>
        <p>اعرض فرسك أو أصلاً فروسياً للمزايدة العلنية</p>
    </div>
</div>

<div class="container">
    <div class="booking-card">
        <?php if ($error): ?><div class="alert alert-error">⚠️ <?= $error ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <h3 style="color: var(--gold); margin-bottom: 16px;">📝 معلومات المزاد</h3>
            <div class="form-group">
                <label>عنوان المزاد *</label>
                <input type="text" name="title" required class="form-control" placeholder="مثلاً: كحيلان الثالث - عربي أصيل" value="<?= sanitize($_POST['title'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>الوصف</label>
                <textarea name="description" rows="4" class="form-control" placeholder="تفاصيل الفرس، خبراته، حالته الصحية..."><?= sanitize($_POST['description'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label>مدينة المزاد *</label>
                <select name="city" required class="form-control">
                    <option value="">— اختر المدينة —</option>
                    <?php
                    $cities = ['رام الله','نابلس','الخليل','بيت لحم','جنين','طولكرم','قلقيلية','أريحا','سلفيت','طوباس','القدس','غزة'];
                    foreach ($cities as $c):
                        $sel = (($_POST['city'] ?? '') === $c) ? 'selected' : '';
                    ?>
                    <option value="<?= $c ?>" <?= $sel ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>اربطه بأحد خيولك (اختياري)</label>
                    <select name="horse_id" class="form-control">
                        <option value="">— مزاد عام —</option>
                        <?php foreach ($user_horses as $h): ?>
                            <option value="<?= $h['id'] ?>"><?= sanitize($h['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>صورة المزاد (اختياري — تأخذ صورة الفرس افتراضياً)</label>
                    <input type="file" name="image" accept="image/*" class="form-control">
                </div>
            </div>

            <h3 style="color: var(--gold); margin: 26px 0 16px;">💰 الأسعار</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>سعر البداية (₪) *</label>
                    <input type="number" name="starting_price" min="100" step="50" required class="form-control" value="<?= sanitize($_POST['starting_price'] ?? 1000) ?>">
                </div>
                <div class="form-group">
                    <label>الحد الأدنى للزيادة (₪)</label>
                    <input type="number" name="min_increment" min="10" step="10" class="form-control" value="<?= sanitize($_POST['min_increment'] ?? 100) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>السعر الاحتياطي (اختياري)</label>
                    <input type="number" name="reserve_price" min="0" step="50" class="form-control" placeholder="لن يُباع تحت هذا السعر" value="<?= sanitize($_POST['reserve_price'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>سعر الشراء الفوري (اختياري)</label>
                    <input type="number" name="buyout_price" min="0" step="50" class="form-control" placeholder="ينهي المزاد فوراً" value="<?= sanitize($_POST['buyout_price'] ?? '') ?>">
                </div>
            </div>

            <h3 style="color: var(--gold); margin: 26px 0 16px;">⏰ التوقيت</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>وقت البداية *</label>
                    <input type="datetime-local" name="starts_at" required class="form-control" value="<?= sanitize($_POST['starts_at'] ?? date('Y-m-d\TH:i')) ?>">
                </div>
                <div class="form-group">
                    <label>وقت الانتهاء *</label>
                    <input type="datetime-local" name="ends_at" required class="form-control" value="<?= sanitize($_POST['ends_at'] ?? date('Y-m-d\TH:i', strtotime('+3 days'))) ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">🔨 إنشاء المزاد</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
