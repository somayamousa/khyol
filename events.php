<?php
$page_title = 'الفعاليات والمسابقات';
require_once 'config/db.php';

$type_filter = $_GET['type'] ?? '';

$query = "SELECT e.*, c.name AS center_name, c.city FROM events e LEFT JOIN centers c ON e.center_id = c.id WHERE e.event_date >= CURDATE()";
$params = [];

if ($type_filter && in_array($type_filter, ['competition','event','workshop'])) {
    $query .= " AND e.type = ?";
    $params[] = $type_filter;
}

$query .= " ORDER BY e.event_date DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll();

$types = [
    '' => ['الكل', '🎯'],
    'competition' => ['مسابقات', '🏆'],
    'event' => ['فعاليات', '🎉'],
    'workshop' => ['ورشات', '📚']
];

$months = ['','يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>🏆 الفعاليات والمسابقات</h1>
        <p>شارك في أهم فعاليات الفروسية في فلسطين</p>
    </div>
</div>

<div class="container" style="padding: 40px 0;">
    <div class="tabs" style="margin-bottom: 30px;">
        <?php foreach ($types as $key => [$lbl, $ic]): ?>
        <a href="?type=<?= $key ?>" class="tab <?= $type_filter==$key?'active':'' ?>" style="text-decoration: none;"><?= $ic ?> <?= $lbl ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($events)): ?>
    <div class="empty-state">
        <div class="big-icon">🏆</div>
        <h3>لا توجد فعاليات</h3>
        <p>لا توجد فعاليات مجدولة حالياً</p>
    </div>
    <?php else: ?>
    <div class="events-grid">
        <?php foreach ($events as $e): ?>
        <a href="event.php?id=<?= $e['id'] ?>" class="event-card" style="text-decoration: none;">
            <div class="event-image">
                <span class="event-type-badge <?= $e['type'] ?>">
                    <?= $e['type']=='competition'?'🏆 مسابقة':($e['type']=='workshop'?'📚 ورشة':'🎉 فعالية') ?>
                </span>
                <div class="event-date-box">
                    <span class="day"><?= date('d', strtotime($e['event_date'])) ?></span>
                    <span class="month"><?= $months[(int)date('n', strtotime($e['event_date']))] ?></span>
                </div>
                <img src="<?= sanitize($e['image']) ?>" alt="<?= sanitize($e['title']) ?>" loading="lazy">
            </div>
            <div class="event-info">
                <h3><?= sanitize($e['title']) ?></h3>
                <p class="desc"><?= sanitize($e['description']) ?></p>
                <div class="event-meta">
                    <div>🕐 <?= date('H:i', strtotime($e['event_time'])) ?></div>
                    <div>📍 <?= sanitize($e['location']) ?></div>
                    <div>👥 <?= $e['max_participants'] ?> مشارك</div>
                </div>
                <div class="event-footer">
                    <span class="event-price <?= $e['price']==0?'free':'' ?>">
                        <?= $e['price']==0 ? 'مجاناً 🎁' : number_format($e['price'],0).' ₪' ?>
                    </span>
                    <span style="color: var(--gold); font-weight: 700;">سجّل الآن ←</span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
