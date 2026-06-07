<?php
$page_title = 'إيواء وتدريب الخيل';
require_once 'config/db.php';
require_once 'includes/notify.php';

$logged = isLoggedIn();
$success = false;
$error = '';

if ($logged) {
    $horses_stmt = $conn->prepare("SELECT id, name FROM horses WHERE owner_id = ?");
    $horses_stmt->execute([$_SESSION['user_id']]);
    $user_horses = $horses_stmt->fetchAll();

    $centers = $conn->query("SELECT id, name, city FROM centers ORDER BY rating DESC")->fetchAll();

    $my_agreements = $conn->prepare("
        SELECT b.*, c.name AS center_name, h.name AS horse_name
        FROM boarding_agreements b
        JOIN centers c ON c.id = b.center_id
        JOIN horses h ON h.id = b.horse_id
        WHERE b.owner_id = ?
        ORDER BY b.created_at DESC
    ");
    $my_agreements->execute([$_SESSION['user_id']]);
    $my_agreements = $my_agreements->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $center_id = (int)($_POST['center_id'] ?? 0);
        $horse_id = (int)($_POST['horse_id'] ?? 0);
        $monthly_fee = (float)($_POST['monthly_fee'] ?? 0);
        $share = (float)($_POST['trainer_share_pct'] ?? 20);
        $start_date = $_POST['start_date'] ?? '';
        $notes = sanitize($_POST['notes'] ?? '');

        $valid_horse = false;
        foreach ($user_horses as $h) if ((int)$h['id'] === $horse_id) { $valid_horse = true; break; }

        if (!$center_id || !$valid_horse || !$start_date) {
            $error = 'الرجاء تعبئة كل الحقول المطلوبة';
        } else {
            $ins = $conn->prepare("INSERT INTO boarding_agreements (owner_id, center_id, horse_id, monthly_fee, trainer_share_pct, start_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($ins->execute([$_SESSION['user_id'], $center_id, $horse_id, $monthly_fee, $share, $start_date, $notes])) {
                $success = true;

                // إشعار للعميل
                send_notification(
                    $conn,
                    (int)$_SESSION['user_id'],
                    'تم إرسال طلب الإيواء',
                    'طلبك تحت المراجعة من المركز. سنخبرك بقرارهم قريباً.',
                    'boarding', '🏇', 'account.php?tab=boarding'
                );

                // إشعار لصاحب المركز
                $center_owner = $conn->prepare("SELECT owner_id, name FROM centers WHERE id = ?");
                $center_owner->execute([$center_id]);
                $co = $center_owner->fetch();
                if ($co && $co['owner_id']) {
                    $horse_name = '';
                    foreach ($user_horses as $h) if ((int)$h['id'] === $horse_id) { $horse_name = $h['name']; break; }
                    send_notification(
                        $conn,
                        (int)$co['owner_id'],
                        'طلب إيواء جديد! 🏇',
                        'طلب إيواء للفرس "' . $horse_name . '" في "' . $co['name'] . '" ابتداءً من ' . $start_date,
                        'boarding', '📩', 'my-center.php?tab=boarding'
                    );
                }
            } else {
                $error = 'حدث خطأ';
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>🏇 إيواء وتدريب الخيل</h1>
        <p>أودع فرسك في النادي، وندربه مع الفرسان المشاركين، ونتقاسم الفائدة بنسبة عادلة</p>
    </div>
</div>

<div class="container" style="padding: 40px 0;">
    <div class="info-card" style="margin-bottom:25px;">
        <h3>🤝 كيف تعمل الخدمة؟</h3>
        <ul style="line-height:2; color: var(--text-dark);">
            <li>📥 تسجل طلب إيواء فرسك مع المركز المختار وتختار النسبة المتفق عليها.</li>
            <li>🩺 المركز يتكفل بالعناية اليومية (طعام، تنظيف، فحص أساسي).</li>
            <li>🎓 الفرس متاح للمشتركين تحت إشراف مدرب، وأنت تستفيد من نسبة من إيرادات الحصص.</li>
            <li>📜 الفرس يبقى مُلكاً لك طوال فترة الاتفاقية.</li>
        </ul>
    </div>

    <?php if (!$logged): ?>
        <div class="empty-state">
            <p>سجّل الدخول أولاً لتقديم طلب إيواء</p>
            <a href="login.php?redirect=boarding" class="btn btn-primary">دخول</a>
        </div>
    <?php elseif ($success): ?>
        <div class="alert alert-success">✅ تم إرسال طلب الإيواء بنجاح. سيتم التواصل معك من المركز قريباً.</div>
    <?php endif; ?>

    <?php if ($logged): ?>
        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?= $error ?></div>
        <?php endif; ?>

        <?php if (empty($user_horses)): ?>
            <div class="empty-state">
                <p>أضف فرسك أولاً قبل تقديم طلب إيواء</p>
                <a href="horse_edit.php" class="btn btn-primary">➕ إضافة خيل</a>
            </div>
        <?php else: ?>
        <div class="form-card" style="background: var(--bg-2); padding: 30px; border-radius: 16px; border: 1px solid rgba(201,162,39,0.15); margin-bottom: 30px;">
            <h3 style="color: var(--gold); margin-bottom: 20px;">📝 طلب إيواء جديد</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>المركز</label>
                        <select name="center_id" required class="form-control">
                            <option value="">اختر المركز</option>
                            <?php foreach ($centers as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?> — <?= sanitize($c['city']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>الفرس</label>
                        <select name="horse_id" required class="form-control">
                            <option value="">اختر الفرس</option>
                            <?php foreach ($user_horses as $h): ?>
                                <option value="<?= $h['id'] ?>"><?= sanitize($h['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>الرسم الشهري المقترح (₪)</label>
                        <input type="number" min="0" step="50" name="monthly_fee" value="500" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>نسبة المركز من إيرادات التدريب (%)</label>
                        <input type="number" min="0" max="100" step="1" name="trainer_share_pct" value="20" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>تاريخ البدء</label>
                        <input type="date" name="start_date" required min="<?= date('Y-m-d') ?>" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>ملاحظات (تغذية خاصة، نشاطات مرغوبة، إلخ)</label>
                    <textarea name="notes" rows="3" class="form-control"></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-block">📩 إرسال الطلب</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if (!empty($my_agreements)): ?>
        <h3 style="color: var(--gold); margin-bottom: 15px;">📋 طلباتي السابقة</h3>
        <div class="bookings-cards">
            <?php foreach ($my_agreements as $a):
                $st_color = ['pending'=>'#f39c12','active'=>'var(--green)','ended'=>'#3498db','rejected'=>'var(--red)'][$a['status']] ?? '#888';
                $st_label = ['pending'=>'⏳ قيد المراجعة','active'=>'✅ نشط','ended'=>'🏁 منتهٍ','rejected'=>'❌ مرفوض'][$a['status']] ?? $a['status'];
            ?>
            <div class="booking-card-v2 <?= $a['status']==='pending'?'upcoming':'' ?>">
                <div class="bc-date" style="background: linear-gradient(135deg,#3498db,#2c3e50); color: white;">
                    <span class="bc-day"><?= date('d', strtotime($a['start_date'])) ?></span>
                    <span class="bc-month">بدء</span>
                </div>
                <div class="bc-body">
                    <div class="bc-head">
                        <h3>🐎 <?= sanitize($a['horse_name']) ?> @ <?= sanitize($a['center_name']) ?></h3>
                        <span class="status-badge" style="background: <?= $st_color ?>; color: white;"><?= $st_label ?></span>
                    </div>
                    <div class="bc-meta">
                        <span>💰 رسم شهري: <strong><?= number_format($a['monthly_fee'],0) ?> ₪</strong></span>
                        <span>📊 نسبة المركز: <strong><?= $a['trainer_share_pct'] ?>%</strong></span>
                        <?php if ($a['end_date']): ?><span>🗓️ ينتهي: <?= $a['end_date'] ?></span><?php endif; ?>
                    </div>
                    <?php if ($a['notes']): ?><div style="color: var(--text-dark-muted); font-size: 14px;"><?= sanitize($a['notes']) ?></div><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
