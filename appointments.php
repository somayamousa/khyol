<?php
$page_title = 'مواعيدي البيطرية';
require_once 'config/db.php';

if (!isLoggedIn()) redirect('login.php?redirect=appointments');

$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $app_id = (int)$_POST['appointment_id'];
    $upd = $conn->prepare("UPDATE clinic_appointments SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status IN ('pending','confirmed')");
    $upd->execute([$app_id, $user_id]);
    redirect('appointments.php');
}

$stmt = $conn->prepare("
    SELECT ca.*, cl.name AS clinic_name, cl.phone AS clinic_phone, cl.city,
           h.name AS horse_name
    FROM clinic_appointments ca
    JOIN clinics cl ON cl.id = ca.clinic_id
    LEFT JOIN horses h ON h.id = ca.horse_id
    WHERE ca.user_id = ?
    ORDER BY ca.appointment_date DESC, ca.appointment_time DESC
");
$stmt->execute([$user_id]);
$appointments = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>📅 مواعيدي البيطرية</h1>
        <p>قائمة جميع حجوزاتك مع العيادات البيطرية</p>
    </div>
</div>

<div class="container" style="padding: 40px 0;">
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">✅ تم حجز الموعد بنجاح! سيتم التأكيد قريباً من العيادة.</div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h2>عدد المواعيد: <?= count($appointments) ?></h2>
        <a href="clinics.php" class="btn btn-primary">➕ احجز موعد جديد</a>
    </div>

    <?php if (empty($appointments)): ?>
        <div class="empty-state">
            <span style="font-size: 64px;">📅</span>
            <h2>لا توجد مواعيد بعد</h2>
            <p>احجز موعدك الأول مع إحدى العيادات البيطرية</p>
            <a href="clinics.php" class="btn btn-primary">تصفح العيادات</a>
        </div>
    <?php else: ?>
        <div class="appointments-list">
            <?php foreach ($appointments as $a):
                $status_colors = ['pending' => '#f39c12', 'confirmed' => 'var(--green)', 'completed' => '#3498db', 'cancelled' => 'var(--red)'];
                $status_labels = ['pending' => '⏳ قيد الانتظار', 'confirmed' => '✅ مؤكد', 'completed' => '🏁 منجز', 'cancelled' => '❌ ملغى'];
                $can_cancel = in_array($a['status'], ['pending','confirmed']) && $a['appointment_date'] >= date('Y-m-d');
            ?>
            <div class="appointment-card">
                <div class="appointment-date">
                    <div class="app-day"><?= date('d', strtotime($a['appointment_date'])) ?></div>
                    <div class="app-month"><?= ['','يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'][(int)date('n', strtotime($a['appointment_date']))] ?></div>
                    <div class="app-time">🕐 <?= substr($a['appointment_time'], 0, 5) ?></div>
                </div>
                <div class="appointment-info">
                    <h3><?= sanitize($a['clinic_name']) ?> <small style="color: var(--text-dark-muted);">— <?= sanitize($a['city']) ?></small></h3>
                    <?php if ($a['horse_name']): ?>
                        <div style="color: var(--gold); margin: 5px 0;">🐎 للخيل: <?= sanitize($a['horse_name']) ?></div>
                    <?php endif; ?>
                    <div><strong>السبب:</strong> <?= sanitize($a['reason']) ?></div>
                    <?php if ($a['notes']): ?><div style="color: var(--text-dark-muted); font-size: 14px; margin-top: 5px;"><?= sanitize($a['notes']) ?></div><?php endif; ?>
                    <div style="margin-top: 10px;">
                        <span class="badge" style="background: <?= $status_colors[$a['status']] ?>;"><?= $status_labels[$a['status']] ?></span>
                        <span style="color: var(--text-dark-muted); margin-right: 10px;">📱 <?= sanitize($a['clinic_phone']) ?></span>
                    </div>
                </div>
                <?php if ($can_cancel): ?>
                <div>
                    <form method="POST" onsubmit="return confirm('هل أنت متأكد من إلغاء الموعد؟');">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                        <button type="submit" class="btn btn-outline" style="border-color: var(--red); color: var(--red);">❌ إلغاء</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
