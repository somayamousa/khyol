<?php
$page_title = 'سوق الخيول';
require_once 'config/db.php';

$gender    = $_GET['gender'] ?? '';
$pure_only = isset($_GET['pure']);
$max_price = (int)($_GET['max_price'] ?? 0);
$search    = trim($_GET['q'] ?? '');
$city      = trim($_GET['city'] ?? '');

$sql = "
    SELECT h.*, u.full_name AS owner_name, u.city AS owner_city,
        (SELECT COUNT(*) FROM horse_certificates WHERE horse_id = h.id) AS certs_count
    FROM horses h
    JOIN users u ON u.id = h.owner_id
    WHERE h.is_for_sale = 1
";
$params = [];

if ($gender && in_array($gender, ['male','female'])) {
    $sql .= " AND h.gender = ?";
    $params[] = $gender;
}
if ($pure_only) {
    $sql .= " AND h.is_pure = 1";
}
if ($max_price > 0) {
    $sql .= " AND h.sale_price <= ?";
    $params[] = $max_price;
}
if ($city) {
    $sql .= " AND (h.sale_city = ? OR (h.sale_city IS NULL AND u.city = ?))";
    $params[] = $city;
    $params[] = $city;
}
if ($search) {
    $sql .= " AND (h.name LIKE ? OR h.breed LIKE ? OR h.sale_description LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
$sql .= " ORDER BY h.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$horses = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>🏇 سوق الخيول</h1>
        <p>اكتشف خيولاً أصيلة ومميزة للبيع من أفضل المربين</p>
    </div>
</div>

<div class="container" style="padding: 30px 0 60px;">
    <!-- فلترة -->
    <form method="GET" class="filter-bar">
        <input type="search" name="q" value="<?= sanitize($search) ?>" placeholder="🔍 ابحث عن خيل..." class="filter-input filter-search">
        <select name="gender" class="filter-input">
            <option value="">كل الأجناس</option>
            <option value="male" <?= $gender === 'male' ? 'selected' : '' ?>>♂️ ذكر</option>
            <option value="female" <?= $gender === 'female' ? 'selected' : '' ?>>♀️ أنثى</option>
        </select>
        <select name="city" class="filter-input">
            <option value="">📍 كل المدن</option>
            <?php foreach ($palestinian_cities as $c): ?>
                <option value="<?= $c ?>" <?= $city === $c ? 'selected' : '' ?>><?= $c ?></option>
            <?php endforeach; ?>
        </select>
        <select name="max_price" class="filter-input">
            <option value="0">كل الأسعار</option>
            <option value="10000" <?= $max_price == 10000 ? 'selected' : '' ?>>حتى 10,000 ₪</option>
            <option value="25000" <?= $max_price == 25000 ? 'selected' : '' ?>>حتى 25,000 ₪</option>
            <option value="50000" <?= $max_price == 50000 ? 'selected' : '' ?>>حتى 50,000 ₪</option>
            <option value="100000" <?= $max_price == 100000 ? 'selected' : '' ?>>حتى 100,000 ₪</option>
        </select>
        <label class="filter-pure-label">
            <input type="checkbox" name="pure" <?= $pure_only ? 'checked' : '' ?>>
            <span>⭐ أصيل فقط</span>
        </label>
        <button type="submit" class="btn btn-primary">بحث</button>
        <span class="filter-count"><?= count($horses) ?> خيل</span>
    </form>

    <?php if (isLoggedIn()): ?>
        <div class="market-cta-row">
            <a href="horse_edit.php" class="btn btn-outline">➕ اعرض خيلك للبيع</a>
        </div>
    <?php endif; ?>

    <?php if (empty($horses)): ?>
        <div class="empty-state">
            <span class="big-icon">🐎</span>
            <h2>لا توجد خيول معروضة حالياً</h2>
            <p>جرّب تغيير معايير البحث أو عُد لاحقاً</p>
        </div>
    <?php else: ?>
        <div class="horses-market-grid">
            <?php foreach ($horses as $h):
                $age = $h['birth_date'] ? (int)((time() - strtotime($h['birth_date'])) / (365.25 * 86400)) : null;
            ?>
            <a href="horse_public.php?id=<?= $h['id'] ?>" class="market-horse-card">
                <div class="market-horse-image">
                    <img src="<?= sanitize($h['main_image']) ?>" onerror="this.src='assets/images/horses/default.jpg'" alt="<?= sanitize($h['name']) ?>">
                    <div class="market-horse-price"><?= number_format($h['sale_price'], 0) ?> ₪</div>
                    <?php if ($h['is_pure']): ?>
                        <span class="horse-badge-pure">⭐ أصيل</span>
                    <?php endif; ?>
                </div>
                <div class="market-horse-body">
                    <h3><?= sanitize($h['name']) ?></h3>
                    <div class="horse-meta">
                        <span><?= $h['gender'] === 'male' ? '♂️ ذكر' : '♀️ أنثى' ?></span>
                        <?php if ($age !== null): ?><span>🎂 <?= $age ?> سنة</span><?php endif; ?>
                        <?php if ($h['breed']): ?><span>🏇 <?= sanitize($h['breed']) ?></span><?php endif; ?>
                    </div>
                    <div class="market-horse-footer">
                        <?php if ($h['certs_count']): ?>
                            <span class="market-badge">📜 <?= $h['certs_count'] ?> شهادة</span>
                        <?php endif; ?>
                        <?php if ($h['sale_city']): ?>
                            <span class="market-city-label" style="background:rgba(201,162,39,0.15); color:var(--gold); padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600;">
                                📍 <?= sanitize($h['sale_city']) ?>
                            </span>
                        <?php else: ?>
                            <span class="market-city-label" style="background:rgba(150,150,150,0.1); color:#888; padding:3px 10px; border-radius:20px; font-size:12px;">
                                📍 <?= sanitize($h['owner_city'] ?: 'غير محدد') ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
