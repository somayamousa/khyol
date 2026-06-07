<?php
require_once 'config/db.php';
require_once 'includes/notify.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT e.*, c.name AS center_name, c.city, c.phone FROM events e LEFT JOIN centers c ON e.center_id = c.id WHERE e.id = ?");
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) redirect('events.php');

$page_title = $event['title'];

$registered = false;
$reg_status = null;
if (isLoggedIn()) {
    $check = $conn->prepare("SELECT id, status FROM event_registrations WHERE user_id = ? AND event_id = ?");
    $check->execute([$_SESSION['user_id'], $id]);
    $reg_row = $check->fetch();
    if ($reg_row) {
        $reg_status = $reg_row['status'];
        $registered  = ($reg_status !== 'cancelled');
    }
}

$participants_count = $conn->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND status != 'cancelled'");
$participants_count->execute([$id]);
$count = (int)$participants_count->fetchColumn();

$msg      = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'register' && !$registered) {
        // تسجيل جديد أو إعادة تفعيل بعد إلغاء
        if ($reg_row) {
            $stmt2 = $conn->prepare("UPDATE event_registrations SET status='registered' WHERE user_id=? AND event_id=?");
            $stmt2->execute([$_SESSION['user_id'], $id]);
        } else {
            $stmt2 = $conn->prepare("INSERT IGNORE INTO event_registrations (user_id, event_id) VALUES (?, ?)");
            $stmt2->execute([$_SESSION['user_id'], $id]);
        }
        $msg      = 'تم تسجيلك بنجاح في الفعالية! 🎉';
        $registered  = true;
        $reg_status  = 'registered';
        $count++;

        // إشعار لصاحب المركز
        $owner = $conn->prepare("SELECT owner_id FROM centers WHERE id = ?");
        $owner->execute([$event['center_id']]);
        $owner_id = (int)$owner->fetchColumn();
        if ($owner_id) {
            send_notification(
                $conn,
                $owner_id,
                'تسجيل جديد في فعالية! 🏆',
                $_SESSION['user_name'] . ' سجّل في "' . $event['title'] . '" — المشاركون الآن: ' . $count . ' / ' . $event['max_participants'],
                'event',
                '🎉',
                'my-center.php?tab=events'
            );
        }

    } elseif ($action === 'cancel' && $registered) {
        $stmt2 = $conn->prepare("UPDATE event_registrations SET status='cancelled' WHERE user_id=? AND event_id=?");
        $stmt2->execute([$_SESSION['user_id'], $id]);
        $msg        = 'تم إلغاء تسجيلك في الفعالية.';
        $msg_type   = 'warning';
        $registered = false;
        $reg_status = 'cancelled';
        $count      = max(0, $count - 1);
    }
}

$months = ['','يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
$date_fmt = date('d', strtotime($event['event_date'])) . ' ' . $months[(int)date('n', strtotime($event['event_date']))] . ' ' . date('Y', strtotime($event['event_date']));
$time_fmt = date('H:i', strtotime($event['event_time']));

$seats_left  = $event['max_participants'] - $count;
$seats_pct   = $event['max_participants'] > 0 ? round(($count / $event['max_participants']) * 100) : 0;
$price_label = $event['price'] == 0 ? 'مجاناً' : number_format($event['price'], 0) . ' ₪';

$type_map = [
    'competition' => ['label' => 'مسابقة', 'icon' => '🏆', 'class' => 'ev-badge--comp'],
    'workshop'    => ['label' => 'ورشة عمل', 'icon' => '📚', 'class' => 'ev-badge--work'],
    'event'       => ['label' => 'فعالية', 'icon' => '🎉', 'class' => 'ev-badge--event'],
];
$type_info = $type_map[$event['type']] ?? $type_map['event'];

include 'includes/header.php';
?>

<!-- ===== HERO ===== -->
<div class="ev-hero">
    <img src="<?= sanitize($event['image']) ?>" alt="<?= sanitize($event['title']) ?>" class="ev-hero__img">
    <div class="ev-hero__overlay"></div>
    <div class="ev-hero__content container">
        <span class="ev-badge <?= $type_info['class'] ?>"><?= $type_info['icon'] ?> <?= $type_info['label'] ?></span>
        <h1 class="ev-hero__title"><?= sanitize($event['title']) ?></h1>
        <div class="ev-hero__meta">
            <span>📅 <?= $date_fmt ?></span>
            <span>🕐 <?= $time_fmt ?></span>
            <?php if ($event['location']): ?>
            <span>📍 <?= sanitize($event['location']) ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===== BODY ===== -->
<div class="container ev-body">

    <?php if ($msg): ?>
    <div class="ev-alert ev-alert--<?= $msg_type ?>">
        <?= $msg_type === 'success' ? '✅' : '↩️' ?> <?= $msg ?>
    </div>
    <?php endif; ?>

    <div class="ev-layout">

        <!-- MAIN -->
        <main class="ev-main">

            <!-- Description -->
            <section class="ev-card">
                <h2 class="ev-section-title">📖 عن الفعالية</h2>
                <p class="ev-desc"><?= nl2br(sanitize($event['description'])) ?></p>
            </section>

            <!-- Info Grid -->
            <section class="ev-card">
                <h2 class="ev-section-title">تفاصيل سريعة</h2>
                <div class="ev-info-grid">
                    <div class="ev-info-tile">
                        <span class="ev-info-tile__icon">📅</span>
                        <span class="ev-info-tile__label">التاريخ</span>
                        <strong class="ev-info-tile__val"><?= $date_fmt ?></strong>
                    </div>
                    <div class="ev-info-tile">
                        <span class="ev-info-tile__icon">🕐</span>
                        <span class="ev-info-tile__label">الوقت</span>
                        <strong class="ev-info-tile__val"><?= $time_fmt ?></strong>
                    </div>
                    <div class="ev-info-tile">
                        <span class="ev-info-tile__icon">📍</span>
                        <span class="ev-info-tile__label">الموقع</span>
                        <strong class="ev-info-tile__val"><?= sanitize($event['location']) ?></strong>
                    </div>
                    <div class="ev-info-tile">
                        <span class="ev-info-tile__icon">👥</span>
                        <span class="ev-info-tile__label">المشاركون</span>
                        <strong class="ev-info-tile__val"><?= $count ?> / <?= $event['max_participants'] ?></strong>
                    </div>
                    <div class="ev-info-tile">
                        <span class="ev-info-tile__icon">💰</span>
                        <span class="ev-info-tile__label">رسم الاشتراك</span>
                        <strong class="ev-info-tile__val ev-info-tile__val--gold"><?= $price_label ?></strong>
                    </div>
                    <?php if ($event['center_name']): ?>
                    <div class="ev-info-tile">
                        <span class="ev-info-tile__icon">🏇</span>
                        <span class="ev-info-tile__label">المنظم</span>
                        <strong class="ev-info-tile__val"><?= sanitize($event['center_name']) ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

        </main>

        <!-- SIDEBAR -->
        <aside class="ev-sidebar">
            <div class="ev-reg-card">

                <!-- Price -->
                <div class="ev-reg-card__price-block">
                    <span class="ev-reg-card__price-label">رسم الاشتراك</span>
                    <div class="ev-reg-card__price"><?= $price_label ?></div>
                </div>

                <!-- Seats progress -->
                <div class="ev-seats">
                    <div class="ev-seats__row">
                        <span class="ev-seats__label">المقاعد المتبقية</span>
                        <span class="ev-seats__count <?= $seats_left <= 5 ? 'ev-seats__count--warn' : '' ?>"><?= $seats_left ?> / <?= $event['max_participants'] ?></span>
                    </div>
                    <div class="ev-seats__bar">
                        <div class="ev-seats__fill <?= $seats_pct >= 80 ? 'ev-seats__fill--warn' : '' ?>" style="width:<?= $seats_pct ?>%"></div>
                    </div>
                </div>

                <!-- CTA -->
                <div class="ev-reg-card__cta">
                    <?php if (!isLoggedIn()): ?>
                        <a href="login.php" class="ev-btn ev-btn--primary">🔑 سجّل دخول للتسجيل</a>
                    <?php elseif ($registered): ?>
                        <div class="ev-reg-card__registered">
                            <span class="ev-reg-card__registered-icon">✅</span>
                            <div>
                                <strong>أنت مسجل</strong>
                                <p>تم تسجيلك في هذه الفعالية</p>
                            </div>
                        </div>
                        <form method="POST" style="margin-top: 12px;" onsubmit="return confirm('هل أنت متأكد من إلغاء التسجيل؟')">
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="ev-btn ev-btn--cancel">↩️ إلغاء التسجيل</button>
                        </form>
                    <?php elseif ($count >= $event['max_participants']): ?>
                        <div class="ev-reg-card__full">
                            <span>⚠️</span>
                            <span>اكتملت المقاعد</span>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="register">
                            <button type="submit" class="ev-btn ev-btn--primary">✍️ سجّل الآن</button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Contact -->
                <?php if ($event['phone']): ?>
                <div class="ev-reg-card__contact">
                    <span>📱</span>
                    <a href="tel:<?= sanitize($event['phone']) ?>"><?= sanitize($event['phone']) ?></a>
                </div>
                <?php endif; ?>

                <!-- Organizer -->
                <?php if ($event['center_name']): ?>
                <div class="ev-reg-card__organizer">
                    <span class="ev-reg-card__organizer-label">المنظم</span>
                    <span class="ev-reg-card__organizer-name">🏇 <?= sanitize($event['center_name']) ?></span>
                </div>
                <?php endif; ?>

            </div>
        </aside>

    </div>
</div>

<style>
/* ===== EVENT PAGE — PREMIUM REDESIGN ===== */

/* Hero */
.ev-hero {
    position: relative;
    height: 460px;
    overflow: hidden;
    margin-bottom: 0;
}
.ev-hero__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center 35%;
    display: block;
}
.ev-hero__overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        to top,
        rgba(4,4,4,1) 0%,
        rgba(4,4,4,0.72) 40%,
        rgba(4,4,4,0.25) 75%,
        rgba(4,4,4,0.1) 100%
    );
}
.ev-hero__content {
    position: absolute;
    bottom: 0;
    right: 0;
    left: 0;
    padding-bottom: 40px;
}
.ev-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.4px;
    margin-bottom: 14px;
    backdrop-filter: blur(8px);
}
.ev-badge--comp  { background: rgba(220,53,34,0.85);  color: #fff; border: 1px solid rgba(255,100,80,0.4); }
.ev-badge--work  { background: rgba(37,99,235,0.85);  color: #fff; border: 1px solid rgba(80,150,255,0.4); }
.ev-badge--event { background: rgba(180,135,20,0.85); color: #fff; border: 1px solid rgba(229,191,61,0.4); }

.ev-hero__title {
    font-size: 42px;
    font-weight: 900;
    color: #ffffff;
    margin-bottom: 16px;
    line-height: 1.25;
    text-shadow: 0 2px 16px rgba(0,0,0,0.7);
    max-width: 780px;
}
.ev-hero__meta {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    font-size: 14px;
    color: rgba(245,237,220,0.8);
    font-weight: 500;
}
.ev-hero__meta span {
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Body */
.ev-body { padding: 40px 0 70px; }

.ev-alert--success {
    background: rgba(39,174,96,0.12);
    border: 1px solid rgba(46,204,113,0.35);
    color: #4ade80;
    padding: 14px 20px;
    border-radius: 12px;
    font-weight: 600;
    margin-bottom: 28px;
    font-size: 14px;
}
.ev-alert--warning {
    background: rgba(230,120,30,0.12);
    border: 1px solid rgba(230,140,50,0.35);
    color: #f0a060;
    padding: 14px 20px;
    border-radius: 12px;
    font-weight: 600;
    margin-bottom: 28px;
    font-size: 14px;
}

/* Layout */
.ev-layout {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 28px;
    align-items: start;
}

/* Cards */
.ev-card {
    background: rgba(22,18,13,0.95);
    border: 1px solid rgba(201,162,39,0.12);
    border-radius: 20px;
    padding: 30px 32px;
    margin-bottom: 22px;
    transition: border-color 0.25s;
}
.ev-card:last-child { margin-bottom: 0; }
.ev-card:hover { border-color: rgba(201,162,39,0.25); }

.ev-section-title {
    font-size: 17px;
    font-weight: 800;
    color: #e8d8b0;
    margin-bottom: 20px;
    padding-bottom: 14px;
    border-bottom: 1px solid rgba(201,162,39,0.1);
    display: flex;
    align-items: center;
    gap: 8px;
}
.ev-desc {
    color: #b8b0a0;
    line-height: 2;
    font-size: 15px;
    font-weight: 400;
}

/* Info Grid */
.ev-info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
}
.ev-info-tile {
    background: rgba(15,12,8,0.7);
    border: 1px solid rgba(201,162,39,0.1);
    border-radius: 14px;
    padding: 18px 16px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    transition: all 0.25s;
}
.ev-info-tile:hover {
    border-color: rgba(201,162,39,0.3);
    background: rgba(25,20,14,0.9);
    transform: translateY(-2px);
}
.ev-info-tile__icon { font-size: 22px; line-height: 1; }
.ev-info-tile__label {
    font-size: 11px;
    font-weight: 600;
    color: #7a7060;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
.ev-info-tile__val {
    font-size: 15px;
    font-weight: 700;
    color: #e8e0d0;
    line-height: 1.3;
}
.ev-info-tile__val--gold { color: #c9a227; }

/* Sidebar Registration Card */
.ev-sidebar { position: sticky; top: 90px; }

.ev-reg-card {
    background: rgba(18,14,9,0.97);
    border: 1px solid rgba(201,162,39,0.2);
    border-radius: 22px;
    padding: 28px;
    backdrop-filter: blur(20px);
    box-shadow: 0 20px 60px rgba(0,0,0,0.5), 0 0 0 1px rgba(201,162,39,0.06);
}

.ev-reg-card__price-block {
    text-align: center;
    padding-bottom: 24px;
    border-bottom: 1px solid rgba(201,162,39,0.1);
    margin-bottom: 20px;
}
.ev-reg-card__price-label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: #6a6050;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-bottom: 8px;
}
.ev-reg-card__price {
    font-size: 44px;
    font-weight: 900;
    color: #c9a227;
    line-height: 1;
    letter-spacing: -1px;
}

/* Seats */
.ev-seats { margin-bottom: 22px; }
.ev-seats__row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.ev-seats__label { font-size: 12px; color: #7a7060; font-weight: 600; }
.ev-seats__count { font-size: 13px; font-weight: 800; color: #a09080; }
.ev-seats__count--warn { color: #e07040; }
.ev-seats__bar {
    height: 5px;
    background: rgba(255,255,255,0.06);
    border-radius: 10px;
    overflow: hidden;
}
.ev-seats__fill {
    height: 100%;
    background: linear-gradient(90deg, #c9a227, #e5bf3d);
    border-radius: 10px;
    transition: width 0.6s ease;
}
.ev-seats__fill--warn {
    background: linear-gradient(90deg, #e05020, #f07040);
}

/* CTA */
.ev-reg-card__cta { margin-bottom: 20px; }

.ev-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 15px 20px;
    border-radius: 14px;
    font-family: 'Cairo', sans-serif;
    font-size: 15px;
    font-weight: 800;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: all 0.25s ease;
    letter-spacing: 0.2px;
}
.ev-btn--primary {
    background: linear-gradient(135deg, #f0cc50 0%, #c9a227 100%);
    color: #0d0a05;
    box-shadow: 0 6px 24px rgba(201,162,39,0.35);
}
.ev-btn--primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 32px rgba(201,162,39,0.55);
    background: linear-gradient(135deg, #f5d660 0%, #d4ab2c 100%);
    color: #0d0a05;
}
.ev-btn--cancel {
    background: transparent;
    border: 1px solid rgba(200,60,40,0.25);
    color: #d07060;
    font-size: 13px;
    padding: 11px 20px;
}
.ev-btn--cancel:hover {
    background: rgba(200,60,40,0.1);
    border-color: rgba(200,60,40,0.45);
    color: #e08070;
    transform: translateY(-1px);
}

.ev-reg-card__registered {
    display: flex;
    align-items: center;
    gap: 14px;
    background: rgba(39,174,96,0.1);
    border: 1px solid rgba(46,204,113,0.25);
    border-radius: 14px;
    padding: 16px 18px;
}
.ev-reg-card__registered-icon { font-size: 26px; }
.ev-reg-card__registered strong { color: #4ade80; font-size: 14px; display: block; margin-bottom: 2px; }
.ev-reg-card__registered p { color: #7a9a7a; font-size: 12px; margin: 0; }

.ev-reg-card__full {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: rgba(220,53,34,0.1);
    border: 1px solid rgba(220,53,34,0.25);
    border-radius: 14px;
    padding: 14px;
    color: #f07060;
    font-weight: 700;
    font-size: 14px;
}

/* Contact */
.ev-reg-card__contact {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 0;
    border-top: 1px solid rgba(201,162,39,0.1);
    font-size: 14px;
    color: #a09080;
}
.ev-reg-card__contact a {
    color: #c9a227;
    font-weight: 700;
    text-decoration: none;
    direction: ltr;
    display: inline-block;
}
.ev-reg-card__contact a:hover { color: #e5bf3d; }

/* Organizer */
.ev-reg-card__organizer {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding-top: 14px;
    border-top: 1px solid rgba(201,162,39,0.1);
}
.ev-reg-card__organizer-label { font-size: 11px; color: #6a6050; font-weight: 600; letter-spacing: 0.5px; }
.ev-reg-card__organizer-name  { font-size: 14px; color: #e0d0b0; font-weight: 700; }

/* Responsive */
@media (max-width: 1024px) {
    .ev-layout { grid-template-columns: 1fr; }
    .ev-sidebar { position: static; }
    .ev-reg-card { max-width: 480px; margin: 0 auto; }
    .ev-hero__title { font-size: 34px; }
}
@media (max-width: 768px) {
    .ev-hero { height: 360px; }
    .ev-hero__title { font-size: 26px; }
    .ev-hero__meta { gap: 12px; font-size: 13px; }
    .ev-info-grid { grid-template-columns: 1fr 1fr; }
    .ev-card { padding: 22px 18px; }
    .ev-hero__content { padding-bottom: 28px; }
}
@media (max-width: 480px) {
    .ev-info-grid { grid-template-columns: 1fr; }
    .ev-hero__title { font-size: 22px; }
    .ev-hero { height: 300px; }
}
</style>

<?php include 'includes/footer.php'; ?>
