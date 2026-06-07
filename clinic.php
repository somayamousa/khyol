<?php
require_once 'config/db.php';
require_once 'includes/notify.php';

$id = (int)($_GET['id'] ?? 0);
$preselect_horse = (int)($_GET['horse'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM clinics WHERE id = ?");
$stmt->execute([$id]);
$clinic = $stmt->fetch();

if (!$clinic) redirect('clinics.php');

$page_title = $clinic['name'];

$success = false;
$errors = [];

// ===== حجز موعد =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $user_id = (int)$_SESSION['user_id'];
    $horse_id = (int)($_POST['horse_id'] ?? 0) ?: null;
    $date = $_POST['appointment_date'] ?? '';
    $time = $_POST['appointment_time'] ?? '';
    $reason = sanitize($_POST['reason'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');

    if (!$date || !strtotime($date)) $errors[] = 'التاريخ غير صحيح';
    if ($date < date('Y-m-d')) $errors[] = 'لا يمكن اختيار تاريخ ماضٍ';
    if (!$time) $errors[] = 'الوقت مطلوب';
    if (strlen($reason) < 3) $errors[] = 'سبب الزيارة مطلوب';

    // تحقق من تعارض الوقت
    if ($date && $time) {
        $conflict = $conn->prepare("
            SELECT COUNT(*) FROM clinic_appointments
            WHERE clinic_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'cancelled'
        ");
        $conflict->execute([$id, $date, $time]);
        if ($conflict->fetchColumn() > 0) $errors[] = 'هذا الوقت محجوز مسبقاً، الرجاء اختيار وقت آخر.';
    }

    // تأكيد أن الخيل ملك المستخدم
    if ($horse_id) {
        $h_check = $conn->prepare("SELECT id FROM horses WHERE id = ? AND owner_id = ?");
        $h_check->execute([$horse_id, $user_id]);
        if (!$h_check->fetch()) $horse_id = null;
    }

    if (empty($errors)) {
        $ins = $conn->prepare("INSERT INTO clinic_appointments (user_id, clinic_id, horse_id, appointment_date, appointment_time, reason, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$user_id, $id, $horse_id, $date, $time, $reason, $notes]);

        // إشعار للعميل
        send_notification(
            $conn,
            $user_id,
            'تم حجز موعد في العيادة',
            '"' . $clinic['name'] . '" بتاريخ ' . $date . ' الساعة ' . substr($time,0,5) . ' — ' . $reason,
            'clinic',
            '🏥',
            'appointments.php'
        );

        // إشعار للبيطري (صاحب العيادة)
        if (!empty($clinic['owner_id'])) {
            send_notification(
                $conn,
                (int)$clinic['owner_id'],
                'موعد جديد! 🏥',
                'حجز موعد في "' . $clinic['name'] . '" بتاريخ ' . $date . ' الساعة ' . substr($time,0,5) . ' — ' . $reason,
                'clinic',
                '📅',
                'my-clinic.php?tab=appointments'
            );
        }

        redirect('appointments.php?success=1');
    }
}

// خيول المستخدم
$user_horses = [];
if (isLoggedIn()) {
    $hs = $conn->prepare("SELECT id, name FROM horses WHERE owner_id = ? ORDER BY name");
    $hs->execute([$_SESSION['user_id']]);
    $user_horses = $hs->fetchAll();
}

include 'includes/header.php';
?>

<div class="clinic-hero">
    <img src="<?= sanitize($clinic['image']) ?>" onerror="this.onerror=null;this.src='assets/images/hero.jpg';">
    <div class="container clinic-hero-inner">
        <h1><?= sanitize($clinic['name']) ?></h1>
        <div class="clinic-hero-meta">
            <span>📍 <?= sanitize($clinic['city']) ?></span>
            <span>⭐ <?= $clinic['rating'] ?> (<?= $clinic['reviews_count'] ?> تقييم)</span>
            <span>🕐 <?= sanitize($clinic['opening_hours']) ?></span>
        </div>
    </div>
</div>

<div class="container" style="padding: 30px 0;">
    <div class="clinic-layout">
        <main>
            <section class="info-card">
                <h2 style="color: var(--gold);">📖 عن العيادة</h2>
                <p style="line-height: 2; color: var(--text-dark);"><?= nl2br(sanitize($clinic['description'])) ?></p>
            </section>

            <section class="info-card">
                <h2 style="color: var(--gold);">👨‍⚕️ الطبيب البيطري</h2>
                <div style="font-size: 18px; margin-top: 10px;"><strong><?= sanitize($clinic['vet_name']) ?></strong></div>
                <div style="color: var(--text-dark-muted); margin-top: 8px;">🎯 <?= sanitize($clinic['specialization']) ?></div>
            </section>

            <section class="info-card">
                <h2 style="color: var(--gold);">✨ الخدمات</h2>
                <ul style="line-height: 2.2; list-style: none;">
                    <li>✅ فحص دوري شامل</li>
                    <li>✅ علاج الأمراض والإصابات</li>
                    <li>✅ اللقاحات والتطعيمات</li>
                    <li>✅ عمليات جراحية</li>
                    <?php if ($clinic['emergency_available']): ?><li>🚨 طوارئ على مدار 24 ساعة</li><?php endif; ?>
                    <?php if ($clinic['home_visit']): ?><li>🏠 زيارات منزلية</li><?php endif; ?>
                </ul>
            </section>

            <!-- نموذج الحجز -->
            <section class="info-card" id="book" style="border: 2px solid var(--gold);">
                <h2 style="color: var(--gold);">📅 احجز موعد</h2>

                <?php if (!isLoggedIn()): ?>
                    <div class="alert alert-info">
                        للحجز يجب <a href="login.php?redirect=clinic.php?id=<?= $id ?>">تسجيل الدخول</a> أولاً
                    </div>
                <?php elseif (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $e): ?><div><?= $e ?></div><?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (isLoggedIn()): ?>
                <form method="POST" class="booking-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>الخيل المطلوب فحصه</label>
                            <select name="horse_id">
                                <option value="">— اختر خيل (اختياري) —</option>
                                <?php foreach ($user_horses as $h): ?>
                                    <option value="<?= $h['id'] ?>" <?= $preselect_horse === (int)$h['id'] ? 'selected' : '' ?>>🐎 <?= sanitize($h['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($user_horses)): ?>
                                <small style="color: var(--text-dark-muted);">ليس لديك خيول مسجلة. <a href="horse_edit.php">أضف خيل</a></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>التاريخ *</label>
                            <input type="date" name="appointment_date" id="clinicDate" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>الوقت *</label>
                            <select name="appointment_time" id="clinicTime" required>
                                <option value="">اختر تاريخاً أولاً</option>
                            </select>
                            <small id="clinicSlotHint" style="color:var(--gold);font-size:12px;margin-top:4px;display:none;">
                                ⚠️ الأوقات الرمادية محجوزة مسبقاً
                            </small>
                        </div>
                    </div>

                    <script>
                    (function(){
                        var dateEl = document.getElementById('clinicDate');
                        var timeEl = document.getElementById('clinicTime');
                        var hint   = document.getElementById('clinicSlotHint');
                        var clinicId = <?= $id ?>;
                        var allSlots = <?php
                            $slots = [];
                            for ($h = 8; $h <= 20; $h++) $slots[] = sprintf('%02d:00', $h);
                            echo json_encode($slots);
                        ?>;

                        function buildSlots(booked) {
                            timeEl.innerHTML = '';
                            var hasBooked = booked.length > 0;
                            allSlots.forEach(function(s) {
                                var isBooked = booked.indexOf(s) !== -1;
                                var opt = document.createElement('option');
                                opt.value = s;
                                opt.textContent = isBooked ? s + ' — محجوز' : s;
                                opt.disabled = isBooked;
                                if (isBooked) opt.style.color = '#aaa';
                                timeEl.appendChild(opt);
                            });
                            hint.style.display = hasBooked ? 'block' : 'none';
                        }

                        dateEl.addEventListener('change', function() {
                            var d = this.value;
                            if (!d) { timeEl.innerHTML = '<option value="">اختر تاريخاً أولاً</option>'; return; }
                            fetch('get_booked_slots.php?type=clinic&id=' + clinicId + '&date=' + d)
                                .then(function(r){ return r.json(); })
                                .then(function(booked){ buildSlots(booked); })
                                .catch(function(){ buildSlots([]); });
                        });
                    })();
                    </script>

                    <div class="form-group">
                        <label>سبب الزيارة *</label>
                        <input type="text" name="reason" placeholder="مثل: فحص دوري، مغص، إصابة..." required>
                    </div>

                    <div class="form-group">
                        <label>ملاحظات إضافية</label>
                        <textarea name="notes" rows="3"></textarea>
                    </div>

                    <div class="alert alert-info" style="background: rgba(201,162,39,0.1); border: 1px solid var(--gold); color: var(--text-dark);">
                        💰 رسوم الاستشارة: <strong><?= number_format($clinic['consultation_fee'], 0) ?> ₪</strong>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">📅 تأكيد الحجز</button>
                </form>
                <?php endif; ?>
            </section>
        </main>

        <aside class="center-sidebar">
            <h3>📞 تواصل مع العيادة</h3>
            <div class="contact-row"><span class="icon">📍</span><div><?= sanitize($clinic['address']) ?></div></div>
            <div class="contact-row"><span class="icon">📱</span><div><?= sanitize($clinic['phone']) ?></div></div>
            <?php if ($clinic['email']): ?>
                <div class="contact-row"><span class="icon">✉️</span><div style="font-size: 13px;"><?= sanitize($clinic['email']) ?></div></div>
            <?php endif; ?>
            <div class="contact-row"><span class="icon">🕐</span><div><?= sanitize($clinic['opening_hours']) ?></div></div>
            <div class="contact-row"><span class="icon">💰</span><div><?= number_format($clinic['consultation_fee'], 0) ?> ₪ للاستشارة</div></div>

            <div style="margin-top: 20px;">
                <a href="#book" class="btn btn-primary btn-block">📅 احجز موعد</a>
                <?php
                $clinic_owner = (int)($clinic['owner_id'] ?? 0);
                $me = (int)($_SESSION['user_id'] ?? 0);
                if (isLoggedIn() && $clinic_owner && $clinic_owner !== $me): ?>
                    <a href="chat.php?user_id=<?= $clinic_owner ?>" class="btn btn-outline btn-block" style="margin-top: 10px;">💬 محادثة مع البيطري</a>
                <?php elseif (!isLoggedIn()): ?>
                    <a href="login.php?redirect=<?= urlencode('chat.php?user_id=' . $clinic_owner) ?>" class="btn btn-outline btn-block" style="margin-top: 10px;">💬 محادثة مع البيطري</a>
                <?php endif; ?>
            </div>

            <?php if ($clinic['emergency_available']): ?>
                <div class="alert alert-error" style="margin-top: 20px; text-align: center;">
                    🚨 <strong>طوارئ 24/7</strong><br>
                    اتصل على الفور
                </div>
            <?php endif; ?>
        </aside>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
