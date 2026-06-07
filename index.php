<?php
$page_title = 'الرئيسية';
require_once 'config/db.php';
require_once 'includes/notify.php';

// كروت الإشعارات العائمة على الهيرو
$hero_notis = get_public_notifications($conn, 4);

// جلب الفئات
$categories = $conn->query("SELECT * FROM categories")->fetchAll();

// جلب المنتجات المميزة
$featured = $conn->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.featured = 1 LIMIT 8")->fetchAll();

// جلب المراكز المميزة
$top_centers = $conn->query("SELECT * FROM centers WHERE featured = 1 ORDER BY rating DESC LIMIT 3")->fetchAll();

// جلب الفعاليات القادمة
$upcoming_events = $conn->query("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 3")->fetchAll();

// جلب العروض
$top_offers = $conn->query("SELECT o.*, c.name AS center_name FROM offers o LEFT JOIN centers c ON o.center_id = c.id WHERE o.valid_until >= CURDATE() ORDER BY o.discount_percent DESC LIMIT 3")->fetchAll();

$months_ar = ['','يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];

// مزاد مباشر للعرض على الرئيسية
$conn->exec("UPDATE auctions SET status='live' WHERE status='scheduled' AND starts_at <= NOW() AND ends_at > NOW()");
$conn->exec("UPDATE auctions SET status='ended' WHERE status IN ('scheduled','live') AND ends_at <= NOW()");
$live_auction = $conn->query("SELECT id, title, main_image, current_bid, starting_price, ends_at, bids_count FROM auctions WHERE status='live' ORDER BY featured DESC, ends_at ASC LIMIT 1")->fetch();

include 'includes/header.php';
?>

<!-- الهيرو -->
<section class="hero">
    <?php if (!empty($hero_notis)): ?>
    <div class="hero-floating-notis">
        <?php foreach ($hero_notis as $i => $n): ?>
            <a href="<?= sanitize($n['link'] ?: '#') ?>" class="hero-noti-card" style="text-decoration:none;">
                <?php if ($n['color'] === 'green'): ?>
                    <span class="noti-pulse"></span>
                <?php else: ?>
                    <span class="noti-emoji"><?= sanitize($n['icon']) ?></span>
                <?php endif; ?>
                <span><?= sanitize($n['title']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="hero-content">
        <span class="hero-tag">✨ منصة الفروسية الأولى في فلسطين</span>
        <h1>
            عالم الفروسية
            <span>بين يديك</span>
        </h1>
        <p>اكتشف أفضل مراكز الفروسية، احجز دروسك، واقتنِ أفخر مستلزمات الخيول من مكان واحد. انضم لمجتمع عشاق الخيول في نابلس وكل فلسطين.</p>
        <div class="hero-actions">
            <a href="shop.php" class="btn btn-primary">🛒 تسوق الآن</a>
            <a href="register.php" class="btn btn-outline">انضم إلينا</a>
        </div>
    </div>
</section>

<!-- شريط الخدمات الجديدة -->
<section class="container" style="margin-top: -30px; position: relative; z-index: 4;">
    <div class="services-strip">
        <a href="auctions.php" class="srv-card srv-auction">
            <span class="srv-icon">🔨</span>
            <div>
                <strong>المزاد المباشر</strong>
                <small>زاحم على أفضل الخيول</small>
            </div>
        </a>
        <a href="photoshoots.php" class="srv-card srv-photo">
            <span class="srv-icon">📸</span>
            <div>
                <strong>جلسات تصوير</strong>
                <small>مع ملابس مجانية</small>
            </div>
        </a>
        <a href="boarding.php" class="srv-card srv-board">
            <span class="srv-icon">🏇</span>
            <div>
                <strong>إيواء وتدريب</strong>
                <small>أودع فرسك واربح نسبة</small>
            </div>
        </a>
        <a href="diseases.php" class="srv-card srv-vet">
            <span class="srv-icon">🩺</span>
            <div>
                <strong>أمراض الخيل</strong>
                <small>دليل صحي بإشراف الأطباء</small>
            </div>
        </a>
    </div>
</section>

<?php if (!empty($top_offers)): ?>
<!-- العروض -->
<section class="section" style="padding-top: 50px;">
    <div class="container">
        <div class="section-title">
            <span class="eyebrow">العروض</span>
            <h2>🔥 عروض حصرية</h2>
            <p>خصومات لفترة محدودة</p>
        </div>
        <div class="offers-grid">
            <?php foreach ($top_offers as $o): ?>
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
                        <div class="offer-expiry">⏰ حتى: <strong><?= date('d/m/Y', strtotime($o['valid_until'])) ?></strong></div>
                        <a href="<?= $o['center_id'] ? 'center.php?id='.$o['center_id'] : 'offers.php' ?>" class="btn btn-primary btn-block">استفد من العرض 🎁</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="text-align: center; margin-top: 35px;">
            <a href="offers.php" class="btn btn-outline">كل العروض ←</a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($live_auction): ?>
<!-- مزاد مباشر -->
<section class="section">
    <div class="container">
        <div class="live-auction-banner">
            <div class="lab-image">
                <span class="ac-badge-live" style="position:absolute;top:14px;right:14px;font-size:13px;">⦿ LIVE</span>
                <img src="<?= sanitize($live_auction['main_image']) ?>" alt="<?= sanitize($live_auction['title']) ?>">
            </div>
            <div class="lab-body">
                <span class="eyebrow">🔨 مزاد مباشر الآن</span>
                <h2><?= sanitize($live_auction['title']) ?></h2>
                <div class="lab-stats">
                    <div>
                        <small>السعر الحالي</small>
                        <strong><?= number_format($live_auction['current_bid'] ?: $live_auction['starting_price'], 0) ?> ₪</strong>
                    </div>
                    <div>
                        <small>عدد المزايدات</small>
                        <strong><?= (int)$live_auction['bids_count'] ?></strong>
                    </div>
                    <div>
                        <small>الوقت المتبقي</small>
                        <strong class="lab-cd" data-end="<?= $live_auction['ends_at'] ?>">--:--:--</strong>
                    </div>
                </div>
                <a href="auction.php?id=<?= $live_auction['id'] ?>" class="btn btn-primary">دخول الغرفة 🔨</a>
            </div>
        </div>
    </div>
    <script>
    (function(){
        const el = document.querySelector('.lab-cd');
        if (!el) return;
        const end = new Date(el.dataset.end.replace(' ','T'));
        function tick(){
            const d = Math.max(0, (end - new Date())/1000);
            const h = Math.floor(d/3600), m = Math.floor((d%3600)/60), s = Math.floor(d%60);
            el.textContent = String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
        }
        setInterval(tick,1000); tick();
    })();
    </script>
</section>
<?php endif; ?>

<!-- إحصائيات -->
<section class="container">
    <div class="stats">
        <div class="stat-box">
            <span class="num">50+</span>
            <div class="lbl">مركز فروسية</div>
        </div>
        <div class="stat-box">
            <span class="num">1200+</span>
            <div class="lbl">عميل سعيد</div>
        </div>
        <div class="stat-box">
            <span class="num">300+</span>
            <div class="lbl">منتج متنوع</div>
        </div>
        <div class="stat-box">
            <span class="num">15+</span>
            <div class="lbl">مدينة فلسطينية</div>
        </div>
    </div>
</section>

<!-- الفئات -->
<section class="section" id="categories">
    <div class="container">
        <div class="section-title">
            <span class="eyebrow">الفئات</span>
            <h2>تسوق حسب الفئة</h2>
            <p>كل ما تحتاجه للفروسية في مكان واحد</p>
        </div>
        <div class="categories-grid">
            <?php foreach ($categories as $cat): ?>
            <a href="shop.php?category=<?= $cat['id'] ?>" class="category-card">
                <span class="category-icon"><?= $cat['icon'] ?></span>
                <h3><?= sanitize($cat['name']) ?></h3>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- المراكز المميزة -->
<section class="section">
    <div class="container">
        <div class="section-title">
            <span class="eyebrow">المراكز</span>
            <h2>🏇 مراكز مميزة</h2>
            <p>أفضل مراكز الفروسية في فلسطين</p>
        </div>
        <div class="centers-grid">
            <?php foreach ($top_centers as $c): ?>
            <a href="center.php?id=<?= $c['id'] ?>" class="center-card" style="text-decoration: none;">
                <div class="center-image">
                    <span class="center-featured-badge">⭐ مميز</span>
                    <span class="center-city-badge">📍 <?= sanitize($c['city']) ?></span>
                    <img src="<?= sanitize($c['image']) ?>" alt="<?= sanitize($c['name']) ?>" loading="lazy">
                </div>
                <div class="center-info">
                    <h3><?= sanitize($c['name']) ?></h3>
                    <p class="desc"><?= sanitize($c['description']) ?></p>
                    <div class="center-meta">
                        <div class="center-rating">⭐ <?= $c['rating'] ?> <small>(<?= $c['reviews_count'] ?>)</small></div>
                        <div style="color: var(--gold);">التفاصيل ←</div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <div style="text-align: center; margin-top: 35px;">
            <a href="centers.php" class="btn btn-outline">عرض كل المراكز ←</a>
        </div>
    </div>
</section>

<!-- الفعاليات القادمة -->
<?php if (!empty($upcoming_events)): ?>
<section class="section" style="background: var(--bg-2);">
    <div class="container">
        <div class="section-title">
            <span class="eyebrow">الفعاليات</span>
            <h2>🏆 فعاليات قادمة</h2>
            <p>لا تفوّت أهم الفعاليات والمسابقات</p>
        </div>
        <div class="events-grid">
            <?php foreach ($upcoming_events as $e): ?>
            <a href="event.php?id=<?= $e['id'] ?>" class="event-card" style="text-decoration: none;">
                <div class="event-image">
                    <span class="event-type-badge <?= $e['type'] ?>">
                        <?= $e['type']=='competition'?'🏆 مسابقة':($e['type']=='workshop'?'📚 ورشة':'🎉 فعالية') ?>
                    </span>
                    <div class="event-date-box">
                        <span class="day"><?= date('d', strtotime($e['event_date'])) ?></span>
                        <span class="month"><?= $months_ar[(int)date('n', strtotime($e['event_date']))] ?></span>
                    </div>
                    <img src="<?= sanitize($e['image']) ?>" alt="<?= sanitize($e['title']) ?>" loading="lazy">
                </div>
                <div class="event-info">
                    <h3><?= sanitize($e['title']) ?></h3>
                    <p class="desc"><?= sanitize($e['description']) ?></p>
                    <div class="event-footer">
                        <span class="event-price <?= $e['price']==0?'free':'' ?>">
                            <?= $e['price']==0 ? 'مجاناً' : number_format($e['price'],0).' ₪' ?>
                        </span>
                        <span style="color: var(--gold); font-weight: 700;">سجّل الآن ←</span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <div style="text-align: center; margin-top: 35px;">
            <a href="events.php" class="btn btn-outline">كل الفعاليات ←</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- المنتجات المميزة -->
<section class="section" style="background: var(--bg-2);">
    <div class="container">
        <div class="section-title">
            <span class="eyebrow">المتجر</span>
            <h2>منتجات مختارة</h2>
            <p>الأكثر تميزاً من عالم الفروسية</p>
        </div>
        <div class="products-grid">
            <?php foreach ($featured as $p): ?>
            <div class="product-card">
                <div class="product-image">
                    <?php if ($p['old_price']): ?>
                    <span class="product-badge">خصم</span>
                    <?php else: ?>
                    <span class="product-badge featured">مميز</span>
                    <?php endif; ?>
                    <a href="product.php?id=<?= $p['id'] ?>">
                        <img src="<?= sanitize($p['image']) ?>" alt="<?= sanitize($p['name']) ?>" loading="lazy">
                    </a>
                </div>
                <div class="product-info">
                    <div class="product-category"><?= sanitize($p['category_name']) ?></div>
                    <div class="product-name">
                        <a href="product.php?id=<?= $p['id'] ?>"><?= sanitize($p['name']) ?></a>
                    </div>
                    <div class="product-rating">
                        <?= str_repeat('⭐', (int)round($p['rating'])) ?>
                        <span style="color: var(--gray); font-size: 13px;">(<?= $p['rating'] ?>)</span>
                    </div>
                    <div class="product-price-row">
                        <div class="product-price">
                            <span class="price-current"><?= number_format($p['price'], 0) ?></span>
                            <span class="currency">₪</span>
                            <?php if ($p['old_price']): ?>
                            <span class="price-old"><?= number_format($p['old_price'], 0) ?></span>
                            <?php endif; ?>
                        </div>
                        <form method="POST" action="cart.php" style="margin:0;">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="redirect" value="index.php?added=1#featured">
                            <button class="btn-add-cart" title="أضف إلى السلة">+</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="text-align: center; margin-top: 40px;">
            <a href="shop.php" class="btn btn-outline">عرض جميع المنتجات ←</a>
        </div>
    </div>
</section>

<!-- من نحن -->
<section class="section" id="about">
    <div class="container">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 50px; align-items: center;">
            <div>
                <span class="eyebrow" style="margin-bottom: 15px; display: inline-block;">من نحن</span>
                <h2 style="font-size: 40px; color: #ffffff; margin-bottom: 20px;">عن منصة <span style="color: var(--gold);">خيـــول</span></h2>
                <p style="color: #e8e0d0; line-height: 2; margin-bottom: 15px; font-size: 16px;">منصة خيـــول هي الوجهة الأولى لعشاق الفروسية في فلسطين. نجمع بين أفضل مراكز الفروسية، المدربين المحترفين، ومتجر شامل لكل احتياجاتك من مستلزمات الخيول والفارس.</p>
                <p style="color: #c9bfa8; line-height: 2; font-size: 16px;">هدفنا نشر ثقافة الفروسية وتسهيل وصول الهواة والمحترفين لأجود المعدات والخدمات بأسعار تنافسية.</p>
                <div style="margin-top: 30px;">
                    <a href="register.php" class="btn btn-primary">ابدأ رحلتك معنا 🏇</a>
                </div>
            </div>
            <div style="border-radius: 20px; overflow: hidden; border: 2px solid var(--gold); box-shadow: var(--shadow-gold);">
                <img src="assets/images/about.jpg" alt="فروسية" style="width: 100%; height: 400px; object-fit: cover;">
            </div>
        </div>
    </div>
</section>

<?php if (isset($_GET['added'])): ?>
<div class="toast">
    <div class="toast-icon">✅</div>
    <div class="toast-content">
        <strong>تمت الإضافة إلى السلة</strong>
        <span>تم إضافة المنتج بنجاح - <a href="cart.php" style="color:var(--gold);">عرض السلة</a></span>
    </div>
    <button class="toast-close" onclick="this.parentElement.classList.remove('show')">×</button>
</div>
<script>
document.querySelectorAll('.toast').forEach(t => {
    setTimeout(() => t.classList.add('show'), 100);
    setTimeout(() => t.classList.remove('show'), 4500);
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
