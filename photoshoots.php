<?php
$page_title = 'جلسات التصوير';
require_once 'config/db.php';

$photographer_filter = (int)($_GET['photographer'] ?? 0);
$where = "WHERE p.is_active = 1";
$params = [];
if ($photographer_filter) { $where .= " AND p.photographer_id = ?"; $params[] = $photographer_filter; }

$pkg_stmt = $conn->prepare("
    SELECT p.*, c.name AS center_name, c.city AS center_city,
           pg.studio_name AS photographer_name, pg.id AS photographer_id_out
    FROM photoshoot_packages p
    LEFT JOIN centers c ON c.id = p.center_id
    LEFT JOIN photographers pg ON pg.id = p.photographer_id
    $where
    ORDER BY p.price ASC
");
$pkg_stmt->execute($params);
$packages = $pkg_stmt->fetchAll();

$active_photographer = null;
if ($photographer_filter) {
    $pg_stmt = $conn->prepare("SELECT * FROM photographers WHERE id = ? AND approval_status='approved'");
    $pg_stmt->execute([$photographer_filter]);
    $active_photographer = $pg_stmt->fetch();
}

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>📸 <?= $active_photographer ? 'باقات ' . sanitize($active_photographer['studio_name']) : 'جلسات تصوير مع الخيول' ?></h1>
        <p>التقط أجمل اللحظات مع فرسك المفضل — مع ملابس فروسية مجانية ضمن الباقة</p>
        <div style="margin-top: 12px; display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="photographers.php" class="btn btn-outline">📷 تصفح المصوّرين</a>
            <?php if ($active_photographer): ?><a href="photoshoots.php" class="btn btn-outline">عرض جميع الباقات</a><?php endif; ?>
        </div>
    </div>
</div>

<div class="container" style="padding: 40px 0;">
    <?php if (empty($packages)): ?>
        <div class="empty-state">
            <span style="font-size:64px;">📷</span>
            <h2>لا توجد باقات حالياً</h2>
            <p>سيتم إضافة باقات جديدة قريباً</p>
        </div>
    <?php else: ?>
        <div class="photoshoot-grid">
            <?php foreach ($packages as $p): ?>
            <div class="photoshoot-card">
                <div class="ps-image">
                    <img src="<?= sanitize($p['image']) ?>" alt="<?= sanitize($p['title']) ?>" loading="lazy" class="ps-package-img" data-package-id="<?= $p['id'] ?>" style="width:100%; height:100%; object-fit:cover;">
                    <span class="ps-price"><?= number_format($p['price'],0) ?> ₪</span>
                </div>
                <div class="ps-body">
                    <h3><?= sanitize($p['title']) ?></h3>
                    <?php if ($p['photographer_name']): ?>
                        <div class="ps-meta">📷 <a href="photoshoots.php?photographer=<?= $p['photographer_id_out'] ?>" style="color: var(--gold);"><?= sanitize($p['photographer_name']) ?></a></div>
                    <?php elseif ($p['center_name']): ?>
                        <div class="ps-meta">📍 <?= sanitize($p['center_name']) ?> — <?= sanitize($p['center_city']) ?></div>
                    <?php endif; ?>
                    <p class="ps-desc"><?= sanitize($p['description']) ?></p>
                    <div class="ps-features">
                        <div>⏱️ <?= (int)$p['duration_minutes'] ?> دقيقة</div>
                        <div>📷 <?= (int)$p['photos_count'] ?> صورة</div>
                    </div>
                    <?php if ($p['free_outfits']): ?>
                        <div class="ps-free">🎁 مجاناً ضمن الباقة: <strong><?= sanitize($p['free_outfits']) ?></strong></div>
                    <?php endif; ?>
                    <a href="photoshoot_book.php?package=<?= $p['id'] ?>" class="btn btn-primary btn-block">📅 احجز جلستك</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle missing package images gracefully
    const packageImages = document.querySelectorAll('.ps-package-img');
    packageImages.forEach((img) => {
        img.addEventListener('error', function() {
            // Instead of showing a generic fallback, show a placeholder specific to the package
            const pkgId = this.getAttribute('data-package-id');
            const pkgCard = this.closest('.photoshoot-card');
            const pkgTitle = pkgCard ? pkgCard.querySelector('h3')?.textContent : 'باقة';

            // Create a gradient placeholder based on package ID
            const colors = [
                'linear-gradient(135deg, #c9a227 0%, #a88419 100%)',
                'linear-gradient(135deg, #e5bf3d 0%, #c9a227 100%)',
                'linear-gradient(135deg, #f0cc50 0%, #d9b835 100%)'
            ];
            const bgColor = colors[parseInt(pkgId) % colors.length];

            this.style.background = bgColor;
            this.style.display = 'flex';
            this.style.alignItems = 'center';
            this.style.justifyContent = 'center';
            this.style.fontSize = '48px';
            this.style.color = '#1a1510';
            this.style.fontWeight = 'bold';
            this.textContent = '📸';
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
