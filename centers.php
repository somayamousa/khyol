<?php
$page_title = 'مراكز الفروسية';
require_once 'config/db.php';

$city_filter = $_GET['city'] ?? '';
$search = sanitize($_GET['q'] ?? '');

$query = "SELECT * FROM centers WHERE (approval_status = 'approved' OR approval_status IS NULL)";
$params = [];

if ($city_filter) {
    $query .= " AND city = ?";
    $params[] = $city_filter;
}

if ($search) {
    $query .= " AND name LIKE ?";
    $params[] = "%$search%";
}

$query .= " ORDER BY featured DESC, created_at DESC, rating DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$centers = $stmt->fetchAll();

$cities = $conn->query("SELECT DISTINCT city FROM centers ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>🏇 مراكز الفروسية</h1>
        <p>اختر المركز الأنسب لك من بين <?= count($centers) ?> مركز</p>
    </div>
</div>

<div class="container" style="padding: 40px 0;">
    <form method="GET" style="display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap;">
        <input type="text" name="q" class="form-control" placeholder="🔍 ابحث عن مركز..." value="<?= $search ?>" style="flex: 1; min-width: 250px;">
        <select name="city" class="form-control" style="max-width: 220px;">
            <option value="">كل المدن</option>
            <?php foreach ($cities as $city): ?>
            <option value="<?= $city ?>" <?= $city_filter==$city?'selected':'' ?>><?= $city ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">بحث</button>
    </form>

    <?php if (empty($centers)): ?>
    <div class="empty-state">
        <div class="big-icon">🏇</div>
        <h3>لا توجد مراكز</h3>
        <p>لم نجد مراكز تطابق بحثك</p>
    </div>
    <?php else: ?>

    <div class="centers-grid">
        <?php foreach ($centers as $c): ?>
        <a href="center.php?id=<?= $c['id'] ?>" class="center-card" style="text-decoration: none;">
            <div class="center-image">
                <?php if ($c['featured']): ?>
                <span class="center-featured-badge">⭐ مميز</span>
                <?php endif; ?>
                <span class="center-city-badge">📍 <?= sanitize($c['city']) ?></span>
                <img src="<?= sanitize($c['image']) ?>" alt="<?= sanitize($c['name']) ?>" loading="lazy">
            </div>
            <div class="center-info">
                <h3><?= sanitize($c['name']) ?></h3>
                <p class="desc"><?= sanitize($c['description']) ?></p>
                <div class="center-meta">
                    <div class="center-rating">
                        ⭐ <?= $c['rating'] ?> <small>(<?= $c['reviews_count'] ?> تقييم)</small>
                    </div>
                    <div style="color: var(--gold); font-weight: 700;">عرض التفاصيل ←</div>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- CTA تسجيل مركز -->
<section style="background: linear-gradient(135deg, rgba(212,175,55,0.1) 0%, rgba(212,175,55,0.02) 100%); padding: 50px 0; margin-top: 40px; border-top: 1px solid rgba(212,175,55,0.15);">
    <div class="container" style="text-align: center;">
        <div style="font-size: 50px; margin-bottom: 15px;">🏇</div>
        <h2 style="color: var(--gold); font-size: 32px; margin-bottom: 10px;">عندك مركز فروسية؟</h2>
        <p style="color: #ccc; font-size: 16px; margin-bottom: 25px;">سجّل مركزك على منصة خيول مجاناً وابدأ استقبال الحجوزات</p>
        <a href="register.php?type=center" class="btn btn-primary" style="padding: 14px 35px;">📝 سجّل مركزك الآن</a>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
