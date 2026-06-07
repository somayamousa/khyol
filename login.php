<?php
$page_title = 'تسجيل الدخول';
require_once 'config/db.php';

$redirect_after = $_GET['redirect'] ?? $_POST['redirect'] ?? 'account.php';
// تأكد أن الـ redirect داخلي فقط (لا يبدأ بـ http)
if (preg_match('#^https?://#i', $redirect_after)) $redirect_after = 'account.php';

if (isLoggedIn()) redirect($redirect_after);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password)) {
        $errors[] = 'الرجاء تعبئة جميع الحقول بشكل صحيح.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            redirect($redirect_after);
        } else {
            $errors[] = 'البريد الإلكتروني أو كلمة المرور غير صحيحة.';
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-head">
            <span class="icon">🏇</span>
            <h2>تسجيل الدخول</h2>
            <p>مرحباً بعودتك إلى عالم الفروسية</p>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <span>⚠️</span>
            <div>
                <?php foreach ($errors as $e): ?>
                    <div><?= $e ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['registered'])): ?>
        <div class="alert alert-success">
            <span>✅</span>
            <div>تم إنشاء حسابك بنجاح، يمكنك الآن تسجيل الدخول.</div>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="redirect" value="<?= sanitize($redirect_after) ?>">

            <div class="form-group">
                <label>البريد الإلكتروني</label>
                <input type="email" name="email" class="form-control" required placeholder="example@mail.com" value="<?= isset($_POST['email']) ? sanitize($_POST['email']) : '' ?>">
            </div>

            <div class="form-group">
                <label>كلمة المرور</label>
                <div style="position:relative;">
                    <input type="password" name="password" id="passwordInput" class="form-control" required placeholder="••••••••" style="padding-left: 44px;">
                    <button type="button" id="togglePassword" onclick="(function(){var i=document.getElementById('passwordInput'),b=document.getElementById('togglePassword');if(i.type==='password'){i.type='text';b.textContent='🙈';}else{i.type='password';b.textContent='👁';}})()" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:18px;line-height:1;padding:0;color:var(--gray);">👁</button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">دخول 🔓</button>
        </form>

        <div class="auth-footer">
            ليس لديك حساب؟ <a href="register.php">أنشئ حساباً جديداً</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
