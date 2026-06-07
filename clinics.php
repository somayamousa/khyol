<?php
$page_title = 'العيادات البيطرية';
require_once 'config/db.php';

$city_filter = $_GET['city'] ?? '';

$sql = "SELECT * FROM clinics WHERE (approval_status = 'approved' OR approval_status IS NULL)";
$params = [];
if ($city_filter) {
    $sql .= " AND city = ?";
    $params[] = $city_filter;
}
$sql .= " ORDER BY featured DESC, created_at DESC, rating DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$clinics = $stmt->fetchAll();

$cities = $conn->query("SELECT DISTINCT city FROM clinics ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>🩺 العيادات البيطرية</h1>
        <p>اختر عيادة بيطرية متخصصة واحجز موعدك مع أفضل الأطباء</p>
    </div>
</div>

<div class="container" style="padding: 30px 0;">
    <!-- فلترة -->
    <form method="GET" class="filter-bar">
        <select name="city" onchange="this.form.submit()">
            <option value="">كل المدن</option>
            <?php foreach ($cities as $c): ?>
                <option value="<?= sanitize($c) ?>" <?= $city_filter === $c ? 'selected' : '' ?>><?= sanitize($c) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="filter-count"><?= count($clinics) ?> عيادة</span>
    </form>

    <?php if (empty($clinics)): ?>
        <div class="empty-state">
            <span style="font-size: 64px;">🩺</span>
            <p>لا توجد عيادات مطابقة</p>
        </div>
    <?php else: ?>
        <div class="clinics-grid">
            <?php foreach ($clinics as $cl): ?>
            <div class="clinic-card">
                <div class="clinic-card-image">
                    <img src="<?= sanitize($cl['image']) ?>" onerror="this.onerror=null;this.src='assets/images/hero.jpg';" alt="<?= sanitize($cl['name']) ?>">
                    <?php if ($cl['emergency_available']): ?>
                        <span class="clinic-badge clinic-badge-emergency">🚨 طوارئ 24/7</span>
                    <?php endif; ?>
                    <?php if ($cl['home_visit']): ?>
                        <span class="clinic-badge clinic-badge-home">🏠 زيارة منزلية</span>
                    <?php endif; ?>
                </div>
                <div class="clinic-card-body">
                    <h3><?= sanitize($cl['name']) ?></h3>
                    <div class="clinic-vet">👨‍⚕️ <?= sanitize($cl['vet_name']) ?></div>
                    <div class="clinic-spec">🎯 <?= sanitize($cl['specialization']) ?></div>
                    <div class="clinic-meta">
                        <span>📍 <?= sanitize($cl['city']) ?></span>
                        <span>⭐ <?= $cl['rating'] ?></span>
                        <span>🕐 <?= sanitize($cl['opening_hours']) ?></span>
                    </div>
                    <div class="clinic-fee">رسوم الاستشارة: <strong><?= number_format($cl['consultation_fee'], 0) ?> ₪</strong></div>
                    <div class="clinic-actions">
                        <a href="clinic.php?id=<?= $cl['id'] ?><?= isset($_GET['horse']) ? '&horse=' . (int)$_GET['horse'] : '' ?>" class="btn btn-outline">التفاصيل</a>
                        <a href="clinic.php?id=<?= $cl['id'] ?><?= isset($_GET['horse']) ? '&horse=' . (int)$_GET['horse'] : '' ?>#book" class="btn btn-primary">📅 احجز موعد</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
