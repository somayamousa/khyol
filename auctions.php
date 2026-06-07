<?php
$page_title = 'المزادات';
require_once 'config/db.php';

// تحديث حالة المزادات تلقائياً
$conn->exec("UPDATE auctions SET status='live' WHERE status='scheduled' AND starts_at <= NOW() AND ends_at > NOW()");
$conn->exec("UPDATE auctions SET status='ended' WHERE status IN ('scheduled','live') AND ends_at <= NOW()");

$live = $conn->query("SELECT a.*, u.full_name AS seller_name FROM auctions a JOIN users u ON u.id = a.seller_id WHERE a.status = 'live' ORDER BY a.featured DESC, a.ends_at ASC LIMIT 12")->fetchAll();
$upcoming = $conn->query("SELECT a.*, u.full_name AS seller_name FROM auctions a JOIN users u ON u.id = a.seller_id WHERE a.status = 'scheduled' ORDER BY a.starts_at ASC LIMIT 8")->fetchAll();
$ended = $conn->query("SELECT a.*, u.full_name AS seller_name, w.full_name AS winner_name FROM auctions a JOIN users u ON u.id = a.seller_id LEFT JOIN users w ON w.id = a.winner_id WHERE a.status = 'ended' ORDER BY a.ends_at DESC LIMIT 6")->fetchAll();

include 'includes/header.php';

function fmt_remaining($end) {
    $diff = strtotime($end) - time();
    if ($diff <= 0) return 'انتهى';
    $h = floor($diff / 3600);
    $m = floor(($diff % 3600) / 60);
    if ($h >= 24) return floor($h/24) . ' يوم';
    return sprintf('%02d:%02d', $h, $m);
}
?>

<div class="page-header">
    <div class="container">
        <h1>🔨 ساحة المزادات</h1>
        <p>زاحم على أفضل الخيول والأصول الفروسية</p>
        <?php if (isLoggedIn()): ?>
            <a href="auction_create.php" class="btn btn-primary" style="margin-top: 12px;">➕ أنشئ مزادك</a>
        <?php endif; ?>
    </div>
</div>

<div class="container" style="padding: 40px 0;">
    <!-- مزادات مباشرة -->
    <div class="section-title" style="text-align: right; margin-bottom: 25px;">
        <span class="eyebrow">🟢 مباشر الآن</span>
        <h2 style="font-size: 28px;">مزادات جارية (<?= count($live) ?>)</h2>
    </div>
    <?php if (empty($live)): ?>
        <div class="empty-small">لا توجد مزادات مباشرة حالياً</div>
    <?php else: ?>
    <div class="auctions-grid">
        <?php foreach ($live as $a): ?>
        <a href="auction.php?id=<?= $a['id'] ?>" class="auction-card live" style="text-decoration:none;">
            <div class="ac-image">
                <span class="ac-badge-live">⦿ LIVE</span>
                <span class="ac-countdown" data-end="<?= $a['ends_at'] ?>"><?= fmt_remaining($a['ends_at']) ?></span>
                <img src="<?= sanitize($a['main_image']) ?>" alt="<?= sanitize($a['title']) ?>" loading="lazy">
            </div>
            <div class="ac-body">
                <h3><?= sanitize($a['title']) ?></h3>
                <div class="ac-bids"><?= (int)$a['bids_count'] ?> مزايدة • <?= sanitize($a['seller_name']) ?><?= $a['city'] ? ' • 📍 ' . sanitize($a['city']) : '' ?></div>
                <div class="ac-price-row">
                    <div class="ac-current">
                        <small>السعر الحالي</small>
                        <strong><?= number_format($a['current_bid'] ?: $a['starting_price'], 0) ?> ₪</strong>
                    </div>
                    <span class="ac-cta">دخول →</span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- مزادات قادمة -->
    <?php if (!empty($upcoming)): ?>
    <div class="section-title" style="text-align: right; margin: 50px 0 25px;">
        <span class="eyebrow">⏳ قريباً</span>
        <h2 style="font-size: 28px;">مزادات قادمة</h2>
    </div>
    <div class="auctions-grid">
        <?php foreach ($upcoming as $a): ?>
        <a href="auction.php?id=<?= $a['id'] ?>" class="auction-card" style="text-decoration:none;">
            <div class="ac-image">
                <span class="ac-badge-upcoming">⏳ قادم</span>
                <img src="<?= sanitize($a['main_image']) ?>" alt="<?= sanitize($a['title']) ?>" loading="lazy">
            </div>
            <div class="ac-body">
                <h3><?= sanitize($a['title']) ?></h3>
                <div class="ac-bids">يبدأ: <?= date('d/m H:i', strtotime($a['starts_at'])) ?><?= $a['city'] ? ' • 📍 ' . sanitize($a['city']) : '' ?></div>
                <div class="ac-price-row">
                    <div class="ac-current">
                        <small>سعر البداية</small>
                        <strong><?= number_format($a['starting_price'], 0) ?> ₪</strong>
                    </div>
                    <span class="ac-cta">عرض →</span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- مزادات منتهية -->
    <?php if (!empty($ended)): ?>
    <div class="section-title" style="text-align: right; margin: 50px 0 25px;">
        <span class="eyebrow">🏁 مكتملة</span>
        <h2 style="font-size: 28px;">آخر النتائج</h2>
    </div>
    <div class="auctions-grid">
        <?php foreach ($ended as $a): ?>
        <a href="auction.php?id=<?= $a['id'] ?>" class="auction-card ended" style="text-decoration:none;">
            <div class="ac-image">
                <span class="ac-badge-ended">🏁 منتهٍ</span>
                <img src="<?= sanitize($a['main_image']) ?>" alt="<?= sanitize($a['title']) ?>" loading="lazy">
            </div>
            <div class="ac-body">
                <h3><?= sanitize($a['title']) ?></h3>
                <div class="ac-bids">الفائز: <?= sanitize($a['winner_name'] ?: 'لا يوجد فائز') ?><?= $a['city'] ? ' • 📍 ' . sanitize($a['city']) : '' ?></div>
                <div class="ac-price-row">
                    <div class="ac-current">
                        <small>سعر النهاية</small>
                        <strong><?= number_format($a['winner_amount'] ?: $a['current_bid'] ?: $a['starting_price'], 0) ?> ₪</strong>
                    </div>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// عداد تنازلي للمزادات المباشرة في القائمة
function tickCountdowns() {
    document.querySelectorAll('.ac-countdown').forEach(el => {
        const end = new Date(el.dataset.end.replace(' ', 'T'));
        const diff = (end - new Date()) / 1000;
        if (diff <= 0) { el.textContent = 'انتهى'; return; }
        const h = Math.floor(diff / 3600);
        const m = Math.floor((diff % 3600) / 60);
        const s = Math.floor(diff % 60);
        if (h >= 24) el.textContent = Math.floor(h/24) + ' يوم';
        else el.textContent = String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
    });
}
setInterval(tickCountdowns, 1000);
tickCountdowns();
</script>

<?php include 'includes/footer.php'; ?>
