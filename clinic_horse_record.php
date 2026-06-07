<?php
$page_title = 'السجل الطبي للحصان';
require_once 'config/db.php';

if (!isLoggedIn()) redirect('login.php');
$user_id   = (int)$_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'user';
if (!in_array($user_role, ['clinic', 'admin'])) redirect('index.php');

// جلب العيادة
$cl = $conn->prepare("SELECT * FROM clinics WHERE owner_id = ? LIMIT 1");
$cl->execute([$user_id]);
$clinic = $cl->fetch();
if (!$clinic) redirect('my-clinic.php');
$clinic_id = (int)$clinic['id'];

$horse_id = (int)($_GET['horse'] ?? 0);
if (!$horse_id) redirect('my-clinic.php?tab=patients');

// جلب بيانات الحصان
$hs = $conn->prepare("SELECT h.*, u.full_name AS owner_name, u.phone AS owner_phone FROM horses h LEFT JOIN users u ON u.id = h.owner_id WHERE h.id = ?");
$hs->execute([$horse_id]);
$horse = $hs->fetch();
if (!$horse) redirect('my-clinic.php?tab=patients');

// السجلات الصحية لهذه العيادة فقط
$records = $conn->prepare("SELECT * FROM horse_health_records WHERE horse_id = ? AND clinic_id = ? ORDER BY record_date DESC");
$records->execute([$horse_id, $clinic_id]);
$records = $records->fetchAll();

// اللقاحات لهذه العيادة فقط
$vaccines = $conn->prepare("SELECT * FROM horse_vaccinations WHERE horse_id = ? AND clinic_id = ? ORDER BY vaccine_date DESC");
$vaccines->execute([$horse_id, $clinic_id]);
$vaccines = $vaccines->fetchAll();

// الوصفات لهذه العيادة فقط
$prescriptions = $conn->prepare("SELECT * FROM prescriptions WHERE horse_id = ? AND clinic_id = ? ORDER BY issue_date DESC");
$prescriptions->execute([$horse_id, $clinic_id]);
$prescriptions = $prescriptions->fetchAll();

// المواعيد السابقة
$appointments = $conn->prepare("SELECT * FROM clinic_appointments WHERE horse_id = ? AND clinic_id = ? ORDER BY appointment_date DESC LIMIT 20");
$appointments->execute([$horse_id, $clinic_id]);
$appointments = $appointments->fetchAll();

$record_labels = ['disease'=>['🦠','مرض'],'checkup'=>['🔍','فحص'],'treatment'=>['💊','علاج'],'note'=>['📝','ملاحظة']];
$status_labels = ['pending'=>'⏳ معلق','confirmed'=>'✅ مؤكد','completed'=>'🏁 مكتمل','cancelled'=>'❌ ملغي'];

include 'includes/header.php';
?>

<div class="mc-wrap">
    <aside class="mc-sidebar">
        <div class="mc-brand">
            <img src="<?= sanitize($clinic['image']) ?>" alt="" onerror="this.src='assets/images/hero.jpg'">
            <div>
                <strong><?= sanitize($clinic['name']) ?></strong>
                <small>📍 <?= sanitize($clinic['city']) ?></small>
            </div>
        </div>
        <ul class="mc-nav">
            <li><a href="my-clinic.php?tab=patients">← العودة للمرضى</a></li>
            <li><a href="my-clinic.php">🏠 لوحة العيادة</a></li>
        </ul>
    </aside>

    <main class="mc-main">
        <!-- بيانات الحصان -->
        <div class="mc-header">
            <div style="display:flex; gap:18px; align-items:center;">
                <img src="<?= sanitize($horse['main_image']) ?>" onerror="this.src='assets/images/horses/h1.jpg'"
                     style="width:80px;height:80px;border-radius:12px;object-fit:cover;border:2px solid rgba(201,162,39,0.4);">
                <div>
                    <div class="mc-breadcrumb">لوحة العيادة / المرضى / السجل الطبي</div>
                    <h1>🐴 <?= sanitize($horse['name']) ?></h1>
                    <p>
                        <?= sanitize($horse['breed'] ?? '—') ?>
                        <?php if ($horse['birth_date']): ?>
                            · <?= (int)((time()-strtotime($horse['birth_date']))/(365.25*86400)) ?> سنة
                        <?php endif; ?>
                        · 👤 <strong><?= sanitize($horse['owner_name']) ?></strong>
                        <?php if ($horse['owner_phone']): ?>
                            · 📱 <span dir="ltr"><?= sanitize($horse['owner_phone']) ?></span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- إحصاء سريع -->
        <div class="mc-stats-grid">
            <div class="mc-stat" style="--c:#3498db;"><div class="mc-stat-ic">🩺</div><div><b><?= count($records) ?></b><small>سجل طبي</small></div></div>
            <div class="mc-stat" style="--c:#2ecc71;"><div class="mc-stat-ic">💉</div><div><b><?= count($vaccines) ?></b><small>لقاح</small></div></div>
            <div class="mc-stat" style="--c:#9b59b6;"><div class="mc-stat-ic">💊</div><div><b><?= count($prescriptions) ?></b><small>وصفة</small></div></div>
            <div class="mc-stat" style="--c:#c9a227;"><div class="mc-stat-ic">📅</div><div><b><?= count($appointments) ?></b><small>زيارة</small></div></div>
        </div>

        <!-- السجلات الصحية -->
        <div class="mc-panel">
            <div class="mc-panel-head"><h3>🩺 السجلات الصحية</h3></div>
            <?php if (empty($records)): ?>
                <div class="mc-empty">🩺<p>لا سجلات بعد.</p></div>
            <?php else: ?>
            <div class="mc-table-wrap">
                <table class="mc-table">
                    <thead><tr><th>النوع</th><th>العنوان</th><th>التاريخ</th><th>التفاصيل</th></tr></thead>
                    <tbody>
                    <?php foreach ($records as $r):
                        [$icon, $label] = $record_labels[$r['record_type']] ?? ['📋','—'];
                    ?>
                    <tr>
                        <td><span class="mc-pill" style="--c:#3498db;"><?= $icon ?> <?= $label ?></span></td>
                        <td><strong><?= sanitize($r['title']) ?></strong></td>
                        <td><?= date('d/m/Y', strtotime($r['record_date'])) ?></td>
                        <td style="max-width:300px; white-space:pre-wrap;"><?= sanitize($r['description'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- اللقاحات -->
        <div class="mc-panel">
            <div class="mc-panel-head"><h3>💉 اللقاحات</h3></div>
            <?php if (empty($vaccines)): ?>
                <div class="mc-empty">💉<p>لا لقاحات مسجّلة.</p></div>
            <?php else: ?>
            <div class="mc-table-wrap">
                <table class="mc-table">
                    <thead><tr><th>اللقاح</th><th>تاريخ التطعيم</th><th>الجرعة التالية</th><th>ملاحظات</th></tr></thead>
                    <tbody>
                    <?php foreach ($vaccines as $v): ?>
                    <tr>
                        <td><strong><?= sanitize($v['vaccine_name']) ?></strong></td>
                        <td><?= date('d/m/Y', strtotime($v['vaccine_date'])) ?></td>
                        <td><?= $v['next_due'] ? date('d/m/Y', strtotime($v['next_due'])) : '—' ?></td>
                        <td><?= sanitize($v['notes'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- الوصفات -->
        <div class="mc-panel">
            <div class="mc-panel-head"><h3>💊 الوصفات الطبية</h3></div>
            <?php if (empty($prescriptions)): ?>
                <div class="mc-empty">💊<p>لا وصفات مسجّلة.</p></div>
            <?php else: ?>
            <div class="mc-table-wrap">
                <table class="mc-table">
                    <thead><tr><th>الدواء</th><th>الجرعة</th><th>التكرار</th><th>المدة</th><th>تاريخ</th><th>تعليمات</th></tr></thead>
                    <tbody>
                    <?php foreach ($prescriptions as $pr): ?>
                    <tr>
                        <td><strong><?= sanitize($pr['medication']) ?></strong></td>
                        <td><?= sanitize($pr['dosage'] ?? '—') ?></td>
                        <td><?= sanitize($pr['frequency'] ?? '—') ?></td>
                        <td><?= sanitize($pr['duration'] ?? '—') ?></td>
                        <td><?= date('d/m/Y', strtotime($pr['issue_date'])) ?></td>
                        <td><?= sanitize($pr['instructions'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- المواعيد السابقة -->
        <div class="mc-panel">
            <div class="mc-panel-head"><h3>📅 المواعيد السابقة</h3></div>
            <?php if (empty($appointments)): ?>
                <div class="mc-empty">📅<p>لا مواعيد.</p></div>
            <?php else: ?>
            <div class="mc-table-wrap">
                <table class="mc-table">
                    <thead><tr><th>التاريخ</th><th>الوقت</th><th>السبب</th><th>الحالة</th><th>ملخص</th></tr></thead>
                    <tbody>
                    <?php foreach ($appointments as $ap): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($ap['appointment_date'])) ?></td>
                        <td><?= substr($ap['appointment_time'], 0, 5) ?></td>
                        <td><?= sanitize($ap['reason']) ?></td>
                        <td><span class="mc-pill" style="--c:#2ecc71;"><?= $status_labels[$ap['status']] ?? '—' ?></span></td>
                        <td><?= sanitize($ap['visit_summary'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<?php include 'includes/footer.php'; ?>
