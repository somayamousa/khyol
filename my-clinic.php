<?php
$page_title = 'لوحة العيادة';
require_once 'config/db.php';

if (!isLoggedIn()) redirect('login.php?redirect=my-clinic');

$user_id = (int)$_SESSION['user_id'];

$c_stmt = $conn->prepare("SELECT * FROM clinics WHERE owner_id = ? LIMIT 1");
$c_stmt->execute([$user_id]);
$clinic = $c_stmt->fetch();

if (!$clinic) {
    redirect('register.php?type=clinic');
}

$clinic_id = (int)$clinic['id'];
$tab = $_GET['tab'] ?? 'overview';
$errors = [];

// ==================== POST ACTIONS ====================

// تحديث بيانات العيادة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = sanitize($_POST['name'] ?? '');
    $vet_name = sanitize($_POST['vet_name'] ?? '');
    $specialization = sanitize($_POST['specialization'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $opening_hours = sanitize($_POST['opening_hours'] ?? '');
    $consultation_fee = (float)($_POST['consultation_fee'] ?? 0);
    $emergency = isset($_POST['emergency_available']) ? 1 : 0;
    $home_visit = isset($_POST['home_visit']) ? 1 : 0;
    $license = sanitize($_POST['license_number'] ?? '');

    if (strlen($name) < 3) $errors[] = 'اسم العيادة قصير.';
    if (strlen($vet_name) < 3) $errors[] = 'اسم الطبيب البيطري مطلوب.';

    $image_path = $clinic['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['image']['size'] < 5 * 1024 * 1024) {
            $dir = __DIR__ . '/assets/images/clinics/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = 'cl_' . $clinic_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dir . $fname)) {
                $image_path = 'assets/images/clinics/' . $fname;
            }
        }
    }

    if (empty($errors)) {
        $upd = $conn->prepare("
            UPDATE clinics
            SET name=?, vet_name=?, specialization=?, description=?, city=?, address=?, phone=?, email=?,
                opening_hours=?, consultation_fee=?, emergency_available=?, home_visit=?, license_number=?, image=?
            WHERE id=? AND owner_id=?
        ");
        $upd->execute([$name, $vet_name, $specialization, $description, $city, $address, $phone, $email,
            $opening_hours, $consultation_fee, $emergency, $home_visit, $license, $image_path, $clinic_id, $user_id]);
        redirect('my-clinic.php?tab=profile&updated=1');
    }
}

// تغيير حالة موعد
if (isset($_GET['appt_action'], $_GET['aid'])) {
    $aid = (int)$_GET['aid'];
    $action = $_GET['appt_action'];
    $new_status = ['confirm'=>'confirmed','reject'=>'cancelled','complete'=>'completed'][$action] ?? null;
    if ($new_status) {
        $upd = $conn->prepare("UPDATE clinic_appointments SET status = ? WHERE id = ? AND clinic_id = ?");
        $upd->execute([$new_status, $aid, $clinic_id]);
        redirect('my-clinic.php?tab=appointments&appt_updated=1');
    }
}

// توثيق زيارة (سجل صحي + لقاح + وصفة + شهادة)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['document_visit'])) {
    $aid = (int)($_POST['appointment_id'] ?? 0);
    $horse_id = (int)($_POST['horse_id'] ?? 0);
    $client_uid = (int)($_POST['client_user_id'] ?? 0);

    // تحقق إن الموعد تبع العيادة (إذا موجود)
    if ($aid) {
        $chk = $conn->prepare("SELECT horse_id, user_id FROM clinic_appointments WHERE id=? AND clinic_id=?");
        $chk->execute([$aid, $clinic_id]);
        $ap = $chk->fetch();
        if ($ap) {
            $horse_id = $horse_id ?: (int)$ap['horse_id'];
            $client_uid = $client_uid ?: (int)$ap['user_id'];
        }
    }

    $saved_count = 0;

    // ملخص الزيارة — يُحفظ في clinic_appointments
    $visit_summary = sanitize($_POST['visit_summary'] ?? '');
    if ($aid && $visit_summary) {
        $conn->prepare("UPDATE clinic_appointments SET visit_summary = ?, status = 'completed' WHERE id = ? AND clinic_id = ?")
            ->execute([$visit_summary, $aid, $clinic_id]);
        $saved_count++;
    }

    if ($horse_id) {
        // سجل صحي
        if (!empty($_POST['record_title'])) {
            $r_type = in_array($_POST['record_type'] ?? '', ['disease','checkup','treatment','note']) ? $_POST['record_type'] : 'checkup';
            $r_title = sanitize($_POST['record_title']);
            $r_desc = sanitize($_POST['record_description'] ?? '');
            $r_date = $_POST['record_date'] ?? date('Y-m-d');
            $q = $conn->prepare("INSERT INTO horse_health_records (horse_id, clinic_id, appointment_id, record_type, title, description, record_date, vet_name) VALUES (?,?,?,?,?,?,?,?)");
            $q->execute([$horse_id, $clinic_id, $aid ?: null, $r_type, $r_title, $r_desc, $r_date, $clinic['vet_name']]);
            $saved_count++;
        }

        // لقاح
        if (!empty($_POST['vaccine_name'])) {
            $v_name = sanitize($_POST['vaccine_name']);
            $v_date = $_POST['vaccine_date'] ?? date('Y-m-d');
            $v_next = $_POST['vaccine_next'] ?: null;
            $v_notes = sanitize($_POST['vaccine_notes'] ?? '');
            $q = $conn->prepare("INSERT INTO horse_vaccinations (horse_id, clinic_id, appointment_id, vaccine_name, vaccine_date, next_due, notes) VALUES (?,?,?,?,?,?,?)");
            $q->execute([$horse_id, $clinic_id, $aid ?: null, $v_name, $v_date, $v_next, $v_notes]);
            $saved_count++;
        }

        // وصفة طبية
        if (!empty($_POST['rx_medication'])) {
            $rx_med = sanitize($_POST['rx_medication']);
            $rx_dosage = sanitize($_POST['rx_dosage'] ?? '');
            $rx_freq = sanitize($_POST['rx_frequency'] ?? '');
            $rx_dur = sanitize($_POST['rx_duration'] ?? '');
            $rx_notes = sanitize($_POST['rx_instructions'] ?? '');
            $rx_date = $_POST['rx_date'] ?? date('Y-m-d');
            $q = $conn->prepare("INSERT INTO prescriptions (clinic_id, horse_id, user_id, appointment_id, medication, dosage, frequency, duration, instructions, issue_date) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $q->execute([$clinic_id, $horse_id, $client_uid ?: null, $aid ?: null, $rx_med, $rx_dosage, $rx_freq, $rx_dur, $rx_notes, $rx_date]);
            $saved_count++;
        }

        // شهادة
        if (!empty($_POST['cert_title'])) {
            $cert_type = in_array($_POST['cert_type'] ?? '', ['pedigree','competition','health','other']) ? $_POST['cert_type'] : 'health';
            $cert_title = sanitize($_POST['cert_title']);
            $cert_date = $_POST['cert_issue_date'] ?: date('Y-m-d');
            $cert_notes = sanitize($_POST['cert_notes'] ?? '');
            $cert_file = null;
            if (isset($_FILES['cert_file']) && $_FILES['cert_file']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['pdf','jpg','jpeg','png'];
                $ext = strtolower(pathinfo($_FILES['cert_file']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed) && $_FILES['cert_file']['size'] < 8 * 1024 * 1024) {
                    $dir = __DIR__ . '/assets/uploads/certificates/';
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    $fname = 'cert_' . $clinic_id . '_' . $horse_id . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['cert_file']['tmp_name'], $dir . $fname)) {
                        $cert_file = 'assets/uploads/certificates/' . $fname;
                    }
                }
            }
            $q = $conn->prepare("INSERT INTO horse_certificates (horse_id, clinic_id, cert_type, title, issue_date, issuer, file_path, notes) VALUES (?,?,?,?,?,?,?,?)");
            $q->execute([$horse_id, $clinic_id, $cert_type, $cert_title, $cert_date, $clinic['name'], $cert_file, $cert_notes]);
            $saved_count++;
        }
    }

    redirect('my-clinic.php?tab=appointments&visit_saved=' . $saved_count);
}

// إضافة / تعديل / حذف خدمة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_service'])) {
    $sid = (int)($_POST['service_id'] ?? 0);
    $sname = sanitize($_POST['service_name'] ?? '');
    $sdesc = sanitize($_POST['service_description'] ?? '');
    $sprice = (float)($_POST['service_price'] ?? 0);
    $sduration = sanitize($_POST['service_duration'] ?? '30 دقيقة');
    $sicon = sanitize($_POST['service_icon'] ?? '🩺');

    if (strlen($sname) >= 3) {
        if ($sid) {
            $q = $conn->prepare("UPDATE clinic_services SET name=?, description=?, price=?, duration=?, icon=? WHERE id=? AND clinic_id=?");
            $q->execute([$sname, $sdesc, $sprice, $sduration, $sicon, $sid, $clinic_id]);
        } else {
            $q = $conn->prepare("INSERT INTO clinic_services (clinic_id, name, description, price, duration, icon) VALUES (?,?,?,?,?,?)");
            $q->execute([$clinic_id, $sname, $sdesc, $sprice, $sduration, $sicon]);
        }
        redirect('my-clinic.php?tab=services&saved=1');
    }
}
if (isset($_GET['delete_service'])) {
    $conn->prepare("DELETE FROM clinic_services WHERE id=? AND clinic_id=?")
        ->execute([(int)$_GET['delete_service'], $clinic_id]);
    redirect('my-clinic.php?tab=services&deleted=1');
}

// إضافة وصفة مستقلة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prescription'])) {
    $horse_id = (int)($_POST['horse_id'] ?? 0);
    $rx_med = sanitize($_POST['medication'] ?? '');
    $rx_dosage = sanitize($_POST['dosage'] ?? '');
    $rx_freq = sanitize($_POST['frequency'] ?? '');
    $rx_dur = sanitize($_POST['duration'] ?? '');
    $rx_notes = sanitize($_POST['instructions'] ?? '');
    $rx_date = $_POST['issue_date'] ?? date('Y-m-d');

    if ($horse_id && strlen($rx_med) >= 2) {
        $h_owner = $conn->prepare("SELECT owner_id FROM horses WHERE id=?");
        $h_owner->execute([$horse_id]);
        $owner_id = $h_owner->fetchColumn();
        $q = $conn->prepare("INSERT INTO prescriptions (clinic_id, horse_id, user_id, medication, dosage, frequency, duration, instructions, issue_date) VALUES (?,?,?,?,?,?,?,?,?)");
        $q->execute([$clinic_id, $horse_id, $owner_id ?: null, $rx_med, $rx_dosage, $rx_freq, $rx_dur, $rx_notes, $rx_date]);
        redirect('my-clinic.php?tab=prescriptions&saved=1');
    }
    $errors[] = 'اختر الفرس وأدخل اسم الدواء.';
}
if (isset($_GET['delete_prescription'])) {
    $conn->prepare("DELETE FROM prescriptions WHERE id=? AND clinic_id=?")
        ->execute([(int)$_GET['delete_prescription'], $clinic_id]);
    redirect('my-clinic.php?tab=prescriptions&deleted=1');
}

// ==================== جلب البيانات ====================

$stats = [
    'appts_total'     => (int)$conn->query("SELECT COUNT(*) FROM clinic_appointments WHERE clinic_id=$clinic_id")->fetchColumn(),
    'appts_pending'   => (int)$conn->query("SELECT COUNT(*) FROM clinic_appointments WHERE clinic_id=$clinic_id AND status='pending'")->fetchColumn(),
    'appts_today'     => (int)$conn->query("SELECT COUNT(*) FROM clinic_appointments WHERE clinic_id=$clinic_id AND appointment_date=CURDATE() AND status IN ('pending','confirmed')")->fetchColumn(),
    'appts_emergency' => (int)$conn->query("SELECT COUNT(*) FROM clinic_appointments WHERE clinic_id=$clinic_id AND is_emergency=1 AND status IN ('pending','confirmed')")->fetchColumn(),
    'rx_count'        => (int)$conn->query("SELECT COUNT(*) FROM prescriptions WHERE clinic_id=$clinic_id")->fetchColumn(),
    'services_count'  => (int)$conn->query("SELECT COUNT(*) FROM clinic_services WHERE clinic_id=$clinic_id")->fetchColumn(),
    'patients_count'  => (int)$conn->query("SELECT COUNT(DISTINCT horse_id) FROM horse_health_records WHERE clinic_id=$clinic_id")->fetchColumn(),
    'rating'          => (float)$clinic['rating'],
    'reviews_count'   => (int)$clinic['reviews_count'],
];

// المواعيد
$ap_status = $_GET['ap_status'] ?? 'all';
$ap_where = "a.clinic_id = ?";
$ap_params = [$clinic_id];
if (in_array($ap_status, ['pending','confirmed','completed','cancelled'])) {
    $ap_where .= " AND a.status = ?";
    $ap_params[] = $ap_status;
}
$ap_stmt = $conn->prepare("
    SELECT a.*, u.full_name AS user_name, u.phone AS user_phone, h.name AS horse_name, h.breed
    FROM clinic_appointments a
    JOIN users u ON u.id = a.user_id
    LEFT JOIN horses h ON h.id = a.horse_id
    WHERE $ap_where
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 200
");
$ap_stmt->execute($ap_params);
$appointments = $ap_stmt->fetchAll();

// المواعيد اليوم (للـoverview)
$today_appts = $conn->prepare("
    SELECT a.*, u.full_name AS user_name, h.name AS horse_name
    FROM clinic_appointments a
    JOIN users u ON u.id = a.user_id
    LEFT JOIN horses h ON h.id = a.horse_id
    WHERE a.clinic_id = ? AND a.appointment_date = CURDATE()
    ORDER BY a.appointment_time ASC
");
$today_appts->execute([$clinic_id]);
$today_appts = $today_appts->fetchAll();

// خدمات العيادة
$services = $conn->prepare("SELECT * FROM clinic_services WHERE clinic_id = ? ORDER BY id DESC");
$services->execute([$clinic_id]);
$services = $services->fetchAll();

$edit_service = null;
if (isset($_GET['edit_service'])) {
    $s = $conn->prepare("SELECT * FROM clinic_services WHERE id=? AND clinic_id=?");
    $s->execute([(int)$_GET['edit_service'], $clinic_id]);
    $edit_service = $s->fetch() ?: null;
}

// الوصفات
$prescriptions = $conn->prepare("
    SELECT p.*, h.name AS horse_name, u.full_name AS owner_name
    FROM prescriptions p
    LEFT JOIN horses h ON h.id = p.horse_id
    LEFT JOIN users u ON u.id = p.user_id
    WHERE p.clinic_id = ?
    ORDER BY p.issue_date DESC, p.id DESC
    LIMIT 100
");
$prescriptions->execute([$clinic_id]);
$prescriptions = $prescriptions->fetchAll();

// المرضى (خيول العيادة)
$patients = $conn->prepare("
    SELECT DISTINCT h.id, h.name, h.breed, h.main_image, u.full_name AS owner_name, u.id AS owner_id,
           (SELECT MAX(record_date) FROM horse_health_records WHERE horse_id=h.id AND clinic_id=?) AS last_visit
    FROM horse_health_records r
    JOIN horses h ON h.id = r.horse_id
    JOIN users u ON u.id = h.owner_id
    WHERE r.clinic_id = ?
    ORDER BY last_visit DESC
");
$patients->execute([$clinic_id, $clinic_id]);
$patients = $patients->fetchAll();

// موعد محدد (لتوثيق الزيارة)
$active_appointment = null;
$active_horse = null;
$active_client = null;
if ($tab === 'visit' && isset($_GET['aid'])) {
    $q = $conn->prepare("
        SELECT a.*, u.full_name AS user_name, u.phone AS user_phone, h.name AS horse_name, h.breed
        FROM clinic_appointments a
        JOIN users u ON u.id = a.user_id
        LEFT JOIN horses h ON h.id = a.horse_id
        WHERE a.id = ? AND a.clinic_id = ?
    ");
    $q->execute([(int)$_GET['aid'], $clinic_id]);
    $active_appointment = $q->fetch() ?: null;
}

// قائمة كل الخيول المعروفة للعيادة (للوصفة المستقلة)
$all_horses = $conn->prepare("
    SELECT h.id, h.name, u.full_name AS owner_name
    FROM horses h
    JOIN users u ON u.id = h.owner_id
    WHERE h.id IN (SELECT horse_id FROM clinic_appointments WHERE clinic_id=? AND horse_id IS NOT NULL)
       OR h.id IN (SELECT horse_id FROM horse_health_records WHERE clinic_id=?)
    ORDER BY h.name
");
$all_horses->execute([$clinic_id, $clinic_id]);
$all_horses = $all_horses->fetchAll();

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
            <img src="<?= sanitize($clinic['image']) ?>" alt="" onerror="this.src='assets/images/hero.jpg'">
            <div>
                <strong><?= sanitize($clinic['name']) ?></strong>
                <small>👨‍⚕️ <?= sanitize($clinic['vet_name']) ?></small>
            </div>
        </div>
        <ul class="mc-nav">
            <li><a href="?tab=overview" class="<?= $tab=='overview'?'active':'' ?>"><span>📊</span> نظرة عامة</a></li>
            <li><a href="?tab=profile" class="<?= $tab=='profile'?'active':'' ?>"><span>✏️</span> بيانات العيادة</a></li>
            <li><a href="?tab=appointments" class="<?= $tab=='appointments'?'active':'' ?>">
                <span>📅</span> المواعيد
                <?php if ($stats['appts_pending']): ?><b class="mc-badge"><?= $stats['appts_pending'] ?></b><?php endif; ?>
            </a></li>
            <li><a href="?tab=patients" class="<?= $tab=='patients'?'active':'' ?>"><span>🐴</span> المرضى (<?= $stats['patients_count'] ?>)</a></li>
            <li><a href="?tab=prescriptions" class="<?= $tab=='prescriptions'?'active':'' ?>"><span>💊</span> الوصفات (<?= $stats['rx_count'] ?>)</a></li>
            <li><a href="?tab=services" class="<?= $tab=='services'?'active':'' ?>"><span>🎯</span> الخدمات (<?= $stats['services_count'] ?>)</a></li>
            <li class="mc-divider"></li>
            <li><a href="chat.php"><span>💬</span> المحادثات</a></li>

            <li><a href="account.php"><span>👤</span> حسابي</a></li>
        </ul>
    </aside>

    <!-- المحتوى -->
    <main class="mc-main">
        <?php if (isset($_GET['updated'])): ?><div class="mc-alert success">✅ تم تحديث البيانات</div><?php endif; ?>
        <?php if (isset($_GET['saved'])): ?><div class="mc-alert success">✅ تم الحفظ</div><?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?><div class="mc-alert success">🗑️ تم الحذف</div><?php endif; ?>
        <?php if (isset($_GET['appt_updated'])): ?><div class="mc-alert success">✅ تم تحديث الموعد</div><?php endif; ?>
        <?php if (isset($_GET['visit_saved'])): ?><div class="mc-alert success">✅ تم توثيق الزيارة — حُفظ <?= (int)$_GET['visit_saved'] ?> عنصر</div><?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="mc-alert error"><strong>⚠️</strong> <?= implode('، ', $errors) ?></div>
        <?php endif; ?>

        <?php if ($tab === 'overview'): ?>
            <!-- ==================== نظرة عامة ==================== -->
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">لوحة العيادة / نظرة عامة</div>
                    <h1>🩺 لوحة <?= sanitize($clinic['name']) ?></h1>
                    <p>إدارة المواعيد، المرضى، الوصفات والسجلات الطبية.</p>
                </div>
            </div>

            <div class="mc-stats-grid">
                <div class="mc-stat" style="--c:#3498db;">
                    <div class="mc-stat-ic">📅</div>
                    <div><b><?= $stats['appts_total'] ?></b><small>إجمالي المواعيد</small></div>
                </div>
                <div class="mc-stat" style="--c:#f39c12;">
                    <div class="mc-stat-ic">⏳</div>
                    <div><b><?= $stats['appts_pending'] ?></b><small>بانتظار الرد</small></div>
                </div>
                <div class="mc-stat" style="--c:#2ecc71;">
                    <div class="mc-stat-ic">🗓️</div>
                    <div><b><?= $stats['appts_today'] ?></b><small>مواعيد اليوم</small></div>
                </div>
                <?php if ($stats['appts_emergency'] > 0): ?>
                <div class="mc-stat" style="--c:#e74c3c;">
                    <div class="mc-stat-ic">🚨</div>
                    <div><b><?= $stats['appts_emergency'] ?></b><small>حالات طارئة</small></div>
                </div>
                <?php endif; ?>
                <div class="mc-stat" style="--c:#9b59b6;">
                    <div class="mc-stat-ic">🐴</div>
                    <div><b><?= $stats['patients_count'] ?></b><small>مريض</small></div>
                </div>
                <div class="mc-stat" style="--c:#c9a227;">
                    <div class="mc-stat-ic">💊</div>
                    <div><b><?= $stats['rx_count'] ?></b><small>وصفة</small></div>
                </div>
                <div class="mc-stat" style="--c:#e5bf3d;">
                    <div class="mc-stat-ic">⭐</div>
                    <div><b><?= number_format($stats['rating'], 1) ?></b><small><?= $stats['reviews_count'] ?> تقييم</small></div>
                </div>
            </div>

            <?php if (!empty($today_appts)): ?>
            <div class="mc-panel">
                <div class="mc-panel-head"><h3>📅 مواعيد اليوم</h3><a href="?tab=appointments" class="mc-link">الكل ←</a></div>
                <?php foreach ($today_appts as $ap): ?>
                <div class="mc-mini-row">
                    <div>
                        <strong><?= sanitize($ap['user_name']) ?> — 🐴 <?= sanitize($ap['horse_name'] ?: '—') ?></strong>
                        <small><?= sanitize($ap['reason'] ?: 'فحص عام') ?> <?= $ap['is_emergency']?' • <b style="color:#e74c3c">🚨 طارئ</b>':'' ?></small>
                    </div>
                    <div><?= substr($ap['appointment_time'], 0, 5) ?></div>
                    <div class="mc-mini-actions">
                        <?php if ($ap['status'] === 'pending'): ?>
                            <a href="?appt_action=confirm&aid=<?= $ap['id'] ?>&tab=overview" class="mc-btn sm ok">تأكيد</a>
                        <?php elseif ($ap['status'] === 'confirmed'): ?>
                            <a href="?tab=visit&aid=<?= $ap['id'] ?>" class="mc-btn sm primary">توثيق المعاينة</a>
                        <?php else: ?>
                            <span class="mc-pill" style="--c:<?= $status_labels[$ap['status']][1] ?>;"><?= $status_labels[$ap['status']][0] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (empty($services)): ?>
            <div class="mc-panel warn">
                <h3>⚠️ لم تضف خدمات بعد</h3>
                <p>أضف الخدمات والتخصصات التي تقدمها عيادتك (فحص، جراحة، تطعيم...) مع أسعارها.</p>
                <a href="?tab=services" class="mc-btn primary">➕ أضف خدمة</a>
            </div>
            <?php endif; ?>

        <?php elseif ($tab === 'profile'): ?>
            <!-- ==================== تعديل البيانات ==================== -->
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">لوحة العيادة / البيانات</div>
                    <h1>✏️ تعديل بيانات العيادة</h1>
                    <p>الزوار سيشاهدون هذه المعلومات على صفحة العيادة.</p>
                </div>
            </div>

            <div class="mc-panel">
                <form method="POST" enctype="multipart/form-data" class="mc-form">
                    <div class="mc-form-grid">
                        <div class="form-group full">
                            <label>اسم العيادة</label>
                            <input type="text" name="name" class="form-control" required value="<?= sanitize($clinic['name']) ?>">
                        </div>
                        <div class="form-group">
                            <label>اسم الطبيب البيطري</label>
                            <input type="text" name="vet_name" class="form-control" required value="<?= sanitize($clinic['vet_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label>التخصص</label>
                            <input type="text" name="specialization" class="form-control" value="<?= sanitize($clinic['specialization']) ?>" placeholder="جراحة عامة، توليد، ...">
                        </div>
                        <div class="form-group full">
                            <label>الوصف</label>
                            <textarea name="description" class="form-control" rows="4"><?= sanitize($clinic['description']) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>المدينة</label>
                            <select name="city" class="form-control">
                                <?php foreach ($palestinian_cities as $ct): ?>
                                    <option value="<?= $ct ?>" <?= $clinic['city']==$ct?'selected':'' ?>><?= $ct ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>العنوان</label>
                            <input type="text" name="address" class="form-control" value="<?= sanitize($clinic['address']) ?>">
                        </div>
                        <div class="form-group">
                            <label>الهاتف</label>
                            <input type="tel" name="phone" class="form-control" required value="<?= sanitize($clinic['phone']) ?>" dir="ltr">
                        </div>
                        <div class="form-group">
                            <label>البريد الإلكتروني</label>
                            <input type="email" name="email" class="form-control" value="<?= sanitize($clinic['email']) ?>">
                        </div>
                        <div class="form-group">
                            <label>ساعات الدوام</label>
                            <input type="text" name="opening_hours" class="form-control" value="<?= sanitize($clinic['opening_hours']) ?>">
                        </div>
                        <div class="form-group">
                            <label>رسوم الاستشارة (₪)</label>
                            <input type="number" name="consultation_fee" class="form-control" step="0.01" min="0" value="<?= $clinic['consultation_fee'] ?>">
                        </div>
                        <div class="form-group">
                            <label>رقم الترخيص</label>
                            <input type="text" name="license_number" class="form-control" value="<?= sanitize($clinic['license_number'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>الصورة</label>
                            <?php if ($clinic['image']): ?>
                                <img src="<?= sanitize($clinic['image']) ?>" alt="" style="width:100%; max-width:200px; border-radius:10px; margin-bottom:8px;" onerror="this.style.display='none'">
                            <?php endif; ?>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>
                        <div class="form-group full">
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                <input type="checkbox" name="emergency_available" value="1" <?= $clinic['emergency_available']?'checked':'' ?> style="width:20px; height:20px; accent-color: var(--gold);">
                                🚨 متوفر للطوارئ (24/7)
                            </label>
                        </div>
                        <div class="form-group full">
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                <input type="checkbox" name="home_visit" value="1" <?= $clinic['home_visit']?'checked':'' ?> style="width:20px; height:20px; accent-color: var(--gold);">
                                🏠 يقوم بزيارات منزلية
                            </label>
                        </div>
                    </div>
                    <button type="submit" name="update_profile" class="mc-btn primary lg">💾 حفظ التغييرات</button>
                </form>
            </div>

        <?php elseif ($tab === 'appointments'): ?>
            <!-- ==================== المواعيد ==================== -->
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">لوحة العيادة / المواعيد</div>
                    <h1>📅 إدارة المواعيد</h1>
                    <p><?= count($appointments) ?> موعد <?= $ap_status!='all'?'(مفلتر)':'' ?></p>
                </div>
            </div>

            <div class="mc-filters">
                <a href="?tab=appointments&ap_status=all" class="mc-chip <?= $ap_status=='all'?'active':'' ?>">الكل</a>
                <a href="?tab=appointments&ap_status=pending" class="mc-chip <?= $ap_status=='pending'?'active':'' ?>">⏳ قيد المراجعة</a>
                <a href="?tab=appointments&ap_status=confirmed" class="mc-chip <?= $ap_status=='confirmed'?'active':'' ?>">✅ مؤكدة</a>
                <a href="?tab=appointments&ap_status=completed" class="mc-chip <?= $ap_status=='completed'?'active':'' ?>">🏁 مكتملة</a>
                <a href="?tab=appointments&ap_status=cancelled" class="mc-chip <?= $ap_status=='cancelled'?'active':'' ?>">❌ ملغية</a>
            </div>

            <?php if (empty($appointments)): ?>
                <div class="mc-panel"><div class="mc-empty">📅<p>لا مواعيد في هذه الفئة.</p></div></div>
            <?php else: ?>
            <div class="mc-table-wrap">
                <table class="mc-table">
                    <thead>
                        <tr>
                            <th>التاريخ</th><th>الوقت</th><th>العميل</th><th>الفرس</th>
                            <th>السبب</th><th>الحالة</th><th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($appointments as $ap): $st = $status_labels[$ap['status']] ?? ['—','#999']; ?>
                        <tr>
                            <td>
                                <?= date('d/m/Y', strtotime($ap['appointment_date'])) ?>
                                <?php if ($ap['is_emergency']): ?><br><small style="color:#e74c3c;font-weight:800;">🚨 طارئ</small><?php endif; ?>
                            </td>
                            <td><?= substr($ap['appointment_time'], 0, 5) ?></td>
                            <td>
                                <strong><?= sanitize($ap['user_name']) ?></strong>
                                <?php if ($ap['user_phone']): ?><small dir="ltr"><?= sanitize($ap['user_phone']) ?></small><?php endif; ?>
                            </td>
                            <td><?= sanitize($ap['horse_name'] ?: '—') ?><?php if ($ap['breed']): ?><small><?= sanitize($ap['breed']) ?></small><?php endif; ?></td>
                            <td><?= sanitize($ap['reason'] ?: 'فحص عام') ?></td>
                            <td><span class="mc-pill" style="--c:<?= $st[1] ?>;"><?= $st[0] ?></span></td>
                            <td class="mc-row-actions">
                                <?php if ($ap['status'] === 'pending'): ?>
                                    <a href="?appt_action=confirm&aid=<?= $ap['id'] ?>&tab=appointments" class="mc-btn sm ok">قبول</a>
                                    <a href="?appt_action=reject&aid=<?= $ap['id'] ?>&tab=appointments" class="mc-btn sm no" onclick="return confirm('رفض؟')">رفض</a>
                                <?php elseif ($ap['status'] === 'confirmed'): ?>
                                    <a href="?tab=visit&aid=<?= $ap['id'] ?>" class="mc-btn sm primary">🩺 توثيق</a>
                                    <a href="?appt_action=reject&aid=<?= $ap['id'] ?>&tab=appointments" class="mc-btn sm no" onclick="return confirm('إلغاء؟')">إلغاء</a>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!empty($ap['notes'])): ?>
                        <tr class="mc-note-row"><td colspan="7"><em>📝 ملاحظات العميل: <?= sanitize($ap['notes']) ?></em></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($ap['visit_summary'])): ?>
                        <tr class="mc-note-row"><td colspan="7"><em style="color:var(--gold);">🩺 ملخص الزيارة: <?= sanitize($ap['visit_summary']) ?></em></td></tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        <?php elseif ($tab === 'visit'): ?>
            <!-- ==================== توثيق زيارة ==================== -->
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">لوحة العيادة / توثيق زيارة</div>
                    <h1>🩺 توثيق نتائج المعاينة</h1>
                    <p>سجّل نتائج الزيارة — ستُضاف للسجل الصحي للفرس. كل الأقسام اختيارية، املأ ما ينطبق فقط.</p>
                </div>
            </div>

            <?php if ($active_appointment): ?>
            <div class="mc-panel" style="background: linear-gradient(135deg, var(--dark) 0%, rgba(46,134,222,0.08) 100%); border-color: rgba(46,134,222,0.35);">
                <div style="display:flex; gap:20px; flex-wrap:wrap; align-items:center;">
                    <div>
                        <small style="color:var(--text-muted);">معاينة لـ</small>
                        <h3 style="color:#4eaef0; margin:4px 0;">🐴 <?= sanitize($active_appointment['horse_name'] ?: 'بدون فرس محدد') ?></h3>
                        <small style="color:var(--text);">المالك: <?= sanitize($active_appointment['user_name']) ?> — <span dir="ltr"><?= sanitize($active_appointment['user_phone']) ?></span></small>
                    </div>
                    <div style="margin-right:auto;">
                        <small style="color:var(--text-muted);">موعد</small>
                        <div style="color:var(--cream); font-weight:700;"><?= date('d/m/Y', strtotime($active_appointment['appointment_date'])) ?> — <?= substr($active_appointment['appointment_time'],0,5) ?></div>
                        <?php if ($active_appointment['reason']): ?><small>السبب: <?= sanitize($active_appointment['reason']) ?></small><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="mc-form">
                <?php if ($active_appointment): ?>
                    <input type="hidden" name="appointment_id" value="<?= (int)$active_appointment['id'] ?>">
                    <input type="hidden" name="horse_id" value="<?= (int)($active_appointment['horse_id'] ?? 0) ?>">
                    <input type="hidden" name="client_user_id" value="<?= (int)$active_appointment['user_id'] ?>">
                <?php else: ?>
                <div class="mc-panel">
                    <div class="mc-panel-head"><h3>📋 الفرس المستهدف</h3></div>
                    <div class="form-group">
                        <label>اختر الفرس</label>
                        <select name="horse_id" class="form-control" required>
                            <option value="">— اختر —</option>
                            <?php foreach ($all_horses as $h): ?>
                                <option value="<?= $h['id'] ?>"><?= sanitize($h['name']) ?> — <?= sanitize($h['owner_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($active_appointment): ?>
                <div class="mc-panel">
                    <div class="mc-panel-head"><h3>📝 ملخص الزيارة</h3></div>
                    <div class="form-group">
                        <label>ملاحظات عامة عن الزيارة</label>
                        <textarea name="visit_summary" class="form-control" rows="3" placeholder="تشخيص مبدئي، ملاحظات الفحص السريري..."><?= sanitize($active_appointment['visit_summary'] ?? '') ?></textarea>
                    </div>
                </div>
                <?php endif; ?>

                <div class="mc-panel">
                    <div class="mc-panel-head"><h3>🩺 سجل صحي جديد <small style="color:var(--text-muted); font-weight:400;">(اختياري)</small></h3></div>
                    <div class="mc-form-grid">
                        <div class="form-group">
                            <label>النوع</label>
                            <select name="record_type" class="form-control">
                                <option value="checkup">🔍 فحص</option>
                                <option value="disease">🦠 مرض/تشخيص</option>
                                <option value="treatment">💉 علاج</option>
                                <option value="note">📝 ملاحظة</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>التاريخ</label>
                            <input type="date" name="record_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group full">
                            <label>العنوان</label>
                            <input type="text" name="record_title" class="form-control" placeholder="مثلاً: تشخيص مغص خفيف">
                        </div>
                        <div class="form-group full">
                            <label>التفاصيل</label>
                            <textarea name="record_description" class="form-control" rows="3" placeholder="وصف التشخيص والإجراءات..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="mc-panel">
                    <div class="mc-panel-head"><h3>💉 لقاح جديد <small style="color:var(--text-muted); font-weight:400;">(اختياري)</small></h3></div>
                    <div class="mc-form-grid">
                        <div class="form-group">
                            <label>اسم اللقاح</label>
                            <input type="text" name="vaccine_name" class="form-control" placeholder="EHV-1، أنفلونزا الخيل...">
                        </div>
                        <div class="form-group">
                            <label>تاريخ اللقاح</label>
                            <input type="date" name="vaccine_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label>موعد الجرعة القادمة</label>
                            <input type="date" name="vaccine_next" class="form-control">
                        </div>
                        <div class="form-group full">
                            <label>ملاحظات</label>
                            <input type="text" name="vaccine_notes" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="mc-panel">
                    <div class="mc-panel-head"><h3>💊 وصفة طبية <small style="color:var(--text-muted); font-weight:400;">(اختياري)</small></h3></div>
                    <div class="mc-form-grid">
                        <div class="form-group">
                            <label>الدواء</label>
                            <input type="text" name="rx_medication" class="form-control" placeholder="اسم الدواء">
                        </div>
                        <div class="form-group">
                            <label>الجرعة</label>
                            <input type="text" name="rx_dosage" class="form-control" placeholder="10 ملجم/كجم">
                        </div>
                        <div class="form-group">
                            <label>التكرار</label>
                            <input type="text" name="rx_frequency" class="form-control" placeholder="كل 12 ساعة">
                        </div>
                        <div class="form-group">
                            <label>المدة</label>
                            <input type="text" name="rx_duration" class="form-control" placeholder="7 أيام">
                        </div>
                        <div class="form-group">
                            <label>تاريخ الوصفة</label>
                            <input type="date" name="rx_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group full">
                            <label>تعليمات إضافية</label>
                            <textarea name="rx_instructions" class="form-control" rows="2" placeholder="يعطى مع الطعام، تجنب..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="mc-panel">
                    <div class="mc-panel-head"><h3>📜 شهادة <small style="color:var(--text-muted); font-weight:400;">(اختياري)</small></h3></div>
                    <div class="mc-form-grid">
                        <div class="form-group">
                            <label>النوع</label>
                            <select name="cert_type" class="form-control">
                                <option value="health">🏥 صحية</option>
                                <option value="pedigree">📜 نسب</option>
                                <option value="competition">🏆 مسابقات</option>
                                <option value="other">📄 أخرى</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>تاريخ الإصدار</label>
                            <input type="date" name="cert_issue_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group full">
                            <label>عنوان الشهادة</label>
                            <input type="text" name="cert_title" class="form-control" placeholder="شهادة خلو من الأمراض">
                        </div>
                        <div class="form-group">
                            <label>ملف الشهادة (PDF/صورة)</label>
                            <input type="file" name="cert_file" class="form-control" accept=".pdf,image/*">
                        </div>
                        <div class="form-group">
                            <label>ملاحظات</label>
                            <input type="text" name="cert_notes" class="form-control">
                        </div>
                    </div>
                </div>

                <button type="submit" name="document_visit" class="mc-btn primary lg">💾 حفظ كل ما أُدخل</button>
            </form>

        <?php elseif ($tab === 'patients'): ?>
            <!-- ==================== المرضى ==================== -->
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">لوحة العيادة / المرضى</div>
                    <h1>🐴 قائمة المرضى</h1>
                    <p>الخيول التي عالجتها عيادتك (<?= count($patients) ?>) — اضغط لرؤية السجل الطبي الكامل.</p>
                </div>
            </div>

            <?php if (empty($patients)): ?>
                <div class="mc-panel"><div class="mc-empty">🐴<p>لم تسجّل أي سجل طبي بعد.</p></div></div>
            <?php else: ?>
            <div class="mc-svc-grid">
                <?php foreach ($patients as $p): ?>
                <div class="mc-svc-card" style="text-align:center;">
                    <img src="<?= sanitize($p['main_image']) ?>" alt="" style="width:100%; height:140px; object-fit:cover; border-radius:10px; margin-bottom:10px;" onerror="this.src='assets/images/horses/h1.jpg'">
                    <h4>🐴 <?= sanitize($p['name']) ?></h4>
                    <?php if ($p['breed']): ?><p><?= sanitize($p['breed']) ?></p><?php endif; ?>
                    <div class="mc-svc-meta">
                        <span>👤 <?= sanitize($p['owner_name']) ?></span>
                        <small><?= $p['last_visit'] ? date('d/m/Y', strtotime($p['last_visit'])) : '—' ?></small>
                    </div>
                    <div class="mc-svc-actions">
                        <a href="clinic_horse_record.php?horse=<?= $p['id'] ?>" class="mc-btn sm">📋 السجل الكامل</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        <?php elseif ($tab === 'prescriptions'): ?>
            <!-- ==================== الوصفات ==================== -->
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">لوحة العيادة / الوصفات</div>
                    <h1>💊 الوصفات الطبية</h1>
                    <p>جميع الوصفات الصادرة من عيادتك (<?= count($prescriptions) ?>).</p>
                </div>
            </div>

            <div class="mc-panel">
                <div class="mc-panel-head"><h3>➕ وصفة جديدة</h3></div>
                <form method="POST" class="mc-form">
                    <div class="mc-form-grid">
                        <div class="form-group">
                            <label>الفرس</label>
                            <select name="horse_id" class="form-control" required>
                                <option value="">— اختر —</option>
                                <?php foreach ($all_horses as $h): ?>
                                    <option value="<?= $h['id'] ?>"><?= sanitize($h['name']) ?> — <?= sanitize($h['owner_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>تاريخ الإصدار</label>
                            <input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group full">
                            <label>الدواء</label>
                            <input type="text" name="medication" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>الجرعة</label>
                            <input type="text" name="dosage" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>التكرار</label>
                            <input type="text" name="frequency" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>المدة</label>
                            <input type="text" name="duration" class="form-control">
                        </div>
                        <div class="form-group full">
                            <label>التعليمات</label>
                            <textarea name="instructions" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <button type="submit" name="save_prescription" class="mc-btn primary">➕ إصدار الوصفة</button>
                </form>
            </div>

            <div class="mc-panel">
                <div class="mc-panel-head"><h3>الوصفات الصادرة (<?= count($prescriptions) ?>)</h3></div>
                <?php if (empty($prescriptions)): ?>
                    <div class="mc-empty">💊<p>لا وصفات بعد.</p></div>
                <?php else: ?>
                <div class="mc-table-wrap">
                    <table class="mc-table">
                        <thead>
                            <tr><th>التاريخ</th><th>الفرس</th><th>المالك</th><th>الدواء</th><th>الجرعة</th><th>المدة</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($prescriptions as $p): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($p['issue_date'])) ?></td>
                                <td>🐴 <?= sanitize($p['horse_name'] ?: '—') ?></td>
                                <td><?= sanitize($p['owner_name'] ?: '—') ?></td>
                                <td><strong><?= sanitize($p['medication']) ?></strong></td>
                                <td><?= sanitize($p['dosage']) ?: '—' ?> <?= $p['frequency']?' / '.sanitize($p['frequency']):'' ?></td>
                                <td><?= sanitize($p['duration']) ?: '—' ?></td>
                                <td><a href="?delete_prescription=<?= $p['id'] ?>&tab=prescriptions" class="mc-btn sm no" onclick="return confirm('حذف الوصفة؟')">🗑️</a></td>
                            </tr>
                            <?php if ($p['instructions']): ?>
                            <tr class="mc-note-row"><td colspan="7"><em>📝 <?= sanitize($p['instructions']) ?></em></td></tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'services'): ?>
            <!-- ==================== الخدمات ==================== -->
            <div class="mc-header">
                <div>
                    <div class="mc-breadcrumb">لوحة العيادة / الخدمات</div>
                    <h1>🎯 خدمات العيادة</h1>
                    <p>الخدمات والإجراءات التي تقدمها (فحص، جراحة، تطعيم، تعقيم...) مع أسعارها.</p>
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
                        <div class="form-group"><label>المدة</label><input type="text" name="service_duration" class="form-control" value="<?= sanitize($edit_service['duration'] ?? '30 دقيقة') ?>"></div>
                        <div class="form-group"><label>الأيقونة</label><input type="text" name="service_icon" class="form-control" maxlength="4" value="<?= sanitize($edit_service['icon'] ?? '🩺') ?>"></div>
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

        <?php endif; ?>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
