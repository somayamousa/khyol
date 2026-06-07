<?php
$page_title = 'إضافة/تعديل خيل';
require_once 'config/db.php';

if (!isLoggedIn()) redirect('login.php?redirect=horses');

$user_id = (int)$_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);
$horse = null;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM horses WHERE id = ? AND owner_id = ?");
    $stmt->execute([$id, $user_id]);
    $horse = $stmt->fetch();
    if (!$horse) redirect('horses.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $breed = sanitize($_POST['breed'] ?? '');
    $gender = ($_POST['gender'] ?? '') === 'female' ? 'female' : 'male';
    $birth_date = $_POST['birth_date'] ?: null;
    $color = sanitize($_POST['color'] ?? '');
    $height = (float)($_POST['height_cm'] ?? 0) ?: null;
    $weight = (float)($_POST['weight_kg'] ?? 0) ?: null;
    $reg_num = sanitize($_POST['registration_number'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $is_pure = isset($_POST['is_pure']) ? 1 : 0;
    $father = sanitize($_POST['father_name'] ?? '');
    $mother = sanitize($_POST['mother_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $experience = sanitize($_POST['experience_details'] ?? '');
    $health_status = in_array($_POST['health_status'] ?? '', ['healthy','under_care','recovering','critical']) ? $_POST['health_status'] : 'healthy';
    $last_checkup_date = $_POST['last_checkup_date'] ?: null;
    $medical_notes = sanitize($_POST['medical_notes'] ?? '');
    $pedigree_label = sanitize($_POST['pedigree_label'] ?? '');
    $is_for_sale = isset($_POST['is_for_sale']) ? 1 : 0;
    $sale_price = $is_for_sale ? ((float)($_POST['sale_price'] ?? 0) ?: null) : null;
    $sale_city = $is_for_sale ? sanitize($_POST['sale_city'] ?? '') : null;
    $sale_description = $is_for_sale ? sanitize($_POST['sale_description'] ?? '') : null;

    if (strlen($name) < 2) $errors[] = 'اسم الخيل مطلوب';
    if (empty($city)) $errors[] = 'اختر مدينة الخيل';
    if ($is_for_sale && !$sale_price) $errors[] = 'أدخل سعر البيع';
    if ($is_for_sale && empty($sale_city)) $errors[] = 'اختر مدينة البيع';

    // رفع الصورة
    $image_path = $horse['main_image'] ?? 'assets/images/horses/default.jpg';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['image']['size'] < 5 * 1024 * 1024) {
            $upload_dir = __DIR__ . '/assets/images/horses/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $filename = 'h_' . $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                $image_path = 'assets/images/horses/' . $filename;
            }
        } else {
            $errors[] = 'صورة غير صحيحة (jpg/png/webp بحجم < 5MB)';
        }
    }

    if (empty($errors)) {
        if ($horse) {
            $upd = $conn->prepare("
                UPDATE horses SET name=?, breed=?, gender=?, birth_date=?, color=?, height_cm=?, weight_kg=?,
                    registration_number=?, city=?, is_pure=?, father_name=?, mother_name=?, description=?,
                    experience_details=?, health_status=?, last_checkup_date=?, medical_notes=?, pedigree_label=?,
                    main_image=?, is_for_sale=?, sale_price=?, sale_city=?, sale_description=?
                WHERE id=? AND owner_id=?
            ");
            $upd->execute([$name, $breed, $gender, $birth_date, $color, $height, $weight, $reg_num, $city, $is_pure,
                $father, $mother, $description, $experience, $health_status, $last_checkup_date, $medical_notes, $pedigree_label,
                $image_path, $is_for_sale, $sale_price, $sale_city, $sale_description, $id, $user_id]);
            $new_id = $id;
        } else {
            $ins = $conn->prepare("
                INSERT INTO horses (owner_id, name, breed, gender, birth_date, color, height_cm, weight_kg,
                    registration_number, city, is_pure, father_name, mother_name, description, experience_details,
                    health_status, last_checkup_date, medical_notes, pedigree_label, main_image,
                    is_for_sale, sale_price, sale_city, sale_description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([$user_id, $name, $breed, $gender, $birth_date, $color, $height, $weight, $reg_num,
                $city, $is_pure, $father, $mother, $description, $experience,
                $health_status, $last_checkup_date, $medical_notes, $pedigree_label, $image_path,
                $is_for_sale, $sale_price, $sale_city, $sale_description]);
            $new_id = (int)$conn->lastInsertId();
        }

        // معالجة رفع صور المعرض
        if (!empty($_FILES['gallery']['name'][0])) {
            $gallery_dir = __DIR__ . '/assets/images/horses/';
            if (!is_dir($gallery_dir)) mkdir($gallery_dir, 0755, true);
            $allowed_img = ['jpg','jpeg','png','webp'];
            $ins_img = $conn->prepare("INSERT INTO horse_images (horse_id, file_path, sort_order) VALUES (?, ?, ?)");
            foreach ($_FILES['gallery']['tmp_name'] as $i => $tmp) {
                if ($_FILES['gallery']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $ext = strtolower(pathinfo($_FILES['gallery']['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_img) || $_FILES['gallery']['size'][$i] > 5 * 1024 * 1024) continue;
                $fname = 'g_' . $new_id . '_' . time() . '_' . $i . '.' . $ext;
                if (move_uploaded_file($tmp, $gallery_dir . $fname)) {
                    $ins_img->execute([$new_id, 'assets/images/horses/' . $fname, $i]);
                }
            }
        }

        // معالجة الشهادات
        $cert_types  = $_POST['cert_type']  ?? [];
        $cert_titles = $_POST['cert_title'] ?? [];
        $cert_issuers = $_POST['cert_issuer'] ?? [];
        $cert_dates  = $_POST['cert_date']  ?? [];
        $cert_notes  = $_POST['cert_notes'] ?? [];
        foreach ($cert_titles as $ci => $ctitle) {
            $ctitle = trim($ctitle);
            if (!$ctitle) continue;
            $ctype   = in_array($cert_types[$ci] ?? '', ['pedigree','competition','health','other']) ? $cert_types[$ci] : 'other';
            $cissuer = trim($cert_issuers[$ci] ?? '');
            $cdate   = $cert_dates[$ci] ?: null;
            $cnotes  = trim($cert_notes[$ci] ?? '');
            $conn->prepare("INSERT INTO horse_certificates (horse_id, cert_type, title, issuer, issue_date, notes) VALUES (?,?,?,?,?,?)")
                 ->execute([$new_id, $ctype, $ctitle, $cissuer ?: null, $cdate, $cnotes ?: null]);
        }

        // معالجة رفع فيديو
        if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
            $allowed_v = ['mp4','webm','mov','m4v'];
            $vext = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
            if (in_array($vext, $allowed_v) && $_FILES['video']['size'] < 50 * 1024 * 1024) {
                $vdir = __DIR__ . '/assets/uploads/horse_videos/';
                if (!is_dir($vdir)) mkdir($vdir, 0755, true);
                $vfname = 'v_' . $new_id . '_' . time() . '.' . $vext;
                if (move_uploaded_file($_FILES['video']['tmp_name'], $vdir . $vfname)) {
                    $vtitle = sanitize($_POST['video_title'] ?? 'فيديو');
                    $conn->prepare("INSERT INTO horse_videos (horse_id, title, file_path) VALUES (?, ?, ?)")
                        ->execute([$new_id, $vtitle, 'assets/uploads/horse_videos/' . $vfname]);
                }
            }
        }

        redirect('horse.php?id=' . $new_id);
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1><?= $horse ? '✏️ تعديل بيانات الخيل' : '➕ إضافة خيل جديد' ?></h1>
    </div>
</div>

<div class="container" style="padding: 40px 0; max-width: 900px;">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $e): ?><div><?= $e ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="form-card">
        <h3 style="color: var(--gold); margin-bottom: 20px;">📝 البيانات الأساسية</h3>
        <div class="form-row">
            <div class="form-group">
                <label>اسم الخيل *</label>
                <input type="text" name="name" required value="<?= sanitize($horse['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>السلالة</label>
                <input type="text" name="breed" placeholder="مثل: عربي، إنجليزي، مهجن" value="<?= sanitize($horse['breed'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>الجنس *</label>
                <select name="gender" required>
                    <option value="male" <?= ($horse['gender'] ?? '') === 'male' ? 'selected' : '' ?>>ذكر ♂️</option>
                    <option value="female" <?= ($horse['gender'] ?? '') === 'female' ? 'selected' : '' ?>>أنثى ♀️</option>
                </select>
            </div>
            <div class="form-group">
                <label>تاريخ الميلاد</label>
                <input type="date" name="birth_date" value="<?= sanitize($horse['birth_date'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>اللون</label>
                <input type="text" name="color" placeholder="أشقر، أدهم، أشهب..." value="<?= sanitize($horse['color'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>الارتفاع (سم)</label>
                <input type="number" step="0.1" name="height_cm" value="<?= sanitize($horse['height_cm'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>الوزن (كغ)</label>
                <input type="number" step="0.1" name="weight_kg" value="<?= sanitize($horse['weight_kg'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>رقم التسجيل الرسمي</label>
                <input type="text" name="registration_number" value="<?= sanitize($horse['registration_number'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>📍 مدينة الخيل *</label>
                <select name="city" required>
                    <option value="">— اختر المدينة —</option>
                    <?php foreach ($palestinian_cities as $city_opt): ?>
                    <option value="<?= $city_opt ?>" <?= ($horse['city'] ?? '') === $city_opt ? 'selected' : '' ?>><?= $city_opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group" style="margin-top: 15px;">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="is_pure" <?= !empty($horse['is_pure']) ? 'checked' : '' ?>>
                <span>⭐ خيل أصيل (لديه شهادة نسب موثقة)</span>
            </label>
        </div>

        <h3 style="color: var(--gold); margin: 30px 0 20px;">🧬 الأصل والنسب</h3>
        <div class="form-row">
            <div class="form-group">
                <label>اسم الأب</label>
                <input type="text" name="father_name" value="<?= sanitize($horse['father_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>اسم الأم</label>
                <input type="text" name="mother_name" value="<?= sanitize($horse['mother_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>وصف الأصالة</label>
                <input type="text" name="pedigree_label" placeholder="مثلاً: عربي أصيل (مسجل)" value="<?= sanitize($horse['pedigree_label'] ?? '') ?>">
            </div>
        </div>

        <h3 style="color: var(--gold); margin: 30px 0 20px;">🩺 الحالة الصحية</h3>
        <div class="form-row">
            <div class="form-group">
                <label>الحالة الحالية</label>
                <select name="health_status">
                    <?php $hs = $horse['health_status'] ?? 'healthy'; ?>
                    <option value="healthy" <?= $hs==='healthy'?'selected':'' ?>>✅ سليم</option>
                    <option value="under_care" <?= $hs==='under_care'?'selected':'' ?>>🩺 تحت العلاج</option>
                    <option value="recovering" <?= $hs==='recovering'?'selected':'' ?>>🌱 يتعافى</option>
                    <option value="critical" <?= $hs==='critical'?'selected':'' ?>>🚨 حرج</option>
                </select>
            </div>
            <div class="form-group">
                <label>تاريخ آخر فحص</label>
                <input type="date" name="last_checkup_date" value="<?= sanitize($horse['last_checkup_date'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>ملاحظات طبية (حساسيات، أمراض مزمنة...)</label>
            <textarea name="medical_notes" rows="2" placeholder="مثلاً: حساسية موسمية من الغبار"><?= sanitize($horse['medical_notes'] ?? '') ?></textarea>
        </div>

        <h3 style="color: var(--gold); margin: 30px 0 20px;">📸 الصورة الرئيسية</h3>
        <div class="form-group">
            <label>صورة الخيل الأساسية</label>
            <input type="file" name="image" accept="image/*">
            <?php if (!empty($horse['main_image'])): ?>
                <img src="<?= sanitize($horse['main_image']) ?>" style="max-width: 200px; margin-top: 10px; border-radius: 10px;">
            <?php endif; ?>
        </div>

        <h3 style="color: var(--gold); margin: 30px 0 20px;">🖼️ معرض الصور (متعدد)</h3>
        <div class="form-group">
            <label>أضف صور إضافية (اختر عدة صور دفعة واحدة)</label>
            <input type="file" name="gallery[]" accept="image/*" multiple>
            <small style="color: var(--text-dark-muted);">jpg/png/webp بحجم أقل من 5 ميجا للصورة</small>
        </div>

        <h3 style="color: var(--gold); margin: 30px 0 20px;">🎬 فيديو (الجري، التدريب...)</h3>
        <div class="form-row">
            <div class="form-group">
                <label>عنوان الفيديو</label>
                <input type="text" name="video_title" placeholder="مثلاً: فيديو الركض" value="فيديو">
            </div>
            <div class="form-group">
                <label>ملف الفيديو</label>
                <input type="file" name="video" accept="video/mp4,video/webm,video/quicktime">
                <small style="color: var(--text-dark-muted);">mp4/webm/mov بحجم أقل من 50 ميجا</small>
            </div>
        </div>

        <div class="form-group">
            <label>وصف الخيل</label>
            <textarea name="description" rows="3"><?= sanitize($horse['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label>تفاصيل الخبرة والإنجازات</label>
            <textarea name="experience_details" rows="4" placeholder="السباقات، التدريبات، الإنجازات، المشاركات..."><?= sanitize($horse['experience_details'] ?? '') ?></textarea>
        </div>

        <h3 style="color: var(--gold); margin: 30px 0 20px;">💰 عرض للبيع</h3>
        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="is_for_sale" id="is_for_sale_cb" <?= !empty($horse['is_for_sale']) ? 'checked' : '' ?> onchange="document.getElementById('sale_fields').style.display=this.checked?'block':'none'">
                <span>🏷️ عرض هذا الخيل للبيع في السوق العام</span>
            </label>
        </div>

        <div id="sale_fields" style="display: <?= !empty($horse['is_for_sale']) ? 'block' : 'none' ?>;">
            <div class="form-row">
                <div class="form-group">
                    <label>السعر المطلوب (₪) *</label>
                    <input type="number" step="1" name="sale_price" value="<?= sanitize($horse['sale_price'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>📍 مدينة البيع *</label>
                    <select name="sale_city" required>
                        <option value="">— اختر المدينة —</option>
                        <?php foreach ($palestinian_cities as $city): ?>
                        <option value="<?= $city ?>" <?= ($horse['sale_city'] ?? '') === $city ? 'selected' : '' ?>><?= $city ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>وصف إعلان البيع</label>
                <textarea name="sale_description" rows="3" placeholder="مزايا الخيل، سبب البيع، شروط إضافية..."><?= sanitize($horse['sale_description'] ?? '') ?></textarea>
            </div>
        </div>

        <h3 style="color: var(--gold); margin: 30px 0 20px;">📜 الشهادات والوثائق <span style="font-size:13px; color: var(--gray); font-weight:400;">(اختياري)</span></h3>
        <div id="certs-container">
            <div class="cert-row" style="background: rgba(201,162,39,0.05); border: 1px solid rgba(201,162,39,0.15); border-radius: 14px; padding: 20px; margin-bottom: 14px;">
                <div class="form-row" style="margin-bottom: 12px;">
                    <div class="form-group">
                        <label>نوع الشهادة</label>
                        <select name="cert_type[]">
                            <option value="pedigree">🧬 شهادة نسب</option>
                            <option value="competition">🏆 شهادة مسابقة</option>
                            <option value="health">🩺 شهادة صحية</option>
                            <option value="other">📄 أخرى</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>عنوان الشهادة *</label>
                        <input type="text" name="cert_title[]" placeholder="مثلاً: شهادة نسب WAHO">
                    </div>
                </div>
                <div class="form-row" style="margin-bottom: 12px;">
                    <div class="form-group">
                        <label>الجهة المانحة</label>
                        <input type="text" name="cert_issuer[]" placeholder="مثلاً: الاتحاد الفلسطيني للفروسية">
                    </div>
                    <div class="form-group">
                        <label>تاريخ الإصدار</label>
                        <input type="date" name="cert_date[]">
                    </div>
                </div>
                <div class="form-group">
                    <label>ملاحظات</label>
                    <input type="text" name="cert_notes[]" placeholder="تفاصيل إضافية (اختياري)">
                </div>
            </div>
        </div>
        <button type="button" onclick="addCert()" class="btn btn-outline" style="padding: 8px 20px; font-size: 13px; margin-bottom: 30px;">➕ إضافة شهادة أخرى</button>

        <script>
        function addCert() {
            const tpl = document.querySelector('.cert-row').cloneNode(true);
            tpl.querySelectorAll('input, select, textarea').forEach(el => el.value = '');
            const btn = document.querySelector('[onclick="addCert()"]');
            btn.parentNode.insertBefore(tpl, btn);
        }
        </script>

        <div style="display: flex; gap: 10px; margin-top: 10px;">
            <button type="submit" class="btn btn-primary"><?= $horse ? '💾 حفظ التعديلات' : '➕ إضافة الخيل' ?></button>
            <a href="<?= $horse ? 'horse.php?id=' . $id : 'horses.php' ?>" class="btn btn-outline">إلغاء</a>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
