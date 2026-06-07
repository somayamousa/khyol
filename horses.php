<?php
$page_title = 'خيولي';
require_once 'config/db.php';

if (!isLoggedIn()) redirect('login.php?redirect=horses');

$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT h.*,
        (SELECT COUNT(*) FROM horse_health_records r WHERE r.horse_id = h.id) AS records_count,
        (SELECT COUNT(*) FROM horse_vaccinations v WHERE v.horse_id = h.id) AS vaccinations_count,
        (SELECT COUNT(*) FROM horse_certificates c WHERE c.horse_id = h.id) AS certificates_count
    FROM horses h
    WHERE h.owner_id = ?
    ORDER BY h.created_at DESC
");
$stmt->execute([$user_id]);
$horses = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>🐎 خيولي</h1>
        <p>ملفات الخيول الكاملة - السجل الصحي، اللقاحات، الشهادات</p>
    </div>
</div>

<div class="container" style="padding: 40px 0;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h2 style="color: var(--text-dark);">عدد الخيول: <?= count($horses) ?></h2>
        <a href="horse_edit.php" class="btn btn-primary">➕ إضافة خيل جديد</a>
    </div>

    <?php if (empty($horses)): ?>
        <div class="empty-state">
            <span style="font-size: 72px;">🐎</span>
            <h2>لم تسجّل أي خيل بعد</h2>
            <p>ابدأ بإضافة خيلك الأول وحفظ ملفه الكامل</p>
            <a href="horse_edit.php" class="btn btn-primary">➕ أضف أول خيل</a>
        </div>
    <?php else: ?>
        <div class="horses-grid">
            <?php foreach ($horses as $h):
                $age = $h['birth_date'] ? (int)((time() - strtotime($h['birth_date'])) / (365.25 * 86400)) : null;
            ?>
            <a href="horse.php?id=<?= $h['id'] ?>" class="horse-card">
                <div class="horse-card-image">
                    <img src="<?= sanitize($h['main_image']) ?>" alt="<?= sanitize($h['name']) ?>" onerror="this.src='assets/images/horses/default.jpg'">
                    <?php if ($h['is_pure']): ?>
                        <span class="horse-badge-pure">⭐ أصيل</span>
                    <?php endif; ?>
                </div>
                <div class="horse-card-body">
                    <h3><?= sanitize($h['name']) ?></h3>
                    <div class="horse-meta">
                        <span><?= $h['gender'] === 'male' ? '♂️ ذكر' : '♀️ أنثى' ?></span>
                        <?php if ($age !== null): ?><span>🎂 <?= $age ?> سنة</span><?php endif; ?>
                        <?php if ($h['breed']): ?><span>🏇 <?= sanitize($h['breed']) ?></span><?php endif; ?>
                    </div>
                    <div class="horse-stats">
                        <div class="horse-stat"><strong><?= $h['records_count'] ?></strong><small>سجل صحي</small></div>
                        <div class="horse-stat"><strong><?= $h['vaccinations_count'] ?></strong><small>لقاح</small></div>
                        <div class="horse-stat"><strong><?= $h['certificates_count'] ?></strong><small>شهادة</small></div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
