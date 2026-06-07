<?php
$page_title = 'طلب شراء حصان';
require_once 'config/db.php';
require_once 'includes/notify.php';

if (!isLoggedIn()) redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));

$buyer_id = (int)$_SESSION['user_id'];
$horse_id = (int)($_GET['horse_id'] ?? 0);

// جلب بيانات الحصان
$stmt = $conn->prepare("
    SELECT h.*, u.full_name AS seller_name, u.id AS seller_id, u.phone AS seller_phone
    FROM horses h
    JOIN users u ON u.id = h.owner_id
    WHERE h.id = ? AND h.is_for_sale = 1
");
$stmt->execute([$horse_id]);
$horse = $stmt->fetch();

if (!$horse) redirect('horses_market.php');
if ((int)$horse['seller_id'] === $buyer_id) redirect('horse_public.php?id=' . $horse_id);

// هل يوجد طلب قائم؟
$existing = $conn->prepare("SELECT id, status FROM horse_purchase_requests WHERE horse_id = ? AND buyer_id = ? AND status IN ('pending','accepted') LIMIT 1");
$existing->execute([$horse_id, $buyer_id]);
$prev_request = $existing->fetch();

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$prev_request) {
    $offer_price = (float)($_POST['offer_price'] ?? 0) ?: null;
    $message     = sanitize(mb_substr($_POST['message'] ?? '', 0, 1000));

    $ins = $conn->prepare("INSERT INTO horse_purchase_requests (horse_id, buyer_id, seller_id, offer_price, message) VALUES (?, ?, ?, ?, ?)");
    if ($ins->execute([$horse_id, $buyer_id, (int)$horse['seller_id'], $offer_price, $message ?: null])) {
        $success = true;
        send_notification(
            $conn,
            (int)$horse['seller_id'],
            'طلب شراء جديد',
            'مستخدم مهتم بشراء حصانك "' . $horse['name'] . '"',
            'purchase',
            '🛒',
            'horse_public.php?id=' . $horse_id
        );
    } else {
        $error = 'حدث خطأ أثناء إرسال الطلب، حاول مجدداً';
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>🛒 طلب شراء حصان</h1>
    </div>
</div>

<div class="container" style="max-width: 680px; padding: 40px 0;">

    <!-- بطاقة الحصان -->
    <div class="pr-horse-card">
        <img src="<?= sanitize($horse['main_image']) ?>" onerror="this.src='assets/images/horses/default.jpg'" alt="<?= sanitize($horse['name']) ?>">
        <div class="pr-horse-info">
            <h2><?= sanitize($horse['name']) ?></h2>
            <?php if ($horse['breed']): ?><p>🏇 <?= sanitize($horse['breed']) ?></p><?php endif; ?>
            <p class="pr-price"><?= number_format($horse['sale_price'], 0) ?> ₪</p>
            <p class="pr-seller">البائع: <strong><?= sanitize($horse['seller_name']) ?></strong></p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="pr-success">
            <div class="pr-success-icon">✅</div>
            <h2>تم إرسال طلب الشراء!</h2>
            <p>سيتم إشعار البائع وسيتواصل معك قريباً.</p>
            <div class="pr-success-actions">
                <a href="chat.php?user_id=<?= (int)$horse['seller_id'] ?>" class="btn btn-primary">💬 محادثة مع البائع</a>
                <a href="horses_market.php" class="btn btn-outline">تصفّح السوق</a>
            </div>
        </div>

    <?php elseif ($prev_request): ?>
        <div class="pr-already">
            <?php if ($prev_request['status'] === 'accepted'): ?>
                <div class="pr-already-icon">🎉</div>
                <h2>طلبك مقبول!</h2>
                <p>قبل البائع طلبك، تواصل معه لإتمام الصفقة.</p>
            <?php else: ?>
                <div class="pr-already-icon">⏳</div>
                <h2>لديك طلب قائم</h2>
                <p>طلبك قيد المراجعة من قِبَل البائع.</p>
            <?php endif; ?>
            <a href="chat.php?user_id=<?= (int)$horse['seller_id'] ?>" class="btn btn-primary" style="margin-top:16px;">💬 محادثة مع البائع</a>
        </div>

    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?= $error ?></div>
        <?php endif; ?>

        <form method="POST" class="pr-form">
            <div class="form-group">
                <label>💰 سعرك المقترح (اختياري)</label>
                <div class="pr-price-input">
                    <input type="number" name="offer_price" step="1" min="0"
                           placeholder="اتركه فارغاً للموافقة على السعر المطلوب"
                           value="<?= number_format($horse['sale_price'], 0, '.', '') ?>">
                    <span class="pr-currency">₪</span>
                </div>
                <small>السعر المطلوب: <strong><?= number_format($horse['sale_price'], 0) ?> ₪</strong></small>
            </div>

            <div class="form-group">
                <label>📝 رسالة للبائع (اختياري)</label>
                <textarea name="message" rows="4"
                          placeholder="مثلاً: أنا جاد في الشراء، متى يمكنني معاينة الحصان؟"></textarea>
            </div>

            <div class="pr-notice">
                <span>ℹ️</span>
                <p>إرسال هذا الطلب لا يُلزمك بالشراء، بل هو تعبير عن اهتمامك. ستُحاط علماً بردّ البائع.</p>
            </div>

            <div class="pr-actions">
                <button type="submit" class="btn btn-buy btn-block">🛒 إرسال طلب الشراء</button>
                <a href="horse_public.php?id=<?= $horse_id ?>" class="btn btn-outline btn-block">إلغاء</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<style>
/* ── Horse card ── */
.pr-horse-card {
    display: flex;
    gap: 20px;
    align-items: center;
    background: rgba(201,162,39,0.06);
    border: 1.5px solid rgba(201,162,39,0.2);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 28px;
}
.pr-horse-card img {
    width: 110px;
    height: 90px;
    object-fit: cover;
    border-radius: 12px;
    flex-shrink: 0;
}
.pr-horse-info h2 {
    font-size: 20px;
    font-weight: 800;
    color: var(--gold, #c9a227);
    margin: 0 0 4px;
}
.pr-horse-info p { margin: 2px 0; font-size: 14px; }
.pr-price { font-size: 22px; font-weight: 900; color: var(--gold, #c9a227); }
.pr-seller { font-size: 13px; opacity: .8; }

/* ── Form ── */
.pr-form .form-group { margin-bottom: 20px; }
.pr-form label { display: block; font-weight: 700; margin-bottom: 7px; font-size: 14px; }
.pr-form input, .pr-form textarea {
    width: 100%;
    padding: 11px 14px;
    border-radius: 10px;
    border: 1.5px solid rgba(201,162,39,0.25);
    background: rgba(255,255,255,0.05);
    color: inherit;
    font-family: 'Cairo', sans-serif;
    font-size: 14px;
    outline: none;
    transition: border-color .2s;
    box-sizing: border-box;
}
.pr-form input:focus, .pr-form textarea:focus { border-color: var(--gold, #c9a227); }
.pr-form small { font-size: 12px; opacity: .65; margin-top: 5px; display: block; }
.pr-price-input { position: relative; }
.pr-price-input input { padding-left: 40px; }
.pr-currency {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-weight: 800;
    color: var(--gold, #c9a227);
    font-size: 15px;
}
.pr-notice {
    display: flex;
    gap: 10px;
    background: rgba(201,162,39,0.07);
    border-right: 3px solid rgba(201,162,39,0.4);
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 20px;
    font-size: 13px;
    opacity: .85;
}
.pr-notice span { font-size: 18px; flex-shrink: 0; }
.pr-actions { display: flex; flex-direction: column; gap: 10px; }

/* ── Buy button ── */
.btn-buy {
    background: linear-gradient(135deg, var(--gold-light, #e5bf3d), var(--gold, #c9a227));
    color: #0d0a05 !important;
    font-weight: 800;
    border: none;
}
.btn-buy:hover {
    background: linear-gradient(135deg, var(--gold, #c9a227), var(--gold-dark, #8b7510));
    box-shadow: 0 6px 20px rgba(201,162,39,0.35);
    transform: translateY(-1px);
}

/* ── Success / Already states ── */
.pr-success, .pr-already {
    text-align: center;
    padding: 40px 20px;
    background: rgba(201,162,39,0.06);
    border: 1.5px solid rgba(201,162,39,0.2);
    border-radius: 16px;
}
.pr-success-icon, .pr-already-icon { font-size: 64px; margin-bottom: 12px; }
.pr-success h2, .pr-already h2 { color: var(--gold, #c9a227); margin-bottom: 8px; }
.pr-success p, .pr-already p { opacity: .8; margin-bottom: 20px; }
.pr-success-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }

/* light theme */
[data-theme='light'] .pr-form input,
[data-theme='light'] .pr-form textarea {
    background: #f5f3ef;
    color: #1a1510;
    border-color: rgba(201,162,39,0.3);
}
[data-theme='light'] .pr-form label { color: #1a1510; }
[data-theme='light'] .pr-form small { color: #7a6e60; }
[data-theme='light'] .pr-horse-card { background: #f9f6ef; }
</style>

<?php include 'includes/footer.php'; ?>
