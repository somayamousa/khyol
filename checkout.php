<?php
$page_title = 'إتمام الطلب';
require_once 'config/db.php';
require_once 'includes/notify.php';

if (!isLoggedIn()) redirect('login.php');

$user_id = $_SESSION['user_id'];

// جلب بيانات المستخدم
$u_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$u_stmt->execute([$user_id]);
$user = $u_stmt->fetch();

// جلب السلة
$c_stmt = $conn->prepare("
    SELECT c.*, p.name, p.price, p.image, p.stock
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
");
$c_stmt->execute([$user_id]);
$items = $c_stmt->fetchAll();

if (empty($items)) redirect('cart.php');

$subtotal = 0;
foreach ($items as $it) $subtotal += $it['price'] * $it['quantity'];
$shipping = $subtotal > 500 ? 0 : 30;
$total = $subtotal + $shipping;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $full_name = sanitize($_POST['full_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    $payment = $_POST['payment_method'] ?? 'cash';

    if (strlen($full_name) < 3) $errors[] = 'الاسم الكامل مطلوب.';
    if (!preg_match('/^[0-9+\-\s]{8,20}$/', $phone)) $errors[] = 'رقم الهاتف غير صحيح.';
    if (!in_array($city, $palestinian_cities)) $errors[] = 'الرجاء اختيار مدينة.';
    if (strlen($address) < 5) $errors[] = 'العنوان التفصيلي مطلوب.';
    if (!in_array($payment, ['cash','card'])) $errors[] = 'طريقة دفع غير صحيحة.';
    if ($payment === 'card') {
        $card_number = preg_replace('/\s/', '', $_POST['card_number'] ?? '');
        $card_expiry = sanitize($_POST['card_expiry'] ?? '');
        $card_cvv    = sanitize($_POST['card_cvv'] ?? '');
        $card_name   = sanitize($_POST['card_name'] ?? '');
        if (!preg_match('/^\d{13,19}$/', $card_number))   $errors[] = 'رقم البطاقة غير صحيح.';
        if (!preg_match('/^\d{2}\/\d{2}$/', $card_expiry)) $errors[] = 'تاريخ انتهاء البطاقة غير صحيح.';
        if (!preg_match('/^\d{3,4}$/', $card_cvv))         $errors[] = 'رمز CVV غير صحيح.';
        if (strlen($card_name) < 3)                        $errors[] = 'أدخل اسم حامل البطاقة.';
    }

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            $ins_order = $conn->prepare("
                INSERT INTO orders (order_number, user_id, subtotal, shipping, total, full_name, phone, city, address, notes, payment_method)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins_order->execute([$order_number, $user_id, $subtotal, $shipping, $total, $full_name, $phone, $city, $address, $notes, $payment]);
            $order_id = $conn->lastInsertId();

            $ins_item = $conn->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, product_image, price, quantity)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($items as $it) {
                // تقليل المخزون مع التحقق أن الكمية متوفرة (يمنع السالب)
                $upd = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
                $upd->execute([$it['quantity'], $it['product_id'], $it['quantity']]);
                if ($upd->rowCount() === 0) {
                    throw new Exception('نفد مخزون المنتج: ' . $it['name']);
                }
                $ins_item->execute([$order_id, $it['product_id'], $it['name'], $it['image'], $it['price'], $it['quantity']]);
            }

            // تفريغ السلة
            $conn->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user_id]);

            $conn->commit();

            // إشعار لكل بائع فريد في الطلب
            $buyer_name = $full_name;
            $sellers_stmt = $conn->prepare("
                SELECT DISTINCT p.seller_id
                FROM order_items oi
                JOIN products p ON p.id = oi.product_id
                WHERE oi.order_id = ? AND p.seller_id IS NOT NULL AND p.seller_id != ?
            ");
            $sellers_stmt->execute([$order_id, $user_id]);
            foreach ($sellers_stmt->fetchAll() as $sv) {
                send_notification(
                    $conn,
                    (int)$sv['seller_id'],
                    'طلب جديد في متجرك! 🛒',
                    $buyer_name . ' اشترى من متجرك — طلب #' . $order_number,
                    'order', '📦', 'my-shop.php?tab=sales'
                );
            }

            redirect('order-success.php?order=' . $order_number);
        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = 'حدث خطأ أثناء إنشاء الطلب. حاول مرة أخرى.';
        }
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>💳 إتمام الطلب</h1>
        <p>أكمل بياناتك لنشحن طلبك بأسرع وقت</p>
    </div>
</div>

<div class="container" style="padding: 40px 0;">

    <div class="checkout-steps">
        <div class="step done">
            <div class="step-circle">✓</div>
            <div class="step-label">السلة</div>
        </div>
        <div class="step-connector done"></div>
        <div class="step active">
            <div class="step-circle">2</div>
            <div class="step-label">بيانات التوصيل</div>
        </div>
        <div class="step-connector"></div>
        <div class="step">
            <div class="step-circle">3</div>
            <div class="step-label">التأكيد</div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error" style="max-width: 900px; margin: 0 auto 20px;">
        <span>⚠️</span>
        <div><?php foreach ($errors as $e) echo "<div>$e</div>"; ?></div>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="checkout-layout">
            <div>
                <!-- بيانات التوصيل -->
                <div class="checkout-section">
                    <div class="section-head">
                        <div class="num">1</div>
                        <h3>📦 بيانات التوصيل</h3>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>الاسم الكامل</label>
                            <input type="text" name="full_name" class="form-control" required value="<?= sanitize($_POST['full_name'] ?? $user['full_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label>رقم الهاتف</label>
                            <input type="tel" name="phone" class="form-control" required value="<?= sanitize($_POST['phone'] ?? $user['phone']) ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>المدينة</label>
                        <select name="city" class="form-control" required>
                            <option value="">اختر المدينة</option>
                            <?php foreach ($palestinian_cities as $c): ?>
                            <option value="<?= $c ?>" <?= ($_POST['city'] ?? $user['city'])==$c?'selected':'' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>العنوان التفصيلي</label>
                        <textarea name="address" class="form-control" rows="2" required placeholder="الحي، الشارع، رقم البناية..."><?= sanitize($_POST['address'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>ملاحظات إضافية (اختياري)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="أي تعليمات خاصة للتوصيل..."><?= sanitize($_POST['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- طريقة الدفع -->
                <div class="checkout-section">
                    <div class="section-head">
                        <div class="num">2</div>
                        <h3>💳 طريقة الدفع</h3>
                    </div>

                    <div class="payment-options">
                        <label class="payment-option selected" id="pay-cash">
                            <input type="radio" name="payment_method" value="cash" checked onchange="selectPayment('cash')">
                            <span class="icon">💵</span>
                            <div class="info">
                                <strong>الدفع عند الاستلام</strong>
                                <span>ادفع كاش عند وصول الطلب</span>
                            </div>
                        </label>

                        <label class="payment-option" id="pay-card">
                            <input type="radio" name="payment_method" value="card" onchange="selectPayment('card')">
                            <span class="icon">💳</span>
                            <div class="info">
                                <strong>بطاقة ائتمان / مدى</strong>
                                <span>Visa, Mastercard, مدى</span>
                            </div>
                        </label>
                    </div>

                    <div id="card-fields" style="display:none; margin-top:20px; padding:20px; background:rgba(201,162,39,0.05); border:1px solid rgba(201,162,39,0.2); border-radius:14px;">
                        <div class="form-group" style="margin-bottom:14px;">
                            <label>رقم البطاقة</label>
                            <input type="text" name="card_number" class="form-control" placeholder="0000 0000 0000 0000" maxlength="19" oninput="formatCard(this)">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>تاريخ الانتهاء</label>
                                <input type="text" name="card_expiry" class="form-control" placeholder="MM/YY" maxlength="5" oninput="formatExpiry(this)">
                            </div>
                            <div class="form-group">
                                <label>CVV</label>
                                <input type="text" name="card_cvv" class="form-control" placeholder="123" maxlength="4" inputmode="numeric">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>اسم حامل البطاقة</label>
                            <input type="text" name="card_name" class="form-control" placeholder="الاسم كما على البطاقة">
                        </div>
                        <div style="display:flex; gap:10px; margin-top:8px; opacity:0.6; font-size:12px; color:var(--text-dark-muted);">
                            🔒 بياناتك محمية ومشفّرة
                        </div>
                    </div>
                </div>
            </div>

            <!-- الملخص -->
            <div>
                <div class="checkout-summary">
                    <h3 style="color: var(--gold); margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(212, 175, 55, 0.2);">📋 ملخص الطلب</h3>

                    <div class="order-items-preview">
                        <?php foreach ($items as $it): ?>
                        <div class="order-item-mini">
                            <img src="<?= sanitize($it['image']) ?>" alt="">
                            <div>
                                <div class="name"><?= sanitize($it['name']) ?></div>
                                <div class="meta"><?= $it['quantity'] ?> × <?= number_format($it['price'],0) ?> ₪</div>
                            </div>
                            <div class="line-total"><?= number_format($it['price'] * $it['quantity'],0) ?> ₪</div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="summary-row">
                        <span>المجموع الفرعي</span>
                        <span><?= number_format($subtotal,0) ?> ₪</span>
                    </div>
                    <div class="summary-row">
                        <span>الشحن</span>
                        <span><?= $shipping==0?'<span style="color:#2ecc71;">مجاني ✓</span>':number_format($shipping,0).' ₪' ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>المجموع الكلي</span>
                        <span><?= number_format($total,0) ?> ₪</span>
                    </div>

                    <button type="submit" name="place_order" class="btn btn-primary btn-block" style="margin-top: 20px; padding: 16px; font-size: 16px;">
                        ✅ تأكيد الطلب
                    </button>

                    <a href="cart.php" style="display: block; text-align: center; margin-top: 15px; color: var(--gray); font-size: 13px;">← العودة للسلة</a>

                    <div style="margin-top: 20px; padding: 12px; background: rgba(46, 204, 113, 0.08); border-radius: 10px; font-size: 12px; color: #2ecc71; text-align: center; border: 1px solid rgba(46, 204, 113, 0.2);">
                        🔒 طلبك آمن ومحمي
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function selectPayment(type) {
    document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('selected'));
    document.getElementById('pay-' + type).classList.add('selected');
    document.getElementById('card-fields').style.display = type === 'card' ? 'block' : 'none';
    document.querySelectorAll('#card-fields input').forEach(el => el.required = type === 'card');
}

function formatCard(el) {
    let v = el.value.replace(/\D/g, '').substring(0, 16);
    el.value = v.replace(/(.{4})/g, '$1 ').trim();
}

function formatExpiry(el) {
    let v = el.value.replace(/\D/g, '').substring(0, 4);
    if (v.length >= 2) v = v.substring(0,2) + '/' + v.substring(2);
    el.value = v;
}
</script>

<style>
@media (max-width: 900px) {
    .checkout-layout { grid-template-columns: 1fr !important; }
    .checkout-summary { position: static !important; }
}
</style>

<?php include 'includes/footer.php'; ?>
