<?php
$page_title = 'متجري';
require_once 'config/db.php';
require_once 'includes/notify.php';

if (!isLoggedIn()) redirect('login.php?redirect=my-shop');

$user_id = (int)$_SESSION['user_id'];
$tab     = $_GET['tab'] ?? 'overview';
$errors  = [];

// ========== POST: إضافة / تعديل منتج ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $pid         = (int)($_POST['product_id'] ?? 0);
    $name        = sanitize($_POST['pname'] ?? '');
    $description = sanitize($_POST['pdesc'] ?? '');
    $price       = (float)($_POST['pprice'] ?? 0);
    $old_price   = (float)($_POST['pold_price'] ?? 0) ?: null;
    $stock       = max(0, (int)($_POST['pstock'] ?? 0));
    $cat_id      = (int)($_POST['pcat'] ?? 0) ?: null;

    if (strlen($name) < 3)  $errors[] = 'اسم المنتج قصير جداً.';
    if ($price <= 0)         $errors[] = 'السعر يجب أن يكون أكبر من صفر.';

    if (empty($errors)) {
        // رفع الصورة
        $image_path = null;
        if ($pid) {
            $old = $conn->prepare("SELECT image FROM products WHERE id = ? AND seller_id = ?");
            $old->execute([$pid, $user_id]);
            $image_path = $old->fetchColumn() ?: null;
        }
        if (isset($_FILES['pimage']) && $_FILES['pimage']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg','jpeg','png','webp'];
            $ext = strtolower(pathinfo($_FILES['pimage']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed) && $_FILES['pimage']['size'] < 5 * 1024 * 1024) {
                $upload_dir = __DIR__ . '/assets/images/products/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $fname = 'p_' . $user_id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['pimage']['tmp_name'], $upload_dir . $fname)) {
                    $image_path = 'assets/images/products/' . $fname;
                }
            } else {
                $errors[] = 'الصورة يجب أن تكون jpg/png/webp وأصغر من 5MB.';
            }
        }

        if (empty($errors)) {
            if ($pid) {
                $q = $conn->prepare("UPDATE products SET name=?, description=?, price=?, old_price=?, stock=?, category_id=?, image=? WHERE id=? AND seller_id=?");
                $q->execute([$name, $description, $price, $old_price, $stock, $cat_id, $image_path, $pid, $user_id]);
            } else {
                $q = $conn->prepare("INSERT INTO products (seller_id, name, description, price, old_price, stock, category_id, image, approval_status) VALUES (?,?,?,?,?,?,?,?,'approved')");
                $q->execute([$user_id, $name, $description, $price, $old_price, $stock, $cat_id, $image_path]);
            }
            redirect('my-shop.php?tab=products&saved=1');
        }
    }
}

// ========== تحديث حالة الطلب إلى شحن ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seller_ship_order'])) {
    $ship_order_id = (int)($_POST['order_id'] ?? 0);
    $chk = $conn->prepare("
        SELECT o.id FROM orders o
        JOIN order_items oi ON oi.order_id = o.id
        JOIN products p ON p.id = oi.product_id
        WHERE o.id = ? AND p.seller_id = ? AND o.status = 'processing'
        LIMIT 1
    ");
    $chk->execute([$ship_order_id, $user_id]);
    if ($chk->fetch()) {
        $conn->prepare("UPDATE orders SET status = 'shipped' WHERE id = ?")
            ->execute([$ship_order_id]);
        $ord_row = $conn->prepare("SELECT user_id, order_number FROM orders WHERE id = ?");
        $ord_row->execute([$ship_order_id]);
        $ord_row = $ord_row->fetch();
        send_notification($conn, (int)$ord_row['user_id'],
            'طلبك في الطريق إليك 🚚',
            'تم شحن طلبك رقم #' . $ord_row['order_number'] . ' — سيصلك قريباً',
            'order', '🚚', 'order.php?id=' . $ship_order_id
        );
    }
    redirect('my-shop.php?tab=sales&shipped=1');
}

// ========== تأكيد البائع للطلب ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seller_confirm_order'])) {
    $confirm_order_id = (int)($_POST['order_id'] ?? 0);
    // تحقق أن الطلب يحتوي منتجاً يخص هذا البائع
    $chk = $conn->prepare("
        SELECT o.id FROM orders o
        JOIN order_items oi ON oi.order_id = o.id
        JOIN products p ON p.id = oi.product_id
        WHERE o.id = ? AND p.seller_id = ? AND o.status = 'pending'
        LIMIT 1
    ");
    $chk->execute([$confirm_order_id, $user_id]);
    if ($chk->fetch()) {
        $conn->prepare("UPDATE orders SET seller_confirmed = 1, status = 'processing' WHERE id = ?")
            ->execute([$confirm_order_id]);
        // إشعار للمشتري
        $ord_row = $conn->prepare("SELECT user_id, order_number FROM orders WHERE id = ?");
        $ord_row->execute([$confirm_order_id]);
        $ord_row = $ord_row->fetch();
        send_notification($conn, (int)$ord_row['user_id'],
            'تم تأكيد طلبك ✅',
            'البائع أكّد طلبك رقم #' . $ord_row['order_number'] . ' — سيتم الشحن قريباً',
            'order', '📦', 'order.php?id=' . $confirm_order_id
        );
    }
    redirect('my-shop.php?tab=sales&confirmed=1');
}

// ========== حذف منتج ==========
if (isset($_GET['delete_product'])) {
    $pid = (int)$_GET['delete_product'];
    $conn->prepare("DELETE FROM products WHERE id = ? AND seller_id = ?")->execute([$pid, $user_id]);
    redirect('my-shop.php?tab=products&deleted=1');
}

// ========== جلب البيانات ==========
$categories = $conn->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

$my_products = $conn->prepare("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.seller_id = ? ORDER BY p.created_at DESC");
$my_products->execute([$user_id]);
$my_products = $my_products->fetchAll();

// مبيعات
$sales = [];
if ($tab === 'sales' || $tab === 'overview') {
    $sales_stmt = $conn->prepare("
        SELECT oi.*, o.id AS order_id, o.order_number, o.created_at AS order_date,
               o.status AS order_status, o.seller_confirmed,
               o.full_name AS buyer_name, o.phone AS buyer_phone, o.city AS buyer_city
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        JOIN products p ON p.id = oi.product_id
        WHERE p.seller_id = ?
        ORDER BY o.created_at DESC
        LIMIT 200
    ");
    $sales_stmt->execute([$user_id]);
    $sales = $sales_stmt->fetchAll();
}

// إحصائيات
$total_products  = count($my_products);
$total_sold      = (int)$conn->prepare("SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE p.seller_id=?")->execute([$user_id]) ? $conn->prepare("SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE p.seller_id=?")->execute([$user_id]) : 0;

$stats_q = $conn->prepare("SELECT COALESCE(SUM(oi.quantity),0) AS units, COALESCE(SUM(oi.price * oi.quantity),0) AS revenue FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE p.seller_id=?");
$stats_q->execute([$user_id]);
$stats_row = $stats_q->fetch();

$stats = [
    'products' => $total_products,
    'units'    => (int)$stats_row['units'],
    'revenue'  => (float)$stats_row['revenue'],
    'orders'   => (int)$conn->prepare("SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON oi.order_id=o.id JOIN products p ON p.id=oi.product_id WHERE p.seller_id=?")->execute([$user_id]) ? 0 : 0,
];
$ord_q = $conn->prepare("SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON oi.order_id=o.id JOIN products p ON p.id=oi.product_id WHERE p.seller_id=?");
$ord_q->execute([$user_id]);
$stats['orders'] = (int)$ord_q->fetchColumn();

// منتج للتعديل
$edit_product = null;
if (isset($_GET['edit_product'])) {
    $ep = $conn->prepare("SELECT * FROM products WHERE id=? AND seller_id=?");
    $ep->execute([(int)$_GET['edit_product'], $user_id]);
    $edit_product = $ep->fetch() ?: null;
}

$order_status_map = [
    'pending'    => ['📝 بانتظار التأكيد', '#f39c12'],
    'processing' => ['⏳ قيد المعالجة',   '#3498db'],
    'shipped'    => ['🚚 قيد الشحن',       '#9b59b6'],
    'delivered'  => ['✅ تم التوصيل',      '#2ecc71'],
    'cancelled'  => ['❌ ملغي',            '#e74c3c'],
];

include 'includes/header.php';
?>

<div class="mc-wrap">
    <!-- السايدبار -->
    <aside class="mc-sidebar">
        <div class="mc-brand">
            <div class="mc-avatar" style="background:linear-gradient(135deg,#f39c12,#e67e22);">🛍️</div>
            <div>
                <strong>متجري</strong>
                <small><?= sanitize($_SESSION['user_name'] ?? '') ?></small>
            </div>
        </div>
        <ul class="mc-nav">
            <li><a href="?tab=overview"  class="<?= $tab=='overview'?'active':'' ?>"><span>📊</span> نظرة عامة</a></li>
            <li><a href="?tab=products"  class="<?= $tab=='products'?'active':'' ?>"><span>📦</span> منتجاتي (<?= $stats['products'] ?>)</a></li>
            <li><a href="?tab=sales"     class="<?= $tab=='sales'?'active':'' ?>"><span>🛒</span> مبيعاتي (<?= $stats['orders'] ?>)</a></li>
            <li class="mc-divider"></li>
            <li><a href="shop.php"><span>🏪</span> تصفح المتجر</a></li>
            <li><a href="account.php"><span>👤</span> حسابي</a></li>
        </ul>
    </aside>

    <!-- المحتوى -->
    <main class="mc-main">
        <?php if (isset($_GET['saved'])): ?><div class="mc-alert success">✅ تم حفظ المنتج</div><?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?><div class="mc-alert success">🗑️ تم حذف المنتج</div><?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="mc-alert error">⚠️ <ul><?php foreach($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if ($tab === 'overview'): ?>
        <!-- ==================== نظرة عامة ==================== -->
        <div class="mc-header">
            <div>
                <div class="mc-breadcrumb">متجري / نظرة عامة</div>
                <h1>🛍️ مرحباً في متجرك!</h1>
                <p>أضف منتجاتك وتابع مبيعاتك من مكان واحد.</p>
            </div>
        </div>

        <div class="mc-stats-grid">
            <div class="mc-stat" style="--c:#f39c12;">
                <div class="mc-stat-ic">📦</div>
                <div><b><?= $stats['products'] ?></b><small>منتج</small></div>
            </div>
            <div class="mc-stat" style="--c:#9b59b6;">
                <div class="mc-stat-ic">🛒</div>
                <div><b><?= $stats['orders'] ?></b><small>طلب</small></div>
            </div>
            <div class="mc-stat" style="--c:#2ecc71;">
                <div class="mc-stat-ic">📊</div>
                <div><b><?= $stats['units'] ?></b><small>وحدة مباعة</small></div>
            </div>
            <div class="mc-stat" style="--c:#c9a227;">
                <div class="mc-stat-ic">💰</div>
                <div><b><?= number_format($stats['revenue'], 0) ?> ₪</b><small>إجمالي المبيعات</small></div>
            </div>
        </div>

        <?php if ($stats['products'] === 0): ?>
        <div class="mc-panel warn">
            <h3>⚠️ لم تضف منتجات بعد</h3>
            <p>أضف منتجك الأول وابدأ البيع الآن!</p>
            <a href="?tab=products" class="mc-btn primary">➕ أضف منتج</a>
        </div>
        <?php endif; ?>

        <?php if (!empty($sales)): ?>
        <div class="mc-panel">
            <div class="mc-panel-head"><h3>🛒 آخر المبيعات</h3><a href="?tab=sales" class="mc-link">الكل ←</a></div>
            <?php foreach (array_slice($sales, 0, 5) as $s):
                $st = $order_status_map[$s['order_status']] ?? ['—','#999']; ?>
            <div class="mc-mini-row">
                <div><strong><?= sanitize($s['product_name']) ?></strong><small><?= sanitize($s['buyer_name']) ?> — <?= sanitize($s['buyer_city']) ?></small></div>
                <div><?= $s['quantity'] ?> × <?= number_format($s['price'],0) ?> ₪</div>
                <span class="mc-pill" style="--c:<?= $st[1] ?>;"><?= $st[0] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php elseif ($tab === 'products'): ?>
        <!-- ==================== منتجاتي ==================== -->
        <div class="mc-header">
            <div>
                <div class="mc-breadcrumb">متجري / منتجاتي</div>
                <h1>📦 إدارة المنتجات</h1>
                <p><?= count($my_products) ?> منتج في متجرك</p>
            </div>
        </div>

        <div class="mc-panel">
            <div class="mc-panel-head">
                <h3><?= $edit_product ? '✏️ تعديل منتج' : '➕ منتج جديد' ?></h3>
                <?php if ($edit_product): ?><a href="?tab=products" class="mc-link">إلغاء</a><?php endif; ?>
            </div>
            <form method="POST" enctype="multipart/form-data" class="mc-form">
                <input type="hidden" name="product_id" value="<?= $edit_product['id'] ?? '' ?>">
                <div class="mc-form-grid">
                    <div class="form-group full">
                        <label>اسم المنتج</label>
                        <input type="text" name="pname" class="form-control" required value="<?= sanitize($edit_product['name'] ?? '') ?>" placeholder="مثلاً: سرج جلدي فاخر">
                    </div>
                    <div class="form-group">
                        <label>السعر (₪)</label>
                        <input type="number" name="pprice" class="form-control" required min="1" step="0.01" value="<?= $edit_product['price'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>السعر القديم (₪) — اختياري</label>
                        <input type="number" name="pold_price" class="form-control" min="0" step="0.01" value="<?= $edit_product['old_price'] ?? '' ?>" placeholder="للعروض فقط">
                    </div>
                    <div class="form-group">
                        <label>الكمية المتوفرة</label>
                        <input type="number" name="pstock" class="form-control" required min="0" value="<?= $edit_product['stock'] ?? 1 ?>">
                    </div>
                    <div class="form-group">
                        <label>التصنيف</label>
                        <select name="pcat" class="form-control">
                            <option value="">— بدون تصنيف —</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($edit_product['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>صورة المنتج</label>
                        <?php if (!empty($edit_product['image'])): ?>
                            <img src="<?= sanitize($edit_product['image']) ?>" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:8px;margin-bottom:6px;" onerror="this.style.display='none'">
                        <?php endif; ?>
                        <input type="file" name="pimage" class="form-control" accept="image/*" <?= $edit_product ? '' : 'required' ?>>
                    </div>
                    <div class="form-group full">
                        <label>الوصف</label>
                        <textarea name="pdesc" class="form-control" rows="3" placeholder="اكتب وصفاً مختصراً للمنتج..."><?= sanitize($edit_product['description'] ?? '') ?></textarea>
                    </div>
                </div>
                <button type="submit" name="save_product" class="mc-btn primary lg"><?= $edit_product ? '💾 حفظ التعديلات' : '➕ نشر المنتج' ?></button>
            </form>
        </div>

        <?php if (!empty($my_products)): ?>
        <div class="mc-panel">
            <div class="mc-panel-head"><h3>منتجاتي (<?= count($my_products) ?>)</h3></div>
            <div class="mc-table-wrap">
                <table class="mc-table">
                    <thead>
                        <tr><th>الصورة</th><th>الاسم</th><th>التصنيف</th><th>السعر</th><th>المخزون</th><th>إجراءات</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($my_products as $p): ?>
                    <tr>
                        <td><img src="<?= sanitize($p['image'] ?: 'assets/images/hero.jpg') ?>" alt="" style="width:50px;height:50px;object-fit:cover;border-radius:8px;" onerror="this.src='assets/images/hero.jpg'"></td>
                        <td><strong><?= sanitize($p['name']) ?></strong></td>
                        <td><?= sanitize($p['cat_name'] ?? '—') ?></td>
                        <td>
                            <strong style="color:var(--gold);"><?= number_format($p['price'],0) ?> ₪</strong>
                            <?php if ($p['old_price']): ?><small style="text-decoration:line-through;color:#888;"><?= number_format($p['old_price'],0) ?></small><?php endif; ?>
                        </td>
                        <td>
                            <span class="mc-pill" style="--c:<?= $p['stock'] > 0 ? '#2ecc71' : '#e74c3c' ?>;">
                                <?= $p['stock'] > 0 ? $p['stock'] . ' قطعة' : 'نفد' ?>
                            </span>
                        </td>
                        <td class="mc-row-actions">
                            <a href="product.php?id=<?= $p['id'] ?>" class="mc-btn sm" target="_blank">👁️</a>
                            <a href="?tab=products&edit_product=<?= $p['id'] ?>" class="mc-btn sm">✏️</a>
                            <a href="?delete_product=<?= $p['id'] ?>" class="mc-btn sm no" onclick="return confirm('حذف المنتج نهائياً؟')">🗑️</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php elseif ($tab === 'sales'): ?>
        <!-- ==================== مبيعاتي ==================== -->
        <div class="mc-header">
            <div>
                <div class="mc-breadcrumb">متجري / مبيعاتي</div>
                <h1>🛒 سجل المبيعات</h1>
                <p><?= count($sales) ?> عملية بيع — إجمالي <?= number_format($stats['revenue'],0) ?> ₪</p>
            </div>
        </div>

        <?php if (isset($_GET['confirmed'])): ?>
            <div class="alert alert-success" style="margin-bottom:16px;"><span>✅</span><div>تم تأكيد الطلب وإشعار المشتري</div></div>
        <?php endif; ?>
        <?php if (isset($_GET['shipped'])): ?>
            <div class="alert alert-success" style="margin-bottom:16px;"><span>🚚</span><div>تم تحديث حالة الطلب إلى قيد الشحن وإشعار المشتري</div></div>
        <?php endif; ?>

        <?php if (empty($sales)): ?>
            <div class="mc-empty">🛒<p>لا توجد مبيعات بعد. أضف منتجاتك وشاركها!</p></div>
        <?php else: ?>
        <div class="mc-table-wrap">
            <table class="mc-table">
                <thead>
                    <tr><th>المنتج</th><th>المشتري</th><th>الكمية</th><th>الإجمالي</th><th>التاريخ</th><th>حالة الطلب</th><th>إجراء</th></tr>
                </thead>
                <tbody>
                <?php foreach ($sales as $s):
                    $st = $order_status_map[$s['order_status']] ?? ['—','#999']; ?>
                <tr>
                    <td>
                        <img src="<?= sanitize($s['product_image'] ?: 'assets/images/hero.jpg') ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:6px;vertical-align:middle;margin-left:8px;" onerror="this.src='assets/images/hero.jpg'">
                        <?= sanitize($s['product_name']) ?>
                    </td>
                    <td>
                        <strong><?= sanitize($s['buyer_name']) ?></strong>
                        <small dir="ltr"><?= sanitize($s['buyer_phone']) ?></small>
                    </td>
                    <td><?= (int)$s['quantity'] ?> ×</td>
                    <td><strong style="color:var(--gold);"><?= number_format($s['price'] * $s['quantity'], 0) ?> ₪</strong></td>
                    <td><?= date('d/m/Y', strtotime($s['order_date'])) ?></td>
                    <td><span class="mc-pill" style="--c:<?= $st[1] ?>;"><?= $st[0] ?></span></td>
                    <td>
                        <?php if ($s['order_status'] === 'pending' && !$s['seller_confirmed']): ?>
                        <form method="POST" style="margin:0;" onsubmit="return confirm('تأكيد استلام هذا الطلب؟');">
                            <input type="hidden" name="order_id" value="<?= (int)$s['order_id'] ?>">
                            <button type="submit" name="seller_confirm_order" class="mc-btn sm" style="background:rgba(46,204,113,0.15);color:#2ecc71;border-color:#2ecc71;">✅ تأكيد</button>
                        </form>
                        <?php elseif ($s['order_status'] === 'processing'): ?>
                        <form method="POST" style="margin:0;" onsubmit="return confirm('تأكيد شحن هذا الطلب؟');">
                            <input type="hidden" name="order_id" value="<?= (int)$s['order_id'] ?>">
                            <button type="submit" name="seller_ship_order" class="mc-btn sm" style="background:rgba(155,89,182,0.15);color:#9b59b6;border-color:#9b59b6;">🚚 شحن</button>
                        </form>
                        <?php elseif (in_array($s['order_status'], ['shipped','delivered'])): ?>
                        <span style="color:#9b59b6;font-size:12px;">🚚 تم الشحن</span>
                        <?php else: ?>
                        <span style="color:var(--gray);font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
