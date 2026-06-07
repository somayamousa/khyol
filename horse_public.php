<?php
require_once 'config/db.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT h.*, u.full_name AS owner_name, u.city AS owner_city, u.phone AS owner_phone, u.email AS owner_email, u.id AS owner_id
    FROM horses h
    JOIN users u ON u.id = h.owner_id
    WHERE h.id = ?
");
$stmt->execute([$id]);
$horse = $stmt->fetch();

if (!$horse) redirect('horses_market.php');

// إذا مش للبيع والمستخدم مش المالك، يروح للسوق
$is_owner = isLoggedIn() && (int)$_SESSION['user_id'] === (int)$horse['owner_id'];
if (!$horse['is_for_sale'] && !$is_owner) redirect('horses_market.php');

$page_title = $horse['name'];
$age = $horse['birth_date'] ? (int)((time() - strtotime($horse['birth_date'])) / (365.25 * 86400)) : null;

// جلب الشهادات (للعرض العام)
$certs = $conn->prepare("SELECT * FROM horse_certificates WHERE horse_id = ? ORDER BY issue_date DESC");
$certs->execute([$id]);
$certs = $certs->fetchAll();

include 'includes/header.php';
?>

<div class="horse-public-hero">
    <div class="container">
        <div class="horse-public-hero-grid">
            <div class="horse-public-gallery">
                <img src="<?= sanitize($horse['main_image']) ?>" onerror="this.src='assets/images/horses/default.jpg'" alt="<?= sanitize($horse['name']) ?>">
                <?php if ($horse['is_pure']): ?>
                    <span class="horse-badge-pure horse-badge-pure--gallery">⭐ أصيل</span>
                <?php endif; ?>
            </div>
            <div class="horse-public-info">
                <?php if ($horse['is_for_sale']): ?>
                    <span class="market-badge-big">🏷️ معروض للبيع</span>
                <?php endif; ?>
                <h1><?= sanitize($horse['name']) ?></h1>
                <div class="horse-public-meta">
                    <?php if ($horse['breed']): ?><span>🏇 <?= sanitize($horse['breed']) ?></span><?php endif; ?>
                    <span><?= $horse['gender'] === 'male' ? '♂️ ذكر' : '♀️ أنثى' ?></span>
                    <?php if ($age !== null): ?><span>🎂 <?= $age ?> سنة</span><?php endif; ?>
                    <?php if ($horse['color']): ?><span>🎨 <?= sanitize($horse['color']) ?></span><?php endif; ?>
                </div>

                <?php if ($horse['is_for_sale'] && $horse['sale_price']): ?>
                    <div class="horse-price-box">
                        <span>السعر</span>
                        <strong><?= number_format($horse['sale_price'], 0) ?> ₪</strong>
                    </div>
                <?php endif; ?>
                <?php if ($horse['is_for_sale'] && $horse['sale_city']): ?>
                    <div class="horse-price-box" style="background:rgba(201,162,39,0.08);">
                        <span>مدينة البيع</span>
                        <strong>📍 <?= sanitize($horse['sale_city']) ?></strong>
                    </div>
                <?php elseif ($horse['is_for_sale'] && $is_owner): ?>
                    <a href="horse_edit.php?id=<?= $id ?>" style="display:inline-block;margin-top:8px;font-size:13px;color:#e67e22;">
                        ⚠️ لم تحدد مدينة البيع — اضغط هنا لإضافتها
                    </a>
                <?php endif; ?>

                <?php if ($horse['is_for_sale'] && !$is_owner): ?>
                    <div class="horse-contact-box">
                        <h3>👤 البائع</h3>
                        <div><strong><?= sanitize($horse['owner_name']) ?></strong></div>
                        <?php if ($horse['owner_city']): ?><div>📍 <?= sanitize($horse['owner_city']) ?></div><?php endif; ?>
                        <?php if ($horse['owner_phone']): ?>
                            <div>📱 <a href="tel:<?= sanitize($horse['owner_phone']) ?>"><?= sanitize($horse['owner_phone']) ?></a></div>
                        <?php endif; ?>

                        <div class="horse-buy-actions">
                            <?php if (isLoggedIn()): ?>
                                <a href="chat.php?user_id=<?= (int)$horse['owner_id'] ?>"
                                   class="btn btn-primary btn-block">
                                    💬 محادثة مع البائع
                                </a>
                                <a href="horse_purchase_request.php?horse_id=<?= $id ?>"
                                   class="btn btn-buy btn-block">
                                    🛒 طلب شراء الحصان
                                </a>
                            <?php else: ?>
                                <a href="login.php?redirect=horse_public.php?id=<?= $id ?>"
                                   class="btn btn-primary btn-block">
                                    🔐 سجّل الدخول للتواصل
                                </a>
                            <?php endif; ?>
                        </div>
                        <style>
                        .btn-buy {
                            background: linear-gradient(135deg, var(--gold-light, #e5bf3d), var(--gold, #c9a227));
                            color: #0d0a05;
                            font-weight: 800;
                            border: none;
                            margin-top: 8px;
                        }
                        .btn-buy:hover {
                            background: linear-gradient(135deg, var(--gold, #c9a227), var(--gold-dark, #8b7510));
                            transform: translateY(-1px);
                            box-shadow: 0 6px 20px rgba(201,162,39,0.35);
                        }
                        </style>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="horse-public-content">

        <?php if ($horse['is_for_sale'] && $horse['sale_description']): ?>
        <section class="info-card info-card--sale">
            <h2>🏷️ وصف البيع</h2>
            <p><?= nl2br(sanitize($horse['sale_description'])) ?></p>
        </section>
        <?php endif; ?>

        <section class="info-card horse-specs-card">
            <h2>📋 المواصفات التفصيلية</h2>
            <div class="info-grid horse-specs-grid">
                <div><span class="spec-label">الاسم:</span> <span class="spec-value"><?= sanitize($horse['name']) ?></span></div>
                <div><span class="spec-label">السلالة:</span> <span class="spec-value"><?= sanitize($horse['breed'] ?: 'غير محدد') ?></span></div>
                <div><span class="spec-label">الجنس:</span> <span class="spec-value"><?= $horse['gender'] === 'male' ? 'ذكر ♂️' : 'أنثى ♀️' ?></span></div>
                <div><span class="spec-label">تاريخ الميلاد:</span> <span class="spec-value"><?= $horse['birth_date'] ? date('d/m/Y', strtotime($horse['birth_date'])) : 'غير محدد' ?></span></div>
                <div><span class="spec-label">العمر:</span> <span class="spec-value"><?= $age !== null ? $age . ' سنة' : 'غير محدد' ?></span></div>
                <div><span class="spec-label">اللون:</span> <span class="spec-value"><?= sanitize($horse['color'] ?: 'غير محدد') ?></span></div>
                <div><span class="spec-label">الارتفاع:</span> <span class="spec-value"><?= $horse['height_cm'] ? $horse['height_cm'] . ' سم' : 'غير محدد' ?></span></div>
                <div><span class="spec-label">الوزن:</span> <span class="spec-value"><?= $horse['weight_kg'] ? $horse['weight_kg'] . ' كغ' : 'غير محدد' ?></span></div>
                <div><span class="spec-label">رقم التسجيل:</span> <span class="spec-value"><?= sanitize($horse['registration_number'] ?: 'غير محدد') ?></span></div>
                <div><span class="spec-label">خيل أصيل:</span> <span class="spec-value"><?= $horse['is_pure'] ? '⭐ نعم، خيل أصيل' : 'غير أصيل' ?></span></div>
            </div>
        </section>

        <?php if ($horse['father_name'] || $horse['mother_name']): ?>
        <section class="info-card">
            <h2>🧬 النسب</h2>
            <div class="info-grid">
                <?php if ($horse['father_name']): ?><div><strong>🐎 الأب:</strong> <?= sanitize($horse['father_name']) ?></div><?php endif; ?>
                <?php if ($horse['mother_name']): ?><div><strong>🐴 الأم:</strong> <?= sanitize($horse['mother_name']) ?></div><?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($horse['description']): ?>
        <section class="info-card">
            <h2>📖 الوصف</h2>
            <p><?= nl2br(sanitize($horse['description'])) ?></p>
        </section>
        <?php endif; ?>

        <?php if ($horse['experience_details']): ?>
        <section class="info-card">
            <h2>🏆 الخبرة والإنجازات</h2>
            <p><?= nl2br(sanitize($horse['experience_details'])) ?></p>
        </section>
        <?php endif; ?>

        <!-- الشهادات -->
        <section class="info-card">
            <h2>📜 الشهادات والوثائق (<?= count($certs) ?>)</h2>
            <?php if (empty($certs)): ?>
                <p class="cert-note">لا توجد شهادات مسجلة لهذا الخيل.</p>
            <?php else: ?>
                <div class="certs-public-grid">
                    <?php foreach ($certs as $c):
                        $types = ['pedigree' => ['🧬 نسب', '#8e44ad'], 'competition' => ['🏆 مسابقة', '#e67e22'], 'health' => ['🩺 صحية', '#27ae60'], 'other' => ['📋 أخرى', 'var(--gold)']];
                        $t = $types[$c['cert_type']] ?? ['📋', 'var(--gold)'];
                    ?>
                    <div class="cert-public-card">
                        <div class="cert-public-type" style="background: <?= $t[1] ?>;"><?= $t[0] ?></div>
                        <div class="cert-public-body">
                            <h4><?= sanitize($c['title']) ?></h4>
                            <?php if ($c['issuer']): ?><div>🏛️ <?= sanitize($c['issuer']) ?></div><?php endif; ?>
                            <?php if ($c['issue_date']): ?><div>📅 <?= $c['issue_date'] ?></div><?php endif; ?>
                            <?php if ($c['notes']): ?><p class="cert-note"><?= sanitize($c['notes']) ?></p><?php endif; ?>
                            <?php if ($c['file_path']): ?>
                                <a href="<?= sanitize($c['file_path']) ?>" target="_blank" class="btn btn-outline btn-cert-view">📄 عرض الشهادة</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($is_owner): ?>
            <div class="horse-owner-actions">
                <a href="horse_edit.php?id=<?= $id ?>" class="btn btn-outline">✏️ تعديل البيانات</a>
                <a href="horse.php?id=<?= $id ?>" class="btn btn-primary">📋 الملف الكامل</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
