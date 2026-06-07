<?php
$page_title = 'تم الطلب بنجاح';
require_once 'config/db.php';

if (!isLoggedIn()) redirect('login.php');

$order_number = sanitize($_GET['order'] ?? '');

$stmt = $conn->prepare("SELECT * FROM orders WHERE order_number = ? AND user_id = ?");
$stmt->execute([$order_number, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) redirect('account.php?tab=orders');

$items_stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
$items_stmt->execute([$order['id']]);
$items = $items_stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container">
    <div class="success-wrapper">
        <div class="success-icon">🎉</div>
        <h1>تم إتمام طلبك بنجاح!</h1>
        <p>شكراً لك <strong style="color: var(--gold);"><?= sanitize($order['full_name']) ?></strong>!<br>
        سيتم التواصل معك قريباً لتأكيد الطلب وتحديد موعد التوصيل.</p>

        <div class="order-number-box">
            <div class="lbl">رقم طلبك</div>
            <div class="num"><?= sanitize($order['order_number']) ?></div>
        </div>

        <div class="order-status-timeline">
            <div class="timeline-step active">
                <div class="timeline-circle">📝</div>
                <div class="lbl">تم الاستلام</div>
            </div>
            <div class="timeline-step">
                <div class="timeline-circle">⏳</div>
                <div class="lbl">قيد المعالجة</div>
            </div>
            <div class="timeline-step">
                <div class="timeline-circle">🚚</div>
                <div class="lbl">قيد الشحن</div>
            </div>
            <div class="timeline-step">
                <div class="timeline-circle">✅</div>
                <div class="lbl">تم التوصيل</div>
            </div>
        </div>

        <div class="order-detail-card" style="text-align: right;">
            <h3 style="color: var(--gold); margin-bottom: 20px;">📦 تفاصيل الطلب</h3>

            <?php foreach ($items as $it): ?>
            <div class="order-row">
                <img src="<?= sanitize($it['product_image']) ?>" alt="">
                <div>
                    <strong style="color: var(--white); display: block; margin-bottom: 5px;"><?= sanitize($it['product_name']) ?></strong>
                    <small style="color: var(--gray);"><?= $it['quantity'] ?> × <?= number_format($it['price'],0) ?> ₪</small>
                </div>
                <div style="color: var(--gold); font-weight: 900; font-size: 17px;">
                    <?= number_format($it['price'] * $it['quantity'],0) ?> ₪
                </div>
            </div>
            <?php endforeach; ?>

            <div style="padding-top: 20px; margin-top: 15px; border-top: 1px solid rgba(212, 175, 55, 0.15);">
                <div class="summary-row"><span>المجموع الفرعي</span><span><?= number_format($order['subtotal'],0) ?> ₪</span></div>
                <div class="summary-row"><span>الشحن</span><span><?= $order['shipping']==0?'مجاني ✓':number_format($order['shipping'],0).' ₪' ?></span></div>
                <div class="summary-row total"><span>المجموع الكلي</span><span><?= number_format($order['total'],0) ?> ₪</span></div>
            </div>

            <div style="margin-top: 25px; padding: 18px; background: var(--dark-2); border-radius: 12px;">
                <h4 style="color: var(--gold); margin-bottom: 12px;">📍 عنوان التوصيل</h4>
                <p style="color: #ccc; line-height: 1.9; font-size: 14px;">
                    <strong style="color: white;"><?= sanitize($order['full_name']) ?></strong><br>
                    📱 <span dir="ltr"><?= sanitize($order['phone']) ?></span><br>
                    📍 <?= sanitize($order['city']) ?> - <?= sanitize($order['address']) ?>
                    <?php if ($order['notes']): ?><br>📝 <?= sanitize($order['notes']) ?><?php endif; ?>
                </p>
            </div>

            <div style="margin-top: 15px; padding: 12px; background: var(--dark-2); border-radius: 10px; display: flex; justify-content: space-between; align-items: center;">
                <span style="color: var(--gray);">طريقة الدفع:</span>
                <strong style="color: var(--gold);"><?= $order['payment_method']=='cash'?'💵 الدفع عند الاستلام':'💳 بطاقة ائتمان' ?></strong>
            </div>
        </div>

        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; margin-top: 30px;">
            <a href="account.php?tab=orders" class="btn btn-primary">📦 عرض طلباتي</a>
            <a href="shop.php" class="btn btn-outline">🛍️ متابعة التسوق</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
