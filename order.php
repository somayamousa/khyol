<?php
$page_title = 'تفاصيل الطلب';
require_once 'config/db.php';

if (!isLoggedIn()) redirect('login.php');

$order_id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) redirect('account.php?tab=orders');

$items_stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
$items_stmt->execute([$order_id]);
$items = $items_stmt->fetchAll();

$msg = '';
$can_cancel = in_array($order['status'], ['pending','processing'])
    && (time() - strtotime($order['created_at'])) < 86400;

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cancel_order'])) {
    if ($can_cancel) {
        $conn->prepare("UPDATE orders SET status='cancelled' WHERE id=? AND user_id=?")
            ->execute([$order_id, $_SESSION['user_id']]);
        redirect('order.php?id='.$order_id.'&cancelled=1');
    }
}

$status_map = [
    'pending' => ['📝 قيد الانتظار', '#f39c12', 1],
    'processing' => ['⏳ قيد المعالجة', '#3498db', 2],
    'shipped' => ['🚚 قيد الشحن', '#9b59b6', 3],
    'delivered' => ['✅ تم التوصيل', '#2ecc71', 4],
    'cancelled' => ['❌ ملغي', '#e74c3c', 0],
];
$current_step = $status_map[$order['status']][2] ?? 0;

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>📦 تفاصيل الطلب</h1>
        <p><?= sanitize($order['order_number']) ?></p>
    </div>
</div>

<div class="container" style="padding: 40px 0; max-width: 900px;">
    <?php if (isset($_GET['cancelled'])): ?>
    <div class="alert alert-success"><span>✅</span><div>تم إلغاء الطلب بنجاح</div></div>
    <?php endif; ?>

    <div class="order-detail-card">
        <div class="order-head-row">
            <div>
                <div style="color: var(--gray); font-size: 13px; margin-bottom: 5px;">رقم الطلب</div>
                <h2 style="color: var(--gold); font-family: monospace; letter-spacing: 1px;"><?= sanitize($order['order_number']) ?></h2>
                <small style="color: var(--gray);">📅 <?= date('d/m/Y - H:i', strtotime($order['created_at'])) ?></small>
            </div>
            <div>
                <span class="status-badge" style="background: <?= $status_map[$order['status']][1] ?>22; color: <?= $status_map[$order['status']][1] ?>; padding: 8px 18px; font-size: 14px;">
                    <?= $status_map[$order['status']][0] ?>
                </span>
            </div>
        </div>

        <?php if ($order['status'] !== 'cancelled'): ?>
        <div class="order-status-timeline" style="margin-bottom: 30px;">
            <?php
            $steps = [['📝','استُلم'],['⏳','المعالجة'],['🚚','الشحن'],['✅','التوصيل']];
            foreach ($steps as $i => [$ic, $lbl]):
                $active = ($i+1) <= $current_step;
            ?>
            <div class="timeline-step <?= $active?'active':'' ?>">
                <div class="timeline-circle"><?= $ic ?></div>
                <div class="lbl"><?= $lbl ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <h3 style="color: var(--gold); margin-bottom: 15px;">📦 المنتجات</h3>
        <?php foreach ($items as $it): ?>
        <div class="order-row">
            <img src="<?= sanitize($it['product_image'] ?: 'assets/images/hero.jpg') ?>" alt="" onerror="this.src='assets/images/hero.jpg'">
            <div>
                <?php if ($it['product_id']): ?>
                <a href="product.php?id=<?= $it['product_id'] ?>" style="color: var(--white); font-weight: 700;"><?= sanitize($it['product_name']) ?></a>
                <?php else: ?>
                <strong style="color: var(--white);"><?= sanitize($it['product_name']) ?></strong>
                <?php endif; ?>
                <div style="color: var(--gray); font-size: 13px; margin-top: 5px;">
                    <?= $it['quantity'] ?> × <?= number_format($it['price'],0) ?> ₪
                </div>
            </div>
            <div style="color: var(--gold); font-weight: 900; font-size: 18px;">
                <?= number_format($it['price'] * $it['quantity'],0) ?> ₪
            </div>
        </div>
        <?php endforeach; ?>

        <div style="padding-top: 20px; margin-top: 15px; border-top: 1px solid rgba(212, 175, 55, 0.15);">
            <div class="summary-row"><span>المجموع الفرعي</span><span><?= number_format($order['subtotal'],0) ?> ₪</span></div>
            <div class="summary-row"><span>الشحن</span><span><?= $order['shipping']==0?'مجاني ✓':number_format($order['shipping'],0).' ₪' ?></span></div>
            <div class="summary-row total"><span>المجموع الكلي</span><span><?= number_format($order['total'],0) ?> ₪</span></div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;" class="order-info-grid">
        <div class="order-detail-card">
            <h3 style="color: var(--gold); margin-bottom: 15px;">📍 عنوان التوصيل</h3>
            <p style="color: #ccc; line-height: 2;">
                <strong style="color: white;"><?= sanitize($order['full_name']) ?></strong><br>
                📱 <span dir="ltr"><?= sanitize($order['phone']) ?></span><br>
                🏙️ <?= sanitize($order['city']) ?><br>
                📍 <?= sanitize($order['address']) ?>
                <?php if ($order['notes']): ?><br><br><em style="color: var(--gray);">📝 <?= sanitize($order['notes']) ?></em><?php endif; ?>
            </p>
        </div>

        <div class="order-detail-card">
            <h3 style="color: var(--gold); margin-bottom: 15px;">💳 الدفع</h3>
            <p style="color: #ccc; line-height: 2;">
                <strong style="color: white;">طريقة الدفع:</strong><br>
                <?= $order['payment_method']=='cash'?'💵 الدفع عند الاستلام':'💳 بطاقة ائتمان' ?>
            </p>
        </div>
    </div>

    <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; margin-top: 30px;">
        <a href="account.php?tab=orders" class="btn btn-outline">← العودة للطلبات</a>
        <?php if (in_array($order['status'], ['pending','processing'])): ?>
            <?php if ($can_cancel): ?>
            <form method="POST" style="margin:0;" onsubmit="return confirm('إلغاء الطلب؟ لا يمكن التراجع');">
                <button type="submit" name="cancel_order" class="btn btn-outline" style="color: #e74c3c; border-color: #e74c3c;">❌ إلغاء الطلب</button>
            </form>
            <?php else: ?>
            <span style="color: var(--gray); font-size: 13px; padding: 10px 18px; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px;">
                ⏰ انتهت مهلة الإلغاء (24 ساعة)
            </span>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
@media (max-width: 700px) {
    .order-info-grid { grid-template-columns: 1fr !important; }
}
</style>

<?php include 'includes/footer.php'; ?>
