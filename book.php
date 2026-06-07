<?php
$page_title = 'حجز درس';
require_once 'config/db.php';
require_once 'includes/notify.php';

if (!isLoggedIn()) redirect('login.php?redirect=' . urlencode('book.php?center=' . (int)($_GET['center'] ?? 0) . '&service=' . (int)($_GET['service'] ?? 0)));

$center_id = (int)($_GET['center'] ?? 0);
$service_id = (int)($_GET['service'] ?? 0);

$stmt = $conn->prepare("SELECT s.*, c.name AS center_name, c.id AS center_id, c.city AS center_city FROM services s JOIN centers c ON s.center_id = c.id WHERE s.id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if (!$service) redirect('centers.php');

// خيول المستخدم لاختيار واحد منها (اختياري)
$horses_stmt = $conn->prepare("SELECT id, name, breed FROM horses WHERE owner_id = ? ORDER BY name");
$horses_stmt->execute([$_SESSION['user_id']]);
$user_horses = $horses_stmt->fetchAll();

$success = false;
$error = '';
$skill_options = [
    'jumping'    => ['🏇 القفز', 'jumping'],
    'dressage'   => ['🎯 ترويض', 'dressage'],
    'canter'     => ['🐎 الكانتر', 'canter'],
    'khabab'     => ['💨 الخبب', 'khabab'],
    'trail'      => ['🌲 ركوب الدروب', 'trail'],
    'racing'     => ['🏁 السباق', 'racing'],
    'general'    => ['🪄 ركوب عام', 'general'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $notes = sanitize($_POST['notes'] ?? '');
    $rider_level = in_array($_POST['rider_level'] ?? '', ['beginner','intermediate','advanced']) ? $_POST['rider_level'] : 'beginner';
    $picked_skills = array_intersect((array)($_POST['skills'] ?? []), array_keys($skill_options));
    $skills_csv = $picked_skills ? implode(',', $picked_skills) : null;
    $horse_id = (int)($_POST['horse_id'] ?? 0) ?: null;
    $preferred_horse_name = sanitize($_POST['preferred_horse_name'] ?? '');
    $weather_notify = isset($_POST['weather_notify']) ? 1 : 0;

    // تحقق أن الخيل من خيول المستخدم
    if ($horse_id) {
        $valid = false;
        foreach ($user_horses as $h) { if ((int)$h['id'] === $horse_id) { $valid = true; break; } }
        if (!$valid) $horse_id = null;
    }

    if (!$date || !$time) {
        $error = 'الرجاء اختيار التاريخ والوقت.';
    } elseif (strtotime($date) < strtotime(date('Y-m-d'))) {
        $error = 'لا يمكن الحجز لتاريخ في الماضي.';
    } else {
        $ins = $conn->prepare("INSERT INTO bookings (user_id, center_id, service_id, rider_level, skills, horse_id, preferred_horse_name, weather_notify, booking_date, booking_time, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($ins->execute([$_SESSION['user_id'], $service['center_id'], $service_id, $rider_level, $skills_csv, $horse_id, $preferred_horse_name, $weather_notify, $date, $time, $notes])) {
            $success = true;
            // إشعار للمستخدم
            $horse_label = $preferred_horse_name ?: 'سيتم تخصيص الفرس لاحقاً';
            send_notification(
                $conn,
                (int)$_SESSION['user_id'],
                'تم استلام طلب الحجز',
                'حصة في ' . $service['center_name'] . ' بتاريخ ' . $date . ' الساعة ' . substr($time,0,5) . ' — الفرس: ' . $horse_label,
                'booking',
                '🏇',
                'account.php?tab=bookings'
            );

            // إشعار لصاحب المركز
            $owner = $conn->prepare("SELECT owner_id FROM centers WHERE id = ?");
            $owner->execute([$service['center_id']]);
            $owner_id = (int)$owner->fetchColumn();
            if ($owner_id) {
                send_notification(
                    $conn,
                    $owner_id,
                    'حجز جديد! 📅',
                    $_SESSION['user_name'] . ' حجز "' . $service['name'] . '" بتاريخ ' . $date . ' الساعة ' . substr($time, 0, 5),
                    'booking',
                    '📩',
                    'my-center.php?tab=bookings&bk_status=pending'
                );
            }
        } else {
            $error = 'حدث خطأ أثناء الحجز.';
        }
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>📅 حجز درس</h1>
        <p>احجز درسك بسهولة وسرعة</p>
    </div>
</div>

<div class="container">
    <div class="booking-card">
        <?php if ($success): ?>
        <div style="text-align: center; padding: 30px;">
            <div style="font-size: 80px; margin-bottom: 20px;">✅</div>
            <h2 style="color: var(--gold); margin-bottom: 15px;">تم الحجز بنجاح!</h2>
            <p style="color: var(--text-dark-muted); line-height: 2; margin-bottom: 25px;">
                تم إرسال طلب الحجز وسيتم التواصل معك قريباً من <strong style="color: var(--gold);"><?= sanitize($service['center_name']) ?></strong> لتأكيد الموعد.
            </p>
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="account.php?tab=bookings" class="btn btn-primary">عرض حجوزاتي</a>
                <a href="centers.php" class="btn btn-outline">المزيد من المراكز</a>
            </div>
        </div>
        <?php else: ?>

        <h2 style="color: var(--gold); margin-bottom: 25px; text-align: center;">📝 بيانات الحجز</h2>

        <div class="booking-summary">
            <h4>🏇 <?= sanitize($service['center_name']) ?></h4>
            <p><strong>الخدمة:</strong> <?= $service['icon'] ?> <?= sanitize($service['name']) ?></p>
            <p><strong>المدة:</strong> <?= sanitize($service['duration']) ?></p>
            <p><strong>السعر:</strong> <span style="color: var(--gold); font-weight: 900;"><?= number_format($service['price'], 0) ?> ₪</span></p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error"><span>⚠️</span><div><?= $error ?></div></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>📅 التاريخ</label>
                    <input type="date" name="date" class="form-control" required min="<?= date('Y-m-d') ?>" value="<?= sanitize($_POST['date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>🕐 الوقت</label>
                    <select name="time" class="form-control" required>
                        <option value="">اختر الوقت</option>
                        <?php for($h=8; $h<=20; $h++): ?>
                            <option value="<?= sprintf('%02d:00',$h) ?>" <?= (($_POST['time'] ?? '')==sprintf('%02d:00',$h))?'selected':'' ?>><?= sprintf('%02d:00', $h) ?></option>
                            <option value="<?= sprintf('%02d:30',$h) ?>" <?= (($_POST['time'] ?? '')==sprintf('%02d:30',$h))?'selected':'' ?>><?= sprintf('%02d:30', $h) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>🎓 مستوى الفارس</label>
                <div class="level-pills">
                    <?php
                    $levels = ['beginner'=>'مبتدئ 🌱', 'intermediate'=>'متوسط ⚡', 'advanced'=>'محترف 🏆'];
                    $current_level = $_POST['rider_level'] ?? 'beginner';
                    foreach ($levels as $lv => $lbl): ?>
                        <label class="level-pill <?= $current_level === $lv ? 'active':'' ?>">
                            <input type="radio" name="rider_level" value="<?= $lv ?>" <?= $current_level === $lv ? 'checked':'' ?>>
                            <span><?= $lbl ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label>🎯 المهارات المطلوب التدرب عليها (اختر واحدة أو أكثر)</label>
                <div class="skills-grid">
                    <?php
                    $picked = (array)($_POST['skills'] ?? []);
                    foreach ($skill_options as $key => [$lbl, $val]): ?>
                        <label class="skill-chip <?= in_array($key, $picked) ? 'active':'' ?>">
                            <input type="checkbox" name="skills[]" value="<?= $key ?>" <?= in_array($key, $picked) ? 'checked':'' ?>>
                            <span><?= $lbl ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>🐎 اختر فرسك (إن أردت إحضاره)</label>
                    <select name="horse_id" class="form-control">
                        <option value="">— لا أحضر فرس / يخصص لي من المركز —</option>
                        <?php foreach ($user_horses as $h): ?>
                            <option value="<?= $h['id'] ?>" <?= (($_POST['horse_id'] ?? '') == $h['id'])?'selected':'' ?>>
                                <?= sanitize($h['name']) ?> <?= $h['breed'] ? '— '.sanitize($h['breed']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>🏷️ اسم الفرس المفضل من المركز (اختياري)</label>
                    <input type="text" name="preferred_horse_name" class="form-control" value="<?= sanitize($_POST['preferred_horse_name'] ?? '') ?>" placeholder="مثلاً: صقر، نجمة...">
                </div>
            </div>

            <div class="form-group">
                <label>📝 ملاحظات إضافية (اختياري)</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="أي ملاحظات تريد إضافتها..."><?= sanitize($_POST['notes'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="weather-opt">
                    <input type="checkbox" name="weather_notify" value="1" <?= (isset($_POST['weather_notify']) || $_SERVER['REQUEST_METHOD'] !== 'POST') ? 'checked':'' ?>>
                    <span>🌤️ أرسل لي تذكير بالحصة قبل ٢٤ ساعة مع حالة الطقس واسم الفرس</span>
                </label>
            </div>

            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; padding: 15px;">✅ تأكيد الحجز</button>
                <a href="center.php?id=<?= $service['center_id'] ?>" class="btn btn-outline">إلغاء</a>
            </div>
        </form>

        <script>
        // تفعيل/إلغاء النشاط البصري للـ pills
        document.querySelectorAll('.level-pill input').forEach(r => {
            r.addEventListener('change', function() {
                document.querySelectorAll('.level-pill').forEach(p => p.classList.remove('active'));
                this.closest('.level-pill').classList.add('active');
            });
        });
        document.querySelectorAll('.skill-chip input').forEach(c => {
            c.addEventListener('change', function() {
                this.closest('.skill-chip').classList.toggle('active', this.checked);
            });
        });
        </script>

        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
