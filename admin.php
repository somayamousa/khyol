<?php
$page_title = 'لوحة الإدارة';
require_once 'config/db.php';
require_once 'includes/notify.php';

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'admin') {
    redirect('login.php');
}

$admin_id = $_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'dashboard';
$action = $_GET['action'] ?? '';

function logAction($conn, $admin_id, $action, $details = '') {
    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$admin_id, $action, $details]);
}

// ========== الإجراءات ==========
if ($action === 'ban_user' && isset($_GET['id'])) {
    $uid = (int)$_GET['id'];
    if ($uid != $admin_id) {
        $user = $conn->prepare("SELECT email, status FROM users WHERE id = ?");
        $user->execute([$uid]);
        $u = $user->fetch();
        $new_status = $u['status'] === 'banned' ? 'active' : 'banned';
        $conn->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new_status, $uid]);
        logAction($conn, $admin_id, $new_status === 'banned' ? 'حظر مستخدم' : 'إلغاء حظر مستخدم', $u['email']);
        if ($new_status === 'banned') {
            send_notification($conn, $uid, 'تم تعليق حسابك', 'تم تعليق حسابك من قِبل الإدارة. للاستفسار تواصل معنا.', 'admin', '🚫', 'contact.php');
        } else {
            send_notification($conn, $uid, 'تم تفعيل حسابك', 'تم رفع الحظر عن حسابك وأصبح نشطاً من جديد.', 'admin', '✅', 'account.php');
        }
        redirect('admin.php?tab=users&updated=1');
    }
}

if ($action === 'verify_user' && isset($_GET['id'])) {
    $uid = (int)$_GET['id'];
    $u = $conn->prepare("SELECT email, verified FROM users WHERE id = ?");
    $u->execute([$uid]);
    $user = $u->fetch();
    $new_val = $user['verified'] ? 0 : 1;
    $conn->prepare("UPDATE users SET verified = ? WHERE id = ?")->execute([$new_val, $uid]);
    logAction($conn, $admin_id, $new_val ? 'توثيق مستخدم' : 'إزالة توثيق', $user['email']);
    if ($new_val) {
        send_notification($conn, $uid, 'تم توثيق حسابك ✓', 'تهانينا! تم منح حسابك شارة التوثيق الرسمية.', 'admin', '✅', 'account.php');
    } else {
        send_notification($conn, $uid, 'تم إزالة توثيق حسابك', 'تم سحب شارة التوثيق من حسابك من قِبل الإدارة.', 'admin', '⚠️', 'contact.php');
    }
    redirect('admin.php?tab=users&updated=1');
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['change_role'])) {
    $uid  = (int)$_POST['user_id'];
    $role = $_POST['role'];
    if (in_array($role, ['user','center','clinic','photographer','admin']) && $uid != $admin_id) {
        // جلب بيانات المستخدم الحالية
        $udata = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $udata->execute([$uid]);
        $udata = $udata->fetch();

        $conn->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $uid]);

        // لو صار مركز → أضف سجل في centers إذا ما في
        if ($role === 'center') {
            $existing = $conn->prepare("SELECT id FROM centers WHERE owner_id = ?");
            $existing->execute([$uid]);
            if (!$existing->fetch()) {
                $conn->prepare("
                    INSERT INTO centers (name, description, city, address, phone, email, image, cover_image, opening_hours, owner_id, approval_status, verified, featured, rating, reviews_count)
                    VALUES (?, 'مركز فروسية — يُرجى تحديث البيانات من لوحة المركز.', ?, ?, ?, ?, 'assets/images/centers/c1.jpg', 'assets/images/centers/c1.jpg', '8:00 ص - 8:00 م', ?, 'approved', 1, 0, 4.5, 0)
                ")->execute([
                    $udata['full_name'],
                    $udata['city'] ?? 'رام الله',
                    $udata['city'] ?? 'رام الله',
                    $udata['phone'] ?? '',
                    $udata['email'] ?? '',
                    $uid,
                ]);
            }
            send_notification($conn, $uid,
                'تمت ترقيتك إلى مركز فروسية ✅',
                'يمكنك الآن إدارة مركزك من خلال لوحة التحكم.',
                'system', '🏇', 'my-center.php'
            );
        }

        // لو صار مصور → أضف سجل في photographers إذا ما في
        if ($role === 'photographer') {
            $tbl = $conn->query("SHOW TABLES LIKE 'photographers'")->fetchColumn();
            if ($tbl) {
                $existing = $conn->prepare("SELECT id FROM photographers WHERE user_id = ?");
                $existing->execute([$uid]);
                if (!$existing->fetch()) {
                    $conn->prepare("INSERT INTO photographers (user_id, name, city, phone, email, bio, approval_status) VALUES (?,?,?,?,?,'مصور فروسية محترف','approved')")
                        ->execute([$uid, $udata['full_name'], $udata['city'] ?? '', $udata['phone'] ?? '', $udata['email'] ?? '']);
                }
            }
            send_notification($conn, $uid,
                'تمت ترقيتك إلى مصور ✅',
                'يمكنك الآن إدارة ملفك كمصور فروسية.',
                'system', '📷', 'my-photographer.php'
            );
        }

        // لو رجع user عادي → أرسل إشعار
        if ($role === 'user') {
            send_notification($conn, $uid,
                'تم تغيير صلاحياتك',
                'تم تغيير دورك في المنصة إلى مستخدم عادي.',
                'system', 'ℹ️', 'account.php'
            );
        }

        logAction($conn, $admin_id, 'تغيير صلاحية', "user_id=$uid role=$role");
        redirect('admin.php?tab=users&updated=1');
    }
}

if ($action === 'delete_user' && isset($_GET['id'])) {
    $uid = (int)$_GET['id'];
    if ($uid != $admin_id) {
        $u = $conn->prepare("SELECT email FROM users WHERE id = ?");
        $u->execute([$uid]);
        $user = $u->fetch();
        $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
        logAction($conn, $admin_id, 'حذف مستخدم', $user['email']);
        redirect('admin.php?tab=users&deleted=1');
    }
}

// اعتماد/رفض مراكز
if ($action === 'approve_center' && isset($_GET['id'])) {
    $cid = (int)$_GET['id'];
    $c = $conn->prepare("SELECT owner_id, name FROM centers WHERE id = ?");
    $c->execute([$cid]);
    $center = $c->fetch();
    $conn->prepare("UPDATE centers SET approval_status = 'approved', verified = 1 WHERE id = ?")->execute([$cid]);
    // ترقية دور صاحب المركز إلى center
    if ($center && $center['owner_id']) {
        $conn->prepare("UPDATE users SET role = 'center' WHERE id = ? AND role = 'user'")
            ->execute([$center['owner_id']]);
        send_notification($conn, (int)$center['owner_id'],
            'تمت الموافقة على مركزك ✅',
            'تهانينا! تمت الموافقة على مركز "' . $center['name'] . '" وأصبح ظاهراً للزوار.',
            'admin', '✅', 'my-center.php'
        );
    }
    logAction($conn, $admin_id, 'اعتماد مركز', $center['name'] ?? "center_id=$cid");
    redirect('admin.php?tab=centers&approved=1');
}

if ($action === 'reject_center' && isset($_GET['id'])) {
    $cid = (int)$_GET['id'];
    $c2 = $conn->prepare("SELECT owner_id, name FROM centers WHERE id = ?");
    $c2->execute([$cid]);
    $center2 = $c2->fetch();
    $conn->prepare("UPDATE centers SET approval_status = 'rejected', verified = 0 WHERE id = ?")->execute([$cid]);
    if ($center2 && $center2['owner_id']) {
        send_notification($conn, (int)$center2['owner_id'],
            'لم تتم الموافقة على مركزك',
            'نأسف، لم تتم الموافقة على مركز "' . $center2['name'] . '". للاستفسار تواصل مع الإدارة.',
            'admin', '❌', 'contact.php'
        );
    }
    logAction($conn, $admin_id, 'رفض مركز', "center_id=$cid");
    redirect('admin.php?tab=centers&rejected=1');
}

if ($action === 'verify_center' && isset($_GET['id'])) {
    $cid = (int)$_GET['id'];
    $c = $conn->prepare("SELECT owner_id, name, verified FROM centers WHERE id = ?");
    $c->execute([$cid]);
    $center = $c->fetch();
    $new_val = $center['verified'] ? 0 : 1;
    $conn->prepare("UPDATE centers SET verified = ? WHERE id = ?")->execute([$new_val, $cid]);
    if ($center['owner_id']) {
        if ($new_val) {
            send_notification($conn, (int)$center['owner_id'],
                'تم توثيق مركزك ✓',
                'حصل مركز "' . $center['name'] . '" على شارة التوثيق الرسمية.',
                'admin', '✅', 'my-center.php'
            );
        } else {
            send_notification($conn, (int)$center['owner_id'],
                'تم إزالة توثيق مركزك',
                'تم سحب شارة التوثيق من مركز "' . $center['name'] . '" من قِبل الإدارة.',
                'admin', '⚠️', 'contact.php'
            );
        }
    }
    logAction($conn, $admin_id, $new_val ? 'توثيق مركز' : 'إزالة توثيق مركز', "center_id=$cid");
    redirect('admin.php?tab=centers');
}

// الرد على شكوى
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reply_complaint'])) {
    $cid = (int)$_POST['complaint_id'];
    $reply = sanitize($_POST['admin_reply'] ?? '');
    $status = $_POST['status'] ?? 'resolved';
    // جلب user_id صاحب الشكوى
    $comp = $conn->prepare("SELECT user_id, subject FROM complaints WHERE id = ?");
    $comp->execute([$cid]);
    $comp_row = $comp->fetch();
    $conn->prepare("UPDATE complaints SET admin_reply=?, status=?, replied_at=NOW() WHERE id=?")
        ->execute([$reply, $status, $cid]);
    if ($comp_row && $comp_row['user_id']) {
        $status_label = ['resolved' => 'تم حل شكواك', 'in_progress' => 'شكواك قيد المعالجة', 'closed' => 'تم إغلاق شكواك'];
        send_notification($conn, (int)$comp_row['user_id'],
            $status_label[$status] ?? 'رد على شكواك',
            'ردّت الإدارة على شكوى "' . $comp_row['subject'] . '": ' . mb_substr($reply, 0, 80, 'UTF-8') . (mb_strlen($reply, 'UTF-8') > 80 ? '…' : ''),
            'admin', '📩', 'account.php?tab=complaints'
        );
    }
    logAction($conn, $admin_id, 'رد على شكوى', "complaint_id=$cid");
    redirect('admin.php?tab=complaints&replied=1');
}

// التعامل مع البلاغات
if ($action === 'dismiss_report' && isset($_GET['id'])) {
    $conn->prepare("UPDATE reports SET status = 'dismissed' WHERE id = ?")->execute([(int)$_GET['id']]);
    logAction($conn, $admin_id, 'رفض بلاغ', $_GET['id']);
    redirect('admin.php?tab=reports');
}

if ($action === 'action_report' && isset($_GET['id'])) {
    $conn->prepare("UPDATE reports SET status = 'actioned' WHERE id = ?")->execute([(int)$_GET['id']]);
    logAction($conn, $admin_id, 'اتخاذ إجراء على بلاغ', $_GET['id']);
    redirect('admin.php?tab=reports');
}

// إعلانات
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_announcement'])) {
    $title = sanitize($_POST['title']);
    $content = sanitize($_POST['content']);
    $type = $_POST['type'];
    if ($title && $content && in_array($type, ['info','warning','success','danger'])) {
        $conn->prepare("INSERT INTO announcements (title, content, type) VALUES (?,?,?)")
            ->execute([$title, $content, $type]);
        logAction($conn, $admin_id, 'إضافة إعلان', $title);
        redirect('admin.php?tab=announcements&added=1');
    }
}

if ($action === 'toggle_announcement' && isset($_GET['id'])) {
    $aid = (int)$_GET['id'];
    $conn->prepare("UPDATE announcements SET is_active = NOT is_active WHERE id = ?")->execute([$aid]);
    redirect('admin.php?tab=announcements');
}

if ($action === 'delete_announcement' && isset($_GET['id'])) {
    $conn->prepare("DELETE FROM announcements WHERE id = ?")->execute([(int)$_GET['id']]);
    logAction($conn, $admin_id, 'حذف إعلان', $_GET['id']);
    redirect('admin.php?tab=announcements&deleted=1');
}

// التصنيفات
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_category'])) {
    $name = sanitize($_POST['name']);
    $icon = sanitize($_POST['icon'] ?: '📦');
    $slug = strtolower(str_replace(' ', '-', $_POST['slug'] ?? ''));
    if ($name && $slug) {
        try {
            $conn->prepare("INSERT INTO categories (name, slug, icon) VALUES (?,?,?)")
                ->execute([$name, $slug, $icon]);
            logAction($conn, $admin_id, 'إضافة تصنيف', $name);
            redirect('admin.php?tab=categories&added=1');
        } catch (Exception $e) {
            redirect('admin.php?tab=categories&error=1');
        }
    }
}

if ($action === 'delete_category' && isset($_GET['id'])) {
    $conn->prepare("DELETE FROM categories WHERE id = ?")->execute([(int)$_GET['id']]);
    logAction($conn, $admin_id, 'حذف تصنيف', $_GET['id']);
    redirect('admin.php?tab=categories&deleted=1');
}

// تعليم رسالة كمقروءة
if ($action === 'mark_read' && isset($_GET['id'])) {
    $conn->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?")->execute([(int)$_GET['id']]);
    redirect('admin.php?tab=messages');
}

if ($action === 'delete_message' && isset($_GET['id'])) {
    $conn->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([(int)$_GET['id']]);
    redirect('admin.php?tab=messages&deleted=1');
}

// إرسال رد الأدمن من تبويب admin_chats
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_admin_chat'])) {
    $conv_id = (int)$_POST['admin_conv_id'];
    $body = trim($_POST['admin_chat_body'] ?? '');
    if ($conv_id && $body) {
        // تأكد أن المحادثة admin_chat
        $chk = $conn->prepare("SELECT id FROM conversations WHERE id = ? AND is_admin_chat = 1");
        $chk->execute([$conv_id]);
        if ($chk->fetchColumn()) {
            $conn->prepare("INSERT INTO messages (conversation_id, sender_id, sender_type, body) VALUES (?, ?, 'admin', ?)")
                ->execute([$conv_id, $admin_id, $body]);
            $conn->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = ?")->execute([$conv_id]);
            logAction($conn, $admin_id, 'رد على محادثة تواصل', "conv_id=$conv_id");
        }
    }
    redirect("admin.php?tab=admin_chats&conv=$conv_id&replied=1");
}

// الرد على رسالة تواصل معنا عبر المحادثات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_contact_message'])) {
    $msg_id = (int)$_POST['contact_message_id'];
    $reply_body = trim($_POST['admin_reply_body'] ?? '');
    // جلب بيانات الرسالة
    $cm = $conn->prepare("SELECT * FROM contact_messages WHERE id = ?");
    $cm->execute([$msg_id]);
    $cm_row = $cm->fetch();
    if ($cm_row && $reply_body) {
        // البحث عن مستخدم بهذا الإيميل
        $u_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $u_stmt->execute([$cm_row['email']]);
        $target_user_id = $u_stmt->fetchColumn();
        if ($target_user_id) {
            // ابحث أو أنشئ محادثة أدمن مع هذا المستخدم
            $find_conv = $conn->prepare("SELECT id FROM conversations WHERE user_id = ? AND is_admin_chat = 1 LIMIT 1");
            $find_conv->execute([$target_user_id]);
            $conv_id = $find_conv->fetchColumn();
            if (!$conv_id) {
                $conn->prepare("INSERT INTO conversations (user_id, is_admin_chat) VALUES (?, 1)")
                    ->execute([$target_user_id]);
                $conv_id = (int)$conn->lastInsertId();
                // أضف رسالة المستخدم الأصلية
                $conn->prepare("INSERT INTO messages (conversation_id, sender_id, sender_type, body, created_at) VALUES (?, ?, 'user', ?, ?)")
                    ->execute([$conv_id, $target_user_id, $cm_row['message'], $cm_row['created_at']]);
            }
            // أضف رد الأدمن
            $conn->prepare("INSERT INTO messages (conversation_id, sender_id, sender_type, body) VALUES (?, ?, 'admin', ?)")
                ->execute([$conv_id, $admin_id, $reply_body]);
            $conn->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = ?")->execute([$conv_id]);
        }
        // تعليم الرسالة كمقروءة
        $conn->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?")->execute([$msg_id]);
        logAction($conn, $admin_id, 'رد على رسالة تواصل معنا', "message_id=$msg_id");
    }
    redirect('admin.php?tab=messages&replied=1');
}

// ========== البيانات ==========
$stats = [
    'total_users' => $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active_users' => $conn->query("SELECT COUNT(*) FROM users WHERE status='active' AND role='user'")->fetchColumn(),
    'banned_users' => $conn->query("SELECT COUNT(*) FROM users WHERE status='banned'")->fetchColumn(),
    'total_centers' => $conn->query("SELECT COUNT(*) FROM centers")->fetchColumn(),
    'verified_centers' => $conn->query("SELECT COUNT(*) FROM centers WHERE verified=1")->fetchColumn(),
    'pending_centers' => $conn->query("SELECT COUNT(*) FROM centers WHERE approval_status='pending'")->fetchColumn(),
    'new_complaints' => $conn->query("SELECT COUNT(*) FROM complaints WHERE status='new'")->fetchColumn(),
    'pending_reports' => $conn->query("SELECT COUNT(*) FROM reports WHERE status='pending'")->fetchColumn(),
    'unread_messages' => $conn->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn(),
    'new_users_week' => $conn->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn(),
];

$admin_page = true;
$extra_css = ['assets/css/admin.css'];
include 'includes/header.php';
?>

<div class="admin-wrapper" id="adminWrapper">

    <!-- طبقة الخلفية للموبايل -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-brand">
            <div class="brand-icon">👑</div>
            <div class="brand-text">
                <h3>لوحة الإدارة</h3>
                <p>التحكم الكامل بالمنصة</p>
            </div>
            <button type="button" class="sidebar-desktop-toggle" id="sidebarDesktopToggle" title="طي القائمة" aria-label="طي القائمة">
                <span class="toggle-arrow">‹</span>
            </button>
        </div>

        <div class="admin-nav-section">الرئيسية</div>
        <ul class="admin-nav">
            <li><a href="?tab=dashboard" class="<?= $tab=='dashboard'?'active':'' ?>">
                <span class="nav-icon">📊</span><span class="nav-label">الإحصائيات</span>
            </a></li>
            <li><a href="?tab=activity" class="<?= $tab=='activity'?'active':'' ?>">
                <span class="nav-icon">⏱️</span><span class="nav-label">سجل النشاط</span>
            </a></li>
        </ul>

        <div class="admin-nav-section">إدارة المستخدمين</div>
        <ul class="admin-nav">
            <li><a href="?tab=users" class="<?= $tab=='users'?'active':'' ?>">
                <span class="nav-icon">👥</span><span class="nav-label">المستخدمون</span>
            </a></li>
            <li><a href="?tab=centers" class="<?= $tab=='centers'?'active':'' ?>">
                <span class="nav-icon">🏇</span><span class="nav-label">المراكز</span>
                <?php if ($stats['pending_centers']): ?><span class="badge"><?= $stats['pending_centers'] ?></span><?php endif; ?>
            </a></li>
        </ul>

        <div class="admin-nav-section">الإشراف</div>
        <ul class="admin-nav">
            <li><a href="?tab=complaints" class="<?= $tab=='complaints'?'active':'' ?>">
                <span class="nav-icon">⚠️</span><span class="nav-label">الشكاوى</span>
                <?php if ($stats['new_complaints']): ?><span class="badge"><?= $stats['new_complaints'] ?></span><?php endif; ?>
            </a></li>
            <li><a href="?tab=reports" class="<?= $tab=='reports'?'active':'' ?>">
                <span class="nav-icon">🚩</span><span class="nav-label">البلاغات</span>
                <?php if ($stats['pending_reports']): ?><span class="badge"><?= $stats['pending_reports'] ?></span><?php endif; ?>
            </a></li>
            <li><a href="?tab=messages" class="<?= $tab=='messages'?'active':'' ?>">
                <span class="nav-icon">✉️</span><span class="nav-label">الرسائل</span>
                <?php if ($stats['unread_messages']): ?><span class="badge gold"><?= $stats['unread_messages'] ?></span><?php endif; ?>
            </a></li>
            <li><a href="?tab=admin_chats" class="<?= $tab=='admin_chats'?'active':'' ?>">
                <span class="nav-icon">💬</span><span class="nav-label">محادثات التواصل</span>
                <?php
                $unread_admin_chats = $conn->query("SELECT COUNT(DISTINCT m.conversation_id) FROM messages m JOIN conversations c ON c.id = m.conversation_id WHERE c.is_admin_chat = 1 AND m.sender_type = 'user' AND m.is_read = 0")->fetchColumn();
                if ($unread_admin_chats): ?><span class="badge"><?= $unread_admin_chats ?></span><?php endif; ?>
            </a></li>
        </ul>

        <div class="admin-nav-section">إعدادات المنصة</div>
        <ul class="admin-nav">
            <li><a href="?tab=announcements" class="<?= $tab=='announcements'?'active':'' ?>">
                <span class="nav-icon">📢</span><span class="nav-label">الإعلانات</span>
            </a></li>
            <li><a href="?tab=categories" class="<?= $tab=='categories'?'active':'' ?>">
                <span class="nav-icon">🏷️</span><span class="nav-label">التصنيفات</span>
            </a></li>
        </ul>
    </aside>

    <main class="admin-main">
        <?php if (isset($_GET['updated'])): ?><div class="admin-alert success">✅ تم التحديث بنجاح</div><?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?><div class="admin-alert success">🗑️ تم الحذف</div><?php endif; ?>
        <?php if (isset($_GET['replied'])): ?><div class="admin-alert success">✍️ تم إرسال الرد</div><?php endif; ?>
        <?php if (isset($_GET['approved'])): ?><div class="admin-alert success">✅ تم اعتماد المركز</div><?php endif; ?>
        <?php if (isset($_GET['rejected'])): ?><div class="admin-alert warning">⚠️ تم رفض المركز</div><?php endif; ?>
        <?php if (isset($_GET['added'])): ?><div class="admin-alert success">✅ تمت الإضافة</div><?php endif; ?>

        <?php if ($tab === 'dashboard'): ?>
            <div class="admin-header">
                <div>
                    <div class="admin-breadcrumb">الرئيسية / الإحصائيات</div>
                    <h1>مرحباً بك في <span>لوحة الإدارة</span></h1>
                    <p>نظرة شاملة على أداء المنصة</p>
                </div>
            </div>

            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-head">
                        <div class="kpi-icon blue">👥</div>
                        <span class="kpi-trend up">+<?= $stats['new_users_week'] ?> هذا الأسبوع</span>
                    </div>
                    <div class="kpi-value"><?= $stats['total_users'] ?></div>
                    <div class="kpi-label">إجمالي المستخدمين</div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-head">
                        <div class="kpi-icon green">✅</div>
                    </div>
                    <div class="kpi-value"><?= $stats['active_users'] ?></div>
                    <div class="kpi-label">مستخدم نشط</div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-head">
                        <div class="kpi-icon gold">🏇</div>
                    </div>
                    <div class="kpi-value"><?= $stats['total_centers'] ?></div>
                    <div class="kpi-label">مركز فروسية (<?= $stats['verified_centers'] ?> موثّق)</div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-head">
                        <div class="kpi-icon red">🚫</div>
                    </div>
                    <div class="kpi-value"><?= $stats['banned_users'] ?></div>
                    <div class="kpi-label">مستخدم محظور</div>
                </div>
            </div>

            <?php
            $pending_actions = [];
            if ($stats['new_complaints']) $pending_actions[] = ['🔴', $stats['new_complaints'], 'شكوى بانتظار الرد', '?tab=complaints'];
            if ($stats['pending_centers']) $pending_actions[] = ['🟡', $stats['pending_centers'], 'مركز بانتظار الاعتماد', '?tab=centers'];
            if ($stats['pending_reports']) $pending_actions[] = ['🚩', $stats['pending_reports'], 'بلاغ لم يُراجع', '?tab=reports'];
            if ($stats['unread_messages']) $pending_actions[] = ['✉️', $stats['unread_messages'], 'رسالة لم تُقرأ', '?tab=messages'];
            if (!empty($pending_actions)):
            ?>
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">⏳ <span>مهام</span> تحتاج انتباهك</div>
                </div>
                <div class="pending-actions-grid">
                    <?php foreach ($pending_actions as [$ic, $count, $label, $link]): ?>
                    <a href="<?= $link ?>" class="pending-action-item">
                        <div class="pending-action-icon"><?= $ic ?></div>
                        <div>
                            <div class="pending-action-count"><?= $count ?></div>
                            <div class="pending-action-label"><?= $label ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php $recent_users = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll(); ?>
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">👤 آخر المنضمين</div>
                    <a href="?tab=users" class="btn-admin">عرض الكل ←</a>
                </div>
                <table class="admin-table">
                    <thead><tr><th>المستخدم</th><th>الصلاحية</th><th>الحالة</th><th>التاريخ</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent_users as $u): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar <?= $u['role'] ?>"><?= mb_substr($u['full_name'],0,1,'UTF-8') ?></div>
                                    <div class="user-info">
                                        <strong><?= sanitize($u['full_name']) ?></strong>
                                        <small><?= sanitize($u['email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="admin-badge badge-<?= $u['role'] ?>"><?= ['user'=>'مستخدم','center'=>'مركز','admin'=>'مدير','clinic'=>'عيادة','photographer'=>'مصور'][$u['role']] ?? $u['role'] ?></span></td>
                            <td><span class="admin-badge badge-<?= $u['status'] ?? 'active' ?>"><?= ['active'=>'نشط','banned'=>'محظور','pending'=>'معلق'][$u['status'] ?? 'active'] ?></span></td>
                            <td style="color: #6b7280; font-size: 13px;"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($tab === 'users'): ?>
            <?php
            $filter = $_GET['filter'] ?? 'all';
            $where = '1=1';
            if ($filter === 'banned') $where = "status='banned'";
            elseif ($filter === 'active') $where = "status='active'";
            elseif ($filter === 'admin') $where = "role='admin'";
            elseif ($filter === 'center') $where = "role='center'";
            $users = $conn->query("SELECT * FROM users WHERE $where ORDER BY created_at DESC")->fetchAll();
            ?>
            <div class="admin-header">
                <div>
                    <div class="admin-breadcrumb">الإدارة / المستخدمون</div>
                    <h1>👥 إدارة <span>المستخدمين</span></h1>
                    <p>إجمالي <?= count($users) ?> مستخدم</p>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <?php foreach (['all'=>'الكل','active'=>'نشط','banned'=>'محظور','admin'=>'مدير','center'=>'مركز'] as $k=>$v): ?>
                        <a href="?tab=users&filter=<?= $k ?>" class="btn-admin" style="<?= $filter==$k?'background:rgba(201,162,39,0.1);color:#c9a227;border-color:#c9a227;':'' ?>"><?= $v ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <table class="admin-table">
                    <thead><tr><th>المستخدم</th><th>الهاتف</th><th>المدينة</th><th>الصلاحية</th><th>الحالة</th><th>التاريخ</th><th>إجراءات</th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar <?= $u['role'] ?>"><?= mb_substr($u['full_name'],0,1,'UTF-8') ?></div>
                                    <div class="user-info">
                                        <strong><?= sanitize($u['full_name']) ?> <?= $u['verified']??0 ? '<span style="color:#3498db;">✓</span>' : '' ?></strong>
                                        <small><?= sanitize($u['email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td dir="ltr" style="font-size: 13px;"><?= sanitize($u['phone']) ?></td>
                            <td><?= sanitize($u['city']) ?></td>
                            <td>
                                <?php if ($u['id'] != $admin_id): ?>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <select name="role" onchange="this.form.submit()">
                                        <option value="user" <?= $u['role']=='user'?'selected':'' ?>>مستخدم</option>
                                        <option value="center" <?= $u['role']=='center'?'selected':'' ?>>مركز</option>
                                        <option value="clinic" <?= $u['role']=='clinic'?'selected':'' ?>>عيادة</option>
                                        <option value="photographer" <?= $u['role']=='photographer'?'selected':'' ?>>مصور</option>
                                        <option value="admin" <?= $u['role']=='admin'?'selected':'' ?>>مدير</option>
                                    </select>
                                    <input type="hidden" name="change_role" value="1">
                                </form>
                                <?php else: ?>
                                <span class="admin-badge badge-admin">أنت 👑</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="admin-badge badge-<?= $u['status'] ?? 'active' ?>">
                                    <?= ['active'=>'نشط','banned'=>'محظور','pending'=>'معلق'][$u['status'] ?? 'active'] ?>
                                </span>
                            </td>
                            <td style="color: #6b7280; font-size: 13px;"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <?php if ($u['id'] != $admin_id): ?>
                                    <a href="?action=verify_user&id=<?= $u['id'] ?>" class="btn-admin" title="<?= $u['verified']??0?'إزالة التوثيق':'توثيق' ?>"><?= $u['verified']??0?'✓':'☐' ?></a>
                                    <a href="?action=ban_user&id=<?= $u['id'] ?>" class="btn-admin danger" title="<?= ($u['status']??'active')=='banned'?'إلغاء الحظر':'حظر' ?>"><?= ($u['status']??'active')=='banned'?'🔓':'🚫' ?></a>
                                    <a href="?action=delete_user&id=<?= $u['id'] ?>" onclick="return confirm('حذف المستخدم نهائياً؟')" class="btn-admin danger">🗑️</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($tab === 'centers'): ?>
            <?php $centers = $conn->query("SELECT * FROM centers ORDER BY approval_status='pending' DESC, created_at DESC")->fetchAll(); ?>
            <div class="admin-header">
                <div>
                    <div class="admin-breadcrumb">الإدارة / المراكز</div>
                    <h1>🏇 اعتماد <span>المراكز</span></h1>
                    <p>راجع طلبات التسجيل ووثّق المراكز</p>
                </div>
            </div>

            <?php if ($stats['pending_centers']): ?>
            <div class="admin-alert warning">⚠️ يوجد <?= $stats['pending_centers'] ?> مركز بانتظار الاعتماد</div>
            <?php endif; ?>

            <div class="panel">
                <table class="admin-table">
                    <thead><tr><th>المركز</th><th>المدينة</th><th>التقييم</th><th>حالة الاعتماد</th><th>التوثيق</th><th>إجراءات</th></tr></thead>
                    <tbody>
                        <?php foreach ($centers as $c): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <img src="<?= sanitize($c['image']) ?>" style="width:45px;height:45px;border-radius:10px;object-fit:cover;">
                                    <div class="user-info">
                                        <strong><?= sanitize($c['name']) ?></strong>
                                        <small><?= sanitize($c['email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>📍 <?= sanitize($c['city']) ?></td>
                            <td style="color: #c9a227;">⭐ <?= $c['rating'] ?></td>
                            <td>
                                <span class="admin-badge badge-<?= ['approved'=>'active','pending'=>'pending','rejected'=>'banned'][$c['approval_status'] ?? 'approved'] ?>">
                                    <?= ['approved'=>'✓ معتمد','pending'=>'⏳ معلق','rejected'=>'✗ مرفوض'][$c['approval_status'] ?? 'approved'] ?>
                                </span>
                            </td>
                            <td>
                                <?= ($c['verified']??0) ? '<span class="admin-badge badge-verified">✓ موثّق</span>' : '<span class="admin-badge badge-pending">غير موثّق</span>' ?>
                            </td>
                            <td>
                                <a href="center.php?id=<?= $c['id'] ?>" target="_blank" class="btn-admin">👁️</a>
                                <?php if (($c['approval_status']??'approved') === 'pending'): ?>
                                    <a href="?action=approve_center&id=<?= $c['id'] ?>" class="btn-action btn-approve">✓ اعتماد</a>
                                    <a href="?action=reject_center&id=<?= $c['id'] ?>" class="btn-action btn-reject">✗ رفض</a>
                                <?php else: ?>
                                    <a href="?action=verify_center&id=<?= $c['id'] ?>" class="btn-action btn-verify"><?= ($c['verified']??0)?'إزالة ✓':'توثيق ✓' ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($tab === 'complaints'): ?>
            <?php
            $complaints = $conn->query("SELECT * FROM complaints ORDER BY FIELD(status,'new','in_progress','resolved','closed'), created_at DESC")->fetchAll();
            $complaint_counts = ['new'=>0,'in_progress'=>0,'resolved'=>0,'closed'=>0];
            foreach ($complaints as $c) { if (isset($complaint_counts[$c['status']])) $complaint_counts[$c['status']]++; }
            ?>
            <div class="admin-header">
                <div>
                    <div class="admin-breadcrumb">الإشراف / الشكاوى</div>
                    <h1>⚠️ <span>الشكاوى</span> والاقتراحات</h1>
                    <p>راجع وردّ على شكاوى المستخدمين — إجمالي <?= count($complaints) ?> شكوى</p>
                </div>
            </div>

            <!-- بطاقات إحصاء الشكاوى -->
            <div class="kpi-grid" style="grid-template-columns: repeat(auto-fit,minmax(170px,1fr)); margin-bottom: 24px;">
                <div class="kpi-card">
                    <div class="kpi-head">
                        <div class="kpi-icon red">🔴</div>
                    </div>
                    <div class="kpi-value" style="font-size:26px;"><?= $complaint_counts['new'] ?></div>
                    <div class="kpi-label">شكاوى جديدة</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-head">
                        <div class="kpi-icon orange">⏳</div>
                    </div>
                    <div class="kpi-value" style="font-size:26px;"><?= $complaint_counts['in_progress'] ?></div>
                    <div class="kpi-label">قيد المعالجة</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-head">
                        <div class="kpi-icon green">✅</div>
                    </div>
                    <div class="kpi-value" style="font-size:26px;"><?= $complaint_counts['resolved'] ?></div>
                    <div class="kpi-label">تم الحل</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-head">
                        <div class="kpi-icon blue">🔒</div>
                    </div>
                    <div class="kpi-value" style="font-size:26px;"><?= $complaint_counts['closed'] ?></div>
                    <div class="kpi-label">مغلقة</div>
                </div>
            </div>

            <?php if (empty($complaints)): ?>
            <div class="panel"><div class="admin-empty"><div class="icon">✅</div><p>لا توجد شكاوى</p></div></div>
            <?php else: ?>

            <?php
            $type_badge  = ['complaint'=>'badge-new',      'suggestion'=>'badge-verified', 'inquiry'=>'badge-progress'];
            $type_label  = ['complaint'=>'⚠️ شكوى',        'suggestion'=>'💡 اقتراح',      'inquiry'=>'❓ استفسار'];
            $stat_badge  = ['new'=>'badge-new',             'in_progress'=>'badge-progress','resolved'=>'badge-resolved','closed'=>'badge-user'];
            $stat_label  = ['new'=>'جديدة',                 'in_progress'=>'قيد المعالجة', 'resolved'=>'تم الحل',        'closed'=>'مغلقة'];
            $stat_border = ['new'=>'#ef4444',               'in_progress'=>'#f59e0b',       'resolved'=>'#22c55e',        'closed'=>'#9d9184'];
            ?>

            <?php foreach ($complaints as $c):
                $border_color = $stat_border[$c['status']] ?? '#c9a227';
            ?>
            <div class="panel" style="border-right: 4px solid <?= $border_color ?>; margin-bottom: 20px;">

                <!-- رأس بطاقة الشكوى -->
                <div class="panel-header" style="background: #faf8f3;">
                    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; min-width:0;">
                        <!-- أيقونة المستخدم -->
                        <div class="user-avatar" style="width:42px;height:42px;font-size:16px;border-radius:12px;flex-shrink:0;">
                            <?= mb_substr($c['full_name'],0,1,'UTF-8') ?>
                        </div>
                        <div style="min-width:0;">
                            <div style="color:#1a1510;font-size:15px;font-weight:700;line-height:1.3;">
                                <?= sanitize($c['subject']) ?>
                            </div>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:4px;">
                                <span style="color:#2d2720;font-size:12.5px;font-weight:600;">👤 <?= sanitize($c['full_name']) ?></span>
                                <span style="color:#9d9184;font-size:12px;">📧 <?= sanitize($c['email']) ?></span>
                                <span style="color:#9d9184;font-size:12px;">📅 <?= date('d/m/Y — H:i', strtotime($c['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;flex-shrink:0;">
                        <span class="admin-badge <?= $type_badge[$c['type']] ?? 'badge-user' ?>">
                            <?= $type_label[$c['type']] ?? $c['type'] ?>
                        </span>
                        <span class="admin-badge <?= $stat_badge[$c['status']] ?? 'badge-user' ?>">
                            <?= $stat_label[$c['status']] ?? $c['status'] ?>
                        </span>
                    </div>
                </div>

                <!-- نص الشكوى -->
                <div style="padding: 18px 22px 0;">
                    <div style="font-size:11px;font-weight:700;color:#9d9184;text-transform:uppercase;letter-spacing:0.7px;margin-bottom:8px;">رسالة المستخدم</div>
                    <div class="complaint-body" style="margin:0;"><?= nl2br(sanitize($c['message'])) ?></div>
                </div>

                <?php if ($c['admin_reply']): ?>
                <!-- رد الإدارة السابق -->
                <div style="padding: 14px 22px 0;">
                    <div style="font-size:11px;font-weight:700;color:#9d9184;text-transform:uppercase;letter-spacing:0.7px;margin-bottom:8px;">رد الإدارة السابق</div>
                    <div class="complaint-reply" style="margin:0;">
                        <strong>✍️ ردك السابق:</strong>
                        <p><?= nl2br(sanitize($c['admin_reply'])) ?></p>
                        <small style="color:#9ca3af;">📅 <?= date('d/m/Y H:i', strtotime($c['replied_at'])) ?></small>
                    </div>
                </div>
                <?php endif; ?>

                <!-- قسم الرد -->
                <div style="padding: 16px 22px 20px;">
                    <details <?= !$c['admin_reply'] && $c['status']=='new'?'open':'' ?>>
                        <summary>
                            ✍️ <?= $c['admin_reply']?'تعديل الرد على الشكوى':'الرد على الشكوى' ?>
                        </summary>
                        <form method="POST" class="admin-form" style="margin-top: 16px;">
                            <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">

                            <div style="margin-bottom:14px;">
                                <label>نص الرد</label>
                                <textarea name="admin_reply" rows="4" required placeholder="اكتب ردك الرسمي هنا..."><?= sanitize($c['admin_reply']) ?></textarea>
                            </div>

                            <!-- شريط الإجراء السفلي -->
                            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:14px 16px;background:#f5f2ec;border-radius:10px;border:1px solid rgba(201,162,39,0.18);">
                                <div style="flex:1;min-width:160px;">
                                    <label style="font-size:11.5px;margin-bottom:4px;">تحديث الحالة</label>
                                    <select name="status">
                                        <option value="in_progress" <?= $c['status']=='in_progress'?'selected':'' ?>>⏳ قيد المعالجة</option>
                                        <option value="resolved"    <?= $c['status']=='resolved'   ?'selected':'' ?>>✅ تم الحل</option>
                                        <option value="closed"      <?= $c['status']=='closed'     ?'selected':'' ?>>🔒 إغلاق</option>
                                    </select>
                                </div>
                                <div style="padding-top:18px;">
                                    <button type="submit" name="reply_complaint" class="btn btn-primary" style="padding:10px 24px;font-size:13.5px;">
                                        📤 إرسال الرد
                                    </button>
                                </div>
                            </div>
                        </form>
                    </details>
                </div>

            </div>
            <?php endforeach; ?>
            <?php endif; ?>

        <?php elseif ($tab === 'reports'): ?>
            <?php
            $reports = $conn->query("
                SELECT r.*, u.full_name AS reporter_name
                FROM reports r
                LEFT JOIN users u ON r.reporter_id = u.id
                ORDER BY r.status='pending' DESC, r.created_at DESC
            ")->fetchAll();
            ?>
            <div class="admin-header">
                <div>
                    <div class="admin-breadcrumb">الإشراف / البلاغات</div>
                    <h1>🚩 <span>البلاغات</span></h1>
                    <p>بلاغات عن مستخدمين أو محتوى مخالف</p>
                </div>
            </div>

            <div class="panel">
                <table class="admin-table">
                    <thead><tr><th>المُبلِّغ</th><th>النوع</th><th>السبب</th><th>الحالة</th><th>التاريخ</th><th>إجراءات</th></tr></thead>
                    <tbody>
                        <?php foreach ($reports as $r): ?>
                        <tr>
                            <td><strong style="color:#111827;"><?= sanitize($r['reporter_name'] ?? 'مجهول') ?></strong></td>
                            <td>
                                <span class="admin-badge badge-user">
                                    <?= ['user'=>'👤 مستخدم','center'=>'🏇 مركز','product'=>'📦 منتج','review'=>'⭐ تقييم'][$r['target_type']] ?>
                                    #<?= $r['target_id'] ?>
                                </span>
                            </td>
                            <td>
                                <strong style="color: #111827; display: block;"><?= sanitize($r['reason']) ?></strong>
                                <small style="color: #6b7280;"><?= sanitize(mb_substr($r['description'], 0, 80, 'UTF-8')) ?>…</small>
                            </td>
                            <td>
                                <span class="admin-badge badge-<?= ['pending'=>'new','reviewed'=>'progress','dismissed'=>'user','actioned'=>'resolved'][$r['status']] ?>">
                                    <?= ['pending'=>'معلق','reviewed'=>'تمت المراجعة','dismissed'=>'مرفوض','actioned'=>'تم الإجراء'][$r['status']] ?>
                                </span>
                            </td>
                            <td style="color: #6b7280; font-size: 13px;"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
                            <td>
                                <?php if ($r['status'] === 'pending'): ?>
                                <a href="?action=action_report&id=<?= $r['id'] ?>" class="btn-action btn-approve">✓ إجراء</a>
                                <a href="?action=dismiss_report&id=<?= $r['id'] ?>" class="btn-action btn-reject">✗ رفض</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($reports)): ?>
                <div class="admin-empty"><div class="icon">🚩</div><p>لا توجد بلاغات</p></div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'messages'): ?>
            <?php $messages = $conn->query("SELECT * FROM contact_messages ORDER BY is_read ASC, created_at DESC")->fetchAll(); ?>
            <div class="admin-header">
                <div>
                    <div class="admin-breadcrumb">الإشراف / الرسائل</div>
                    <h1>✉️ <span>رسائل التواصل</span></h1>
                    <p>رسائل الزوار من صفحة "تواصل معنا"</p>
                </div>
            </div>

            <?php foreach ($messages as $m): ?>
            <div class="panel" style="<?= !$m['is_read']?'border-right: 3px solid #c9a227;':'' ?>">
                <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; padding: 18px 22px 0;">
                    <div>
                        <strong style="color: #111827; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                            <?= sanitize($m['full_name']) ?>
                            <?php if (!$m['is_read']): ?><span class="admin-badge badge-new">جديدة</span><?php endif; ?>
                        </strong>
                        <div style="color: #6b7280; font-size: 13px; margin-top: 4px;">
                            📧 <?= sanitize($m['email']) ?> • 📱 <?= sanitize($m['phone']) ?>
                        </div>
                    </div>
                    <small style="color: #9ca3af; font-size: 12px;"><?= date('d/m/Y - H:i', strtotime($m['created_at'])) ?></small>
                </div>
                <div class="complaint-body" style="margin: 0 22px 0;"><?= nl2br(sanitize($m['message'])) ?></div>
                <?php
                // تحقق إذا المرسل عنده حساب
                $has_account = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $has_account->execute([$m['email']]);
                $msg_user_id = $has_account->fetchColumn();
                ?>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; padding: 12px 22px 6px;">
                    <?php if (!$m['is_read']): ?>
                    <a href="?action=mark_read&id=<?= $m['id'] ?>" class="btn-admin success">✓ تعليم كمقروءة</a>
                    <?php endif; ?>
                    <a href="mailto:<?= sanitize($m['email']) ?>" class="btn-admin">📧 رد بالإيميل</a>
                    <a href="?action=delete_message&id=<?= $m['id'] ?>" onclick="return confirm('حذف الرسالة؟')" class="btn-admin danger">🗑️ حذف</a>
                </div>
                <?php if ($msg_user_id): ?>
                <details style="margin: 0 22px 16px;">
                    <summary class="btn-admin" style="cursor:pointer; list-style:none; display:inline-flex; align-items:center; gap:5px;">
                        💬 رد عبر المحادثات
                    </summary>
                    <form method="POST" style="margin-top: 10px;">
                        <input type="hidden" name="contact_message_id" value="<?= $m['id'] ?>">
                        <textarea name="admin_reply_body" rows="3" required placeholder="اكتب ردك... سيظهر للمستخدم في المحادثات"
                            style="width:100%; border-radius:8px; border:1px solid rgba(212,175,55,0.3); background:#1a1a2e; color:#e5e7eb; padding:10px; font-size:14px; resize:vertical;"></textarea>
                        <button type="submit" name="reply_contact_message" class="btn btn-primary" style="margin-top:8px;">📤 إرسال الرد للمحادثات</button>
                    </form>
                </details>
                <?php else: ?>
                <div style="padding: 4px 22px 14px; color: #9ca3af; font-size: 12px;">⚠️ المرسل لا يملك حساباً على المنصة — الرد متاح بالإيميل فقط</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (empty($messages)): ?>
            <div class="panel"><div class="admin-empty"><div class="icon">✉️</div><p>لا توجد رسائل</p></div></div>
            <?php endif; ?>

        <?php elseif ($tab === 'announcements'): ?>
            <?php $announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll(); ?>
            <div class="admin-header">
                <div>
                    <div class="admin-breadcrumb">الإعدادات / الإعلانات</div>
                    <h1>📢 <span>الإعلانات</span> العامة</h1>
                    <p>إعلانات تظهر لجميع زوار الموقع</p>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header"><div class="panel-title">➕ إضافة إعلان جديد</div></div>
                <form method="POST" class="admin-form">
                    <div style="display: grid; grid-template-columns: 1fr 200px; gap: 15px; margin-bottom: 15px;">
                        <div><label>العنوان</label><input type="text" name="title" required placeholder="عنوان الإعلان"></div>
                        <div><label>النوع</label>
                            <select name="type">
                                <option value="info">ℹ️ معلومة</option>
                                <option value="warning">⚠️ تحذير</option>
                                <option value="success">✅ إيجابي</option>
                                <option value="danger">🔴 عاجل</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label>المحتوى</label>
                        <textarea name="content" rows="3" required placeholder="نص الإعلان..."></textarea>
                    </div>
                    <button type="submit" name="add_announcement" class="btn btn-primary">📢 نشر الإعلان</button>
                </form>
            </div>

            <div class="panel">
                <div class="panel-header"><div class="panel-title">الإعلانات الحالية (<?= count($announcements) ?>)</div></div>
                <div style="padding: 16px 22px;">
                <?php
                $ann_accent = ['info'=>'#3b82f6','warning'=>'#f59e0b','success'=>'#16a34a','danger'=>'#ef4444'];
                $ann_bg     = ['info'=>'#eff6ff','warning'=>'#fffbeb','success'=>'#f0fdf4','danger'=>'#fef2f2'];
                $ann_icon   = ['info'=>'ℹ️','warning'=>'⚠️','success'=>'✅','danger'=>'🔴'];
                foreach ($announcements as $a):
                    $accent = $ann_accent[$a['type']] ?? '#c9a227';
                    $bg     = $ann_bg[$a['type']] ?? '#fefce8';
                ?>
                <div style="background: <?= $bg ?>; padding: 16px 18px; border-radius: 10px; margin-bottom: 10px; border-right: 3px solid <?= $accent ?>; border: 1px solid <?= $accent ?>22; border-right: 3px solid <?= $accent ?>; <?= !$a['is_active']?'opacity:0.5;':'' ?>">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; flex-wrap: wrap; gap: 8px;">
                        <strong style="color: #111827; font-size: 15px; display: flex; align-items: center; gap: 6px;">
                            <?= $ann_icon[$a['type']] ?>
                            <?= sanitize($a['title']) ?>
                        </strong>
                        <div style="display: flex; gap: 5px; flex-shrink: 0;">
                            <a href="?action=toggle_announcement&id=<?= $a['id'] ?>" class="btn-admin"><?= $a['is_active']?'إخفاء':'إظهار' ?></a>
                            <a href="?action=delete_announcement&id=<?= $a['id'] ?>" onclick="return confirm('حذف؟')" class="btn-admin danger">🗑️</a>
                        </div>
                    </div>
                    <p style="color: #374151; font-size: 13.5px; line-height: 1.7; margin: 0 0 6px;"><?= sanitize($a['content']) ?></p>
                    <small style="color: #9ca3af; font-size: 12px;"><?= date('d/m/Y', strtotime($a['created_at'])) ?></small>
                </div>
                <?php endforeach; ?>
                </div>
                <?php if (empty($announcements)): ?>
                <div class="admin-empty"><div class="icon">📢</div><p>لا توجد إعلانات</p></div>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'categories'): ?>
            <?php $categories = $conn->query("SELECT c.*, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id")->fetchAll(); ?>
            <div class="admin-header">
                <div>
                    <div class="admin-breadcrumb">الإعدادات / التصنيفات</div>
                    <h1>🏷️ <span>تصنيفات</span> المتجر</h1>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header"><div class="panel-title">➕ إضافة تصنيف</div></div>
                <form method="POST" class="admin-form">
                    <div style="display: grid; grid-template-columns: 2fr 1fr 80px; gap: 15px;">
                        <div><label>اسم التصنيف</label><input type="text" name="name" required placeholder="مثال: إكسسوارات"></div>
                        <div><label>الرابط (slug)</label><input type="text" name="slug" required placeholder="accessories"></div>
                        <div><label>أيقونة</label><input type="text" name="icon" value="📦" maxlength="4"></div>
                    </div>
                    <button type="submit" name="add_category" class="btn btn-primary" style="margin-top: 15px;">➕ إضافة</button>
                </form>
            </div>

            <div class="panel">
                <div class="panel-header"><div class="panel-title">التصنيفات الحالية (<?= count($categories) ?>)</div></div>
                <table class="admin-table">
                    <thead><tr><th>الأيقونة</th><th>الاسم</th><th>الرابط</th><th>عدد المنتجات</th><th>إجراء</th></tr></thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td style="font-size: 32px;"><?= $cat['icon'] ?></td>
                            <td><strong style="color: #111827;"><?= sanitize($cat['name']) ?></strong></td>
                            <td style="color: #6b7280;"><?= sanitize($cat['slug']) ?></td>
                            <td><span class="admin-badge badge-verified"><?= $cat['product_count'] ?> منتج</span></td>
                            <td>
                                <a href="?action=delete_category&id=<?= $cat['id'] ?>" onclick="return confirm('حذف؟ (ستبقى المنتجات بدون تصنيف)')" class="btn-admin danger">🗑️</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($tab === 'activity'): ?>
            <?php $logs = $conn->query("
                SELECT l.*, u.full_name AS admin_name
                FROM admin_logs l
                LEFT JOIN users u ON l.admin_id = u.id
                ORDER BY l.created_at DESC LIMIT 100
            ")->fetchAll(); ?>
            <div class="admin-header">
                <div>
                    <div class="admin-breadcrumb">الرئيسية / سجل النشاط</div>
                    <h1>⏱️ <span>سجل</span> نشاط الإدارة</h1>
                    <p>آخر 100 إجراء إداري</p>
                </div>
            </div>

            <div class="panel">
                <?php if (empty($logs)): ?>
                <div class="admin-empty"><div class="icon">📋</div><p>لا يوجد نشاط بعد</p></div>
                <?php else: ?>
                <table class="admin-table">
                    <thead><tr><th>المدير</th><th>الإجراء</th><th>التفاصيل</th><th>التاريخ</th></tr></thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar admin">👑</div>
                                    <div class="user-info">
                                        <strong><?= sanitize($log['admin_name'] ?? 'غير معروف') ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td><strong style="color: #c9a227;"><?= sanitize($log['action']) ?></strong></td>
                            <td style="color: #374151; font-size: 13px;"><?= sanitize($log['details']) ?: '—' ?></td>
                            <td style="color: #6b7280; font-size: 13px;"><?= date('d/m/Y - H:i', strtotime($log['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

        <?php elseif ($tab === 'admin_chats'): ?>
            <?php
            // جلب محادثات التواصل مع الأدمن
            $admin_convs = $conn->query("
                SELECT c.id, c.last_message_at, u.full_name, u.email, u.avatar,
                    (SELECT body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_msg,
                    (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_type = 'user' AND m.is_read = 0) AS unread_count
                FROM conversations c
                JOIN users u ON u.id = c.user_id
                WHERE c.is_admin_chat = 1
                ORDER BY c.last_message_at DESC
            ")->fetchAll();
            $active_chat_id = (int)($_GET['conv'] ?? 0);
            $active_chat = null;
            $active_chat_messages = [];
            if ($active_chat_id) {
                foreach ($admin_convs as $ac) {
                    if ((int)$ac['id'] === $active_chat_id) { $active_chat = $ac; break; }
                }
                if ($active_chat) {
                    $acm = $conn->prepare("SELECT * FROM messages WHERE conversation_id = ? ORDER BY id ASC");
                    $acm->execute([$active_chat_id]);
                    $active_chat_messages = $acm->fetchAll();
                    // تعليم رسائل المستخدم كمقروءة
                    $conn->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = 'user' AND is_read = 0")
                        ->execute([$active_chat_id]);
                }
            }
            ?>
            <div class="admin-header">
                <div>
                    <div class="admin-breadcrumb">الإشراف / محادثات التواصل</div>
                    <h1>💬 <span>محادثات</span> التواصل المباشر</h1>
                    <p>ردود المستخدمين عبر صفحة "تواصل معنا"</p>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 280px 1fr; gap: 20px; height: 560px;">
                <!-- قائمة المحادثات -->
                <div class="panel" style="overflow-y: auto; margin: 0; padding: 0;">
                    <?php if (empty($admin_convs)): ?>
                        <div class="admin-empty"><div class="icon">💬</div><p>لا توجد محادثات</p></div>
                    <?php else: ?>
                        <?php foreach ($admin_convs as $ac): ?>
                        <a href="?tab=admin_chats&conv=<?= $ac['id'] ?>"
                           style="display:flex; gap:12px; align-items:center; padding:14px 18px; border-bottom:1px solid rgba(255,255,255,0.05); text-decoration:none; background:<?= (int)$ac['id']===$active_chat_id?'rgba(212,175,55,0.08)':'transparent' ?>; transition:background 0.15s;">
                            <div style="width:40px; height:40px; border-radius:50%; background:rgba(212,175,55,0.2); display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; overflow:hidden;">
                                <?php if ($ac['avatar']): ?><img src="<?= sanitize($ac['avatar']) ?>" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none'"><?php else: ?><?= mb_substr($ac['full_name'],0,1,'UTF-8') ?><?php endif; ?>
                            </div>
                            <div style="flex:1; min-width:0;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <strong style="color:#e5e7eb; font-size:14px;"><?= sanitize($ac['full_name']) ?></strong>
                                    <?php if ($ac['unread_count']): ?><span class="badge"><?= $ac['unread_count'] ?></span><?php endif; ?>
                                </div>
                                <div style="color:#9ca3af; font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= sanitize(mb_substr($ac['last_msg'] ?? '—', 0, 40)) ?></div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- نافذة المحادثة -->
                <div class="panel" style="margin:0; display:flex; flex-direction:column; overflow:hidden;">
                    <?php if (!$active_chat): ?>
                        <div class="admin-empty" style="margin:auto;"><div class="icon">💬</div><p>اختر محادثة من القائمة</p></div>
                    <?php else: ?>
                        <div style="padding:16px 20px; border-bottom:1px solid rgba(255,255,255,0.08); display:flex; align-items:center; gap:10px;">
                            <strong style="color:var(--gold);"><?= sanitize($active_chat['full_name']) ?></strong>
                            <small style="color:#9ca3af;"><?= sanitize($active_chat['email']) ?></small>
                        </div>
                        <div id="adminChatMsgs" style="flex:1; overflow-y:auto; padding:16px 20px; display:flex; flex-direction:column; gap:10px;">
                            <?php foreach ($active_chat_messages as $acmsg):
                                $is_admin = $acmsg['sender_type'] === 'admin';
                            ?>
                            <div style="display:flex; justify-content:<?= $is_admin?'flex-end':'flex-start' ?>;">
                                <div style="max-width:72%; background:<?= $is_admin?'rgba(212,175,55,0.15); border:1px solid rgba(212,175,55,0.3)':'rgba(255,255,255,0.05)' ?>; padding:10px 14px; border-radius:12px;">
                                    <?php if ($acmsg['body']): ?>
                                    <div style="color:#e5e7eb; font-size:14px; line-height:1.6;"><?= nl2br(sanitize($acmsg['body'])) ?></div>
                                    <?php endif; ?>
                                    <div style="font-size:11px; color:#6b7280; margin-top:4px; text-align:<?= $is_admin?'left':'right' ?>;">
                                        <?= $is_admin ? '👑 الإدارة' : '👤 المستخدم' ?> • <?= date('d/m H:i', strtotime($acmsg['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <form method="POST" style="padding:14px 20px; border-top:1px solid rgba(255,255,255,0.08); display:flex; gap:10px;">
                            <input type="hidden" name="admin_conv_id" value="<?= $active_chat_id ?>">
                            <input type="text" name="admin_chat_body" required placeholder="اكتب ردك هنا..." autocomplete="off"
                                style="flex:1; background:rgba(255,255,255,0.05); border:1px solid rgba(212,175,55,0.2); border-radius:8px; padding:10px 14px; color:#e5e7eb; font-size:14px;">
                            <button type="submit" name="send_admin_chat" class="btn btn-primary" style="padding:10px 20px;">📤 إرسال</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <script>
            (function(){ var el=document.getElementById('adminChatMsgs'); if(el) el.scrollTop=el.scrollHeight; })();
            </script>

        <?php endif; ?>
    </main>
</div>

<script>
(function() {
    var wrapper = document.getElementById('adminWrapper');
    var overlay = document.getElementById('sidebarOverlay');
    var desktopBtn = document.getElementById('sidebarDesktopToggle');
    var mobileBtn = document.getElementById('adminSidebarToggle');

    // استرجاع حالة الشريط الجانبي من localStorage
    if (localStorage.getItem('admin-sidebar-collapsed') === '1') {
        wrapper.classList.add('sidebar-collapsed');
    }

    // زر الطي على سطح المكتب
    if (desktopBtn) {
        desktopBtn.addEventListener('click', function() {
            var collapsed = wrapper.classList.toggle('sidebar-collapsed');
            localStorage.setItem('admin-sidebar-collapsed', collapsed ? '1' : '0');
        });
    }

    // زر الفتح على الموبايل (في شريط التنقل العلوي)
    if (mobileBtn) {
        mobileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            wrapper.classList.toggle('sidebar-open');
        });
    }

    // إغلاق عند النقر على الطبقة الخلفية
    if (overlay) {
        overlay.addEventListener('click', function() {
            wrapper.classList.remove('sidebar-open');
        });
    }

    // إغلاق الشريط على الموبايل عند النقر على رابط
    document.querySelectorAll('.admin-sidebar .admin-nav li a').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 900) {
                wrapper.classList.remove('sidebar-open');
            }
        });
    });
})();
</script>
</body>
</html>
