<?php
$page_title = 'إنشاء حساب';
require_once 'config/db.php';

if (isLoggedIn()) redirect('account.php');

$account_types = [
    'user' => ['👤', 'فارس / عميل', 'احجز دروس، اشتري معدات، اقتني خيلك', null, '#c9a227'],
    'center' => ['🏇', 'مركز فروسية', 'سجّل ناديك واستقبل حجوزات الفرسان', null, '#c9a227'],
    'clinic' => ['🩺', 'عيادة بيطرية', 'استقبل المواعيد وأدر سجلات الخيول', null, '#2e86de'],
    'photographer' => ['📸', 'مصوّر فروسية', 'اعرض باقاتك واستقبل حجوزات تصوير', null, '#9b59b6'],
];

$selected_type = $_POST['account_type'] ?? $_GET['type'] ?? 'user';
if (!isset($account_types[$selected_type])) $selected_type = 'user';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $phone = sanitize($_POST['phone'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($full_name) < 3) $errors[] = 'الاسم الكامل 3 أحرف على الأقل.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'البريد الإلكتروني غير صحيح.';
    if (!preg_match('/^[0-9+\-\s]{8,20}$/', $phone)) $errors[] = 'رقم الهاتف غير صحيح.';
    if (!in_array($city, $palestinian_cities)) $errors[] = 'الرجاء اختيار مدينة فلسطينية صحيحة.';
    if (strlen($password) < 6) $errors[] = 'كلمة المرور 6 أحرف على الأقل.';
    if ($password !== $confirm) $errors[] = 'كلمتا المرور غير متطابقتين.';

    if (empty($errors)) {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'البريد الإلكتروني مستخدم مسبقاً.';
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, city) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$full_name, $email, $hashed, $phone, $city])) {
                $new_user_id = $conn->lastInsertId();
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['user_name'] = $full_name;
                $_SESSION['user_role'] = 'user';

                // تسجيل تلقائي كمركز رسمي
                if ($selected_type === 'center') {
                    $default_image = 'assets/images/centers/c1.jpg';
                    $center_ins = $conn->prepare("
                        INSERT INTO centers (name, description, city, address, phone, email, image, cover_image, opening_hours, owner_id, approval_status, verified, featured, rating, reviews_count)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, '8:00 ص - 8:00 م', ?, 'approved', 0, 0, 4.5, 0)
                    ");
                    $ok = $center_ins->execute([
                        $full_name,
                        'مركز فروسية جديد — يُرجى تحديث الوصف من صفحة المركز.',
                        $city,
                        $city,
                        $phone,
                        $email,
                        $default_image,
                        $default_image,
                        $new_user_id,
                    ]);
                    if ($ok) {
                        $conn->prepare("UPDATE users SET role = 'center' WHERE id = ?")->execute([$new_user_id]);
                        $_SESSION['user_role'] = 'center';
                        redirect('my-center.php?tab=profile');
                    }
                    $errors[] = 'تعذّر إنشاء المركز (قد تكون أعمدة owner_id/approval_status غير موجودة في جدول centers — شغّل database_admin_v2.sql).';
                }

                // تسجيل تلقائي كعيادة بيطرية رسمية
                if ($selected_type === 'clinic') {
                    $default_image = 'assets/images/horses/h1.jpg';
                    $clinic_ins = $conn->prepare("
                        INSERT INTO clinics (name, vet_name, specialization, description, city, address, phone, email, opening_hours,
                            consultation_fee, emergency_available, home_visit, image, license_number, owner_id, approval_status, verified, featured)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, '8:00 ص - 5:00 م', 100, 0, 0, ?, '', ?, 'approved', 0, 0)
                    ");
                    $ok = $clinic_ins->execute([
                        $full_name,
                        $full_name,
                        'طب بيطري عام',
                        'عيادة بيطرية جديدة — يُرجى تحديث الوصف من صفحة العيادة.',
                        $city,
                        $city,
                        $phone,
                        $email,
                        $default_image,
                        $new_user_id,
                    ]);
                    if ($ok) {
                        $conn->prepare("UPDATE users SET role = 'clinic' WHERE id = ?")->execute([$new_user_id]);
                        $_SESSION['user_role'] = 'clinic';
                        redirect('my-clinic.php?tab=profile');
                    }
                    $errors[] = 'تعذّر إنشاء العيادة (قد تكون أعمدة owner_id/approval_status/license_number غير موجودة في جدول clinics — شغّل database_business_accounts.sql).';
                }

                // تسجيل تلقائي كمصوّر رسمي
                if ($selected_type === 'photographer') {
                    $default_image = 'assets/images/horses/h1.jpg';
                    $ph_ins = $conn->prepare("
                        INSERT INTO photographers (owner_id, studio_name, description, city, address, phone, email, image, instagram, portfolio_url, years_experience, approval_status, verified, featured)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', '', 1, 'approved', 0, 0)
                    ");
                    $ok = $ph_ins->execute([
                        $new_user_id,
                        $full_name,
                        'مصوّر فروسية جديد — يُرجى تحديث الوصف من إعدادات الاستوديو.',
                        $city,
                        $city,
                        $phone,
                        $email,
                        $default_image,
                    ]);
                    if ($ok) {
                        $conn->prepare("UPDATE users SET role = 'photographer' WHERE id = ?")->execute([$new_user_id]);
                        $_SESSION['user_role'] = 'photographer';
                        redirect('my-studio.php?tab=profile');
                    }
                    $errors[] = 'تعذّر إنشاء حساب المصوّر (قد يكون جدول photographers غير موجود — شغّل database_business_accounts.sql).';
                }

                $next = $account_types[$selected_type][3] ?? null;
                redirect($next ?: 'account.php');
            } else {
                $errors[] = 'حدث خطأ أثناء إنشاء الحساب.';
            }
        }
    }
}

include 'includes/header.php';
?>

<section class="register-page">
    <div class="register-shell">

        <!-- جانب الهوية -->
        <aside class="register-brand">
            <a href="index.php" class="rb-logo" aria-label="KHYOL - الرئيسية">
                <svg viewBox="0 0 64 64" width="56" height="56" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="rb-gold" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="#e5bf3d"/>
                            <stop offset="55%" stop-color="#c9a227"/>
                            <stop offset="100%" stop-color="#8b7510"/>
                        </linearGradient>
                    </defs>
                    <path fill="url(#rb-gold)" d="M16 60c-1-7 1-13 5-17-3-5-5-10-4-16 0-1 1-2 2-1 2 1 4 3 5 6 3-3 7-5 12-5 11 0 22 8 24 22 1 5-1 8-3 9l-4 1 1 4-3-1-1-3-4 1 1-4-5-2-1 6 3 1-2 2-9-1c-2 4-4 8-3 13 0 1 0 2-2 2h-7c-2 0-3 0-2-2 1-3 0-5-3-7l-1 1zM47 27a2 2 0 100-4 2 2 0 000 4z"/>
                </svg>
                <span class="rb-text">
                    <span class="rb-name">KHYOL</span>
                    <span class="rb-sub">خيـــول</span>
                </span>
            </a>
            <h1 class="rb-title">انضم لعالم<br><span>الفروسية</span></h1>
            <p class="rb-tagline">المنصة الأولى للفروسية في فلسطين — مركز شامل لمحبي الخيل والمدربين والمراكز والعيادات.</p>
            <ul class="rb-perks">
                <li><span>✓</span> حجوزات أونلاين فورية</li>
                <li><span>✓</span> سوق ومزاد مباشر للخيل</li>
                <li><span>✓</span> ملف صحي كامل لكل فرس</li>
                <li><span>✓</span> تواصل مباشر مع المراكز والعيادات</li>
            </ul>
            <div class="rb-foot">
                لديك حساب؟ <a href="login.php">سجّل دخولك</a>
            </div>
        </aside>

        <!-- جانب الفورم -->
        <main class="register-form-side">
            <div class="rf-head">
                <h2>إنشاء حساب جديد</h2>
                <p>اختر نوع حسابك ثم عبّئ بياناتك</p>
            </div>

            <!-- بطاقات نوع الحساب -->
            <div class="acc-cards">
                <?php foreach ($account_types as $key => [$icon, $label, $desc, $next, $color]): ?>
                    <button type="button"
                            class="acc-card <?= $selected_type === $key ? 'is-active' : '' ?>"
                            data-type="<?= $key ?>"
                            data-color="<?= $color ?>"
                            data-next="<?= $next ? 'سيُطلب إكمال ملف النشاط بعد التسجيل' : 'حسابك جاهز فور التسجيل' ?>"
                            style="--acc-color: <?= $color ?>;">
                        <span class="acc-card-icon"><?= $icon ?></span>
                        <span class="acc-card-text">
                            <strong><?= $label ?></strong>
                            <small><?= $desc ?></small>
                        </span>
                        <span class="acc-card-check">✓</span>
                    </button>
                <?php endforeach; ?>
            </div>

            <p class="rf-hint" id="rfHint">
                <?= $account_types[$selected_type][3] ? 'سيُطلب إكمال ملف النشاط بعد التسجيل' : 'حسابك جاهز فور التسجيل' ?>
            </p>

            <?php if (!empty($errors)): ?>
            <div class="rf-error">
                <strong>⚠️ يوجد بعض المشاكل:</strong>
                <ul><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
            </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" class="rf-form" id="registerForm">
                <input type="hidden" name="account_type" value="<?= $selected_type ?>" id="accountTypeInput">

                <div class="rf-field">
                    <label for="rfName">الاسم الكامل</label>
                    <input id="rfName" type="text" name="full_name" required value="<?= sanitize($_POST['full_name'] ?? '') ?>" placeholder="مثلاً: أحمد العمري">
                </div>

                <div class="rf-field">
                    <label for="rfEmail">البريد الإلكتروني</label>
                    <input id="rfEmail" type="email" name="email" required value="<?= sanitize($_POST['email'] ?? '') ?>" placeholder="example@khyol.ps">
                </div>

                <div class="rf-row">
                    <div class="rf-field">
                        <label for="rfPhone">رقم الهاتف</label>
                        <input id="rfPhone" type="tel" name="phone" required value="<?= sanitize($_POST['phone'] ?? '') ?>" placeholder="0591234567" dir="ltr">
                    </div>
                    <div class="rf-field">
                        <label for="rfCity">المدينة</label>
                        <select id="rfCity" name="city" required>
                            <option value="">اختر المدينة</option>
                            <?php foreach ($palestinian_cities as $c): ?>
                                <option value="<?= $c ?>" <?= (($_POST['city'] ?? '') == $c) ? 'selected' : '' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="rf-row">
                    <div class="rf-field">
                        <label for="rfPass">كلمة المرور</label>
                        <input id="rfPass" type="password" name="password" required minlength="6" placeholder="٦ أحرف على الأقل">
                    </div>
                    <div class="rf-field">
                        <label for="rfPass2">تأكيد كلمة المرور</label>
                        <input id="rfPass2" type="password" name="confirm_password" required minlength="6" placeholder="أعد الكتابة">
                    </div>
                </div>

                <button type="submit" class="rf-submit">
                    <span>إنشاء الحساب</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                </button>

                <p class="rf-terms">بإنشاء حسابك توافق على <a href="#">شروط الاستخدام</a> و<a href="#">سياسة الخصوصية</a></p>
            </form>
        </main>
    </div>
</section>

<script>
(function () {
    const cards = document.querySelectorAll('.acc-card');
    const hidden = document.getElementById('accountTypeInput');
    const hint = document.getElementById('rfHint');
    cards.forEach(c => {
        c.addEventListener('click', () => {
            cards.forEach(x => x.classList.remove('is-active'));
            c.classList.add('is-active');
            hidden.value = c.dataset.type;
            hint.textContent = c.dataset.next;
        });
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
