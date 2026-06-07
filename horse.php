<?php
require_once 'config/db.php';

if (!isLoggedIn()) redirect('login.php?redirect=horses');

$user_id   = (int)$_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'user';
$id = (int)($_GET['id'] ?? 0);

// البيطري يقدر يشوف سجل أي خيل عالجته عيادته
$is_vet_view = false;
if (in_array($user_role, ['clinic', 'admin'])) {
    $stmt = $conn->prepare("SELECT * FROM horses WHERE id = ?");
    $stmt->execute([$id]);
    $horse = $stmt->fetch();
    if ($horse) $is_vet_view = true;
    else redirect('my-clinic.php?tab=patients');
} else {
    $stmt = $conn->prepare("SELECT * FROM horses WHERE id = ? AND owner_id = ?");
    $stmt->execute([$id, $user_id]);
    $horse = $stmt->fetch();
    if (!$horse) redirect('horses.php');
}

$page_title = $horse['name'];

// ===== عمليات إضافة سجلات (مالك الخيل فقط) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_vet_view) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_health') {
        $ins = $conn->prepare("INSERT INTO horse_health_records (horse_id, record_type, title, description, record_date, vet_name) VALUES (?, ?, ?, ?, ?, ?)");
        $ins->execute([
            $id,
            $_POST['record_type'] ?? 'note',
            sanitize($_POST['title'] ?? ''),
            sanitize($_POST['description'] ?? ''),
            $_POST['record_date'] ?: date('Y-m-d'),
            sanitize($_POST['vet_name'] ?? '')
        ]);
    } elseif ($action === 'add_vaccination') {
        $ins = $conn->prepare("INSERT INTO horse_vaccinations (horse_id, vaccine_name, vaccine_date, next_due, notes) VALUES (?, ?, ?, ?, ?)");
        $ins->execute([
            $id,
            sanitize($_POST['vaccine_name'] ?? ''),
            $_POST['vaccine_date'] ?: date('Y-m-d'),
            $_POST['next_due'] ?: null,
            sanitize($_POST['notes'] ?? '')
        ]);
    } elseif ($action === 'add_certificate') {
        $file_path = null;
        if (isset($_FILES['cert_file']) && $_FILES['cert_file']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg','jpeg','png','webp','pdf'];
            $ext = strtolower(pathinfo($_FILES['cert_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed) && $_FILES['cert_file']['size'] < 10 * 1024 * 1024) {
                $dir = __DIR__ . '/assets/uploads/certificates/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $filename = 'cert_' . $id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['cert_file']['tmp_name'], $dir . $filename)) {
                    $file_path = 'assets/uploads/certificates/' . $filename;
                }
            }
        }
        $ins = $conn->prepare("INSERT INTO horse_certificates (horse_id, cert_type, title, issue_date, issuer, file_path, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([
            $id,
            $_POST['cert_type'] ?? 'other',
            sanitize($_POST['title'] ?? ''),
            $_POST['issue_date'] ?: null,
            sanitize($_POST['issuer'] ?? ''),
            $file_path,
            sanitize($_POST['notes'] ?? '')
        ]);
    } elseif ($action === 'delete_record') {
        $table = $_POST['table'] ?? '';
        $rec_id = (int)($_POST['record_id'] ?? 0);
        if (in_array($table, ['horse_health_records','horse_vaccinations','horse_certificates','horse_images','horse_videos']) && $rec_id > 0) {
            // إذا كان الحذف من معرض/فيديو، احذف الملف من القرص أيضاً
            if (in_array($table, ['horse_images','horse_videos'])) {
                $f_stmt = $conn->prepare("SELECT file_path FROM $table WHERE id = ? AND horse_id = ?");
                $f_stmt->execute([$rec_id, $id]);
                $fp = $f_stmt->fetchColumn();
                if ($fp && file_exists(__DIR__ . '/' . $fp)) @unlink(__DIR__ . '/' . $fp);
            }
            $del = $conn->prepare("DELETE FROM $table WHERE id = ? AND horse_id = ?");
            $del->execute([$rec_id, $id]);
        }
    }
    redirect('horse.php?id=' . $id . '#' . ($_POST['scroll_to'] ?? ''));
}

// ===== جلب السجلات =====
$health_records = $conn->prepare("SELECT * FROM horse_health_records WHERE horse_id = ? ORDER BY record_date DESC");
$health_records->execute([$id]);
$health_records = $health_records->fetchAll();

$vaccinations = $conn->prepare("SELECT * FROM horse_vaccinations WHERE horse_id = ? ORDER BY vaccine_date DESC");
$vaccinations->execute([$id]);
$vaccinations = $vaccinations->fetchAll();

$certificates = $conn->prepare("SELECT * FROM horse_certificates WHERE horse_id = ? ORDER BY issue_date DESC");
$certificates->execute([$id]);
$certificates = $certificates->fetchAll();

// المواعيد البيطرية المرتبطة
$appointments = $conn->prepare("SELECT ca.*, cl.name AS clinic_name FROM clinic_appointments ca LEFT JOIN clinics cl ON cl.id = ca.clinic_id WHERE ca.horse_id = ? ORDER BY ca.appointment_date DESC LIMIT 5");
$appointments->execute([$id]);
$appointments = $appointments->fetchAll();

// معرض الصور والفيديوهات
$gallery = $conn->prepare("SELECT * FROM horse_images WHERE horse_id = ? ORDER BY sort_order, id");
$gallery->execute([$id]);
$gallery = $gallery->fetchAll();

$videos = $conn->prepare("SELECT * FROM horse_videos WHERE horse_id = ? ORDER BY id DESC");
$videos->execute([$id]);
$videos = $videos->fetchAll();

$health_status_labels = [
    'healthy'    => ['✅ سليم', 'var(--green)'],
    'under_care' => ['🩺 تحت العلاج', '#3498db'],
    'recovering' => ['🌱 يتعافى', '#f39c12'],
    'critical'   => ['🚨 حرج', 'var(--red)']
];
$hsl = $health_status_labels[$horse['health_status'] ?? 'healthy'] ?? $health_status_labels['healthy'];

$age = $horse['birth_date'] ? (int)((time() - strtotime($horse['birth_date'])) / (365.25 * 86400)) : null;

include 'includes/header.php';
?>

<div class="horse-hero">
    <div class="container horse-hero-inner">
        <img src="<?= sanitize($horse['main_image']) ?>" onerror="this.src='assets/images/horses/default.jpg'" class="horse-hero-img">
        <div class="horse-hero-info">
            <h1><?= sanitize($horse['name']) ?> <?php if ($horse['is_pure']): ?><span class="horse-badge-pure">⭐ أصيل</span><?php endif; ?></h1>
            <div class="horse-hero-meta">
                <?php if ($horse['breed']): ?><span>🏇 <?= sanitize($horse['breed']) ?></span><?php endif; ?>
                <span><?= $horse['gender'] === 'male' ? '♂️ ذكر' : '♀️ أنثى' ?></span>
                <?php if ($age !== null): ?><span>🎂 <?= $age ?> سنة</span><?php endif; ?>
                <?php if ($horse['color']): ?><span>🎨 <?= sanitize($horse['color']) ?></span><?php endif; ?>
            </div>
            <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="horse_edit.php?id=<?= $id ?>" class="btn btn-outline">✏️ تعديل</a>
                <a href="clinics.php?horse=<?= $id ?>" class="btn btn-primary">🩺 حجز موعد بيطري</a>
            </div>
        </div>
    </div>
</div>

<div class="container" style="padding: 30px 0;">
    <!-- تبويبات -->
    <div class="horse-tabs">
        <button class="tab-btn active" data-tab="info">📋 البيانات</button>
        <button class="tab-btn" data-tab="gallery">🖼️ المعرض (<?= count($gallery) ?>)</button>
        <button class="tab-btn" data-tab="videos">🎬 فيديو (<?= count($videos) ?>)</button>
        <button class="tab-btn" data-tab="health">🩺 السجل الصحي (<?= count($health_records) ?>)</button>
        <button class="tab-btn" data-tab="vaccinations">💉 اللقاحات (<?= count($vaccinations) ?>)</button>
        <button class="tab-btn" data-tab="certificates">📜 الشهادات (<?= count($certificates) ?>)</button>
        <button class="tab-btn" data-tab="appointments">📅 المواعيد (<?= count($appointments) ?>)</button>
    </div>

    <!-- البيانات -->
    <div class="tab-content active" data-tab="info">
        <div class="health-summary-card">
            <div class="hs-status" style="background: <?= $hsl[1] ?>;"><?= $hsl[0] ?></div>
            <div class="hs-body">
                <div><strong>الحالة الصحية:</strong> <?= $hsl[0] ?></div>
                <?php if ($horse['last_checkup_date']): ?>
                    <div><strong>آخر فحص:</strong> <?= date('d/m/Y', strtotime($horse['last_checkup_date'])) ?></div>
                <?php endif; ?>
                <?php if ($horse['pedigree_label']): ?>
                    <div><strong>الأصالة:</strong> <?= sanitize($horse['pedigree_label']) ?></div>
                <?php endif; ?>
                <?php if ($horse['medical_notes']): ?>
                    <div><strong>ملاحظات طبية:</strong> <?= sanitize($horse['medical_notes']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="info-card">
            <h3>📋 المعلومات التفصيلية</h3>
            <div class="info-grid">
                <div><strong>الارتفاع:</strong> <?= $horse['height_cm'] ? $horse['height_cm'] . ' سم' : '—' ?></div>
                <div><strong>الوزن:</strong> <?= $horse['weight_kg'] ? $horse['weight_kg'] . ' كغ' : '—' ?></div>
                <div><strong>رقم التسجيل:</strong> <?= sanitize($horse['registration_number'] ?: '—') ?></div>
                <div><strong>تاريخ الميلاد:</strong> <?= $horse['birth_date'] ?: '—' ?></div>
                <div><strong>الأب:</strong> <?= sanitize($horse['father_name'] ?: '—') ?></div>
                <div><strong>الأم:</strong> <?= sanitize($horse['mother_name'] ?: '—') ?></div>
            </div>
            <?php if ($horse['description']): ?>
                <h4 style="margin-top: 20px; color: var(--gold);">الوصف</h4>
                <p style="line-height: 1.8;"><?= nl2br(sanitize($horse['description'])) ?></p>
            <?php endif; ?>
            <?php if ($horse['experience_details']): ?>
                <h4 style="margin-top: 20px; color: var(--gold);">الخبرة والإنجازات</h4>
                <p style="line-height: 1.8;"><?= nl2br(sanitize($horse['experience_details'])) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- المعرض -->
    <div class="tab-content" data-tab="gallery">
        <div class="info-card">
            <h3>🖼️ معرض الصور</h3>
            <p style="color: var(--text-dark-muted);">لإضافة صور جديدة، اذهب إلى <a href="horse_edit.php?id=<?= $id ?>" style="color: var(--gold);">صفحة التعديل</a>.</p>
            <?php if (empty($gallery)): ?>
                <div class="empty-small">لا توجد صور بعد</div>
            <?php else: ?>
                <div class="horse-gallery-grid">
                    <?php foreach ($gallery as $img): ?>
                        <div class="gallery-item">
                            <img src="<?= sanitize($img['file_path']) ?>" alt="<?= sanitize($img['caption'] ?: $horse['name']) ?>" onclick="window.open(this.src,'_blank')">
                            <form method="POST" onsubmit="return confirm('حذف الصورة؟');" class="gallery-del">
                                <input type="hidden" name="action" value="delete_record">
                                <input type="hidden" name="table" value="horse_images">
                                <input type="hidden" name="record_id" value="<?= $img['id'] ?>">
                                <input type="hidden" name="scroll_to" value="gallery">
                                <button type="submit" class="btn-icon-delete">🗑️</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- الفيديوهات -->
    <div class="tab-content" data-tab="videos">
        <div class="info-card">
            <h3>🎬 فيديوهات الفرس</h3>
            <p style="color: var(--text-dark-muted);">لرفع فيديو جديد، اذهب إلى <a href="horse_edit.php?id=<?= $id ?>" style="color: var(--gold);">صفحة التعديل</a>.</p>
            <?php if (empty($videos)): ?>
                <div class="empty-small">لا توجد فيديوهات بعد</div>
            <?php else: ?>
                <div class="horse-videos-grid">
                    <?php foreach ($videos as $v): ?>
                        <div class="video-item">
                            <video src="<?= sanitize($v['file_path']) ?>" controls playsinline preload="metadata"></video>
                            <div class="video-foot">
                                <strong><?= sanitize($v['title']) ?></strong>
                                <form method="POST" onsubmit="return confirm('حذف الفيديو؟');">
                                    <input type="hidden" name="action" value="delete_record">
                                    <input type="hidden" name="table" value="horse_videos">
                                    <input type="hidden" name="record_id" value="<?= $v['id'] ?>">
                                    <input type="hidden" name="scroll_to" value="videos">
                                    <button type="submit" class="btn-icon-delete">🗑️</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- السجل الصحي -->
    <div class="tab-content" data-tab="health">
        <div class="info-card">
            <h3>🩺 إضافة سجل صحي</h3>
            <form method="POST" class="inline-form">
                <input type="hidden" name="action" value="add_health">
                <input type="hidden" name="scroll_to" value="health">
                <select name="record_type" required>
                    <option value="checkup">فحص دوري</option>
                    <option value="disease">مرض</option>
                    <option value="treatment">علاج</option>
                    <option value="note">ملاحظة</option>
                </select>
                <input type="text" name="title" placeholder="العنوان (مثل: فحص عام)" required>
                <input type="date" name="record_date" value="<?= date('Y-m-d') ?>" required>
                <input type="text" name="vet_name" placeholder="اسم الطبيب (اختياري)">
                <textarea name="description" placeholder="التفاصيل" rows="2"></textarea>
                <button type="submit" class="btn btn-primary">➕ إضافة</button>
            </form>
        </div>

        <div class="records-list" id="health">
            <?php if (empty($health_records)): ?>
                <div class="empty-small">لا توجد سجلات صحية بعد</div>
            <?php else: foreach ($health_records as $r):
                $type_labels = ['checkup' => ['🔍 فحص', 'var(--green)'], 'disease' => ['🤒 مرض', 'var(--red)'], 'treatment' => ['💊 علاج', '#3498db'], 'note' => ['📝 ملاحظة', 'var(--gold)']];
                $tl = $type_labels[$r['record_type']] ?? ['📝', 'var(--gold)'];
            ?>
            <div class="record-card">
                <div class="record-type" style="background: <?= $tl[1] ?>;"><?= $tl[0] ?></div>
                <div class="record-body">
                    <h4><?= sanitize($r['title']) ?></h4>
                    <div class="record-meta">
                        <span>📅 <?= $r['record_date'] ?></span>
                        <?php if ($r['vet_name']): ?><span>👨‍⚕️ <?= sanitize($r['vet_name']) ?></span><?php endif; ?>
                    </div>
                    <?php if ($r['description']): ?>
                        <p><?= nl2br(sanitize($r['description'])) ?></p>
                    <?php endif; ?>
                </div>
                <form method="POST" onsubmit="return confirm('حذف هذا السجل؟');">
                    <input type="hidden" name="action" value="delete_record">
                    <input type="hidden" name="table" value="horse_health_records">
                    <input type="hidden" name="record_id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="scroll_to" value="health">
                    <button type="submit" class="btn-icon-delete">🗑️</button>
                </form>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- اللقاحات -->
    <div class="tab-content" data-tab="vaccinations">
        <div class="info-card">
            <h3>💉 إضافة لقاح</h3>
            <form method="POST" class="inline-form">
                <input type="hidden" name="action" value="add_vaccination">
                <input type="hidden" name="scroll_to" value="vaccinations">
                <input type="text" name="vaccine_name" placeholder="اسم اللقاح (مثل: الإنفلونزا)" required>
                <input type="date" name="vaccine_date" value="<?= date('Y-m-d') ?>" required>
                <label>الجرعة القادمة:</label>
                <input type="date" name="next_due">
                <textarea name="notes" placeholder="ملاحظات" rows="2"></textarea>
                <button type="submit" class="btn btn-primary">➕ إضافة</button>
            </form>
        </div>

        <div class="records-list" id="vaccinations">
            <?php if (empty($vaccinations)): ?>
                <div class="empty-small">لا توجد لقاحات مسجلة بعد</div>
            <?php else: foreach ($vaccinations as $v):
                $overdue = $v['next_due'] && $v['next_due'] < date('Y-m-d');
                $soon = $v['next_due'] && !$overdue && strtotime($v['next_due']) < strtotime('+30 days');
            ?>
            <div class="record-card">
                <div class="record-type" style="background: <?= $overdue ? 'var(--red)' : ($soon ? '#f39c12' : 'var(--green)') ?>;">💉</div>
                <div class="record-body">
                    <h4><?= sanitize($v['vaccine_name']) ?></h4>
                    <div class="record-meta">
                        <span>📅 <?= $v['vaccine_date'] ?></span>
                        <?php if ($v['next_due']): ?>
                            <span style="color: <?= $overdue ? 'var(--red)' : ($soon ? '#f39c12' : 'inherit') ?>;">
                                ⏰ الجرعة القادمة: <?= $v['next_due'] ?>
                                <?php if ($overdue): ?>(متأخرة!)<?php elseif ($soon): ?>(قريباً)<?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if ($v['notes']): ?><p><?= sanitize($v['notes']) ?></p><?php endif; ?>
                </div>
                <form method="POST" onsubmit="return confirm('حذف؟');">
                    <input type="hidden" name="action" value="delete_record">
                    <input type="hidden" name="table" value="horse_vaccinations">
                    <input type="hidden" name="record_id" value="<?= $v['id'] ?>">
                    <input type="hidden" name="scroll_to" value="vaccinations">
                    <button type="submit" class="btn-icon-delete">🗑️</button>
                </form>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- الشهادات -->
    <div class="tab-content" data-tab="certificates">
        <div class="info-card">
            <h3>📜 إضافة شهادة</h3>
            <form method="POST" enctype="multipart/form-data" class="inline-form">
                <input type="hidden" name="action" value="add_certificate">
                <input type="hidden" name="scroll_to" value="certificates">
                <select name="cert_type" required>
                    <option value="pedigree">شهادة نسب</option>
                    <option value="competition">مسابقة</option>
                    <option value="health">صحية</option>
                    <option value="other">أخرى</option>
                </select>
                <input type="text" name="title" placeholder="عنوان الشهادة" required>
                <input type="text" name="issuer" placeholder="الجهة المانحة">
                <input type="date" name="issue_date">
                <input type="file" name="cert_file" accept="image/*,application/pdf">
                <textarea name="notes" placeholder="ملاحظات" rows="2"></textarea>
                <button type="submit" class="btn btn-primary">➕ إضافة</button>
            </form>
        </div>

        <div class="records-list" id="certificates">
            <?php if (empty($certificates)): ?>
                <div class="empty-small">لا توجد شهادات بعد</div>
            <?php else: foreach ($certificates as $c):
                $types = ['pedigree' => '🧬 نسب', 'competition' => '🏆 مسابقة', 'health' => '🩺 صحية', 'other' => '📋 أخرى'];
            ?>
            <div class="record-card">
                <div class="record-type" style="background: var(--gold); color: var(--dark);"><?= $types[$c['cert_type']] ?? '📋' ?></div>
                <div class="record-body">
                    <h4><?= sanitize($c['title']) ?></h4>
                    <div class="record-meta">
                        <?php if ($c['issuer']): ?><span>🏛️ <?= sanitize($c['issuer']) ?></span><?php endif; ?>
                        <?php if ($c['issue_date']): ?><span>📅 <?= $c['issue_date'] ?></span><?php endif; ?>
                    </div>
                    <?php if ($c['notes']): ?><p><?= sanitize($c['notes']) ?></p><?php endif; ?>
                    <?php if ($c['file_path']): ?>
                        <a href="<?= sanitize($c['file_path']) ?>" target="_blank" class="btn btn-outline" style="margin-top: 10px;">📄 عرض الشهادة</a>
                    <?php endif; ?>
                </div>
                <form method="POST" onsubmit="return confirm('حذف؟');">
                    <input type="hidden" name="action" value="delete_record">
                    <input type="hidden" name="table" value="horse_certificates">
                    <input type="hidden" name="record_id" value="<?= $c['id'] ?>">
                    <input type="hidden" name="scroll_to" value="certificates">
                    <button type="submit" class="btn-icon-delete">🗑️</button>
                </form>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- المواعيد -->
    <div class="tab-content" data-tab="appointments">
        <div class="info-card">
            <h3>📅 المواعيد البيطرية</h3>
            <a href="clinics.php?horse=<?= $id ?>" class="btn btn-primary">➕ حجز موعد جديد</a>
        </div>
        <div class="records-list">
            <?php if (empty($appointments)): ?>
                <div class="empty-small">لا توجد مواعيد بعد</div>
            <?php else: foreach ($appointments as $a):
                $status_colors = ['pending' => '#f39c12', 'confirmed' => 'var(--green)', 'completed' => '#3498db', 'cancelled' => 'var(--red)'];
                $status_labels = ['pending' => 'قيد الانتظار', 'confirmed' => 'مؤكد', 'completed' => 'منجز', 'cancelled' => 'ملغى'];
            ?>
            <div class="record-card">
                <div class="record-type" style="background: <?= $status_colors[$a['status']] ?>;">📅</div>
                <div class="record-body">
                    <h4><?= sanitize($a['clinic_name']) ?></h4>
                    <div class="record-meta">
                        <span>📅 <?= $a['appointment_date'] ?></span>
                        <span>🕐 <?= $a['appointment_time'] ?></span>
                        <span style="color: <?= $status_colors[$a['status']] ?>;"><?= $status_labels[$a['status']] ?></span>
                    </div>
                    <?php if ($a['reason']): ?><p><?= sanitize($a['reason']) ?></p><?php endif; ?>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        document.querySelector(`.tab-content[data-tab="${tab}"]`).classList.add('active');
    });
});
// فتح التبويب حسب الـ hash
const hash = window.location.hash.replace('#', '');
if (hash) {
    const btn = document.querySelector(`.tab-btn[data-tab="${hash}"]`);
    if (btn) btn.click();
}
</script>

<?php include 'includes/footer.php'; ?>
