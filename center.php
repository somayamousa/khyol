<?php
require_once 'config/db.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM centers WHERE id = ?");
$stmt->execute([$id]);
$center = $stmt->fetch();

if (!$center) redirect('centers.php');

$page_title = $center['name'];

$services = $conn->prepare("SELECT * FROM services WHERE center_id = ?");
$services->execute([$id]);
$services = $services->fetchAll();

$events = $conn->prepare("SELECT * FROM events WHERE center_id = ? AND event_date >= CURDATE() ORDER BY event_date LIMIT 3");
$events->execute([$id]);
$events = $events->fetchAll();

include 'includes/header.php';
?>

<div class="center-hero">
    <img src="<?= sanitize($center['cover_image']) ?>" alt="<?= sanitize($center['name']) ?>">
    <div class="center-hero-info">
        <h1><?= sanitize($center['name']) ?></h1>
        <div class="meta">
            <span>📍 <?= sanitize($center['city']) ?></span>
            <span>🕐 <?= sanitize($center['opening_hours']) ?></span>
        </div>
    </div>
</div>

<div class="container">
    <div class="center-layout">
        <main>
            <section style="background: var(--dark); padding: 30px; border-radius: 20px; border: 1px solid rgba(212, 175, 55, 0.15); margin-bottom: 30px;">
                <h2 style="color: var(--gold); margin-bottom: 15px;">📖 عن المركز</h2>
                <p style="color: #ccc; line-height: 2; font-size: 15px;"><?= sanitize($center['description']) ?></p>
            </section>

            <section>
                <h2 style="color: var(--gold); margin-bottom: 20px;">🏇 الخدمات والدروس</h2>
                <div class="services-list">
                    <?php foreach ($services as $s): ?>
                    <div class="service-item">
                        <div class="service-icon"><?= $s['icon'] ?></div>
                        <div class="service-info">
                            <h4><?= sanitize($s['name']) ?></h4>
                            <p><?= sanitize($s['description']) ?></p>
                            <div class="duration">⏱️ <?= sanitize($s['duration']) ?></div>
                        </div>
                        <div class="service-price">
                            <div class="price"><?= number_format($s['price'], 0) ?> ₪</div>
                            <a href="book.php?center=<?= $center['id'] ?>&service=<?= $s['id'] ?>" class="btn btn-primary">احجز الآن</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php if (!empty($events)): ?>
            <section style="margin-top: 40px;">
                <h2 style="color: var(--gold); margin-bottom: 20px;">🏆 فعاليات قادمة</h2>
                <div class="events-grid">
                    <?php foreach ($events as $e): ?>
                    <a href="event.php?id=<?= $e['id'] ?>" style="text-decoration: none;">
                        <div class="event-card">
                            <div class="event-image">
                                <span class="event-type-badge <?= $e['type'] ?>"><?= $e['type']=='competition'?'مسابقة':($e['type']=='workshop'?'ورشة':'فعالية') ?></span>
                                <div class="event-date-box">
                                    <span class="day"><?= date('d', strtotime($e['event_date'])) ?></span>
                                    <span class="month"><?= ['','يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'][(int)date('n', strtotime($e['event_date']))] ?></span>
                                </div>
                                <img src="<?= sanitize($e['image']) ?>" alt="<?= sanitize($e['title']) ?>">
                            </div>
                            <div class="event-info">
                                <h3><?= sanitize($e['title']) ?></h3>
                                <p class="desc"><?= sanitize($e['description']) ?></p>
                                <div class="event-footer">
                                    <span class="event-price <?= $e['price']==0?'free':'' ?>">
                                        <?= $e['price']==0 ? 'مجاناً' : number_format($e['price'],0).' ₪' ?>
                                    </span>
                                    <span style="color: var(--gold);">التفاصيل ←</span>
                                </div>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
        </main>

        <aside class="center-sidebar">
            <h3>📞 تواصل مع المركز</h3>
            <div class="contact-row">
                <span class="icon">📍</span>
                <div><?= sanitize($center['address']) ?></div>
            </div>
            <div class="contact-row">
                <span class="icon">📱</span>
                <div><?= sanitize($center['phone']) ?></div>
            </div>
            <div class="contact-row">
                <span class="icon">✉️</span>
                <div style="font-size: 13px;"><?= sanitize($center['email']) ?></div>
            </div>
            <div class="contact-row">
                <span class="icon">🕐</span>
                <div><?= sanitize($center['opening_hours']) ?></div>
            </div>

            <div style="margin-top: 20px;">
                <a href="tel:<?= sanitize($center['phone']) ?>" class="btn btn-primary btn-block">📞 اتصل الآن</a>
                <?php if (isLoggedIn() && (int)($center['owner_id'] ?? 0) !== (int)($_SESSION['user_id'] ?? 0)): ?>
                    <a href="chat.php?center=<?= $center['id'] ?>" class="btn btn-outline btn-block" style="margin-top: 10px;">💬 محادثة</a>
                <?php else: ?>
                    <a href="login.php?redirect=<?= urlencode('chat.php?center=' . $center['id']) ?>" class="btn btn-outline btn-block" style="margin-top: 10px;">💬 محادثة</a>
                <?php endif; ?>
            </div>

        </aside>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
