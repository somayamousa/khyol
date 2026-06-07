<?php
$page_title = 'تواصل معنا';
require_once 'config/db.php';

$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['full_name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $phone = sanitize($_POST['phone'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    $type = $_POST['type'] ?? 'inquiry';

    if (strlen($name) < 3) $errors[] = 'الرجاء إدخال اسم صحيح.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'البريد الإلكتروني غير صحيح.';
    if (strlen($message) < 10) $errors[] = 'الرجاء كتابة رسالة أطول (10 أحرف على الأقل).';

    if (empty($errors)) {
        if ($type === 'inquiry' && !$subject) {
            $ins = $conn->prepare("INSERT INTO contact_messages (full_name, email, phone, message) VALUES (?, ?, ?, ?)");
            $ins->execute([$name, $email, $phone, $message]);
            $contact_msg_id = (int)$conn->lastInsertId();
        } else {
            if (!$subject) $subject = 'بدون عنوان';
            $uid = isLoggedIn() ? $_SESSION['user_id'] : null;
            $valid_types = ['complaint','suggestion','inquiry'];
            if (!in_array($type, $valid_types)) $type = 'inquiry';
            $ins = $conn->prepare("INSERT INTO complaints (user_id, full_name, email, phone, subject, message, type) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$uid, $name, $email, $phone, $subject, $message, $type]);
            $contact_msg_id = null;
        }

        // إنشاء محادثة مع الأدمن إذا كان المستخدم مسجلاً
        if (isLoggedIn()) {
            $uid = (int)$_SESSION['user_id'];
            // ابحث عن محادثة أدمن موجودة أو أنشئ واحدة
            $find_conv = $conn->prepare("SELECT id FROM conversations WHERE user_id = ? AND is_admin_chat = 1 LIMIT 1");
            $find_conv->execute([$uid]);
            $conv_id = $find_conv->fetchColumn();
            if (!$conv_id) {
                $conn->prepare("INSERT INTO conversations (user_id, is_admin_chat) VALUES (?, 1)")
                    ->execute([$uid]);
                $conv_id = (int)$conn->lastInsertId();
            }
            // أضف الرسالة في المحادثة
            $type_labels = ['inquiry' => 'استفسار', 'complaint' => 'شكوى', 'suggestion' => 'اقتراح'];
            $type_label = $type_labels[$type] ?? 'رسالة';
            $full_body = ($subject ? "【{$type_label}】 {$subject}\n" : "【{$type_label}】\n") . $message;
            $conn->prepare("INSERT INTO messages (conversation_id, sender_id, sender_type, body) VALUES (?, ?, 'user', ?)")
                ->execute([$conv_id, $uid, $full_body]);
            $conn->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = ?")
                ->execute([$conv_id]);
        }

        $success = true;
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>✉️ تواصل معنا</h1>
        <p>نحن هنا لمساعدتك - أرسل لنا استفسارك أو شكواك أو اقتراحك</p>
    </div>
</div>

<div class="container" style="padding: 40px 0;">
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;" class="contact-layout">
        <div class="booking-card" style="margin: 0;">
            <?php if ($success): ?>
            <div style="text-align: center; padding: 40px 20px;">
                <div style="font-size: 80px; margin-bottom: 20px;">✅</div>
                <h2 style="color: var(--gold); margin-bottom: 15px;">تم إرسال رسالتك بنجاح!</h2>
                <p style="color: #ccc; line-height: 2; margin-bottom: 25px;">
                    شكراً لتواصلك معنا. سيقوم فريقنا بالرد عليك في أقرب وقت ممكن.
                </p>
                <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                    <a href="index.php" class="btn btn-primary">العودة للرئيسية</a>
                    <?php if (isLoggedIn()): ?>
                    <a href="chat.php" class="btn" style="background: rgba(212,175,55,0.1); color: var(--gold); border: 1px solid var(--gold);">💬 متابعة عبر المحادثات</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>

            <h2 style="color: var(--gold); margin-bottom: 20px; text-align: center;">📝 أرسل لنا رسالة</h2>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <span>⚠️</span>
                <div><?php foreach ($errors as $e) echo "<div>$e</div>"; ?></div>
            </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>نوع الرسالة</label>
                    <select name="type" class="form-control" required>
                        <option value="inquiry">❓ استفسار</option>
                        <option value="complaint">⚠️ شكوى</option>
                        <option value="suggestion">💡 اقتراح</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>الاسم الكامل</label>
                        <input type="text" name="full_name" class="form-control" required value="<?= isLoggedIn() ? sanitize($_SESSION['user_name']) : sanitize($_POST['full_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control" required value="<?= sanitize($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>رقم الهاتف</label>
                        <input type="tel" name="phone" class="form-control" value="<?= sanitize($_POST['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>الموضوع</label>
                        <input type="text" name="subject" class="form-control" placeholder="موضوع الرسالة" value="<?= sanitize($_POST['subject'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>الرسالة</label>
                    <textarea name="message" class="form-control" rows="6" required placeholder="اكتب رسالتك هنا بالتفصيل..."><?= sanitize($_POST['message'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block">📤 إرسال الرسالة</button>
            </form>
            <?php endif; ?>
        </div>

        <aside class="center-sidebar" style="position: static;">
            <h3>📞 بيانات التواصل</h3>
            <div class="contact-row">
                <span class="icon">📍</span>
                <div>رام الله - فلسطين</div>
            </div>
            <div class="contact-row">
                <span class="icon">📱</span>
                <div dir="ltr">+970-59-1234567</div>
            </div>
            <div class="contact-row">
                <span class="icon">✉️</span>
                <div>info@khoyol.ps</div>
            </div>
            <div class="contact-row">
                <span class="icon">🕐</span>
                <div>9 ص - 9 م يومياً</div>
            </div>

            <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid rgba(212, 175, 55, 0.15);">
                <h4 style="color: var(--gold); margin-bottom: 15px;">تابعنا على</h4>
                <div class="social-icons" style="justify-content: center;">
                    <a href="#">📘</a>
                    <a href="#">📷</a>
                    <a href="#">🐦</a>
                    <a href="#">▶️</a>
                </div>
            </div>
        </aside>
    </div>
</div>

<style>
@media (max-width: 900px) {
    .contact-layout { grid-template-columns: 1fr !important; }
}
</style>

<?php include 'includes/footer.php'; ?>
