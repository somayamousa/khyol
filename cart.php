<?php
$page_title = 'السلة';
require_once 'config/db.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $product_id = (int)($_POST['product_id'] ?? 0);

    if ($action === 'add' && $product_id) {
        $qty = max(1, (int)($_POST['quantity'] ?? 1));

        // تحقق من المخزون
        $p_stmt = $conn->prepare("SELECT stock, name FROM products WHERE id = ?");
        $p_stmt->execute([$product_id]);
        $product = $p_stmt->fetch();

        if (!$product) redirect('shop.php');

        $check = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $check->execute([$user_id, $product_id]);
        $existing = $check->fetch();

        if ($existing) {
            $new_qty = min($product['stock'], $existing['quantity'] + $qty);
            $upd = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $upd->execute([$new_qty, $existing['id']]);
        } else {
            $ins = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $ins->execute([$user_id, $product_id, min($product['stock'], $qty)]);
        }

        // ارجع للصفحة اللي جاي منها
        $redirect_to = $_POST['redirect'] ?? 'cart.php?added=1';
        redirect($redirect_to);
    }

    if ($action === 'increase' && $product_id) {
        $conn->prepare("
            UPDATE cart c JOIN products p ON c.product_id = p.id
            SET c.quantity = LEAST(c.quantity + 1, p.stock)
            WHERE c.user_id = ? AND c.product_id = ?
        ")->execute([$user_id, $product_id]);
        redirect('cart.php');
    }

    if ($action === 'decrease' && $product_id) {
        $check = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $check->execute([$user_id, $product_id]);
        $row = $check->fetch();
        if ($row && $row['quantity'] > 1) {
            $conn->prepare("UPDATE cart SET quantity = quantity - 1 WHERE user_id = ? AND product_id = ?")
                ->execute([$user_id, $product_id]);
        }
        redirect('cart.php');
    }

    if ($action === 'remove' && $product_id) {
        $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?")
            ->execute([$user_id, $product_id]);
        redirect('cart.php?removed=1');
    }

    if ($action === 'clear') {
        $conn->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user_id]);
        redirect('cart.php?cleared=1');
    }
}

// جلب السلة
$stmt = $conn->prepare("
    SELECT c.*, p.name, p.price, p.image, p.stock, cat.name AS category_name
    FROM cart c
    JOIN products p ON c.product_id = p.id
    LEFT JOIN categories cat ON p.category_id = cat.id
    WHERE c.user_id = ?
    ORDER BY c.added_at DESC
");
$stmt->execute([$user_id]);
$items = $stmt->fetchAll();

$subtotal = 0;
$total_items = 0;
foreach ($items as $it) {
    $subtotal += $it['price'] * $it['quantity'];
    $total_items += $it['quantity'];
}
$shipping = $subtotal > 500 ? 0 : 30;
$total = $subtotal + $shipping;

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>🛒 سلة التسوق</h1>
        <p><?= count($items) ?> منتج في سلتك (<?= $total_items ?> قطعة)</p>
    </div>
</div>

<div class="container" style="padding: 40px 0;">

    <?php if (empty($items)): ?>
    <div class="empty-state">
        <div class="big-icon">🛒</div>
        <h3 style="color: var(--gold); margin-bottom: 10px;">سلتك فارغة</h3>
        <p style="margin-bottom: 25px;">لم تضف أي منتجات حتى الآن</p>
        <a href="shop.php" class="btn btn-primary">ابدأ التسوق الآن</a>
    </div>
    <?php else: ?>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;" class="cart-layout">
        <div>
            <div class="cart-items">
                <?php foreach ($items as $it): ?>
                <div class="cart-item">
                    <a href="product.php?id=<?= $it['product_id'] ?>">
                        <img src="<?= sanitize($it['image']) ?>" alt="<?= sanitize($it['name']) ?>" class="cart-item-img">
                    </a>

                    <div class="cart-item-body">
                        <div>
                            <div class="cart-item-cat"><?= sanitize($it['category_name'] ?? '') ?></div>
                            <a href="product.php?id=<?= $it['product_id'] ?>" class="cart-item-name"><?= sanitize($it['name']) ?></a>
                            <div class="cart-item-price-unit" style="margin-top: 6px;">
                                سعر القطعة: <strong><?= number_format($it['price'], 0) ?> ₪</strong>
                            </div>
                        </div>

                        <div class="cart-item-controls">
                            <div class="qty-selector">
                                <form method="POST" style="margin:0; display:inline;">
                                    <input type="hidden" name="action" value="decrease">
                                    <input type="hidden" name="product_id" value="<?= $it['product_id'] ?>">
                                    <button type="submit" title="تقليل" <?= $it['quantity']<=1?'disabled':'' ?>>−</button>
                                </form>
                                <span class="qty-value"><?= $it['quantity'] ?></span>
                                <form method="POST" style="margin:0; display:inline;">
                                    <input type="hidden" name="action" value="increase">
                                    <input type="hidden" name="product_id" value="<?= $it['product_id'] ?>">
                                    <button type="submit" title="زيادة" <?= $it['quantity']>=$it['stock']?'disabled':'' ?>>+</button>
                                </form>
                            </div>

                            <?php if ($it['quantity'] >= $it['stock']): ?>
                            <small style="color: var(--red); font-size: 12px;">⚠️ الحد الأقصى من المخزون</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="cart-item-side">
                        <div class="cart-item-total">
                            <?= number_format($it['price'] * $it['quantity'], 0) ?> ₪
                            <small>الإجمالي</small>
                        </div>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="product_id" value="<?= $it['product_id'] ?>">
                            <button type="submit" class="cart-remove-btn" onclick="return confirm('حذف هذا المنتج من السلة؟')">
                                🗑️ حذف
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top: 20px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <a href="shop.php" class="btn btn-outline">← متابعة التسوق</a>
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="btn btn-outline" style="color: #e74c3c; border-color: #e74c3c;" onclick="return confirm('إفراغ السلة بالكامل؟')">🗑️ إفراغ السلة</button>
                </form>
            </div>
        </div>

        <div>
            <div class="cart-summary" style="position: sticky; top: 100px;">
                <h3 style="color: var(--gold); margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(212, 175, 55, 0.2);">📋 ملخص الطلب</h3>

                <div class="summary-row">
                    <span>عدد المنتجات</span>
                    <span><?= $total_items ?> قطعة</span>
                </div>
                <div class="summary-row">
                    <span>المجموع الفرعي</span>
                    <span><?= number_format($subtotal, 0) ?> ₪</span>
                </div>
                <div class="summary-row">
                    <span>الشحن</span>
                    <span><?= $shipping == 0 ? '<span style="color:#2ecc71;">مجاني ✓</span>' : number_format($shipping, 0).' ₪' ?></span>
                </div>
                <?php if ($subtotal < 500): ?>
                <div style="padding: 12px; background: rgba(212,175,55,0.1); border-radius: 10px; font-size: 13px; color: var(--gold); margin: 12px 0; text-align: center; border: 1px dashed rgba(212,175,55,0.3);">
                    🎉 أضف <strong><?= number_format(500 - $subtotal, 0) ?> ₪</strong> للحصول على شحن مجاني!
                </div>
                <?php endif; ?>
                <div class="summary-row total">
                    <span>المجموع الكلي</span>
                    <span><?= number_format($total, 0) ?> ₪</span>
                </div>
                <a href="checkout.php" class="btn btn-primary btn-block" style="margin-top: 20px; padding: 14px; font-size: 16px;">💳 إتمام الطلب</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Toasts -->
<?php if (isset($_GET['added'])): ?>
<div class="toast" id="toast-added">
    <div class="toast-icon">✅</div>
    <div class="toast-content">
        <strong>تمت الإضافة إلى السلة</strong>
        <span>يمكنك متابعة التسوق أو إتمام الطلب</span>
    </div>
    <button class="toast-close" onclick="this.parentElement.classList.remove('show')">×</button>
</div>
<?php endif; ?>
<?php if (isset($_GET['removed'])): ?>
<div class="toast error" id="toast-removed">
    <div class="toast-icon">🗑️</div>
    <div class="toast-content">
        <strong>تم الحذف من السلة</strong>
        <span>تم إزالة المنتج بنجاح</span>
    </div>
    <button class="toast-close" onclick="this.parentElement.classList.remove('show')">×</button>
</div>
<?php endif; ?>
<?php if (isset($_GET['cleared'])): ?>
<div class="toast error">
    <div class="toast-icon">🗑️</div>
    <div class="toast-content">
        <strong>تم إفراغ السلة</strong>
        <span>لا يوجد منتجات في سلتك</span>
    </div>
    <button class="toast-close" onclick="this.parentElement.classList.remove('show')">×</button>
</div>
<?php endif; ?>

<script>
// إظهار الـ toast
document.querySelectorAll('.toast').forEach(t => {
    setTimeout(() => t.classList.add('show'), 100);
    setTimeout(() => t.classList.remove('show'), 4000);
});
</script>

<style>
@media (max-width: 900px) {
    .cart-layout { grid-template-columns: 1fr !important; }
    .cart-item {
        grid-template-columns: 90px 1fr !important;
    }
    .cart-item-side {
        grid-column: 1 / -1;
        flex-direction: row !important;
        min-height: auto !important;
        justify-content: space-between;
        padding-top: 10px;
        border-top: 1px solid rgba(212, 175, 55, 0.1);
    }
}
</style>

<?php include 'includes/footer.php'; ?>
