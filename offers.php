<?php
$page_title = 'العروض والخصومات';
require_once 'config/db.php';

$offers = $conn->query("
    SELECT o.*, c.name AS center_name
    FROM offers o
    LEFT JOIN centers c ON o.center_id = c.id
    WHERE o.valid_until >= CURDATE()
    ORDER BY o.discount_percent DESC
")->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>🔥 العروض والخصومات</h1>
        <p>استفد من أقوى العروض على دروس الفروسية والفعاليات</p>
    </div>
</div>

<div class="container" style="padding: 40px 0;">
    <?php if (empty($offers)): ?>
    <div class="empty-state">
        <div class="big-icon">🎁</div>
        <h3>لا توجد عروض حالياً</h3>
        <p>تابعنا للحصول على أحدث العروض</p>
    </div>
    <?php else: ?>
    <div class="offers-grid">
        <?php foreach ($offers as $o): ?>
        <div class="offer-card">
            <div class="offer-image">
                <div class="offer-discount">
                    <span class="pct"><?= $o['discount_percent'] ?>%</span>
                    <span class="lbl">خصم</span>
                </div>
                <img src="<?= sanitize($o['image']) ?>" alt="<?= sanitize($o['title']) ?>" loading="lazy">
            </div>
            <div class="offer-content">
                <div>
                    <h3><?= sanitize($o['title']) ?></h3>
                    <p><?= sanitize($o['description']) ?></p>
                </div>
                <div>
                    <?php if ($o['center_name']): ?>
                    <div class="offer-expiry">🏇 <strong><?= sanitize($o['center_name']) ?></strong></div>
                    <?php endif; ?>
                    <div class="offer-expiry">⏰ ساري حتى: <strong><?= date('d/m/Y', strtotime($o['valid_until'])) ?></strong></div>
                    <?php if ($o['center_id']): ?>
                    <a href="center.php?id=<?= $o['center_id'] ?>" class="btn btn-primary btn-block">تصفح المركز 🏇</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
