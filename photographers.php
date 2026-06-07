<?php
$page_title = 'مصوّرو الفروسية';
require_once 'config/db.php';

$photogs = $conn->query("
    SELECT p.*, u.full_name AS owner_name
    FROM photographers p
    JOIN users u ON u.id = p.owner_id
    WHERE (p.approval_status = 'approved' OR p.approval_status IS NULL)
    ORDER BY p.featured DESC, p.created_at DESC, p.rating DESC
")->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>📸 مصوّرو الفروسية</h1>
        <p>اختر مصوّرك واحجز جلستك مع فرسك المفضل</p>
        <?php if (!isLoggedIn()): ?>
            <a href="register.php?type=photographer" class="btn btn-outline" style="margin-top: 12px;">+ سجّل كمصوّر</a>
        <?php endif; ?>
    </div>
</div>

<div class="container" style="padding: 40px 0;">
    <?php if (empty($photogs)): ?>
        <div class="empty-state">
            <span style="font-size:64px;">📷</span>
            <h2>لا يوجد مصوّرون مسجّلون بعد</h2>
            <?php if (!isLoggedIn()): ?>
                <a href="register.php?type=photographer" class="btn btn-primary">كن أول مصوّر معنا</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="photographers-grid">
            <?php foreach ($photogs as $p): ?>
            <div class="photographer-card">
                <div class="pg-image">
                    <img src="<?= sanitize($p['image']) ?>" onerror="this.onerror=null;this.src='assets/images/hero.jpg';" alt="<?= sanitize($p['studio_name']) ?>">
                    <?php if ($p['featured']): ?>
                        <span class="pg-featured">⭐ مميز</span>
                    <?php endif; ?>
                    <?php if ($p['verified']): ?>
                        <span class="pg-verified">✓ موثق</span>
                    <?php endif; ?>
                </div>
                <div class="pg-body">
                    <h3><?= sanitize($p['studio_name']) ?></h3>
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px; flex-wrap:wrap;">
                        <span style="background:rgba(201,162,39,0.18); color:var(--gold); border:1px solid rgba(201,162,39,0.35); border-radius:20px; padding:3px 12px; font-size:0.82rem; font-weight:700; letter-spacing:0.3px;">
                            📍 <?= sanitize($p['city']) ?>
                        </span>
                        <span style="color:#5a4f3e; font-size:0.82rem;">⏳ <?= (int)$p['years_experience'] ?> سنة خبرة</span>
                    </div>
                    <p class="pg-desc"><?= sanitize(mb_substr($p['description'], 0, 110)) ?>...</p>
                    <div class="pg-stats">
                        <span>⭐ <?= $p['rating'] ?></span>
                        <?php if ($p['instagram']): ?><span>📷 <?= sanitize($p['instagram']) ?></span><?php endif; ?>
                    </div>
                    <div style="display:flex; gap:6px;">
                        <a href="photoshoots.php?photographer=<?= $p['id'] ?>" class="btn btn-primary" style="flex:1;">📸 الباقات</a>
                        <?php if (isLoggedIn() && (int)$p['owner_id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                            <a href="chat.php?photographer=<?= (int)$p['id'] ?>" class="btn btn-outline" title="محادثة">💬</a>
                        <?php elseif (!isLoggedIn()): ?>
                            <a href="login.php?redirect=<?= urlencode('chat.php?photographer=' . (int)$p['id']) ?>" class="btn btn-outline" title="محادثة">💬</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
